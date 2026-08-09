<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pay cycles that are not calendar months.
 *
 * Plenty of companies close payroll mid-month — the 26th of one month to the
 * 25th of the next is common — and everything that feeds a run (attendance
 * days, approved OT claims, dated allowances, the service charge pool) has to
 * be counted over THAT range, not over the calendar month it is labelled with.
 *
 * `payroll_cycle_start_day` is the day the cycle OPENS. 1 means calendar
 * months, which stays the default and changes nothing for anyone already
 * running payroll.
 *
 * The run keeps `period_month` as its label and anchor — it is what the unique
 * key, the year-to-date lookup and the reference are built on — and gains an
 * explicit start and end so the figures can be traced to a real date range
 * rather than re-derived from a setting that might since have changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compensation_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('payroll_cycle_start_day')->default(1)->after('company_id');
        });

        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->date('period_start')->nullable()->after('period_month');
            $table->date('period_end')->nullable()->after('period_start');
        });

        // Existing runs were all calendar months, so their range is exactly
        // that. Backfilled rather than left null so nothing has to cope with
        // a run that cannot say what it covered.
        // LAST_DAY() is MySQL-only, so the end of the month is computed in PHP and the
        // rows updated in chunks. Driver-agnostic, and the arithmetic is easier to read
        // than either dialect's date functions.
        \Illuminate\Support\Facades\DB::table('payroll_runs')
            ->whereNull('period_start')
            ->orderBy('id')
            ->chunkById(500, function ($runs) {
                foreach ($runs as $run) {
                    $month = \Illuminate\Support\Carbon::parse($run->period_month);

                    \Illuminate\Support\Facades\DB::table('payroll_runs')
                        ->where('id', $run->id)
                        ->update([
                            'period_start' => $month->copy()->startOfMonth()->toDateString(),
                            'period_end'   => $month->copy()->endOfMonth()->toDateString(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('compensation_settings', function (Blueprint $table) {
            $table->dropColumn('payroll_cycle_start_day');
        });

        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropColumn(['period_start', 'period_end']);
        });
    }
};
