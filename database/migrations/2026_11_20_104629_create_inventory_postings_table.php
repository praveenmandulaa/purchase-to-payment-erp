<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_postings', function (Blueprint $table) {
            $table->id();

            $table->string('posting_number', 40)->unique();

            $table->foreignId('goods_receipt_id')
                ->constrained('goods_receipts')
                ->restrictOnDelete();

            $table->foreignId('qc_inspection_id')
                ->nullable()
                ->constrained('qc_inspections')
                ->restrictOnDelete();

            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();

            $table->foreignId('posted_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('status', 20)->default('posted');

            $table->timestamp('posted_at');

            $table->text('remarks')->nullable();

            $table->timestamps();

            // A GRN may have a QC posting and a separate
            // posting for lines that do not require QC.
            // The service will enforce the posting scope.

            $table->index(
                ['goods_receipt_id', 'status'],
                'inventory_posting_grn_status_index'
            );

            $table->index(
                ['qc_inspection_id', 'status'],
                'inventory_posting_qc_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_postings');
    }
};
