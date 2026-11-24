<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    protected $fillable = [
        'goods_receipt_id',
        'purchase_order_item_id',
        'item_id',
        'received_quantity',
        'qc_status',
    ];

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseOrderItem::class,
            'purchase_order_item_id'
        );
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function qcInspectionItems(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(QcInspectionItem::class);
}
}