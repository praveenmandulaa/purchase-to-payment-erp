<?php
namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\SupplierInvoice;
use App\Services\SupplierInvoiceService;
use App\Services\SupplierInvoiceMatchingService;
use App\Services\SupplierInvoiceApprovalService;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierInvoiceController extends Controller
{
    /**
     * Approved purchase orders available for invoice creation.
     */
    public function approvedPurchaseOrders(): JsonResponse
    {
        $orders = PurchaseOrder::query()
            ->where('status', 'approved')
            ->with(['supplier', 'items.item'])
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn ($po) => [
                'id' => $po->id,
                'po_number' => $po->po_number,
                'supplier_id' => $po->supplier_id,
                'supplier_name' => $po->supplier?->name,
                'items' => $po->items->map(fn ($line) => [
                    'id' => $line->id,
                    'item_id' => $line->item_id,
                    'item_code' => $line->item?->code,
                    'item_name' => $line->item?->name,
                    'uom' => $line->item?->uom,
                    'ordered_quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                ])->values(),
            ]);

        return response()->json([
            'data' => $orders,
        ]);
    }

    /**
     * List supplier invoices.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => [
                'nullable',
                Rule::in([
                    'draft',
                    'pending_matching',
                    'matched',
                    'mismatch',
                    'pending_approval',
                    'approved',
                    'rejected',
                ]),
            ],
        ]);

        $invoices = SupplierInvoice::query()
            ->with(['supplier', 'purchaseOrder', 'createdBy'])
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) =>
                    $query->where('status', $status)
            )
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json($invoices);
    }

    /**
     * Create a draft supplier invoice.
     */
    public function store(
        Request $request,
        SupplierInvoiceService $service
    ): JsonResponse {
        $validated = $request->validate([
            'purchase_order_id' => [
                'required',
                'integer',
                'exists:purchase_orders,id',
            ],
            'supplier_id' => [
                'required',
                'integer',
                'exists:suppliers,id',
            ],
            'supplier_invoice_number' => [
                'required',
                'string',
                'max:100',
            ],
            'invoice_date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'due_date' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:invoice_date',
            ],
            'tax_amount' => [
                'nullable',
                'regex:/^\d{1,15}(\.\d{1,2})?$/',
            ],
            'remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'items' => [
                'required',
                'array',
                'min:1',
            ],
            'items.*.purchase_order_item_id' => [
                'required',
                'integer',
                'exists:purchase_order_items,id',
            ],
            'items.*.invoiced_quantity' => [
                'required',
                'regex:/^\d{1,12}(\.\d{1,3})?$/',
                'not_in:0,0.0,0.00,0.000',
            ],
            'items.*.unit_price' => [
                'required',
                'regex:/^\d{1,15}(\.\d{1,2})?$/',
            ],
        ]);

        $invoice = $service->createDraft(
            $validated,
            $request->user()
        );

        return response()->json([
            'message' => 'Supplier invoice draft created successfully.',
            'data' => $invoice,
        ], 201);
    }

    /**
     * View one invoice and its audit history.
     */
    public function show(SupplierInvoice $invoice): JsonResponse
    {
        $invoice->load([
            'supplier',
            'purchaseOrder',
            'createdBy',
            'approvedBy',
            'items.item',
            'items.purchaseOrderItem',
            'activities.user',
        ]);

        return response()->json([
            'data' => $invoice,
        ]);
    }


public function match(
    Request $request,
    SupplierInvoice $invoice,
    SupplierInvoiceMatchingService $service
): JsonResponse {
    $invoice = $service->match(
        $invoice,
        $request->user()
    );

    return response()->json([
        'message' => $invoice->status === 'matched'
            ? 'Invoice passed 3-way matching.'
            : 'Invoice has matching discrepancies.',
        'data' => $invoice,
    ]);
}

public function submit(
    Request $request,
    SupplierInvoice $invoice,
    SupplierInvoiceApprovalService $service
): JsonResponse {
    $invoice = $service->submit(
        $invoice,
        $request->user()
    );

    return response()->json([
        'message' => 'Invoice submitted for approval.',
        'data' => $invoice,
    ]);
}

public function approve(
    Request $request,
    SupplierInvoice $invoice,
    SupplierInvoiceApprovalService $service
): JsonResponse {
    $invoice = $service->approve(
        $invoice,
        $request->user()
    );

    return response()->json([
        'message' => 'Supplier invoice approved successfully.',
        'data' => $invoice,
    ]);
}

public function reject(
    Request $request,
    SupplierInvoice $invoice,
    SupplierInvoiceApprovalService $service
): JsonResponse {
    $validated = $request->validate([
        'reason' => [
            'required',
            'string',
            'min:5',
            'max:2000',
        ],
    ]);

    $invoice = $service->reject(
        $invoice,
        $request->user(),
        $validated['reason']
    );

    return response()->json([
        'message' => 'Supplier invoice rejected.',
        'data' => $invoice,
    ]);
}

}
