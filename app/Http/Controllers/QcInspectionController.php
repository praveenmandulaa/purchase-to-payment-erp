<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQcInspectionRequest;
use App\Models\QcInspection;
use App\Services\QcInspectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QcInspectionController extends Controller
{
    public function __construct(
        private readonly QcInspectionService $service
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(
            QcInspection::query()
                ->with([
                    'goodsReceipt:id,grn_number',
                    'inspector:id,name',
                    'decisionMaker:id,name',
                    'items',
                ])
                ->latest('id')
                ->paginate(15)
        );
    }

    public function show(QcInspection $inspection): JsonResponse
    {
        return response()->json(
            $inspection->load([
                'goodsReceipt.purchaseOrder',
                'goodsReceipt.warehouse',
                'inspector',
                'decisionMaker',
                'items.goodsReceiptItem.item',
                'activities.user',
            ])
        );
    }

    public function store(
        StoreQcInspectionRequest $request
    ): JsonResponse {
        $inspection = $this->service->create(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'QC inspection created.',
            'data' => $inspection,
        ], 201);
    }

    public function submit(
        Request $request,
        QcInspection $inspection
    ): JsonResponse {
        $result = $this->service->submit(
            $inspection,
            $request->user()
        );

        return response()->json([
            'message' => 'QC inspection submitted.',
            'data' => $result,
        ]);
    }

    public function approve(
        Request $request,
        QcInspection $inspection
    ): JsonResponse {
        $data = Validator::make($request->all(), [
            'reason' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $result = $this->service->decide(
            $inspection,
            $request->user(),
            'approved',
            $data['reason'] ?? null
        );

        return response()->json([
            'message' => 'QC inspection approved.',
            'data' => $result,
        ]);
    }

    public function reject(
        Request $request,
        QcInspection $inspection
    ): JsonResponse {
        $data = Validator::make($request->all(), [
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ])->validate();

        $result = $this->service->decide(
            $inspection,
            $request->user(),
            'rejected',
            $data['reason']
        );

        return response()->json([
            'message' => 'QC inspection rejected.',
            'data' => $result,
        ]);
    }
}