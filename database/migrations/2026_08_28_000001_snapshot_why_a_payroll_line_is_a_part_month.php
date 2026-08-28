<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WHY a basic is short, not merely by how much.
 *
 * A line already carries "11 of 31 days" through paid_days and period_days,
 * which says the month was part-paid but never says what happened. Somebody
 * checking a run then has to leave the sheet, open the employee and work out
 * whether they joined late, resigned, or something is wrong — and the third
 * possibility is the reason anybody is checking.
 *
 * SNAPSHOTTED ONTO THE LINE rather than joined from the employee, for the same
 * reason every other identity field here is: a run has to keep explaining
 * itself after the record moves on. Somebody who resigns in August and is
 * re-hired in November must not make August's run say they were employed
 * throughout, and a deleted employee takes the explanation with them.
 *
 * EACH IS NULL UNLESS IT FALLS INSIDE THE RUN'S OWN PERIOD, which is the same
 * rule paid_days and period_days already follow. A join date two years ago is
 * not why this month is short, so storing it here would put a "joined on"
 * against every employee in the company and make the column meaningless. Null
 * therefore means "not a factor in this run", and a sheet can leave the column
 * out entirely when nobody has either.
 *
 * Existing lines keep both as null until their run is regenerated. Nothing is
 * back-filled: the dates would have to come from the employee as they are
 * TODAY, which is exactly the join this column exists to avoid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_run_lines', function (Blueprint $table) {
            $table->date('joined_on')->nullable()->after('period_days');
            $table->date('resigned_on')->nullable()->after('joined_on');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_run_lines', function (Blueprint $table) {
            $table->dropColumn(['joined_on', 'resigned_on']);
        });
    }
};
