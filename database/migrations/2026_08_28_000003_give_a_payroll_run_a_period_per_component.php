<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A run counts attendance, overtime and service charge over ONE range today.
 * These let each of the three be counted over its own.
 *
 * WHY THREE MORE PAIRS RATHER THAN A WIDER period_start/period_end. The three
 * are separate questions that a company answers on different calendars:
 *
 *   - Service charge is distributed over the period the POOL was saved for,
 *     and a pool is matched on both its exact dates. A company that
 *     distributes by calendar month while running payroll 26th–25th matches
 *     no pool at all, and the run pays nothing — which is the fault this
 *     column exists to make fixable rather than merely visible.
 *   - Overtime is often claimed and approved a cycle behind, so the hours a
 *     run pays are not always the hours inside its own dates.
 *   - Attendance prices HOURLY AND DAILY staff, and a company may close the
 *     timesheet on a different day from the payroll.
 *
 * NULL MEANS INHERIT, and no row is backfilled. Every run written before this
 * migration, and every ordinary run written after it, carries six nulls and
 * resolves to period_start/period_end — so nothing already paid moves, and the
 * common case stays one range in one place. See Services\Payroll\RunPeriods.
 *
 * WHAT THESE MUST NEVER DRIVE, and the reason each is left on the master
 * period: who is on the run (employedDuring), which dated allowances apply,
 * part-month proration and its Employment Act s.60I divisor, the statutory
 * as-of date, and PCB year-to-date. Those decide what somebody is PAID rather
 * than which days were counted, and a sub-period reaching any of them is the
 * expensive mistake available in this work — a short attendance window would
 * cut every monthly salary on the run.
 *
 * period_month remains the run's identity: the uniqueness key, the reference
 * number, EA forms and Form E all key on it. These are inputs, never identity.
 */
return new class extends Migration
{
    /** from/to pairs, in the order they are read on the run screen. */
    private const COLUMNS = [
        'attendance_from', 'attendance_to',
        'overtime_from', 'overtime_to',
        'service_charge_from', 'service_charge_to',
    ];

    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $after = 'period_end';

            foreach (self::COLUMNS as $column) {
                $table->date($column)->nullable()->after($after);
                $after = $column;
            }
        });
    }

    public function down(): void
    {
        $present = array_values(array_filter(
            self::COLUMNS,
            fn ($c) => Schema::hasColumn('payroll_runs', $c),
        ));

        if ($present === []) {
            return;
        }

        Schema::table('payroll_runs', fn (Blueprint $t) => $t->dropColumn($present));
    }
};
