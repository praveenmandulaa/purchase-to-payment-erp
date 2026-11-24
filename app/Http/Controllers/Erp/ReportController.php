<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const MAX_ROWS = 5000;

    private const REPORTS = [
        'purchase_orders',
        'goods_receipts',
        'inventory',
        'invoices',
        'payments',
        'payables',
    ];

    public function index(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        $report = $filters['report'];

        $rows = $this->query($report, $filters)
            ->limit(self::MAX_ROWS)
            ->get();

        return response()->json([
            'report' => $report,
            'filters' => $filters,
            'rows' => $rows,
            'row_count' => $rows->count(),
            'row_limit' => self::MAX_ROWS,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);

        $report = $filters['report'];

        $columns = $this->columns($report);

        $filename = sprintf(
            'erp_%s_%s.csv',
            $report,
            now()->format('Ymd_His')
        );

        return response()->streamDownload(
            function () use ($report, $filters, $columns) {
                $output = fopen('php://output', 'w');

                // UTF-8 BOM for Microsoft Excel.
                fwrite($output, "\xEF\xBB\xBF");

                fputcsv($output, array_values($columns));

                $rows = $this->query($report, $filters)
                    ->limit(self::MAX_ROWS)
                    ->get();

                foreach ($rows as $row) {
                    $values = [];

                    foreach (array_keys($columns) as $key) {
                        $values[] = $this->safeCsvValue(
                            $row->{$key} ?? ''
                        );
                    }

                    fputcsv($output, $values);
                }

                fclose($output);
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]
        );
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'report' => [
                'required',
                Rule::in(self::REPORTS),
            ],
            'from_date' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'to_date' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:from_date',
            ],
            'status' => [
                'nullable',
                'string',
                'max:40',
            ],
        ]);
    }

    private function query(string $report, array $filters)
{
    return match ($report) {
        'purchase_orders' => $this->purchaseOrdersQuery($filters),
        'goods_receipts' => $this->goodsReceiptsQuery($filters),
        'inventory' => $this->inventoryQuery($filters),
        'invoices' => $this->invoicesQuery($filters),
        'payments' => $this->paymentsQuery($filters),
        'payables' => $this->payablesQuery($filters),
    };
}

    private function invoicesQuery(array $filters)
    {
        $query = DB::table('supplier_invoices as si')
            ->leftJoin(
                'suppliers as s',
                's.id',
                '=',
                'si.supplier_id'
            )
            ->select([
                'si.invoice_number',
                'si.supplier_invoice_number',
                's.name as supplier',
                'si.invoice_date',
                'si.due_date',
                'si.status',
                'si.currency',
                'si.subtotal',
                'si.tax_amount',
                'si.total_amount',
            ])
            ->orderByDesc('si.id');

        $this->applyDates(
            $query,
            'si.invoice_date',
            $filters
        );

        if (!empty($filters['status'])) {
            $query->where(
                'si.status',
                $filters['status']
            );
        }

        return $query;
    }

private function purchaseOrdersQuery(array $filters)
{
    $query = DB::table('purchase_orders as po')
        ->leftJoin(
            'suppliers as s',
            's.id',
            '=',
            'po.supplier_id'
        )
        ->select([
            'po.po_number',
            's.name as supplier',
            'po.po_date',
            'po.expected_delivery_date',
            'po.status',
            'po.total_amount',
            'po.remarks',
        ])
        ->orderByDesc('po.id');

    $this->applyDates(
        $query,
        'po.po_date',
        $filters
    );

    if (!empty($filters['status'])) {
        $query->where(
            'po.status',
            $filters['status']
        );
    }

    return $query;
}

private function goodsReceiptsQuery(array $filters)
{
    $query = DB::table('goods_receipts as gr')
        ->leftJoin(
            'purchase_orders as po',
            'po.id',
            '=',
            'gr.purchase_order_id'
        )
        ->leftJoin(
            'suppliers as s',
            's.id',
            '=',
            'gr.supplier_id'
        )
        ->leftJoin(
            'warehouses as w',
            'w.id',
            '=',
            'gr.warehouse_id'
        )
        ->select([
            'gr.grn_number',
            'po.po_number',
            's.name as supplier',
            'w.name as warehouse',
            'gr.receipt_date',
            'gr.delivery_reference',
            'gr.status',
            'gr.remarks',
        ])
        ->orderByDesc('gr.id');

    $this->applyDates(
        $query,
        'gr.receipt_date',
        $filters
    );

    if (!empty($filters['status'])) {
        $query->where(
            'gr.status',
            $filters['status']
        );
    }

    return $query;
}

