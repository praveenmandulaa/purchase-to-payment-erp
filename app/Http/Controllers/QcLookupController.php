<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use Illuminate\Http\JsonResponse;

class QcLookupController extends Controller
{
    public function eligibleGrns(): JsonResponse
    {
        $grns = GoodsReceipt::query()
            ->where('status', 'received')
            ->whereHas('items', function ($query) {
                $query->where('qc_status', 'pending_qc');
            })
            ->whereDoesntHave('qcInspections', function ($query) {
                $query->whereIn('status', [
                    'draft',
                    'pending_approval',
                    'approved',
                ]);
            })
            ->with([
                'purchaseOrder:id,po_number',
                'warehouse:id,code,name',
                'items' => function ($query) {
                    $query->where('qc_status', 'pending_qc')
                        ->with('item:id,code,name');
                },
            ])
            ->latest('id')
            ->get();

        return response()->json($grns);
    }
}