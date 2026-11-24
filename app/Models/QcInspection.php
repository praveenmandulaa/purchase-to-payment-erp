<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QcInspection extends Model
{
    protected $fillable = [
        'inspection_number',
        'goods_receipt_id',
        'inspected_by',
        'decided_by',
        'status',
        'inspection_date',
        'remarks',
        'decision_reason',
        'submitted_at',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'inspection_date' => 'date:Y-m-d',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function decisionMaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QcInspectionItem::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(QcInspectionActivity::class);
    }
}