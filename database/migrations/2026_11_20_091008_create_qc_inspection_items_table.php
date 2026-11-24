<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qc_inspection_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('qc_inspection_id')
                ->constrained('qc_inspections')
                ->restrictOnDelete();

            $table->foreignId('goods_receipt_item_id')
                ->constrained('goods_receipt_items')
                ->restrictOnDelete();

            $table->decimal('accepted_quantity', 15, 3);
            $table->decimal('rejected_quantity', 15, 3);

            $table->text('rejection_reason')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->unique(
                ['qc_inspection_id', 'goods_receipt_item_id'],
                'qc_inspection_grn_line_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_inspection_items');
    }
};