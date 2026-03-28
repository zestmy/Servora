<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_order_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number', 30)->nullable();
            $table->decimal('planned_yield', 15, 4);
            $table->decimal('actual_yield', 15, 4);
            $table->decimal('yield_variance_pct', 6, 2);
            $table->foreignId('uom_id')->constrained('units_of_measure');
            $table->decimal('total_cost', 15, 4);
            $table->foreignId('produced_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('produced_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_logs');
    }
};
