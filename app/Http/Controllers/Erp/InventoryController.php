<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\InventoryBalance;
use App\Models\InventoryPosting;
use App\Models\InventoryPostingLine;
use App\Services\InventoryPostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function balances(Request $request): JsonResponse
    {
        $balances = InventoryBalance::query()
            ->with(['warehouse', 'item'])
            ->when(
                $request->filled('warehouse_id'),
                fn ($query) => $query->where(
                    'warehouse_id',
                    $request->integer('warehouse_id')
                )
            )
            ->orderBy('warehouse_id')
            ->orderBy('item_id')
            ->get()
            ->map(fn ($balance) => [
                'id' => $balance->id,
                'warehouse_id' => $balance->warehouse_id,
                'warehouse' => $balance->warehouse?->name,
                'item_id' => $balance->item_id,
                'item_code' => $balance->item?->code,
                'item_name' => $balance->item?->name,
                'uom' => $balance->item?->uom,
                'available_quantity' => $balance->available_quantity,
                'rejected_quantity' => $balance->rejected_quantity,
            ]);

        return response()->json(['data' => $balances]);
    }

    public function eligibleGrns(): JsonResponse
    {
        $grns = GoodsReceipt::query()
            ->where('status', 'received')
            ->with([
                'warehouse',
                'purchaseOrder',
                'items.item',
                'qcInspections' => fn ($query) =>
                    $query->where('status', 'approved')
                        ->with('items'),
            ])
            ->orderByDesc('id')
            ->get();

        $postedLineIds = InventoryPostingLine::query()
            ->whereIn(
                'goods_receipt_item_id',
                $grns->flatMap(
                    fn ($grn) => $grn->items->pluck('id')
                )->all()
            )
            ->pluck('goods_receipt_item_id')
            ->flip();

        $results = [];

        foreach ($grns as $grn) {
            $approved = $grn->qcInspections
                ->sortByDesc('id')
                ->first();

            $approvedLineIds = $approved
                ? $approved->items
                    ->pluck('goods_receipt_item_id')
                    ->flip()
                : collect();

            $eligibleLines = $grn->items->filter(
                function ($line) use (
                    $postedLineIds,
                    $approvedLineIds
                ) {
                    if ($postedLineIds->has($line->id)) {
                        return false;
                    }

                    return $line->qc_status === 'qc_not_required'
                        || (
                            $line->qc_status === 'pending_qc'
                            && $approvedLineIds->has($line->id)
                        );
                }
            );

            if ($eligibleLines->isEmpty()) {
                continue;
            }

            $results[] = [
                'id' => $grn->id,
                'grn_number' => $grn->grn_number,
                'warehouse' => $grn->warehouse?->name,
                'receipt_date' => $grn->receipt_date,
                'qc_inspection_number' =>
                    $approved?->inspection_number,
                'eligible_line_count' => $eligibleLines->count(),
                'items' => $eligibleLines->map(
                    fn ($line) => [
                        'item_code' => $line->item?->code,
                        'item_name' => $line->item?->name,
                        'received_quantity' =>
                            $line->received_quantity,
                        'qc_status' => $line->qc_status,
                    ]
                )->values(),
            ];
        }

        return response()->json(['data' => $results]);
    }

    public function postings(): JsonResponse
    {
        $postings = InventoryPosting::query()
            ->with([
                'goodsReceipt',
                'warehouse',
                'postedBy',
                'lines.goodsReceiptItem.item',
            ])
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn ($posting) => [
                'id' => $posting->id,
                'posting_number' => $posting->posting_number,
                'grn_number' =>
                    $posting->goodsReceipt?->grn_number,
                'warehouse' => $posting->warehouse?->name,
                'posted_by' => $posting->postedBy?->name,
                'posted_at' => $posting->posted_at,
                'status' => $posting->status,
                'items' => $posting->lines->map(
                    fn ($line) => [
                        'item_code' =>
                            $line->goodsReceiptItem?->item?->code,
                        'item_name' =>
                            $line->goodsReceiptItem?->item?->name,
                        'accepted_quantity' =>
                            $line->accepted_quantity,
                        'rejected_quantity' =>
                            $line->rejected_quantity,
                    ]
                ),
            ]);

        return response()->json(['data' => $postings]);
    }

    public function post(
        Request $request,
        GoodsReceipt $grn,
        InventoryPostingService $service
    ): JsonResponse {
        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $posting = $service->post(
            $grn,
            $request->user(),
            $validated['remarks'] ?? null
        );

        return response()->json([
            'message' => 'Inventory posted successfully.',
            'data' => [
                'id' => $posting->id,
                'posting_number' => $posting->posting_number,
                'grn_id' => $grn->id,
                'status' => $posting->status,
            ],
        ], 201);
    }
}
