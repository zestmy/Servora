<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A department costed against TOTAL sales rather than one sales category.
 *
 * Consumables, packaging and the like are bought for the whole outlet, not
 * for food or beverage, so measuring them against one category's revenue
 * overstates their cost % and pinning them to none drops them out of the
 * P&L rows altogether. When this is set the department carries no
 * sales_category_id and the cost summary gives it a row of its own, with
 * total sales as the revenue it is measured against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->boolean('costs_against_total_sales')->default(false)->after('sales_category_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('departments', 'costs_against_total_sales')) {
            Schema::table('departments', fn (Blueprint $t) => $t->dropColumn('costs_against_total_sales'));
        }
    }
};
