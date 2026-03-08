<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_meal_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('reference_number', 100)->nullable();
            $table->date('meal_date');
            $table->decimal('total_cost', 14, 4)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('staff_meal_record_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_meal_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recipe_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 14, 4);
            $table->foreignId('uom_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('unit_cost', 14, 4);
            $table->decimal('total_cost', 14, 4);
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_meal_record_lines');
        Schema::dropIfExists('staff_meal_records');
    }
};
