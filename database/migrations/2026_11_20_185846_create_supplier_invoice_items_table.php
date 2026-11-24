<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoice_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('supplier_invoice_id')
                ->constrained('supplier_invoices')
                ->restrictOnDelete();

            $table->foreignId('purchase_order_item_id')
                ->constrained('purchase_order_items')
                ->restrictOnDelete();

            $table->foreignId('item_id')
                ->constrained('items')
                ->restrictOnDelete();

            $table->decimal('invoiced_quantity', 15, 3);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('line_amount', 18, 2);

            $table->decimal('matched_quantity', 15, 3)
                ->default(0);

            $table->string('match_status', 30)
                ->default('not_checked');

            $table->text('mismatch_reason')->nullable();

            $table->timestamps();

            // Prevent the same PO line from appearing twice
            // within one supplier invoice.
            $table->unique(
                ['supplier_invoice_id', 'purchase_order_item_id'],
                'supplier_invoice_po_line_unique'
            );

            $table->index([
                'purchase_order_item_id',
                'match_status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_items');
    }
};
