<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();

            $table->foreignId('item_id')
                ->constrained('items')
                ->restrictOnDelete();

            $table->decimal('available_quantity', 15, 3)
                ->default(0);

            $table->decimal('rejected_quantity', 15, 3)
                ->default(0);

            $table->timestamps();

            $table->unique(
                ['warehouse_id', 'item_id'],
                'inventory_balance_warehouse_item_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_balances');
    }
};
