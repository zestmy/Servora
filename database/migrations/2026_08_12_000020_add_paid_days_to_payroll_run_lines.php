<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The working behind `basic` when it is counted in DAYS.
 *
 * `paid_hours` already does this for hourly staff — a payslip can show
 * "37.5 h × RM12.00" instead of an unexplained figure. Two more cases now need
 * the same:
 *
 *   DAILY staff, who are paid days-worked × rate rather than a flat monthly
 *   figure, and
 *
 *   MONTHLY staff on an INCOMPLETE month — a mid-cycle joiner or leaver, or a
 *   short run — whose basic is pro-rated by Employment Act s.60I as
 *   monthly ÷ days of the wage period × days eligible.
 *
 * The second is the one that most needs saying on paper. Somebody who joined
 * on the 20th and receives a third of a month will ask why, and "12 / 31 days"
 * on their payslip answers it without anybody having to reconstruct the
 * calculation from a salary that has since changed.
 *
 * period_days is null for daily staff, which is what tells the two apart:
 * paid_days with a period is a fraction of a month, paid_days without one is a
 * count of days bought at a rate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_run_lines', function (Blueprint $table) {
            // Whole days on both — a wage period and a day worked are counted,
            // never fractional, unlike the hours beside them.
            $table->unsignedSmallInteger('paid_days')->nullable()->after('paid_hours');
            $table->unsignedSmallInteger('period_days')->nullable()->after('paid_days');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_run_lines', function (Blueprint $table) {
            $table->dropColumn(['paid_days', 'period_days']);
        });
    }
};
