<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qc_inspections', function (Blueprint $table) {
            $table->id();

            $table->string('inspection_number', 40)->unique();

            $table->foreignId('goods_receipt_id')
                ->constrained('goods_receipts')
                ->restrictOnDelete();

            $table->foreignId('inspected_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('decided_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('status', 30)->default('draft');
            $table->date('inspection_date');
            $table->text('remarks')->nullable();
            $table->text('decision_reason')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();

            $table->index(['goods_receipt_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_inspections');
    }
};