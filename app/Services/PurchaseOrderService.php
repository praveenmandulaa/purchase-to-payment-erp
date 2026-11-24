<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderApproval;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\PurchaseOrderActivity;
use Illuminate\Support\Facades\Validator;

class PurchaseOrderService
{
    public function create(array $data, User $buyer): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $buyer) {

            $supplier = Supplier::findOrFail($data['supplier_id']);

            if (!$supplier->is_active) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'Supplier is inactive.',
                ]);
            }

            $itemIds = collect($data['items'])
                ->pluck('item_id')
                ->unique()
                ->values();

            if ($itemIds->count() !== count($data['items'])) {
                throw ValidationException::withMessages([
                    'items' => 'Duplicate items are not allowed.',
                ]);
            }

            $items = Item::whereIn('id', $itemIds)
                ->where('is_active', true)
                ->get()
                ->keyBy('id');

            if ($items->count() !== $itemIds->count()) {
                throw ValidationException::withMessages([
                    'items' => 'One or more items are invalid or inactive.',
                ]);
            }

            $sequence = DB::table('document_sequences')
                ->where('document_type', 'PO')
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                throw new \RuntimeException(
                    'PO document sequence is not initialized.'
                );
            }

            $nextNumber = $sequence->last_number + 1;

            DB::table('document_sequences')
                ->where('document_type', 'PO')
                ->update([
                    'last_number' => $nextNumber,
                    'updated_at' => now(),
                ]);

            $poNumber = 'PO-' . now()->year . '-' .
                str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

            $po = PurchaseOrder::create([
                'po_number' => $poNumber,
                'supplier_id' => $supplier->id,
                'created_by' => $buyer->id,
                'po_date' => now()->toDateString(),
                'expected_delivery_date' =>
                    $data['expected_delivery_date'] ?? null,
                'status' => 'draft',
                'total_amount' => 0,
                'remarks' => $data['remarks'] ?? null,
            ]);

            $totalCents = 0;

            foreach ($data['items'] as $line) {
                $quantity = (float) $line['quantity'];
                $priceCents = (int) round(
                    (float) $line['unit_price'] * 100
                );

                // This initial purchasing module supports
                // whole-number quantities only.
                $lineCents = (int) $quantity * $priceCents;
                $totalCents += $lineCents;

                $po->items()->create([
                    'item_id' => $line['item_id'],
                    'quantity' => $quantity,
                    'unit_price' => $priceCents / 100,
                    'line_total' => $lineCents / 100,
                ]);
            }

            $po->update([
                'total_amount' => $totalCents / 100,
            ]);
            PurchaseOrderActivity::create([
    'purchase_order_id' => $po->id,
    'user_id' => $buyer->id,
    'action' => 'created',
    'from_status' => null,
    'to_status' => 'draft',
    'old_values' => null,
    'new_values' => $this->snapshot($po),
]);

            return $po->load(['supplier', 'items.item']);
        });
    }

    public function submit(
        PurchaseOrder $po,
        User $buyer
    ): PurchaseOrder {
        return DB::transaction(function () use ($po, $buyer) {
            $po = PurchaseOrder::whereKey($po->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($po->created_by !== $buyer->id) {
                abort(403, 'Only the creator can submit this PO.');
            }

            if ($po->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => 'Only draft POs can be submitted.',
                ]);
            }

            if (!$po->items()->exists()) {
                throw ValidationException::withMessages([
                    'items' => 'PO must contain at least one item.',
                ]);
            }

           $oldValues = $this->snapshot($po);

$po->update(['status' => 'pending_approval']);

PurchaseOrderActivity::create([
    'purchase_order_id' => $po->id,
    'user_id' => $buyer->id,
    'action' => 'submitted',
    'from_status' => 'draft',
    'to_status' => 'pending_approval',
    'old_values' => $oldValues,
    'new_values' => $this->snapshot($po),
]);

return $po->fresh();
        });
    }

    public function decide(
        PurchaseOrder $po,
        User $manager,
        string $action,
        ?string $remarks = null
    ): PurchaseOrder {
        return DB::transaction(function () use (
            $po, $manager, $action, $remarks
        ) {
            $po = PurchaseOrder::whereKey($po->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array($action, ['approved', 'rejected'], true)) {
                throw ValidationException::withMessages([
                    'action' => 'Invalid approval action.',
                ]);
            }

          // decide()
            if ((int) $po->created_by === (int) $manager->id) {
                abort(403, 'You cannot approve your own PO.');
            }

            if ($po->status !== 'pending_approval') {
                throw ValidationException::withMessages([
                    'status' => 'PO is not pending approval.',
                ]);
            }

            if ($action === 'rejected' && !trim($remarks ?? '')) {
                throw ValidationException::withMessages([
                    'remarks' => 'Rejection reason is required.',
                ]);
            }
$oldValues = $this->snapshot($po);
            $po->update(['status' => $action]);

            PurchaseOrderApproval::create([
                'purchase_order_id' => $po->id,
                'approver_id' => $manager->id,
                'action' => $action,
                'remarks' => $remarks,
                'action_at' => now(),
            ]);
PurchaseOrderActivity::create([
    'purchase_order_id' => $po->id,
    'user_id' => $manager->id,
    'action' => $action,
    'from_status' => 'pending_approval',
    'to_status' => $action,
    'reason' => $remarks,
    'old_values' => $oldValues,
    'new_values' => $this->snapshot($po),
]);
            return $po->fresh(['approvals.approver']);
        });
    }
    private function snapshot(PurchaseOrder $po): array
{
    $po->load('items');

    return [
        'supplier_id' => $po->supplier_id,
        'status' => $po->status,
        'po_date' => $po->po_date,
        'expected_delivery_date' => $po->expected_delivery_date,
        'total_amount' => $po->total_amount,
        'remarks' => $po->remarks,
        'items' => $po->items->map(fn ($line) => [
            'item_id' => $line->item_id,
            'quantity' => $line->quantity,
            'unit_price' => $line->unit_price,
            'line_total' => $line->line_total,
        ])->all(),
    ];
}
public function update(
    PurchaseOrder $po,
    array $data,
    User $buyer
): PurchaseOrder {
    return DB::transaction(function () use ($po, $data, $buyer) {

        // Lock the header to serialize edits, submissions,
        // and cancellation against the same PO.
        $po = PurchaseOrder::whereKey($po->id)
            ->lockForUpdate()
            ->firstOrFail();

        // submit()
        if ((int) $po->created_by !== (int) $buyer->id) {
            abort(403, 'Only the creator can submit this PO.');
        }
        if ($po->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Only draft purchase orders can be edited.',
            ]);
        }

        $supplier = Supplier::findOrFail($data['supplier_id']);

        if (!$supplier->is_active) {
            throw ValidationException::withMessages([
                'supplier_id' => 'Selected supplier is inactive.',
            ]);
        }

        $itemIds = collect($data['items'])
            ->pluck('item_id')
            ->map(fn ($id) => (int) $id);

        if ($itemIds->unique()->count() !== $itemIds->count()) {
            throw ValidationException::withMessages([
                'items' => 'Duplicate items are not allowed.',
            ]);
        }

        $activeCount = Item::whereIn('id', $itemIds)
            ->where('is_active', true)
            ->count();

        if ($activeCount !== $itemIds->count()) {
            throw ValidationException::withMessages([
                'items' => 'One or more items are inactive or invalid.',
            ]);
        }

        $oldValues = $this->snapshot($po);

        // Validate and calculate every line before modifying
        // existing records.
        $lines = [];
        $totalCents = 0;

        foreach ($data['items'] as $line) {
            $quantity = (int) $line['quantity'];

            // Input validation limits prices to two decimals.
            $priceCents = (int) round(
                (float) $line['unit_price'] * 100
            );

            $lineCents = $quantity * $priceCents;
            $totalCents += $lineCents;

            $lines[] = [
                'item_id' => $line['item_id'],
                'quantity' => $quantity,
                'unit_price' => $priceCents / 100,
                'line_total' => $lineCents / 100,
            ];
        }

        if ($totalCents > 999999999999999) {
            throw ValidationException::withMessages([
                'items' => 'Purchase order total exceeds the allowed limit.',
            ]);
        }

        $po->update([
            'supplier_id' => $supplier->id,
            'expected_delivery_date' =>
                $data['expected_delivery_date'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'total_amount' => $totalCents / 100,
        ]);

        // Replace all draft lines atomically.
        // The previous values remain in the activity audit.
        $po->items()->delete();

        foreach ($lines as $line) {
            $po->items()->create($line);
        }

        $po->unsetRelation('items');

        $newValues = $this->snapshot($po);

        PurchaseOrderActivity::create([
            'purchase_order_id' => $po->id,
            'user_id' => $buyer->id,
            'action' => 'updated',
            'from_status' => 'draft',
            'to_status' => 'draft',
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);

        return $po->fresh([
            'supplier',
            'creator',
            'items.item',
            'activities.user',
        ]);
    });
}
public function cancel(
    PurchaseOrder $po,
    User $buyer,
    string $reason
): PurchaseOrder {
    return DB::transaction(function () use ($po, $buyer, $reason) {

        $po = PurchaseOrder::whereKey($po->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ((int) $po->created_by !== (int) $buyer->id) {
            abort(403, 'Only the PO creator can cancel it.');
        }

        if (!in_array(
            $po->status,
            ['draft', 'pending_approval'],
            true
        )) {
            throw ValidationException::withMessages([
                'status' => 'This PO cannot be cancelled.',
            ]);
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Cancellation reason is required.',
            ]);
        }

        $oldStatus = $po->status;
        $oldValues = $this->snapshot($po);

        $po->update([
            'status' => 'cancelled',
        ]);

        PurchaseOrderActivity::create([
            'purchase_order_id' => $po->id,
            'user_id' => $buyer->id,
            'action' => 'cancelled',
            'from_status' => $oldStatus,
            'to_status' => 'cancelled',
            'reason' => $reason,
            'old_values' => $oldValues,
            'new_values' => $this->snapshot($po),
        ]);

        return $po->fresh([
            'supplier',
            'creator',
            'items.item',
            'activities.user',
        ]);
    });
}
}