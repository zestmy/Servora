<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hours worked, for staff paid by the hour.
 *
 * The grid was built for monthly staff, where a day is a yes or a no and a
 * tick says everything. A part-timer's day is a quantity — four and a half
 * hours on Tuesday, six on Saturday — and there was nowhere to put it. The
 * consequence was not a missing feature but a wrong payslip: payroll used
 * basic_salary verbatim whatever the pay type, so somebody on RM12/hr was
 * paid RM12 for the month, with EPF, SOCSO and PCB all computed on it.
 *
 * A cell now holds ONE OF the two, never both:
 *
 *   hours   — a day worked, and what it is worth. attendance_code_id is null.
 *   a code  — MC, annual leave, absent. hours is null.
 *
 * Which is why attendance_code_id becomes nullable. The alternative was a
 * "worked" code on every hourly cell carrying hours beside it, and that is a
 * second thing to enter on the screen whose entry speed is the entire point.
 * Both null cannot happen: an empty cell is no row at all, the same as today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            // 999.99 in a day is unreachable, and two decimals covers the
            // quarter and half hours these are actually written in.
            $table->decimal('hours', 5, 2)->nullable()->after('attendance_code_id');
        });

        // Separately, and after: changing a column requires doctrine/dbal on
        // some setups, so it is kept out of the block that adds the new one.
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->unsignedBigInteger('attendance_code_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rows that only ever held hours have no code to restore, so they are
        // dropped rather than left to break a NOT NULL that is coming back.
        \Illuminate\Support\Facades\DB::table('attendance_records')
            ->whereNull('attendance_code_id')
            ->delete();

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->unsignedBigInteger('attendance_code_id')->nullable(false)->change();
            $table->dropColumn('hours');
        });
    }
};
