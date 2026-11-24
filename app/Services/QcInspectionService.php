<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\QcInspection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QcInspectionService
{
    public function create(array $data, User $inspector): QcInspection
    {
        return DB::transaction(function () use ($data, $inspector) {
            $this->requirePermission($inspector, 'qc.create');

            // Every QC operation for a GRN locks its header.
            $grn = GoodsReceipt::query()
                ->whereKey($data['goods_receipt_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($grn->status !== 'received') {
                $this->fail('goods_receipt_id', 'GRN is not received.');
            }

            // An approved inspection is final. A draft or
            // pending inspection must be completed first.
            $existing = QcInspection::query()
                ->where('goods_receipt_id', $grn->id)
                ->whereIn('status', [
                    'draft',
                    'pending_approval',
                    'approved',
                ])
                ->exists();

            if ($existing) {
                $this->fail(
                    'goods_receipt_id',
                    'This GRN already has an active or approved QC inspection.'
                );
            }

            $grnLines = $grn->items()
                ->where('qc_status', 'pending_qc')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($grnLines->isEmpty()) {
                $this->fail(
                    'goods_receipt_id',
                    'This GRN has no lines requiring QC.'
                );
            }

            // In Part 2 every QC-required GRN line must be
            // completely inspected in one document.
            $submittedIds = collect($data['items'])
                ->pluck('goods_receipt_item_id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            $requiredIds = $grnLines
                ->pluck('id')
                ->sort()
                ->values()
                ->all();

            if ($submittedIds !== $requiredIds) {
                $this->fail(
                    'items',
                    'Include every QC-required line of this GRN exactly once.'
                );
            }

            $lineMap = $grnLines->keyBy('id');
            $validatedLines = [];

            foreach ($data['items'] as $line) {
                $grnLine = $lineMap->get(
                    (int) $line['goods_receipt_item_id']
                );

                $accepted = (int) $line['accepted_quantity'];
                $rejected = (int) $line['rejected_quantity'];

                // Convert DECIMAL(15,3) to integer thousandths.
                $receivedUnits = $this->toUnits(
                    $grnLine->received_quantity
                );

                if (($accepted + $rejected) * 1000 !== $receivedUnits) {
                    $this->fail(
                        'items',
                        "Accepted plus rejected must equal received " .
                        "quantity for GRN line {$grnLine->id}."
                    );
                }

                $reason = trim(
                    (string) ($line['rejection_reason'] ?? '')
                );

                if ($rejected > 0 && mb_strlen($reason) < 5) {
                    $this->fail(
                        'items',
                        "A rejection reason of at least 5 characters " .
                        "is required for GRN line {$grnLine->id}."
                    );
                }

                $validatedLines[] = [
                    'goods_receipt_item_id' => $grnLine->id,
                    'accepted_quantity' => $accepted,
                    'rejected_quantity' => $rejected,
                    'rejection_reason' => $rejected > 0
                        ? $reason
                        : null,
                    'remarks' => $line['remarks'] ?? null,
                ];
            }

            // Use the new inspection's database ID as the
            // unique sequence. No changes to PO/GRN numbering.
            $inspection = QcInspection::create([
                'inspection_number' => 'TMP-' . Str::uuid(),
                'goods_receipt_id' => $grn->id,
                'inspected_by' => $inspector->id,
                'status' => 'draft',
                'inspection_date' => $data['inspection_date'],
                'remarks' => $data['remarks'] ?? null,
            ]);

            $inspection->update([
                'inspection_number' =>
                    'QC-' . now()->format('Y') . '-' .
                    str_pad(
                        (string) $inspection->id,
                        6,
                        '0',
                        STR_PAD_LEFT
                    ),
            ]);

            foreach ($validatedLines as $line) {
                $inspection->items()->create($line);
            }

            $this->recordActivity(
                $inspection,
                $inspector,
                'created',
                null,
                'draft'
            );

            return $this->loadDetails($inspection);
        }, 3);
    }

    public function submit(
        QcInspection $inspection,
        User $inspector
    ): QcInspection {
        return DB::transaction(function () use ($inspection, $inspector) {
            $this->requirePermission($inspector, 'qc.submit');

            $inspection = $this->lockInspection($inspection->id);

            if ($inspection->status !== 'draft') {
                $this->fail(
                    'status',
                    'Only draft inspections can be submitted.'
                );
            }

            if ((int) $inspection->inspected_by !==
                (int) $inspector->id) {
                abort(403, 'Only the original inspector can submit.');
            }

            $inspection->update([
                'status' => 'pending_approval',
                'submitted_at' => now(),
            ]);

            $this->recordActivity(
                $inspection,
                $inspector,
                'submitted',
                'draft',
                'pending_approval'
            );

            return $this->loadDetails($inspection);
        }, 3);
    }

    public function decide(
        QcInspection $inspection,
        User $manager,
        string $decision,
        ?string $reason = null
    ): QcInspection {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new \InvalidArgumentException(
                'Invalid QC decision.'
            );
        }

        return DB::transaction(function () use (
            $inspection,
            $manager,
            $decision,
            $reason
        ) {
            $permission = $decision === 'approved'
                ? 'qc.approve'
                : 'qc.reject';

            $this->requirePermission($manager, $permission);

            $inspection = $this->lockInspection($inspection->id);

            if ($inspection->status !== 'pending_approval') {
                $this->fail(
                    'status',
                    'Only pending inspections can be decided.'
                );
            }

            if ((int) $inspection->inspected_by ===
                (int) $manager->id) {
                abort(403, 'Self-approval or self-rejection is forbidden.');
            }

            $reason = trim((string) $reason);

            if ($decision === 'rejected' && mb_strlen($reason) < 5) {
                $this->fail(
                    'reason',
                    'A rejection reason of at least 5 characters is required.'
                );
            }

            $inspection->update([
                'status' => $decision,
                'decided_by' => $manager->id,
                'decided_at' => now(),
                'decision_reason' => $reason ?: null,
            ]);

            // Important: Do not update goods_receipt_items.qc_status
            // here. Phase 4 Part 3 will introduce controlled
            // inventory posting and QC disposition updates.

            $this->recordActivity(
                $inspection,
                $manager,
                $decision,
                'pending_approval',
                $decision,
                $reason ?: null
            );

            return $this->loadDetails($inspection);
        }, 3);
    }

    private function lockInspection(int $id): QcInspection
    {
        // Consistent lock order: GRN header, then QC header.
        $grnId = QcInspection::whereKey($id)
            ->value('goods_receipt_id');

        if (!$grnId) {
            abort(404);
        }

        GoodsReceipt::whereKey($grnId)
            ->lockForUpdate()
            ->firstOrFail();

        return QcInspection::whereKey($id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function recordActivity(
        QcInspection $inspection,
        User $user,
        string $action,
        ?string $from,
        string $to,
        ?string $reason = null
    ): void {
        $inspection->load('items');

        $inspection->activities()->create([
            'user_id' => $user->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'snapshot' => [
                'inspection_number' => $inspection->inspection_number,
                'goods_receipt_id' => $inspection->goods_receipt_id,
                'status' => $inspection->status,
                'items' => $inspection->items
                    ->map(fn ($item) => [
                        'goods_receipt_item_id' =>
                            $item->goods_receipt_item_id,
                        'accepted_quantity' =>
                            $item->accepted_quantity,
                        'rejected_quantity' =>
                            $item->rejected_quantity,
                    ])->values()->all(),
            ],
        ]);
    }

    private function requirePermission(
        User $user,
        string $permission
    ): void {
        if (!$user->is_active ||
            !$user->hasPermission($permission)) {
            abort(403, 'Insufficient QC permissions.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([
            $field => $message,
        ]);
    }

    private function toUnits(int|float|string $value): int
    {
        $value = (string) $value;

        if (!preg_match('/^\d+(?:\.\d{1,3})?$/', $value)) {
            throw new \InvalidArgumentException(
                'Invalid decimal quantity.'
            );
        }

        [$whole, $fraction] = array_pad(
            explode('.', $value, 2),
            2,
            '0'
        );

        return ((int) $whole * 1000) +
            (int) str_pad($fraction, 3, '0');
    }

    private function loadDetails(QcInspection $inspection): QcInspection
    {
        return $inspection->load([
            'goodsReceipt.purchaseOrder',
            'goodsReceipt.warehouse',
            'inspector',
            'decisionMaker',
            'items.goodsReceiptItem.item',
            'activities.user',
        ]);
    }
}
