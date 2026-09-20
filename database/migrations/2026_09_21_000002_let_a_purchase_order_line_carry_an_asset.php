<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A purchase order line points at EITHER an ingredient or an asset.
 *
 * Phase one of putting assets on purchase orders. Until now an asset could be
 * ASKED for on a request but never ordered: the request line was dropped on
 * conversion, because `purchase_order_lines.ingredient_id` is NOT NULL and
 * every consumer downstream assumed it.
 *
 * The same shape as production_order_lines.recipe_id, and relaxed the same
 * way — `change()` rather than a MySQL-only MODIFY COLUMN, so the SQLite test
 * database is still buildable. The foreign key is left in place; only the
 * nullability moves.
 *
 * WHAT THIS DELIBERATELY DOES NOT DO: let an asset line into the receiving
 * chain. PO → DO → GRN ends in `purchase_record_lines`, whose ingredient_id
 * is also NOT NULL and which IS the inventory receipt — an asset landing
 * there would either be a hard 500 or a phantom stock movement. Both doorways
 * into that chain (ConvertToDoForm and the Index quick-GRN) exclude asset
 * lines and say so. Receiving an ordered asset into the asset register is
 * phase two.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('ingredient_id')->nullable()->change();
        });

        if (! Schema::hasColumn('purchase_order_lines', 'asset_id')) {
            Schema::table('purchase_order_lines', function (Blueprint $table) {
                $table->foreignId('asset_id')->nullable()->after('ingredient_id')
                    ->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Asset lines have no ingredient counterpart, so they must go before
        // the column can be NOT NULL again.
        DB::table('purchase_order_lines')->whereNull('ingredient_id')->delete();

        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropForeign(['asset_id']);
            $table->dropColumn('asset_id');
        });

        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('ingredient_id')->nullable(false)->change();
        });
    }
};
