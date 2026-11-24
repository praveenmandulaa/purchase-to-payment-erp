<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPayment extends Model
{
    protected $fillable = [
        'payment_number',
        'supplier_invoice_id',
        'supplier_id',
        'payment_date',
        'amount',
        'currency',
        'payment_method',
        'payment_reference',
        'status',
        'recorded_by',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(
            SupplierInvoice::class,
            'supplier_invoice_id'
        );
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'recorded_by'
        );
    }

    public function activities(): HasMany
    {
        return $this->hasMany(
            SupplierPaymentActivity::class
        );
    }
}
