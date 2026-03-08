<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_takes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('reference_number')->nullable();
            $table->enum('status', ['draft', 'in_progress', 'completed'])->default('draft');
            $table->date('stock_take_date');
            $table->decimal('total_variance_cost', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stock_take_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_take_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('system_quantity', 15, 4)->default(0);
            $table->decimal('actual_quantity', 15, 4)->default(0);
            $table->decimal('variance_quantity', 15, 4)->default(0);
            $table->foreignId('uom_id')->constrained('units_of_measure');
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('variance_cost', 15, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_take_lines');
        Schema::dropIfExists('stock_takes');
    }
};
