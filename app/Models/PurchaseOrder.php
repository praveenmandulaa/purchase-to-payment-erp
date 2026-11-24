<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'po_number',
        'supplier_id',
        'created_by',
        'po_date',
        'expected_delivery_date',
        'status',
        'total_amount',
        'remarks',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PurchaseOrderApproval::class);
    }
    public function activities(): HasMany
{
    return $this->hasMany(PurchaseOrderActivity::class);
}
public function goodsReceipts(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(GoodsReceipt::class);
}
}