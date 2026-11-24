<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\InventoryBalance;
use App\Models\InventoryPosting;
use App\Models\QcInspection;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\InventoryPostingLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryPostingService
{
    /**
     * Post all currently eligible, unposted lines of a GRN.
     *
     * QC-required lines need an approved inspection.
     * QC-exempt lines can be posted without inspection.
     *
     * Each GRN line can be posted exactly once.
     */
    public function post(
        GoodsReceipt $goodsReceipt,
        User $actor,
        ?string $remarks = null
    ): InventoryPosting {
        $this->requirePermission($actor, 'stock.post');

        return DB::transaction(function () use (
            $goodsReceipt,
            $actor,
            $remarks
        ) {
            // Lock the GRN to serialize postings for this receipt.
            $grn = GoodsReceipt::query()
                ->whereKey($goodsReceipt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($grn->status !== 'received') {
                $this->fail(
                    'goods_receipt_id',
                    'Only received GRNs can be posted.'
                );
            }

            $lines = $grn->items()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lines->isEmpty()) {
                $this->fail(
                    'goods_receipt_id',
                    'This GRN has no receipt lines.'
                );
            }

            // Load the latest approved QC inspection.
            $approvedQc = QcInspection::query()
                ->where('goods_receipt_id', $grn->id)
                ->where('status', 'approved')
                ->latest('id')
                ->first();

            $qcLines = $approvedQc
                ? $approvedQc->items()
                    ->get()
                    ->keyBy('goods_receipt_item_id')
                : collect();

            $eligible = [];

            foreach ($lines as $line) {
                // The stock movement ledger is the authoritative
                // exactly-once marker for each GRN line.
                $alreadyPosted = InventoryPostingLine::query()
                ->where('goods_receipt_item_id', $line->id)
                ->exists();

                if ($alreadyPosted) {
                    continue;
                }

                if ($line->qc_status === 'qc_not_required') {
                    $eligible[] = [
                        'line' => $line,
                        'accepted' => $this->toUnits(
                            $line->received_quantity
                        ),
                        'rejected' => 0,
                        'qc_inspection_id' => null,
                    ];

                    continue;
                }

                if ($line->qc_status !== 'pending_qc') {
                    $this->fail(
                        'goods_receipt_id',
                        "Unexpected QC status on GRN line {$line->id}."
                    );
                }

                if (!$approvedQc) {
                    // Not yet eligible: do not post this line.
                    continue;
                }

                $qcLine = $qcLines->get($line->id);

                if (!$qcLine) {
                    $this->fail(
                        'goods_receipt_id',
                        "Approved QC does not cover GRN line {$line->id}."
                    );
                }

                $accepted = $this->toUnits(
                    $qcLine->accepted_quantity
                );

                $rejected = $this->toUnits(
                    $qcLine->rejected_quantity
                );

                $received = $this->toUnits(
                    $line->received_quantity
                );

                if ($accepted + $rejected !== $received) {
                    $this->fail(
                        'goods_receipt_id',
                        "QC quantities do not reconcile for GRN line {$line->id}."
                    );
                }

                $eligible[] = [
                    'line' => $line,
                    'accepted' => $accepted,
                    'rejected' => $rejected,
                    'qc_inspection_id' => $approvedQc->id,
                ];
            }

            if (empty($eligible)) {
                $this->fail(
                    'goods_receipt_id',
                    'No eligible unposted GRN lines were found.'
                );
            }

            // A posting can contain both QC and non-QC lines.
            // The posting header links to QC when one is involved.
            $qcInspectionId = collect($eligible)
                ->pluck('qc_inspection_id')
                ->filter()
                ->first();

            $posting = InventoryPosting::create([
                // Temporary value until the database ID exists.
                'posting_number' => 'TMP-' . \Illuminate\Support\Str::uuid(),
                'goods_receipt_id' => $grn->id,
                'qc_inspection_id' => $qcInspectionId,
                'warehouse_id' => $grn->warehouse_id,
                'posted_by' => $actor->id,
                'status' => 'posted',
                'posted_at' => now(),
                'remarks' => $remarks,
            ]);

            $posting->update([
                'posting_number' => 'IP-' .
                    now()->format('Y') . '-' .
                    str_pad((string) $posting->id, 6, '0', STR_PAD_LEFT),
            ]);

            /*
             * Lock inventory balance rows in deterministic item order.
             * Create missing rows before locking.
             *
             * The unique warehouse/item index protects against
             * duplicate balance rows.
             */
            $itemIds = collect($eligible)
                ->pluck('line.item_id')
                ->unique()
                ->sort()
                ->values();

            foreach ($itemIds as $itemId) {
                InventoryBalance::query()->firstOrCreate(
                    [
                        'warehouse_id' => $grn->warehouse_id,
                        'item_id' => $itemId,
                    ],
                    [
                        'available_quantity' => 0,
                        'rejected_quantity' => 0,
                    ]
                );
            }

            $balances = InventoryBalance::query()
                ->where('warehouse_id', $grn->warehouse_id)
                ->whereIn('item_id', $itemIds)
                ->orderBy('item_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('item_id');

            foreach ($eligible as $entry) {
                $line = $entry['line'];
                    InventoryPostingLine::create([
                    'inventory_posting_id' => $posting->id,
                    'goods_receipt_item_id' => $line->id,
                    'accepted_quantity' => $this->fromUnits(
                        $entry['accepted']
                    ),
                    'rejected_quantity' => $this->fromUnits(
                        $entry['rejected']
                    ),
                ]);

                $balance = $balances->get($line->item_id);

                if (!$balance) {
                    $this->fail(
                        'goods_receipt_id',
                        "Inventory balance missing for item {$line->item_id}."
                    );
                }

                if ($entry['accepted'] > 0) {
                    $this->recordMovement(
                        $posting,
                        $line,
                        $balance,
                        'receipt_accepted',
                        $entry['accepted']
                    );
                }

                if ($entry['rejected'] > 0) {
                    $this->recordMovement(
                        $posting,
                        $line,
                        $balance,
                        'receipt_rejected',
                        $entry['rejected']
                    );
                }

                // If a line has zero received quantity, no movement
                // can represent its posting. Such lines are rejected.
                if (
                    $entry['accepted'] === 0 &&
                    $entry['rejected'] === 0
                ) {
                    $this->fail(
                        'goods_receipt_id',
                        "Cannot post zero quantity on GRN line {$line->id}."
                    );
                }
            }

        return $posting->load([
            'goodsReceipt',
            'qcInspection',
            'warehouse',
            'postedBy',
            'lines',
            'movements',
        ]);
        }, 3);
    }

    /**
     * Record one immutable movement and update the matching
     * inventory balance category.
     */
    private function recordMovement(
        InventoryPosting $posting,
        $line,
        InventoryBalance $balance,
        string $type,
        int $quantityUnits
    ): void {
        $column = $type === 'receipt_accepted'
            ? 'available_quantity'
            : 'rejected_quantity';

        $before = $this->toUnits($balance->{$column});

        $maximumUnits = 999999999999999;

        if ($quantityUnits > $maximumUnits - $before) {
            $this->fail(
                'quantity',
                'Inventory balance exceeds DECIMAL(15,3) capacity.'
            );
        }
        $after = $before + $quantityUnits;

        if ($after < 0) {
            $this->fail(
                'quantity',
                'Inventory balance cannot become negative.'
            );
        }

        StockMovement::create([
            'inventory_posting_id' => $posting->id,
            'goods_receipt_item_id' => $line->id,
            'warehouse_id' => $posting->warehouse_id,
            'item_id' => $line->item_id,
            'movement_type' => $type,
            'quantity' => $this->fromUnits($quantityUnits),
            'balance_before' => $this->fromUnits($before),
            'balance_after' => $this->fromUnits($after),
            'movement_date' => now(),
            'remarks' => null,
        ]);

        $balance->{$column} = $this->fromUnits($after);
        $balance->save();
    }

    /**
     * Convert a DECIMAL(15,3) quantity to integer thousandths
     * without floating-point arithmetic.
     */
    private function toUnits(mixed $value): int
    {
        $value = trim((string) $value);

        if (!preg_match(
            '/^(\d+)(?:\.(\d{1,3}))?$/',
            $value,
            $matches
        )) {
            $this->fail(
                'quantity',
                'Invalid inventory quantity format.'
            );
        }
        if (strlen(ltrim($matches[1], '0')) > 12) {
            $this->fail(
                'quantity',
                'Inventory quantity exceeds DECIMAL(15,3) capacity.'
            );
        }

        $whole = (int) $matches[1];

        $fraction = str_pad(
            $matches[2] ?? '',
            3,
            '0',
            STR_PAD_RIGHT
        );

        return ($whole * 1000) + (int) $fraction;
    }

    private function fromUnits(int $units): string
    {
        return sprintf(
            '%d.%03d',
            intdiv($units, 1000),
            $units % 1000
        );
    }

    private function requirePermission(
        User $actor,
        string $permission
    ): void {
        if (
            !$actor->is_active ||
            !$actor->hasPermission($permission)
        ) {
            abort(403, 'You are not authorized to post inventory.');
        }
    }

    private function fail(
        string $field,
        string $message
    ): never {
        throw ValidationException::withMessages([
            $field => [$message],
        ]);
    }
}