private function inventoryQuery(array $filters)
{
    $query = DB::table('inventory_balances as ib')
        ->join(
            'items as i',
            'i.id',
            '=',
            'ib.item_id'
        )
        ->join(
            'warehouses as w',
            'w.id',
            '=',
            'ib.warehouse_id'
        )
        ->select([
            'w.code as warehouse_code',
            'w.name as warehouse',
            'i.code as item_code',
            'i.name as item',
            'i.category',
            'i.uom',
            'ib.available_quantity',
            'ib.rejected_quantity',
            'ib.updated_at',
        ])
        ->orderBy('w.name')
        ->orderBy('i.name');

    /*
     * Inventory balances are current stock snapshots,
     * not dated transactions.
     *
     * Date filters are deliberately not applied here.
     * Historical stock reporting requires stock movements.
     */

    if (!empty($filters['status'])) {
        if ($filters['status'] === 'available') {
            $query->where(
                'ib.available_quantity',
                '>',
                0
            );
        } elseif ($filters['status'] === 'rejected') {
            $query->where(
                'ib.rejected_quantity',
                '>',
                0
            );
        } elseif ($filters['status'] === 'zero_stock') {
            $query->where(
                'ib.available_quantity',
                '=',
                0
            );
        } else {
            $query->whereRaw('1 = 0');
        }
    }

    return $query;
}


    private function paymentsQuery(array $filters)
    {
        $query = DB::table('supplier_payments as sp')
            ->leftJoin(
                'supplier_invoices as si',
                'si.id',
                '=',
                'sp.supplier_invoice_id'
            )
            ->leftJoin(
                'suppliers as s',
                's.id',
                '=',
                'sp.supplier_id'
            )
            ->select([
                'sp.payment_number',
                'si.invoice_number',
                's.name as supplier',
                'sp.payment_date',
                'sp.amount',
                'sp.payment_method',
                'sp.status',
            ])
            ->orderByDesc('sp.id');

        // Support the actual reference column if present.
        if (Schema::hasColumn(
            'supplier_payments',
            'payment_reference'
        )) {
            $query->addSelect(
                'sp.payment_reference as reference'
            );
        } elseif (Schema::hasColumn(
            'supplier_payments',
            'reference_number'
        )) {
            $query->addSelect(
                'sp.reference_number as reference'
            );
        } else {
            $query->selectRaw("'' as reference");
        }

        $this->applyDates(
            $query,
            'sp.payment_date',
            $filters
        );

        if (!empty($filters['status'])) {
            $query->where(
                'sp.status',
                $filters['status']
            );
        }

        return $query;
    }

    private function payablesQuery(array $filters)
    {
        /*
         * Aggregate recorded payments by invoice first.
         * This prevents duplicate invoice totals when an
         * invoice has multiple partial payments.
         */
        $paidByInvoice = DB::table('supplier_payments')
            ->select('supplier_invoice_id')
            ->selectRaw(
                'SUM(amount) as paid_amount'
            )
            ->where('status', 'recorded')
            ->groupBy('supplier_invoice_id');

        $query = DB::table('supplier_invoices as si')
            ->leftJoin(
                'suppliers as s',
                's.id',
                '=',
                'si.supplier_id'
            )
            ->leftJoinSub(
                $paidByInvoice,
                'p',
                function ($join) {
                    $join->on(
                        'p.supplier_invoice_id',
                        '=',
                        'si.id'
                    );
                }
            )
            ->where('si.status', 'approved')
            ->select([
                'si.invoice_number',
                'si.supplier_invoice_number',
                's.name as supplier',
                'si.invoice_date',
                'si.due_date',
                'si.currency',
                'si.total_amount',
            ])
            ->selectRaw(
                'COALESCE(p.paid_amount, 0) as paid_amount'
            )
            ->selectRaw(
                '(si.total_amount -
                  COALESCE(p.paid_amount, 0))
                  as outstanding_amount'
            )
            ->selectRaw(
                "CASE
                    WHEN COALESCE(p.paid_amount, 0) = 0
                        THEN 'unpaid'
                    WHEN COALESCE(p.paid_amount, 0)
                        >= si.total_amount
                        THEN 'paid'
                    ELSE 'partially_paid'
                 END as payment_status"
            )
            ->selectRaw(
                'CASE
                    WHEN si.due_date < CURRENT_DATE
                    THEN DATEDIFF(CURRENT_DATE, si.due_date)
                    ELSE 0
                 END as days_overdue'
            )
            ->whereRaw(
                '(si.total_amount -
                  COALESCE(p.paid_amount, 0)) > 0'
            )
            ->orderBy('si.due_date')
            ->orderBy('si.id');

        $this->applyDates(
            $query,
            'si.due_date',
            $filters
        );

        if (!empty($filters['status'])) {
            $status = $filters['status'];

            if ($status === 'unpaid') {
                $query->whereRaw(
                    'COALESCE(p.paid_amount, 0) = 0'
                );
            } elseif ($status === 'partially_paid') {
                $query->whereRaw(
                    'COALESCE(p.paid_amount, 0) > 0'
                )->whereRaw(
                    'COALESCE(p.paid_amount, 0)
                     < si.total_amount'
                );
            } elseif ($status === 'overdue') {
                $query->where(
                    'si.due_date',
                    '<',
                    now()->toDateString()
                );
            } else {
                // Invalid payable status returns no records.
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    private function applyDates(
        $query,
        string $column,
        array $filters
    ): void {
        if (!empty($filters['from_date'])) {
            $query->whereDate(
                $column,
                '>=',
                $filters['from_date']
            );
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate(
                $column,
                '<=',
                $filters['to_date']
            );
        }
    }

   
private function columns(string $report): array
{
    return match ($report) {
        'purchase_orders' => [
            'po_number' => 'Purchase Order',
            'supplier' => 'Supplier',
            'po_date' => 'PO Date',
            'expected_delivery_date' => 'Expected Delivery',
            'status' => 'Status',
            'total_amount' => 'Total Amount',
            'remarks' => 'Remarks',
        ],

        'goods_receipts' => [
            'grn_number' => 'GRN Number',
            'po_number' => 'Purchase Order',
            'supplier' => 'Supplier',
            'warehouse' => 'Warehouse',
            'receipt_date' => 'Receipt Date',
            'delivery_reference' => 'Delivery Reference',
            'status' => 'Status',
            'remarks' => 'Remarks',
        ],

        'inventory' => [
            'warehouse_code' => 'Warehouse Code',
            'warehouse' => 'Warehouse',
            'item_code' => 'Item Code',
            'item' => 'Item',
            'category' => 'Category',
            'uom' => 'Unit of Measure',
            'available_quantity' => 'Available Quantity',
            'rejected_quantity' => 'Rejected Quantity',
            'updated_at' => 'Last Updated',
        ],

        'invoices' => [
            'invoice_number' => 'ERP Invoice',
            'supplier_invoice_number' => 'Supplier Invoice',
            'supplier' => 'Supplier',
            'invoice_date' => 'Invoice Date',
            'due_date' => 'Due Date',
            'status' => 'Approval Status',
            'currency' => 'Currency',
            'subtotal' => 'Subtotal',
            'tax_amount' => 'Tax Amount',
            'total_amount' => 'Total Amount',
        ],

        'payments' => [
            'payment_number' => 'Payment Number',
            'invoice_number' => 'ERP Invoice',
            'supplier' => 'Supplier',
            'payment_date' => 'Payment Date',
            'amount' => 'Amount',
            'payment_method' => 'Payment Method',
            'reference' => 'Reference',
            'status' => 'Status',
        ],

        'payables' => [
            'invoice_number' => 'ERP Invoice',
            'supplier_invoice_number' => 'Supplier Invoice',
            'supplier' => 'Supplier',
            'invoice_date' => 'Invoice Date',
            'due_date' => 'Due Date',
            'currency' => 'Currency',
            'total_amount' => 'Invoice Total',
            'paid_amount' => 'Paid Amount',
            'outstanding_amount' => 'Outstanding Amount',
            'payment_status' => 'Payment Status',
            'days_overdue' => 'Days Overdue',
        ],
    };
}


    private function safeCsvValue(mixed $value): string
    {
        $value = (string) $value;

        // Prevent spreadsheet formula injection.
        if (preg_match('/^[\s]*[=+\-@]/u', $value)) {
            return "'" . $value;
        }

        return $value;
    }
}
