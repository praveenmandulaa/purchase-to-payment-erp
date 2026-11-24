<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_posting_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inventory_posting_id')
                ->constrained('inventory_postings')
                ->restrictOnDelete();

            $table->foreignId('goods_receipt_item_id')
                ->constrained('goods_receipt_items')
                ->restrictOnDelete();

            $table->decimal('accepted_quantity', 15, 3);
            $table->decimal('rejected_quantity', 15, 3);

            $table->timestamps();

            $table->unique(
                'goods_receipt_item_id',
                'inventory_posting_line_grn_item_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_posting_lines');
    }
};
