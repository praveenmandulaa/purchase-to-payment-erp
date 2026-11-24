<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvoiceItem extends Model
{
    protected $fillable = [
        'supplier_invoice_id',
        'purchase_order_item_id',
        'item_id',
        'invoiced_quantity',
        'unit_price',
        'line_amount',
        'matched_quantity',
        'match_status',
        'mismatch_reason',
    ];

    protected function casts(): array
    {
        return [
            'invoiced_quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'line_amount' => 'decimal:2',
            'matched_quantity' => 'decimal:3',
        ];
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
