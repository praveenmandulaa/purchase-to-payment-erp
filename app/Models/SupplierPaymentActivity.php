<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPaymentActivity extends Model
{
    protected $fillable = [
        'supplier_payment_id',
        'user_id',
        'action',
        'from_status',
        'to_status',
        'reason',
        'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(
            SupplierPayment::class,
            'supplier_payment_id'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
