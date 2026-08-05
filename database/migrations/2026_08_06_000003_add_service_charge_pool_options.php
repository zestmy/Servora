<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three additions to a service charge period.
 *
 * retention_percent — what the company holds back before anything is shared.
 *   Distributable = collected − retention%. Kept as a PERCENTAGE rather than
 *   an amount so it survives a correction to the collected figure without
 *   anyone having to remember to redo the arithmetic.
 *
 * fund_allocations — named shares that take points alongside staff, e.g.
 *   "Outlet Fund" 2 points, "Breakages Fund" 2 points. They are added to the
 *   points divisor, which is the whole point: a fund holding 2 of 102 points
 *   dilutes every staff share slightly, exactly as another employee would.
 *   json rather than a table: they belong to one period, are edited as a set,
 *   and are never queried across periods.
 *
 * special_deductions — a per-employee, per-period figure with a reason, for
 *   things no rule can derive (missed KPI, a till shortfall). Deliberately not
 *   a percentage: it is agreed as an amount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_charge_periods', function (Blueprint $table) {
            $table->decimal('retention_percent', 5, 2)->default(0)->after('amount');
            $table->json('fund_allocations')->nullable()->after('abs_percent');
            $table->json('special_deductions')->nullable()->after('fund_allocations');
        });
    }

    public function down(): void
    {
        foreach (['retention_percent', 'fund_allocations', 'special_deductions'] as $column) {
            if (Schema::hasColumn('service_charge_periods', $column)) {
                Schema::table('service_charge_periods', fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }
};
