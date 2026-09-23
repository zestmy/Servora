<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An outlet transfer line names an ingredient, a recipe, OR a free-text item.
 *
 * Until now a transfer could only move Market List stock (prep items included,
 * since they are ingredients). Outlets also hand each other finished dishes and
 * one-off things that are not in the catalogue at all — a tray of cake, a box of
 * takeaway containers borrowed for the weekend — and had no way to record it.
 *
 * Same shape wastage_record_lines took for recipes, relaxed the same way as
 * purchase_order_lines.ingredient_id: `change()`, not a MySQL-only MODIFY, so the
 * SQLite test database still builds.
 *
 * Recipe and custom lines do NOT move stock on hand — StockOnHandService and the
 * stock card key on ingredient_id, and a line without one simply is not in them.
 * They still carry a value (quantity x unit_cost), so the outlet cost figures
 * that sum the lines include them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlet_transfer_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('ingredient_id')->nullable()->change();
        });

        Schema::table('outlet_transfer_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('outlet_transfer_lines', 'recipe_id')) {
                $table->foreignId('recipe_id')->nullable()->after('ingredient_id')
                    ->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('outlet_transfer_lines', 'custom_name')) {
                $table->string('custom_name', 200)->nullable()->after('recipe_id');
            }
        });
    }

    public function down(): void
    {
        DB::table('outlet_transfer_lines')->whereNull('ingredient_id')->delete();

        Schema::table('outlet_transfer_lines', function (Blueprint $table) {
            $table->dropForeign(['recipe_id']);
            $table->dropColumn(['recipe_id', 'custom_name']);
        });

        Schema::table('outlet_transfer_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('ingredient_id')->nullable(false)->change();
        });
    }
};
