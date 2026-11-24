<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\SupplierInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierInvoiceMatchingService
{
    public function match(
        SupplierInvoice $invoice,
        $actor
    ): SupplierInvoice {
        if (
            !$actor->is_active ||
            !$actor->hasPermission('invoice.create')
        ) {
            abort(403, 'Not authorized to match invoices.');
        }

        return DB::transaction(function () use ($invoice, $actor) {

            // Lock the PO first. All invoice matching for the
            // same PO is serialized through this row.
            $po = PurchaseOrder::query()
                ->whereKey($invoice->purchase_order_id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($po->status !== 'approved') {
                throw ValidationException::withMessages([
                    'invoice' =>
                        'Only invoices linked to approved purchase orders can be matched.',
                ]);
            }
            $invoice = SupplierInvoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array($invoice->status, [
                'draft',
                'mismatch',
            ], true)) {
                throw ValidationException::withMessages([
                    'invoice' =>
                        'Only draft or mismatched invoices can be matched.',
                ]);
            }

            $lines = $invoice->items()
                ->orderBy('id')
                ->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'invoice' => 'Invoice has no lines.',
                ]);
            }

            $poLines = $po->items()->get()->keyBy('id');

            $previousStatus = $invoice->status;
            $hasMismatch = false;
            $results = [];

            foreach ($lines as $line) {
                $poLine = $poLines->get(
                    $line->purchase_order_item_id
                );

                $reasons = [];
                $acceptedUnits = 0;
                $reservedUnits = 0;

                $invoiceUnits = $this->quantityUnits(
                    $line->invoiced_quantity
                );

                if (!$poLine) {
                    $reasons[] =
                        'Invoice line does not belong to this PO.';
                } else {
                    // Accepted quantities must come from
                    // completed inventory postings, not
                    // unapproved QC or raw GRN quantities.
                    $postedQuantities = DB::table(
                        'inventory_posting_lines as ipl'
                    )
                        ->join(
                            'goods_receipt_items as gri',
                            'gri.id',
                            '=',
                            'ipl.goods_receipt_item_id'
                        )
                        ->join(
                            'goods_receipts as gr',
                            'gr.id',
                            '=',
                            'gri.goods_receipt_id'
                        )
                        ->join(
                            'inventory_postings as ip',
                            'ip.id',
                            '=',
                            'ipl.inventory_posting_id'
                        )
                        ->where(
                            'gr.purchase_order_id',
                            $po->id
                        )
                        ->where(
                            'gri.purchase_order_item_id',
                            $poLine->id
                        )
                        ->where('ip.status', 'posted')
                        ->select('ipl.accepted_quantity')
                        ->get();

                    foreach ($postedQuantities as $posted) {
                        $acceptedUnits += $this->quantityUnits(
                            $posted->accepted_quantity
                        );
                    }

                    // Previously matched invoices reserve
                    // quantity. Approved invoices will also
                    // continue to count as reserved.
                    $reservedQuantities = DB::table(
                        'supplier_invoice_items as sii'
                    )
                        ->join(
                            'supplier_invoices as si',
                            'si.id',
                            '=',
                            'sii.supplier_invoice_id'
                        )
                        ->where(
                            'si.purchase_order_id',
                            $po->id
                        )
                        ->where(
                            'sii.purchase_order_item_id',
                            $poLine->id
                        )
                        ->where('si.id', '!=', $invoice->id)
                        ->whereIn('si.status', [
                            'matched',
                            'pending_approval',
                            'approved',
                        ])
                        ->select('sii.invoiced_quantity')
                        ->get();

                    foreach ($reservedQuantities as $reserved) {
                        $reservedUnits += $this->quantityUnits(
                            $reserved->invoiced_quantity
                        );
                    }

                    $orderedUnits = $this->quantityUnits(
                        $poLine->quantity
                    );

                    $poPrice = $this->moneyCents(
                        $poLine->unit_price
                    );

                    $invoicePrice = $this->moneyCents(
                        $line->unit_price
                    );

                    $remainingUnits = max(
                        0,
                        min(
                            $orderedUnits,
                            $acceptedUnits
                        ) - $reservedUnits
                    );

                    if ($invoiceUnits > $remainingUnits) {
                        $reasons[] =
                            'Invoice quantity exceeds available ' .
                            'accepted and posted quantity. ' .
                            'Available: ' .
                            $this->formatQuantity($remainingUnits) .
                            ', invoiced: ' .
                            $this->formatQuantity($invoiceUnits) .
                            '.';
                    }

                    if ($invoicePrice !== $poPrice) {
                        $reasons[] =
                            'Invoice unit price does not match PO price. ' .
                            'PO price: ' .
                            $this->formatMoney($poPrice) .
                            ', invoice price: ' .
                            $this->formatMoney($invoicePrice) .
                            '.';
                    }
                }

                $lineMatched = count($reasons) === 0;

                if (!$lineMatched) {
                    $hasMismatch = true;
                }

                $line->update([
                    'match_status' => $lineMatched
                        ? 'matched'
                        : 'mismatch',
                    'matched_quantity' => $lineMatched
                        ? $line->invoiced_quantity
                        : '0.000',
                    'mismatch_reason' => $lineMatched
                        ? null
                        : implode(' ', $reasons),
                ]);

                $results[] = [
                    'purchase_order_item_id' =>
                        $line->purchase_order_item_id,
                    'invoiced_quantity' =>
                        $line->invoiced_quantity,
                    'accepted_posted_quantity' =>
                        $this->formatQuantity($acceptedUnits),
                    'previously_reserved_quantity' =>
                        $this->formatQuantity($reservedUnits),
                    'match_status' =>
                        $lineMatched ? 'matched' : 'mismatch',
                    'reasons' => $reasons,
                ];
            }

            $newStatus = $hasMismatch
                ? 'mismatch'
                : 'matched';

            $invoice->update([
                'status' => $newStatus,
            ]);

            $invoice->activities()->create([
                'user_id' => $actor->id,
                'action' => 'three_way_match',
                'from_status' => $previousStatus,
                'to_status' => $newStatus,
                'reason' => $hasMismatch
                    ? 'One or more invoice lines failed matching.'
                    : 'All invoice lines passed matching.',
                'snapshot' => [
                    'results' => $results,
                ],
            ]);

            return $invoice->load([
                'supplier',
                'purchaseOrder',
                'items.item',
                'activities.user',
            ]);
        }, 3);
    }

    private function quantityUnits(mixed $value): int
    {
        $value = trim((string) $value);

        if (!preg_match(
            '/^(\d{1,12})(?:\.(\d{1,3}))?$/',
            $value,
            $matches
        )) {
            throw ValidationException::withMessages([
                'quantity' => 'Invalid quantity precision.',
            ]);
        }

        return ((int) $matches[1] * 1000)
            + (int) str_pad(
                $matches[2] ?? '',
                3,
                '0'
            );
    }

    private function moneyCents(mixed $value): int
    {
        $value = trim((string) $value);

        if (!preg_match(
            '/^(\d{1,15})(?:\.(\d{1,2}))?$/',
            $value,
            $matches
        )) {
            throw ValidationException::withMessages([
                'price' => 'Invalid price precision.',
            ]);
        }

        return ((int) $matches[1] * 100)
            + (int) str_pad(
                $matches[2] ?? '',
                2,
                '0'
            );
    }

    private function formatQuantity(int $units): string
    {
        return intdiv($units, 1000) . '.' .
            str_pad(
                (string) ($units % 1000),
                3,
                '0',
                STR_PAD_LEFT
            );
    }

    private function formatMoney(int $cents): string
    {
        return intdiv($cents, 100) . '.' .
            str_pad(
                (string) ($cents % 100),
                2,
                '0',
                STR_PAD_LEFT
            );
    }
}
