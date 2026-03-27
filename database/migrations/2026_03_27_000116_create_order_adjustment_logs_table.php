<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_adjustment_logs', function (Blueprint $table) {
            $table->id();
            $table->string('adjustable_type', 100);
            $table->unsignedBigInteger('adjustable_id');
            $table->string('field', 50);
            $table->string('old_value', 100)->nullable();
            $table->string('new_value', 100)->nullable();
            $table->string('reason', 255)->nullable();
            $table->foreignId('adjusted_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['adjustable_type', 'adjustable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_adjustment_logs');
    }
};
