<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payment_activities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('supplier_payment_id')
                ->constrained('supplier_payments')
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
                'supplier_payment_id',
                'created_at',
            ], 'supplier_payment_activities_history_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_activities');
    }
};
