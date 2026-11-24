<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QcInspectionItem extends Model
{
    protected $fillable = [
        'qc_inspection_id',
        'goods_receipt_item_id',
        'accepted_quantity',
        'rejected_quantity',
        'rejection_reason',
        'remarks',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(
            QcInspection::class,
            'qc_inspection_id'
        );
    }

    public function goodsReceiptItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class);
    }
}