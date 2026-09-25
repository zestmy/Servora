<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A stock transfer order line points at EITHER an ingredient or an asset.
 *
 * The central purchasing unit sends crockery and smallwares to outlets as well
 * as food, and the natural list to send against is the outlet's Asset Count
 * sheet. Relaxed the same way as purchase_order_lines — `change()` rather than
 * a MySQL-only MODIFY COLUMN, so the SQLite test database is still buildable.
 * The foreign key is left in place; only the nullability moves.
 *
 * `asset_movements.stock_transfer_order_id` is where an asset receipt came
 * from, so the register can point back at the transfer and receiving it twice
 * cannot count the same plates twice. procurement_invoice_lines already carries
 * asset_id (added when ordered assets were first received), so a chargeable
 * transfer's invoice needs nothing new.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfer_order_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('ingredient_id')->nullable()->change();
        });

        if (! Schema::hasColumn('stock_transfer_order_lines', 'asset_id')) {
            Schema::table('stock_transfer_order_lines', function (Blueprint $table) {
                $table->foreignId('asset_id')->nullable()->after('ingredient_id')
                    ->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('asset_movements', 'stock_transfer_order_id')) {
            Schema::table('asset_movements', function (Blueprint $table) {
                $table->foreignId('stock_transfer_order_id')->nullable()->after('goods_received_note_id')
                    ->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('asset_movements', function (Blueprint $table) {
            $table->dropForeign(['stock_transfer_order_id']);
            $table->dropColumn('stock_transfer_order_id');
        });

        // Asset lines have no ingredient counterpart to fall back on.
        DB::table('stock_transfer_order_lines')->whereNull('ingredient_id')->delete();

        Schema::table('stock_transfer_order_lines', function (Blueprint $table) {
            $table->dropForeign(['asset_id']);
            $table->dropColumn('asset_id');
        });

        Schema::table('stock_transfer_order_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('ingredient_id')->nullable(false)->change();
        });
    }
};
