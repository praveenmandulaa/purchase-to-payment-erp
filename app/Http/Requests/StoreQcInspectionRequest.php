<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQcInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('qc.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'goods_receipt_id' => [
                'required',
                'integer',
                'exists:goods_receipts,id',
            ],
            'inspection_date' => [
                'required',
                'date',
                'before_or_equal:today',
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
                'max:100',
            ],
            'items.*.goods_receipt_item_id' => [
                'required',
                'integer',
                'distinct',
                'exists:goods_receipt_items,id',
            ],
            'items.*.accepted_quantity' => [
                'required',
                'integer',
                'min:0',
            ],
            'items.*.rejected_quantity' => [
                'required',
                'integer',
                'min:0',
            ],
            'items.*.rejection_reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'items.*.remarks' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}