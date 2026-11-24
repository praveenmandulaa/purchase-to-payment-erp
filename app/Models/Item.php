<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $fillable = [
        'code', 'name', 'category',
        'uom', 'requires_qc', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_qc' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}