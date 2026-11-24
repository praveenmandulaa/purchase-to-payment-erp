<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierInvoiceService
{
    public function createDraft(array $data, User $actor): SupplierInvoice
    {
        if (!$actor->is_active || !$actor->hasPermission('invoice.create')) {
            abort(403, 'You are not authorized to create invoices.');
        }

        return DB::transaction(function () use ($data, $actor) {
            $po = PurchaseOrder::query()
                ->whereKey($data['purchase_order_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($po->status !== 'approved') {
                throw ValidationException::withMessages([
                    'purchase_order_id' =>
                        'Only approved purchase orders can be invoiced.',
                ]);
            }

            if ((int) $po->supplier_id !== (int) $data['supplier_id']) {
                throw ValidationException::withMessages([
                    'supplier_id' =>
                        'Invoice supplier must match the PO supplier.',
                ]);
            }

            $duplicate = SupplierInvoice::query()
                ->where('supplier_id', $data['supplier_id'])
                ->where('supplier_invoice_number', $data['supplier_invoice_number'])
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'supplier_invoice_number' =>
                        'This supplier invoice number already exists.',
                ]);
            }

            $poLines = $po->items()
                ->get()
                ->keyBy('id');

            $subtotalCents = 0;
            $preparedItems = [];
            $seenLines = [];

            foreach ($data['items'] as $item) {
                $lineId = (int) $item['purchase_order_item_id'];

                if (isset($seenLines[$lineId])) {
                    throw ValidationException::withMessages([
                        'items' => 'Duplicate purchase order line in invoice.',
                    ]);
                }

                $seenLines[$lineId] = true;

                $poLine = $poLines->get($lineId);

                if (!$poLine) {
                    throw ValidationException::withMessages([
                        'items' =>
                            "PO line {$lineId} does not belong to this purchase order.",
                    ]);
                }

                $quantity = $this->quantityUnits($item['invoiced_quantity']);
                $price = $this->moneyCents($item['unit_price']);

                if ($quantity <= 0 || $price < 0) {
                    throw ValidationException::withMessages([
                        'items' => 'Invoice quantity must be positive and price nonnegative.',
                    ]);
                }

                // Quantity is measured in thousandths and price in cents.
                // Round the extended amount to the nearest cent.
                $lineCents = intdiv(($quantity * $price) + 500, 1000);

                $subtotalCents += $lineCents;

                $preparedItems[] = [
                    'purchase_order_item_id' => $lineId,
                    'item_id' => $poLine->item_id,
                    'invoiced_quantity' => $this->formatQuantity($quantity),
                    'unit_price' => $this->formatMoney($price),
                    'line_amount' => $this->formatMoney($lineCents),
                    'matched_quantity' => '0.000',
                    'match_status' => 'not_checked',
                ];
            }

            if (count($preparedItems) === 0) {
                throw ValidationException::withMessages([
                    'items' => 'At least one invoice item is required.',
                ]);
            }

            $taxCents = $this->moneyCents($data['tax_amount'] ?? '0');

            if ($taxCents < 0) {
                throw ValidationException::withMessages([
                    'tax_amount' => 'Tax amount cannot be negative.',
                ]);
            }

            $invoice = SupplierInvoice::create([
                'invoice_number' => 'TMP-' . \Illuminate\Support\Str::uuid(),
                'supplier_invoice_number' => $data['supplier_invoice_number'],
                'supplier_id' => $po->supplier_id,
                'purchase_order_id' => $po->id,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'status' => 'draft',
                'subtotal' => $this->formatMoney($subtotalCents),
                'tax_amount' => $this->formatMoney($taxCents),
                'total_amount' => $this->formatMoney($subtotalCents + $taxCents),
                'currency' => 'INR',
                'created_by' => $actor->id,
                'remarks' => $data['remarks'] ?? null,
            ]);

            $invoice->update([
                'invoice_number' => 'SI-' .
                    now()->format('Y') . '-' .
                    str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT),
            ]);

            $invoice->items()->createMany($preparedItems);

            SupplierInvoiceActivity::create([
                'supplier_invoice_id' => $invoice->id,
                'user_id' => $actor->id,
                'action' => 'created',
                'from_status' => null,
                'to_status' => 'draft',
                'snapshot' => [
                    'invoice_number' => $invoice->invoice_number,
                    'supplier_invoice_number' => $invoice->supplier_invoice_number,
                    'subtotal' => $invoice->subtotal,
                    'tax_amount' => $invoice->tax_amount,
                    'total_amount' => $invoice->total_amount,
                    'items' => $preparedItems,
                ],
            ]);

            return $invoice->load([
                'supplier',
                'purchaseOrder',
                'items.item',
                'createdBy',
                'activities',
            ]);
        }, 3);
    }

    private function quantityUnits(mixed $value): int
    {
        $value = trim((string) $value);

        if (!preg_match('/^(\d{1,12})(?:\.(\d{1,3}))?$/', $value, $m)) {
            throw ValidationException::withMessages([
                'items' => 'Invalid quantity. Maximum three decimal places.',
            ]);
        }

        return ((int) $m[1] * 1000)
            + (int) str_pad($m[2] ?? '', 3, '0');
    }

    private function moneyCents(mixed $value): int
    {
        $value = trim((string) $value);

        if (!preg_match('/^(\d{1,15})(?:\.(\d{1,2}))?$/', $value, $m)) {
            throw ValidationException::withMessages([
                'items' => 'Invalid monetary amount. Maximum two decimal places.',
            ]);
        }

        return ((int) $m[1] * 100)
            + (int) str_pad($m[2] ?? '', 2, '0');
    }

    private function formatQuantity(int $units): string
    {
        return intdiv($units, 1000) . '.' .
            str_pad((string) ($units % 1000), 3, '0', STR_PAD_LEFT);
    }

    private function formatMoney(int $cents): string
    {
        return intdiv($cents, 100) . '.' .
            str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
