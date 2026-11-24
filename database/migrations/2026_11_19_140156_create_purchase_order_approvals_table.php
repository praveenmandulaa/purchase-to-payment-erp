<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    Schema::create('purchase_order_approvals', function (Blueprint $table) {
        $table->id();

        $table->foreignId('purchase_order_id')
            ->constrained()
            ->restrictOnDelete();

        $table->foreignId('approver_id')
            ->constrained('users')
            ->restrictOnDelete();

        $table->string('action', 30);
        $table->text('remarks')->nullable();
        $table->timestamp('action_at');

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_approvals');
    }
};
