<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether what is deducted from staff goes back into the pool.
 *
 * Until now an MC, absence, lateness or special deduction came off the
 * employee's share and simply was not paid: it sat in the undistributed
 * remainder with the company, alongside the RM/point rounding. Many houses
 * instead return it to the people who were at work, so it lifts the final
 * value of every point.
 *
 * PER POOL and OFF by default, beside min_working_days and the percentages,
 * because it is a term of one period's split: turning it on for September
 * must not re-price August, and no pool saved before this moves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_charge_periods', function (Blueprint $table) {
            $table->boolean('redistribute_deductions')->default(false)->after('min_working_days');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('service_charge_periods', 'redistribute_deductions')) {
            Schema::table('service_charge_periods', fn (Blueprint $t) => $t->dropColumn('redistribute_deductions'));
        }
    }
};
