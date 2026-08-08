<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The working behind an hourly employee's basic pay.
 *
 * Snapshotted like every other figure on a run, for the reason that file's
 * docblock gives: a payslip reprinted next year has to say what was true when
 * it was paid. Recomputing "142.5 hours" from the attendance grid would give
 * whatever the grid says today, and the grid is edited.
 *
 * Null for monthly and daily staff, which is also what makes it safe to show
 * on the payslip without asking the pay type: a figure here means the basic
 * above it was earned by the hour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_run_lines', function (Blueprint $table) {
            $table->decimal('paid_hours', 8, 2)->nullable()->after('pay_type');
            $table->decimal('pay_rate', 10, 2)->nullable()->after('paid_hours');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_run_lines', function (Blueprint $table) {
            $table->dropColumn(['paid_hours', 'pay_rate']);
        });
    }
};
