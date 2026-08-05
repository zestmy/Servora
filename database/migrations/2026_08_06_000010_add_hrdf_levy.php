<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRD Corp levy (HRDF).
 *
 * Unlike EPF, SOCSO, EIS and PCB, this is an EMPLOYER-ONLY levy: nothing is
 * deducted from the employee and it must never appear on a payslip as a
 * deduction. It belongs in the cost to company.
 *
 * Registration is not universal — mandatory at 10 or more Malaysian employees
 * in a covered industry, optional at 5 to 9 at a lower rate — so it is OFF by
 * default and the rate is a setting rather than a constant.
 *
 * Per-employee too: the levy is charged on Malaysian employees, so a foreign
 * worker is normally excluded, and that is a per-person fact the company-wide
 * setting cannot answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statutory_settings', function (Blueprint $table) {
            $table->boolean('hrdf_enabled')->default(false)->after('pcb_enabled');
            // 1.0 for the mandatory tier, 0.5 for the 5–9 optional tier.
            $table->decimal('hrdf_employer_rate', 5, 2)->default(1.00)->after('eis_ceiling');
            // HRD Corp does not cap the levy the way SOCSO caps contributions,
            // but the column exists so a company can bound it if their scheme
            // does. Null means uncapped.
            $table->decimal('hrdf_ceiling', 10, 2)->nullable()->after('hrdf_employer_rate');
            $table->string('employer_hrdf_number', 30)->nullable()->after('employer_tax_number');
        });

        Schema::table('employee_statutory_profiles', function (Blueprint $table) {
            $table->boolean('hrdf_enabled')->default(true)->after('pcb_enabled');
        });

        Schema::table('payroll_run_lines', function (Blueprint $table) {
            $table->decimal('hrdf_employer', 12, 2)->default(0)->after('eis_employer');
        });
    }

    public function down(): void
    {
        Schema::table('statutory_settings', function (Blueprint $table) {
            $table->dropColumn(['hrdf_enabled', 'hrdf_employer_rate', 'hrdf_ceiling', 'employer_hrdf_number']);
        });

        Schema::table('employee_statutory_profiles', function (Blueprint $table) {
            $table->dropColumn('hrdf_enabled');
        });

        Schema::table('payroll_run_lines', function (Blueprint $table) {
            $table->dropColumn('hrdf_employer');
        });
    }
};
