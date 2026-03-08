<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_sku')->nullable();
            $table->decimal('last_cost', 15, 4)->nullable();
            $table->foreignId('uom_id')->constrained('units_of_measure');
            $table->boolean('is_preferred')->default(false);
            $table->timestamps();

            $table->unique(['supplier_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_ingredients');
    }
};
