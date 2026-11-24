<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBalance extends Model
{
    protected $fillable = [
        'warehouse_id',
        'item_id',
        'available_quantity',
        'rejected_quantity',
    ];

    protected function casts(): array
    {
        return [
            'available_quantity' => 'decimal:3',
            'rejected_quantity' => 'decimal:3',
        ];
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
