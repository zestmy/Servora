<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\PayrollRun;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The payroll run's employee table, printed as it appears on screen.
 *
 * Distinct from the payslips, which are one document per person and go to the
 * staff. This is the sheet somebody checks the run against before approving
 * it, and hands to whoever signs off the payment: every employee on one page,
 * the same columns in the same order, with the run's totals underneath.
 *
 * Available on a DRAFT as well as an approved run, unlike the statutory and
 * bank exports. Those commit the company to a figure and are rightly held back
 * until approval; this one exists precisely so the numbers can be read on
 * paper BEFORE that decision, which is when checking them is still useful.
 */
class PayrollRunListPdfController extends Controller
{
    public function __invoke(Request $request, string $run)
    {
        abort_unless(Auth::user()?->can('hr.payroll'), 403);

        $payrollRun = PayrollRun::with('outlet:id,name', 'section:id,name', 'approvedBy:id,name')
            ->where('uuid', $run)
            ->firstOrFail();

        // The global scope already limits this to the viewer's company; the
        // outlet check is what stops a run for a branch they cannot see.
        abort_unless($payrollRun->isWithinOutlets(Auth::user()), 403);

        $lines = $payrollRun->lines()->orderBy('employee_name')->get();

        $pdf = Pdf::loadView('pdf.payroll-run-list', [
            'company'     => Company::find($payrollRun->company_id),
            'run'         => $payrollRun,
            'lines'       => $lines,
            // Shown only when the run actually has one — an all-zero column
            // costs width on an already wide table and says nothing. The same
            // call the Excel export makes, on the same four columns.
            'hasService'  => (float) $payrollRun->total_service_charge > 0,
            'hasAdjust'   => $lines->contains(fn ($l) => (float) $l->adjustments_total != 0.0),
            'hasZakat'    => $lines->sum(fn ($l) => (float) $l->zakat) > 0,
            'hasSkbbk'    => $lines->sum(fn ($l) => (float) $l->skbbk) > 0,
            'hasEmploymentChange' => $lines->contains(fn ($l) => $l->employmentNote() !== null),
            'generatedBy' => Auth::user()->name,
        ])
            // Landscape: twelve money columns do not fit portrait without
            // shrinking the figures past the point of being readable, and
            // these are the figures somebody is checking.
            ->setPaper('a4', 'landscape');

        return $pdf->download('payroll-' . $payrollRun->reference . '.pdf');
    }
}
