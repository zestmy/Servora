<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forgiving a late charge, without pretending the lateness did not happen.
 *
 * There was already a way to change what somebody is charged —
 * override_late_minutes substitutes a figure and re-prices from it — and it is
 * the wrong tool for this. An override says "they were late by this much
 * instead", which is a correction to a MEASUREMENT. Waiving says "they were
 * late by exactly what we recorded, and they are not paying for it", which is a
 * DECISION about the charge. Driving the second through the first means writing
 * a zero into the minutes and losing the only record that the person was ever
 * late, along with any way to tell an act of discretion apart from a broken
 * roster entry.
 *
 * So penalty_amount is left exactly as computed and three columns are added
 * beside it. The punch goes on saying "18 minutes late, RM9.00" — and now also
 * says who decided it would not be collected, when, and why. Everything the
 * table already does with a decision it took: minutes_late keeps the figure
 * before grace, chargeable_late_minutes keeps it after, and both survive an
 * override for the same reason.
 *
 * THE REASON IS THE POINT. A waiver with no reason is indistinguishable from a
 * mis-click three weeks later, and this is money moving between an employee and
 * a service charge pool that everybody else is paid out of. The column is
 * nullable only because the database cannot express "required when waived"; the
 * screen refuses to waive without one.
 *
 * What it does NOT touch: the per-shift cap arithmetic in ClockInService, which
 * sums penalty_amount for punches already made on the same shift. A waiver is
 * almost always a later decision than the punch it forgives, so making the cap
 * waiver-aware would change nothing in the ordinary case and would make a
 * payroll-adjacent calculation depend on when somebody happened to click.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clock_events', function (Blueprint $table) {
            $table->dateTime('lateness_waived_at')->nullable()->after('penalty_amount');

            $table->foreignId('lateness_waived_by')
                ->nullable()
                ->after('lateness_waived_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->text('lateness_waive_reason')->nullable()->after('lateness_waived_by');
        });
    }

    public function down(): void
    {
        Schema::table('clock_events', function (Blueprint $table) {
            $table->dropForeign(['lateness_waived_by']);
            $table->dropColumn(['lateness_waived_at', 'lateness_waived_by', 'lateness_waive_reason']);
        });
    }
};
