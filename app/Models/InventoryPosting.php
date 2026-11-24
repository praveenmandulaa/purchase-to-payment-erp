<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryPosting extends Model
{
    protected $fillable = [
        'posting_number',
        'goods_receipt_id',
        'qc_inspection_id',
        'warehouse_id',
        'posted_by',
        'status',
        'posted_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function qcInspection(): BelongsTo
    {
        return $this->belongsTo(QcInspection::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
    public function lines(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(InventoryPostingLine::class);
}
}
