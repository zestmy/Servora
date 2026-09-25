<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An outlet-to-outlet transfer line may name an asset.
 *
 * Moving crockery from one branch to another used to be two unconnected
 * documents in the asset module — a disposal at one outlet, a receipt at the
 * other. The transfer is now that document: its asset lines post both
 * movements, and `asset_movements.outlet_transfer_id` ties each back to the
 * transfer so cancelling or deleting it can take them away again.
 *
 * `outlet_transfer_lines.ingredient_id` is already nullable (recipe and custom
 * lines), so only the new column is needed there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('outlet_transfer_lines', 'asset_id')) {
            Schema::table('outlet_transfer_lines', function (Blueprint $table) {
                $table->foreignId('asset_id')->nullable()->after('recipe_id')
                    ->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('asset_movements', 'outlet_transfer_id')) {
            Schema::table('asset_movements', function (Blueprint $table) {
                $table->foreignId('outlet_transfer_id')->nullable()->after('stock_transfer_order_id')
                    ->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('asset_movements', function (Blueprint $table) {
            $table->dropForeign(['outlet_transfer_id']);
            $table->dropColumn('outlet_transfer_id');
        });

        // An asset line has nothing else to be once the column is gone.
        DB::table('outlet_transfer_lines')->whereNotNull('asset_id')->delete();

        Schema::table('outlet_transfer_lines', function (Blueprint $table) {
            $table->dropForeign(['asset_id']);
            $table->dropColumn('asset_id');
        });
    }
};
