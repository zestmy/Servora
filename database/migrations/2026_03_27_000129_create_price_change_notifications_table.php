<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_change_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->decimal('old_price', 15, 4);
            $table->decimal('new_price', 15, 4);
            $table->decimal('change_percent', 6, 2);
            $table->decimal('change_amount', 15, 4);
            $table->enum('direction', ['increase', 'decrease']);
            $table->boolean('is_read')->default(false);
            $table->boolean('is_dismissed')->default(false);
            $table->timestamp('detected_at');
            $table->timestamps();

            $table->index(['company_id', 'is_read', 'is_dismissed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_change_notifications');
    }
};
