<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends Model
{
    protected $fillable = [
        'grn_number',
        'purchase_order_id',
        'supplier_id',
        'warehouse_id',
        'received_by',
        'receipt_date',
        'delivery_reference',
        'status',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'receipt_date' => 'date:Y-m-d',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }
    public function qcInspections(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(QcInspection::class);
}
}