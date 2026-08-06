<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zakat on the payroll line, and the employer address for the EA form.
 *
 * Zakat was used in the PCB calculation but never stored, so the year's total
 * could only be re-derived from a standing monthly figure that may since have
 * changed. Part D of the EA form asks for zakat actually paid through salary
 * deduction, which is a historical fact and has to come off the line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_run_lines', function (Blueprint $table) {
            $table->decimal('zakat', 12, 2)->default(0)->after('pcb');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_run_lines', function (Blueprint $table) {
            $table->dropColumn('zakat');
        });
    }
};
