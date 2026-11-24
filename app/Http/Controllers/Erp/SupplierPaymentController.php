<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Services\SupplierPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierPaymentController extends Controller
{
    /**
     * List approved invoices with payment balances.
     */
    public function approvedInvoices(): JsonResponse
    {
        $invoices = SupplierInvoice::query()
            ->with(['supplier'])
            ->where('status', 'approved')
            ->withSum(
                ['payments as paid_amount' => function ($query) {
                    $query->where('status', 'recorded');
                }],
                'amount'
            )
            ->orderByDesc('id')
            ->get()
            ->map(function ($invoice) {
                $total = (string) $invoice->total_amount;
                $paid = (string) ($invoice->paid_amount ?? '0.00');

                $outstanding = bcsub($total, $paid, 2);

                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'supplier_invoice_number' =>
                        $invoice->supplier_invoice_number,
                    'supplier_id' => $invoice->supplier_id,
                    'supplier_name' =>
                        $invoice->supplier?->name,
                    'invoice_date' => $invoice->invoice_date,
                    'due_date' => $invoice->due_date,
                    'status' => $invoice->status,
                    'currency' => $invoice->currency,
                    'total_amount' => $total,
                    'paid_amount' => $paid,
                    'outstanding_amount' => $outstanding,
                    'payment_status' => bccomp(
                        $outstanding,
                        '0.00',
                        2
                    ) <= 0
                        ? 'paid'
                        : (
                            bccomp($paid, '0.00', 2) > 0
                                ? 'partially_paid'
                                : 'unpaid'
                        ),
                ];
            });

        return response()->json([
            'data' => $invoices,
        ]);
    }

    /**
     * Payment register.
     */
    public function index(): JsonResponse
    {
        $payments = SupplierPayment::query()
            ->with([
                'invoice:id,invoice_number',
                'supplier:id,name',
                'recordedBy:id,name',
            ])
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json($payments);
    }

    /**
     * Record a new supplier payment.
     */
    public function store(
        Request $request,
        SupplierPaymentService $service
    ): JsonResponse {
        $data = $request->validate([
            'supplier_invoice_id' => [
                'required',
                'integer',
                'exists:supplier_invoices,id',
            ],
            'payment_date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'amount' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,2',
                'regex:/^\d{1,13}(\.\d{1,2})?$/',
            ],
            'payment_method' => [
                'required',
                'in:bank_transfer,upi,cheque,cash',
            ],
            'payment_reference' => [
                'required',
                'string',
                'max:100',
            ],
            'remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $invoice = SupplierInvoice::findOrFail(
            $data['supplier_invoice_id']
        );

        $payment = $service->record(
            $invoice,
            $request->user(),
            $data
        );

        return response()->json([
            'message' => 'Supplier payment recorded successfully.',
            'payment' => $payment,
        ], 201);
    }

    /**
     * Show payment details and audit history.
     */
    public function show(SupplierPayment $payment): JsonResponse
    {
        return response()->json([
            'payment' => $payment->load([
                'invoice',
                'supplier',
                'recordedBy:id,name',
                'activities.user:id,name',
            ]),
        ]);
    }
}
