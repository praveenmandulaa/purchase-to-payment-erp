<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoice_activities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('supplier_invoice_id')
                ->constrained('supplier_invoices')
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('action', 50);

            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();

            $table->text('reason')->nullable();
            $table->json('snapshot')->nullable();

            $table->timestamps();

            $table->index([
                'supplier_invoice_id',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_activities');
    }
};
