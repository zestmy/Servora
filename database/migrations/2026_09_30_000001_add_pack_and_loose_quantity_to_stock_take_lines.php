<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A stock take line can be counted as full packs (purchase UOM) plus loose
 * stock (recipe UOM) — "2 ctn + 350 ml".
 *
 * actual_quantity stays the TOTAL in the line's recipe UOM, because stock on
 * hand, variance and the balance reports read it without looking at a unit.
 * These two columns only keep what was typed, so a reopened draft shows the
 * count the way it was entered. Both null = a line counted before the split
 * existed, or one with no pack conversion: actual_quantity is all there is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_take_lines', function (Blueprint $table) {
            $table->decimal('pack_quantity', 15, 4)->nullable()->after('actual_quantity');
            $table->decimal('loose_quantity', 15, 4)->nullable()->after('pack_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('stock_take_lines', function (Blueprint $table) {
            $table->dropColumn(['pack_quantity', 'loose_quantity']);
        });
    }
};
