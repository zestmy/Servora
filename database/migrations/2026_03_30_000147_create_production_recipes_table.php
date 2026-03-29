<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kitchen_id')->constrained('central_kitchens')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30)->nullable();
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->decimal('yield_quantity', 15, 4);
            $table->foreignId('yield_uom_id')->constrained('units_of_measure');
            $table->string('packaging_uom', 50)->nullable();
            $table->unsignedInteger('per_carton_qty')->nullable();
            $table->decimal('carton_weight', 10, 2)->nullable();
            $table->unsignedInteger('shelf_life_days')->nullable();
            $table->string('storage_temperature', 50)->nullable();
            $table->decimal('min_batch_size', 15, 4)->nullable();
            $table->decimal('packaging_cost_per_unit', 15, 4)->default(0);
            $table->decimal('label_cost', 15, 4)->default(0);
            $table->decimal('raw_material_cost', 15, 4)->default(0);
            $table->decimal('total_cost_per_unit', 15, 4)->default(0);
            $table->decimal('selling_price_per_unit', 15, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'kitchen_id']);
        });

        Schema::create('production_recipe_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->foreignId('uom_id')->constrained('units_of_measure');
            $table->decimal('waste_percentage', 6, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Add production_recipe_id to production_order_lines
        Schema::table('production_order_lines', function (Blueprint $table) {
            $table->foreignId('production_recipe_id')->nullable()->after('recipe_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('production_order_lines', function (Blueprint $table) {
            $table->dropForeign(['production_recipe_id']);
            $table->dropColumn('production_recipe_id');
        });
        Schema::dropIfExists('production_recipe_lines');
        Schema::dropIfExists('production_recipes');
    }
};
