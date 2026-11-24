<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('grn.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'purchase_order_id' => [
                'required', 'integer', 'exists:purchase_orders,id',
            ],
            'warehouse_id' => [
                'required', 'integer', 'exists:warehouses,id',
            ],
            'receipt_date' => [
                'required', 'date', 'before_or_equal:today',
            ],
            'delivery_reference' => [
                'required', 'string', 'max:100',
            ],
            'remarks' => [
                'nullable', 'string', 'max:1000',
            ],
            'items' => [
                'required', 'array', 'min:1', 'max:100',
            ],
            'items.*.purchase_order_item_id' => [
                'required', 'integer', 'distinct',
                'exists:purchase_order_items,id',
            ],
            'items.*.received_quantity' => [
                'required', 'integer', 'min:1', 'max:1000000',
            ],
        ];
    }
}