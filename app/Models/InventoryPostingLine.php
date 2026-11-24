<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryPostingLine extends Model
{
    protected $fillable = [
        'inventory_posting_id',
        'goods_receipt_item_id',
        'accepted_quantity',
        'rejected_quantity',
    ];

    protected function casts(): array
    {
        return [
            'accepted_quantity' => 'decimal:3',
            'rejected_quantity' => 'decimal:3',
        ];
    }

    public function posting(): BelongsTo
    {
        return $this->belongsTo(
            InventoryPosting::class,
            'inventory_posting_id'
        );
    }

    public function goodsReceiptItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class);
    }
}
