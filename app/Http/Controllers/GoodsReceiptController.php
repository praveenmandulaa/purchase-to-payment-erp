<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGoodsReceiptRequest;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use App\Services\GoodsReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GoodsReceiptController extends Controller
{
    public function __construct(
        private readonly GoodsReceiptService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $receipts = GoodsReceipt::query()
            ->with([
                'purchaseOrder:id,po_number',
                'supplier:id,name',
                'warehouse:id,code,name',
                'receiver:id,name',
                'items',
            ])
            ->latest('id')
            ->paginate(15);

        return response()->json($receipts);
    }

    public function show(GoodsReceipt $grn): JsonResponse
    {
        return response()->json(
            $grn->load([
                'purchaseOrder',
                'supplier',
                'warehouse',
                'receiver',
                'items.item',
                'items.purchaseOrderItem',
            ])
        );
    }

    public function store(
        StoreGoodsReceiptRequest $request
    ): JsonResponse {
        $grn = $this->service->create(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'Goods Receipt created successfully.',
            'data' => $grn,
        ], 201);
    }

    public function warehouses(): JsonResponse
    {
        return response()->json(
            Warehouse::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'location'])
        );
    }

    public function approvedPurchaseOrders(): JsonResponse
    {
        $orders = PurchaseOrder::query()
            ->where('status', 'approved')
            ->with([
                'supplier:id,name',
                'items.item:id,code,name,uom,requires_qc',
            ])
            ->orderBy('id')
            ->get();

        $receivedByLine = DB::table('goods_receipt_items as gri')
            ->join(
                'goods_receipts as gr',
                'gr.id',
                '=',
                'gri.goods_receipt_id'
            )
            ->where('gr.status', 'received')
            ->select(
                'gri.purchase_order_item_id',
                DB::raw('SUM(gri.received_quantity) as received_total')
            )
            ->groupBy('gri.purchase_order_item_id')
            ->pluck('received_total', 'purchase_order_item_id');

        $result = $orders->map(function ($po) use ($receivedByLine) {
            $po->items->each(function ($line) use ($receivedByLine) {
                $received = (float) (
                    $receivedByLine[$line->id] ?? 0
                );

                $line->setAttribute(
                    'received_quantity',
                    number_format($received, 3, '.', '')
                );

                $line->setAttribute(
                    'outstanding_quantity',
                    number_format(
                        max(0, (float) $line->quantity - $received),
                        3,
                        '.',
                        ''
                    )
                );
            });

            return $po;
        })->filter(function ($po) {
            return $po->items->contains(
                fn ($line) =>
                    (float) $line->outstanding_quantity > 0
            );
        })->values();

        return response()->json($result);
    }
}
