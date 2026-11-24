<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Http\Request;
use App\Http\Requests\UpdatePurchaseOrderRequest;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private PurchaseOrderService $service
    ) {}

    public function index(Request $request)
    {
        $query = PurchaseOrder::with([
            'supplier', 'creator', 'items.item',
        ])->latest();

        // Buyers see only their own orders.
        if (
            !$request->user()->hasPermission('po.approve')
            && !$request->user()->hasRole('admin')
        ) {
            $query->where('created_by', $request->user()->id);
        }

        return response()->json($query->paginate(15));
    }

    public function store(StorePurchaseOrderRequest $request)
    {
        $po = $this->service->create(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'Purchase Order created',
            'data' => $po,
        ], 201);
    }

    public function show(Request $request, PurchaseOrder $po)
    {
        $user = $request->user();

        if (
            !$user->hasPermission('po.approve')
            && !$user->hasRole('admin')
            && $po->created_by !== $user->id
        ) {
            abort(403);
        }

        return response()->json(
            $po->load([
                'supplier',
                'creator',
                'items.item',
                'approvals.approver',
                'activities.user',
            ])
        );
    }

    public function submit(Request $request, PurchaseOrder $po)
    {
        $updated = $this->service->submit(
            $po,
            $request->user()
        );

        return response()->json([
            'message' => 'PO submitted for approval',
            'data' => $updated,
        ]);
    }

    public function approve(Request $request, PurchaseOrder $po)
    {
        $data = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $updated = $this->service->decide(
            $po,
            $request->user(),
            'approved',
            $data['remarks'] ?? null
        );

        return response()->json([
            'message' => 'PO approved',
            'data' => $updated,
        ]);
    }

    public function reject(Request $request, PurchaseOrder $po)
    {
        $data = $request->validate([
            'remarks' => ['required', 'string', 'max:1000'],
        ]);

        $updated = $this->service->decide(
            $po,
            $request->user(),
            'rejected',
            $data['remarks']
        );

        return response()->json([
            'message' => 'PO rejected',
            'data' => $updated,
        ]);
    }
    public function update(
    UpdatePurchaseOrderRequest $request,
    PurchaseOrder $po
) {
    $updated = $this->service->update(
        $po,
        $request->validated(),
        $request->user()
    );

    return response()->json([
        'message' => 'Purchase Order updated successfully',
        'data' => $updated,
    ]);
}

public function cancel(Request $request, PurchaseOrder $po)
{
    $data = $request->validate([
        'reason' => [
            'required', 'string', 'min:5', 'max:1000',
        ],
    ]);

    $updated = $this->service->cancel(
        $po,
        $request->user(),
        $data['reason']
    );

    return response()->json([
        'message' => 'Purchase Order cancelled successfully',
        'data' => $updated,
    ]);
}
}