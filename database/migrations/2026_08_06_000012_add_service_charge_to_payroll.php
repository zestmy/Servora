<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service charge on the payroll line.
 *
 * It was deliberately left out of CompensationSummary: the distribution needs
 * a whole month of attendance codes per employee, and reproducing that inside
 * the compensation calculation would have meant two implementations of the
 * same rules and two figures that can disagree.
 *
 * A payroll RUN is different — it is a snapshot taken at one moment, so it can
 * ASK the existing distribution what each person got and copy the answer. One
 * implementation still, one figure, now on the payslip where staff expect it.
 *
 * Nullable-by-default zero: a company with no service charge pool for the
 * period simply has zeroes, and the payslip omits the line entirely.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_run_lines', function (Blueprint $table) {
            $table->decimal('service_charge', 12, 2)->default(0)->after('ot_amount');
            // The working behind the figure, so a payslip can show points ×
            // RM/point and the deductions without recomputing anything.
            $table->json('service_charge_detail')->nullable()->after('service_charge');
        });

        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->decimal('total_service_charge', 14, 2)->default(0)->after('total_gross');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_run_lines', function (Blueprint $table) {
            $table->dropColumn(['service_charge', 'service_charge_detail']);
        });

        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropColumn('total_service_charge');
        });
    }
};
