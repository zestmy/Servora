<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production recipes cost the way outlet recipes do.
 *
 * The kitchen's own recipe form asked for packaging and labels as two flat
 * numbers typed into the costing panel, and for one selling price. The outlet
 * recipe form has had the real shape for a while: packaging is a LIST OF
 * INGREDIENTS costed like any other, extra costs are named rows that can be a
 * value or a percentage, and a selling price exists per price class rather
 * than once.
 *
 * Two flat numbers cannot answer "what is the box costing us this month" —
 * they are a figure somebody typed, not a cost that moves with the ingredient.
 * This gives the kitchen the same three mechanisms, reusing the outlet's own
 * price classes so a company defines "Dine In" and "Delivery" once.
 *
 * The old columns are LEFT IN PLACE and still read. Nothing migrates
 * automatically: packaging_cost_per_unit is a number with no ingredient behind
 * it, so there is nothing to turn it into, and dropping it would silently
 * change what existing recipes cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_recipe_lines', function (Blueprint $table) {
            // Same mechanism as recipe_lines: packaging shares the table and
            // is told apart by a flag, so it costs through one code path.
            $table->boolean('is_packaging')->default(false)->after('ingredient_id');
        });

        Schema::table('production_recipes', function (Blueprint $table) {
            // [['label' => 'Gas', 'type' => 'value'|'percent', 'amount' => 1.5], …]
            $table->json('extra_costs')->nullable()->after('label_cost');
        });

        Schema::create('production_recipe_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_recipe_id')->constrained()->cascadeOnDelete();
            // The outlet's classes, not a second set: a company that has said
            // what "Delivery" means should not have to say it again here.
            $table->foreignId('recipe_price_class_id')->constrained()->cascadeOnDelete();
            $table->decimal('selling_price', 12, 4)->default(0);
            $table->timestamps();

            $table->unique(['production_recipe_id', 'recipe_price_class_id'], 'prod_recipe_price_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_recipe_prices');

        Schema::table('production_recipes', function (Blueprint $table) {
            $table->dropColumn('extra_costs');
        });

        Schema::table('production_recipe_lines', function (Blueprint $table) {
            $table->dropColumn('is_packaging');
        });
    }
};
