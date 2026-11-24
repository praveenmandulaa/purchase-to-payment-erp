<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoodsReceiptService
{
    public function create(array $data, User $receiver): GoodsReceipt
    {
        return DB::transaction(function () use ($data, $receiver) {

            if (!$receiver->is_active ||
                !$receiver->hasPermission('grn.create')) {
                abort(403, 'Not authorized to receive goods.');
            }

            // Lock the PO header first. All GRN creation for this
            // PO must serialize on this same row.
            $po = PurchaseOrder::whereKey(
                $data['purchase_order_id']
            )->lockForUpdate()->firstOrFail();

            if ($po->status !== 'approved') {
                throw ValidationException::withMessages([
                    'purchase_order_id' =>
                        'Goods can be received only against an approved PO.',
                ]);
            }

            $warehouse = Warehouse::findOrFail(
                $data['warehouse_id']
            );

            if (!$warehouse->is_active) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Warehouse is inactive.',
                ]);
            }

            $deliveryReference = trim(
                $data['delivery_reference']
            );

            if ($deliveryReference === '') {
                throw ValidationException::withMessages([
                    'delivery_reference' =>
                        'Delivery reference is required.',
                ]);
            }

            // A supplier delivery note must not be received twice.
            $duplicate = GoodsReceipt::where(
                'supplier_id',
                $po->supplier_id
            )->where(
                'delivery_reference',
                $deliveryReference
            )->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'delivery_reference' =>
                        'This supplier delivery reference was already received.',
                ]);
            }

            $poLines = PurchaseOrderItem::where(
                'purchase_order_id',
                $po->id
            )->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $lineIds = collect($data['items'])
                ->pluck('purchase_order_item_id')
                ->map(fn ($id) => (int) $id);

            if ($lineIds->unique()->count() !== $lineIds->count()) {
                throw ValidationException::withMessages([
                    'items' => 'Duplicate PO lines are not allowed.',
                ]);
            }

            // Validate every requested line before inserting a GRN.
            $validatedLines = [];

            foreach ($data['items'] as $line) {
                $poLineId = (int) $line['purchase_order_item_id'];
                $quantity = (int) $line['received_quantity'];

                $poLine = $poLines->get($poLineId);

                if (!$poLine) {
                    throw ValidationException::withMessages([
                        'items' =>
                            'A selected line does not belong to this PO.',
                    ]);
                }

                // Current business rule: whole units only.
                if ($quantity <= 0 || $quantity > 1000000) {
                    throw ValidationException::withMessages([
                        'items' => 'Invalid received quantity.',
                    ]);
                }

                // Decimal(15,3) values are converted to integer
                // thousandths for exact quantity comparisons.
                $orderedUnits = $this->toUnits($poLine->quantity);
                $requestedUnits = $quantity * 1000;

                $receivedUnits = $this->toUnits(
                    DB::table('goods_receipt_items as gri')
                        ->join(
                            'goods_receipts as gr',
                            'gr.id',
                            '=',
                            'gri.goods_receipt_id'
                        )
                        ->where(
                            'gri.purchase_order_item_id',
                            $poLineId
                        )
                        ->where('gr.status', 'received')
                        ->sum('gri.received_quantity')
                );

                $outstandingUnits = $orderedUnits - $receivedUnits;

                if ($outstandingUnits <= 0 ||
                    $requestedUnits > $outstandingUnits) {
                    throw ValidationException::withMessages([
                        'items' =>
                            "Over-receipt is not allowed for PO line {$poLineId}. " .
                            'Outstanding quantity: ' .
                            number_format($outstandingUnits / 1000, 3, '.', ''),
                    ]);
                }

                $item = Item::findOrFail($poLine->item_id);

                $validatedLines[] = [
                    'purchase_order_item_id' => $poLine->id,
                    'item_id' => $poLine->item_id,
                    'received_quantity' => $quantity,
                    'qc_status' => $item->requires_qc
                        ? 'pending_qc'
                        : 'qc_not_required',
                ];
            }

            // The sequence row must exist before locking.
            $sequence = DB::table('document_sequences')
                ->where('document_type', 'GRN')
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                throw ValidationException::withMessages([
                    'grn_number' =>
                        'GRN sequence is not configured. Run WarehouseSeeder.',
                ]);
            }

            $nextNumber = (int) $sequence->last_number + 1;

            DB::table('document_sequences')
                ->where('document_type', 'GRN')
                ->update([
                    'last_number' => $nextNumber,
                    'updated_at' => now(),
                ]);

            $grnNumber = 'GRN-' . now()->format('Y') . '-' .
                str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);

            $grn = GoodsReceipt::create([
                'grn_number' => $grnNumber,
                'purchase_order_id' => $po->id,
                'supplier_id' => $po->supplier_id,
                'warehouse_id' => $warehouse->id,
                'received_by' => $receiver->id,
                'receipt_date' => $data['receipt_date'],
                'delivery_reference' => $deliveryReference,
                'status' => 'received',
                'remarks' => $data['remarks'] ?? null,
            ]);

            foreach ($validatedLines as $line) {
                $grn->items()->create($line);
            }

            return $grn->load([
                'purchaseOrder',
                'supplier',
                'warehouse',
                'receiver',
                'items.item',
                'items.purchaseOrderItem',
            ]);
        }, 3);
    }

    private function toUnits(int|float|string $quantity): int
    {
        // MySQL DECIMAL(15,3) should be handled as a
        // decimal string, avoiding float multiplication.
        $value = (string) $quantity;

        if (!preg_match('/^\d+(?:\.\d{1,3})?$/', $value)) {
            throw new \InvalidArgumentException(
                'Invalid decimal quantity: ' . $value
            );
        }

        [$whole, $fraction] = array_pad(
            explode('.', $value, 2),
            2,
            '0'
        );

        return ((int) $whole * 1000)
            + (int) str_pad($fraction, 3, '0');
    }
}
