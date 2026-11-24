<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'inventory_posting_id',
        'goods_receipt_item_id',
        'warehouse_id',
        'item_id',
        'movement_type',
        'quantity',
        'balance_before',
        'balance_after',
        'movement_date',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'balance_before' => 'decimal:3',
            'balance_after' => 'decimal:3',
            'movement_date' => 'datetime',
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

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
