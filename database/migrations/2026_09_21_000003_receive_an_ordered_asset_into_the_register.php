<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase two: an ordered asset can be received.
 *
 * Phase one put an asset on a purchase order and stopped there, because the
 * chain below it — DO → GRN → invoice — assumed an ingredient everywhere and
 * ends in a stock receipt. The three documents in that chain now carry an
 * asset the same way the order does.
 *
 * `purchase_record_lines` is POINTEDLY NOT in this list. That table IS the
 * inventory receipt: a row there means stock arrived, and an asset is not
 * stock. Receiving an asset writes an AssetMovement receipt instead, which is
 * what the register counts. Its ingredient_id stays NOT NULL on purpose, so
 * the database itself refuses an asset if a future change ever routes one
 * there by mistake.
 *
 * The invoice DOES carry assets. Its header total is summed from every GRN
 * line, so excluding asset lines from the lines alone would produce an
 * invoice that does not add up to itself — and the supplier did bill for the
 * mixer.
 */
return new class extends Migration
{
    /** The three documents between an order and an invoice. */
    private const TABLES = [
        'delivery_order_lines',
        'goods_received_note_lines',
        'procurement_invoice_lines',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('ingredient_id')->nullable()->change();
            });

            if (! Schema::hasColumn($table, 'asset_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->foreignId('asset_id')->nullable()->after('ingredient_id')
                        ->constrained()->nullOnDelete();
                });
            }
        }

        // Where an asset receipt came from, so the register can point back at
        // the delivery and a re-confirmed GRN cannot receive the same asset
        // twice.
        if (! Schema::hasColumn('asset_movements', 'goods_received_note_id')) {
            Schema::table('asset_movements', function (Blueprint $t) {
                $t->foreignId('goods_received_note_id')->nullable()->after('purchase_request_id')
                    ->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('asset_movements', function (Blueprint $t) {
            $t->dropForeign(['goods_received_note_id']);
            $t->dropColumn('goods_received_note_id');
        });

        foreach (self::TABLES as $table) {
            // Asset lines have no ingredient counterpart to fall back on.
            DB::table($table)->whereNull('ingredient_id')->delete();

            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['asset_id']);
                $t->dropColumn('asset_id');
            });

            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('ingredient_id')->nullable(false)->change();
            });
        }
    }
};
