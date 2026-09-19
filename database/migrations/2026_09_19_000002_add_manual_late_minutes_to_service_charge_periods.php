<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lateness typed in by hand, per employee, for one service charge pool.
 *
 * Until now lateness only reached the service charge from web clock-in
 * punches, so an outlet recording attendance by hand had no way to charge it
 * except as a special deduction worked out in RM by somebody with a
 * calculator. This holds MINUTES — employee_id => minutes — priced at the
 * company's per-minute clock rate when the pool is calculated, with no
 * per-shift cap because it is one total for the period, not one shift.
 *
 * On the pool rather than the employee, like special_deductions beside it:
 * it is a decision about one period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_charge_periods', function (Blueprint $table) {
            $table->json('manual_late_minutes')->nullable()->after('special_deductions');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('service_charge_periods', 'manual_late_minutes')) {
            Schema::table('service_charge_periods', fn (Blueprint $t) => $t->dropColumn('manual_late_minutes'));
        }
    }
};
