<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Central Kitchen prices on its own classes.
 *
 * Production recipes were given the outlet recipes' price classes when they
 * gained a Selling Price section, on the reasoning that "Delivery" means the
 * same thing whoever cooked the item. It does not: a kitchen sells to its own
 * branches and to wholesale, and an outlet sells to diners. One list forces
 * both sets of names onto both screens.
 *
 * Same mechanism recipe_categories already uses for exactly this split — a
 * scope column, not a second table — so the two stay one thing with two
 * lists rather than two things that drift.
 *
 * Everything that exists today is outlet: production recipes have only had
 * prices since this morning, so backfilling the other way would be inventing
 * intent. Any kitchen prices already entered keep pointing at the outlet class
 * they were saved against; the kitchen form will show them under the new list
 * once classes are created there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipe_price_classes', function (Blueprint $table) {
            $table->string('scope', 20)->default('outlet')->after('company_id')->index();
        });

        DB::table('recipe_price_classes')->update(['scope' => 'outlet']);
    }

    public function down(): void
    {
        Schema::table('recipe_price_classes', function (Blueprint $table) {
            $table->dropIndex(['scope']);
            $table->dropColumn('scope');
        });
    }
};
