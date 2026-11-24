<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();

            $table->string('invoice_number', 40)->unique();
            $table->string('supplier_invoice_number', 100);

            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->restrictOnDelete();

            $table->foreignId('purchase_order_id')
                ->constrained('purchase_orders')
                ->restrictOnDelete();

            $table->date('invoice_date');
            $table->date('due_date')->nullable();

            $table->string('status', 30)->default('draft');

            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);

            $table->string('currency', 3)->default('INR');

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->text('remarks')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            // One supplier invoice number cannot be entered twice
            // for the same supplier.
            $table->unique(
                ['supplier_id', 'supplier_invoice_number'],
                'supplier_invoice_supplier_reference_unique'
            );

            $table->index(['purchase_order_id', 'status']);
            $table->index(['supplier_id', 'invoice_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};
