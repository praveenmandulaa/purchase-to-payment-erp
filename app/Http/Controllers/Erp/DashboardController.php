<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        /*
         * All monetary calculations are performed in MySQL
         * using DECIMAL columns, not PHP floating-point sums.
         */

        $purchaseOrders = DB::table('purchase_orders')
            ->selectRaw('COUNT(*) as total')
            ->first();

        $goodsReceipts = DB::table('goods_receipts')
            ->selectRaw('COUNT(*) as total')
            ->first();

        $qcInspections = DB::table('qc_inspections')
            ->selectRaw('COUNT(*) as total')
            ->first();

        $inventoryPostings = DB::table('inventory_postings')
            ->selectRaw('COUNT(*) as total')
            ->first();

        $invoiceTotals = SupplierInvoice::query()
            ->selectRaw('COUNT(*) as total_invoices')
            ->selectRaw(
                "COALESCE(SUM(
                    CASE WHEN status = 'approved'
                    THEN total_amount ELSE 0 END
                ), 0) as approved_amount"
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'approved'
                    THEN 1 ELSE 0 END) as approved_count"
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'pending_approval'
                    THEN 1 ELSE 0 END) as pending_count"
            )
            ->first();

        $paymentTotals = SupplierPayment::query()
            ->where('status', 'recorded')
            ->selectRaw('COUNT(*) as total_payments')
            ->selectRaw(
                'COALESCE(SUM(amount), 0) as paid_amount'
            )
            ->first();

        /*
         * Payments can be partial.
         * Outstanding = approved invoice total - recorded payments.
         */
        $approvedAmount = (string) $invoiceTotals->approved_amount;
        $paidAmount = (string) $paymentTotals->paid_amount;

        $outstandingAmount = bcsub(
            $approvedAmount,
            $paidAmount,
            2
        );

        /*
         * Inventory quantities are returned as strings to
         * preserve the database's decimal precision.
         */
        $inventoryTotals = DB::table('inventory_balances')
            ->selectRaw(
                'COALESCE(SUM(available_quantity), 0)
                    as available_quantity'
            )
            ->selectRaw(
                'COALESCE(SUM(rejected_quantity), 0)
                    as rejected_quantity'
            )
            ->first();

        /*
         * Recent invoices for the dashboard.
         */
        $recentInvoices = SupplierInvoice::query()
            ->with('supplier:id,name')
            ->orderByDesc('id')
            ->limit(5)
            ->get([
                'id',
                'invoice_number',
                'supplier_id',
                'status',
                'total_amount',
                'invoice_date',
            ]);

        /*
         * Recent recorded payments.
         */
        $recentPayments = SupplierPayment::query()
            ->with([
                'invoice:id,invoice_number',
                'supplier:id,name',
            ])
            ->where('status', 'recorded')
            ->orderByDesc('id')
            ->limit(5)
            ->get([
                'id',
                'payment_number',
                'supplier_invoice_id',
                'supplier_id',
                'amount',
                'payment_date',
                'status',
            ]);

        return response()->json([
            'purchase_orders' => [
                'total' => (int) $purchaseOrders->total,
            ],
            'goods_receipts' => [
                'total' => (int) $goodsReceipts->total,
            ],
            'quality_control' => [
                'total' => (int) $qcInspections->total,
            ],
            'inventory' => [
                'total_postings' =>
                    (int) $inventoryPostings->total,
                'available_quantity' =>
                    (string) $inventoryTotals->available_quantity,
                'rejected_quantity' =>
                    (string) $inventoryTotals->rejected_quantity,
            ],
            'invoices' => [
                'total' =>
                    (int) $invoiceTotals->total_invoices,
                'approved_count' =>
                    (int) $invoiceTotals->approved_count,
                'pending_count' =>
                    (int) $invoiceTotals->pending_count,
                'approved_amount' => $approvedAmount,
            ],
            'payments' => [
                'total' =>
                    (int) $paymentTotals->total_payments,
                'paid_amount' => $paidAmount,
                'outstanding_amount' => $outstandingAmount,
            ],
            'recent_invoices' => $recentInvoices,
            'recent_payments' => $recentPayments,
        ]);
    }
}
