<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a payroll run cover a SEGMENT rather than always a whole outlet.
 *
 * Outsourced staff are paid to an agent against a contract rate, not to the
 * person and not through EPF/SOCSO/PCB — so they are settled on a different
 * day, against a different document, by a different person. Running them in
 * the same batch as own staff meant one run whose bank file had to be split by
 * hand afterwards. A run now carries the section and the employment status it
 * was built for, and says so on its own row.
 *
 * The unique key has to grow with it. Before this, one run per
 * company/outlet/month was the whole rule; now "August, IOI, outsourcing" and
 * "August, IOI, everyone else" are two different runs of the same month, and
 * only the same segment twice is the mistake the key still has to stop.
 *
 * MySQL treats NULLs in a unique index as distinct, which would let an
 * unsegmented run be created twice over. That was already true of outlet_id
 * before this change, so the behaviour is unchanged rather than newly
 * introduced — the builder looks an existing run up and rebuilds it in place,
 * which is what actually prevents the duplicate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            // Null = every section, which is the ordinary run.
            $table->foreignId('section_id')->nullable()->after('outlet_id')
                ->constrained()->nullOnDelete();

            /*
             * The employment segment, holding the same vocabulary the
             * Employees list filter uses rather than a second one:
             *   null                  every employment status
             *   'none'                staff with no status recorded
             *   'exclude_outsourcing' everybody the company employs directly
             *   <status key>          exactly that status, e.g. 'outsourcing'
             *
             * A string rather than a foreign key because the statuses are a
             * constant on the Employee model, not a table.
             */
            $table->string('employment_status', 30)->nullable()->after('section_id');
        });

        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropUnique('payroll_runs_period_unique');
            $table->unique(
                ['company_id', 'outlet_id', 'section_id', 'employment_status', 'period_month'],
                'payroll_runs_period_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropUnique('payroll_runs_period_unique');
        });

        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('section_id');
            $table->dropColumn('employment_status');
        });

        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->unique(['company_id', 'outlet_id', 'period_month'], 'payroll_runs_period_unique');
        });
    }
};
