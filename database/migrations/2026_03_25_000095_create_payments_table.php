<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('chip_payment_id')->nullable();
            $table->string('chip_purchase_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('MYR');
            $table->string('status')->default('pending'); // pending, completed, failed, refunded
            $table->string('payment_method')->nullable(); // fpx, card, ewallet
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('chip_purchase_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
