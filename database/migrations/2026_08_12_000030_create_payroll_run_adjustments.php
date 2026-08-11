<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-off corrections applied to a payroll run while it is still a draft.
 *
 * WHY THIS IS NOT AN EmployeePayComponent. That table holds STANDING terms —
 * dated allowances and deductions that describe someone's package. Recovering
 * RM500 overpaid in June is not a term of employment; it happened once, it
 * belongs to the July run, and putting it in the employee's component history
 * leaves a permanent-looking row that somebody has to remember to end.
 *
 * WHY IT IS NOT A COLUMN ON payroll_run_lines. The builder DELETES every line
 * and rebuilds it on each regenerate — which is a normal thing to do, not an
 * exception; payroll is usually run once to look at and again once the last OT
 * claims are approved. An adjustment written onto a line would vanish the next
 * time somebody pressed Regenerate, and the run would quietly go back to the
 * uncorrected figures. Holding them here, keyed on the run and the employee,
 * means every rebuild re-applies them.
 *
 * STATUTORY TREATMENT IS PER ADJUSTMENT, because the two common cases need
 * opposite answers:
 *
 *   RECOVERING AN OVERPAYMENT — EPF, SOCSO and PCB were already computed and
 *   remitted on the inflated figure last month. Taking the money back out of
 *   this month's net is the usual remedy; reducing this month's contributions
 *   as well would understate them for a month in which nothing was overpaid.
 *
 *   PAYING ARREARS — an underpayment settled now is additional wages in the
 *   month it is paid, and contributions are due on it.
 *
 * So affects_statutory is a decision the person entering it makes, defaulted
 * by direction rather than assumed for both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_run_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // Belongs to the run: deleting a draft takes its corrections with
            // it, because they described that run and nothing else.
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // What it is FOR, in the words of whoever entered it. Printed on
            // the payslip, so "June overpayment recovery" beats "Adjustment".
            $table->string('label', 120);

            // Always positive; `direction` carries the sign. Storing a signed
            // amount invites a minus typed into a deduction that then pays out.
            $table->decimal('amount', 12, 2);
            $table->string('direction', 12);              // deduction | addition

            // false = net only, applied after the statutory deductions.
            // true  = counts as wages this month, so EPF/SOCSO/EIS/PCB follow.
            $table->boolean('affects_statutory')->default(false);

            $table->string('notes', 255)->nullable();

            // Money moving needs a name against it.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['payroll_run_id', 'employee_id']);
        });

        Schema::table('payroll_run_lines', function (Blueprint $table) {
            // Snapshotted onto the line like everything else here: a payslip
            // must still itemise its corrections years later, after the draft
            // and its adjustment rows are long gone.
            $table->json('adjustments')->nullable()->after('ot_by_type');
            $table->decimal('adjustments_total', 12, 2)->default(0)->after('ot_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_run_lines', function (Blueprint $table) {
            $table->dropColumn(['adjustments', 'adjustments_total']);
        });

        Schema::dropIfExists('payroll_run_adjustments');
    }
};
