<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('po.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'expected_delivery_date' => [
                'nullable', 'date', 'after_or_equal:today',
            ],
            'remarks' => ['nullable', 'string', 'max:1000'],

            'items' => ['required', 'array', 'min:1', 'max:100'],

            'items.*.item_id' => [
    'required',
    'integer',
    'distinct',
    'exists:items,id',
],

            'items.*.quantity' => [
                'required', 'integer', 'min:1', 'max:1000000',
            ],

            'items.*.unit_price' => [
                'required',
                'numeric',
                'min:0.01',
                'max:9999999',
                'decimal:0,2',
            ],
        ];
    }
}