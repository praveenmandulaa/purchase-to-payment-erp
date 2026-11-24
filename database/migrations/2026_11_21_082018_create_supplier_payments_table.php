<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();

            $table->string('payment_number')->unique();

            $table->foreignId('supplier_invoice_id')
                ->constrained('supplier_invoices')
                ->restrictOnDelete();

            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->restrictOnDelete();

            $table->date('payment_date');

            $table->decimal('amount', 15, 2);

            $table->string('currency', 3)->default('INR');

            $table->enum('payment_method', [
                'bank_transfer',
                'upi',
                'cheque',
                'cash',
            ]);

            $table->string('payment_reference', 100);

            $table->string('status', 30)->default('recorded');

            $table->foreignId('recorded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->unique([
                'supplier_id',
                'payment_reference',
            ], 'supplier_payments_supplier_reference_unique');

            $table->index([
                'supplier_invoice_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
