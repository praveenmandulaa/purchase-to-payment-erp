<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();

            $table->string('grn_number', 40)->unique();

            $table->foreignId('purchase_order_id')
                ->constrained('purchase_orders')
                ->restrictOnDelete();

            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->restrictOnDelete();

            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();

            $table->foreignId('received_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->date('receipt_date');
            $table->string('delivery_reference', 100);
            $table->string('status', 30)->default('received');
            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->unique(
                ['supplier_id', 'delivery_reference'],
                'grn_supplier_delivery_unique'
            );

            $table->index(['purchase_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};