<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inventory_posting_id')
                ->constrained('inventory_postings')
                ->restrictOnDelete();

            $table->foreignId('goods_receipt_item_id')
                ->constrained('goods_receipt_items')
                ->restrictOnDelete();

            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();

            $table->foreignId('item_id')
                ->constrained('items')
                ->restrictOnDelete();

            $table->string('movement_type', 30);

            $table->decimal('quantity', 15, 3);

            $table->decimal('balance_before', 15, 3);

            $table->decimal('balance_after', 15, 3);

            $table->timestamp('movement_date');

            $table->text('remarks')->nullable();

            $table->timestamps();

            // One receipt line may produce one accepted movement
            // and one rejected movement, but never duplicates.

            $table->unique(
                ['goods_receipt_item_id', 'movement_type'],
                'stock_movement_receipt_line_type_unique'
            );

            $table->index(
                ['warehouse_id', 'item_id', 'movement_date'],
                'stock_movement_warehouse_item_date_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
