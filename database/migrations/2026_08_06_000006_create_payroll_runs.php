<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll runs and their locked lines.
 *
 * Compensation computes a month live, which is right for reviewing but wrong
 * for paying: a payslip handed to someone in June must not change because an
 * allowance was edited in July. A run SNAPSHOTS every figure at the moment it
 * is generated, and the lines are what payslips, the bank file and the
 * statutory exports all read. Regenerating is allowed while the run is a
 * draft and refused once it is approved.
 *
 * Money is decimal(12,2) throughout — never float — because these figures are
 * summed, submitted to LHDN and paid to people.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // Null = every outlet the run was generated across, which is the
            // common case; a per-outlet run is for companies that pay
            // separately per branch.
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();

            // First day of the month being paid. A date rather than a Y-m
            // string so it sorts and compares in SQL.
            $table->date('period_month');

            $table->string('status', 20)->default('draft');   // draft | approved | paid
            $table->string('reference', 40)->nullable();      // PR-2026-08-0001

            // Totals denormalised onto the run so a list of runs does not have
            // to aggregate every line to show what each one cost.
            $table->decimal('total_gross', 14, 2)->default(0);
            $table->decimal('total_net', 14, 2)->default(0);
            $table->decimal('total_statutory_employee', 14, 2)->default(0);
            $table->decimal('total_statutory_employer', 14, 2)->default(0);
            $table->decimal('total_employer_cost', 14, 2)->default(0);
            $table->unsignedInteger('employee_count')->default(0);

            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->date('payment_date')->nullable();

            // What the statutory figures were computed under, recorded so a
            // run can still be explained after the rates are changed.
            $table->json('rate_snapshot')->nullable();
            $table->boolean('rates_were_confirmed')->default(false);

            $table->text('notes')->nullable();
            $table->timestamps();

            // One run per company/outlet/month: paying the same month twice is
            // a mistake, not a feature.
            $table->unique(['company_id', 'outlet_id', 'period_month'], 'payroll_runs_period_unique');
            $table->index(['company_id', 'period_month']);
        });

        Schema::create('payroll_run_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // Kept nullable on delete: a deleted employee must not take a
            // historical payslip with them.
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();

            // Identity is COPIED, not joined. A payslip has to reproduce who
            // was paid as they were then — a rename or a transfer afterwards
            // must not rewrite a document someone has already been given.
            $table->string('employee_name');
            $table->string('staff_id')->nullable();
            $table->string('ic_number', 20)->nullable();
            $table->string('designation')->nullable();
            $table->string('outlet_name')->nullable();
            $table->string('section_name')->nullable();
            $table->string('bank_name', 60)->nullable();
            $table->string('bank_account_no', 40)->nullable();
            $table->string('epf_number', 30)->nullable();
            $table->string('socso_number', 30)->nullable();
            $table->string('income_tax_number', 30)->nullable();

            $table->string('pay_type', 20)->nullable();
            $table->decimal('basic', 12, 2)->default(0);
            $table->decimal('allowances', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('ot_hours', 10, 2)->default(0);
            $table->decimal('ot_amount', 12, 2)->default(0);
            $table->decimal('gross', 12, 2)->default(0);

            $table->decimal('epf_employee', 12, 2)->default(0);
            $table->decimal('epf_employer', 12, 2)->default(0);
            $table->decimal('socso_employee', 12, 2)->default(0);
            $table->decimal('socso_employer', 12, 2)->default(0);
            $table->decimal('eis_employee', 12, 2)->default(0);
            $table->decimal('eis_employer', 12, 2)->default(0);
            $table->decimal('pcb', 12, 2)->default(0);
            $table->decimal('statutory_employee', 12, 2)->default(0);
            $table->decimal('statutory_employer', 12, 2)->default(0);

            $table->decimal('net', 12, 2)->default(0);
            $table->decimal('employer_cost', 12, 2)->default(0);

            // The itemised allowance/deduction lines and the OT breakdown, so
            // a payslip can show its working without recomputing anything.
            $table->json('components')->nullable();
            $table->json('ot_by_type')->nullable();
            $table->json('statutory_notes')->nullable();

            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
            $table->index(['company_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_lines');
        Schema::dropIfExists('payroll_runs');
    }
};
