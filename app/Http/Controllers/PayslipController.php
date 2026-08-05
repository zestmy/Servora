<?php

namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Models\StatutorySetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

/**
 * Payslips, printed from a run's locked lines.
 *
 * Never recomputed. A payslip is a document someone has been given; if it were
 * regenerated from live figures, reprinting last month's slip after an
 * allowance was edited would produce a different one, and the two would both
 * claim to be authoritative.
 */
class PayslipController extends Controller
{
    /** Every payslip in the run, two to an A4 page. */
    public function all(PayrollRun $run)
    {
        $this->authorise($run);

        $lines = $run->lines()->orderBy('employee_name')->get();

        abort_if($lines->isEmpty(), 404, 'This payroll run has no employees.');

        return $this->render($run, $lines, 'payslips-' . $run->reference . '.pdf');
    }

    /** One employee's payslip. */
    public function single(PayrollRun $run, PayrollRunLine $line)
    {
        $this->authorise($run);

        // The line has to belong to the run in the URL, or a guessed id would
        // print someone else's pay under this run's heading.
        abort_if($line->payroll_run_id !== $run->id, 404);

        $name = str_replace(' ', '-', strtolower($line->employee_name));

        return $this->render($run, collect([$line]), "payslip-{$name}-{$run->reference}.pdf");
    }

    private function authorise(PayrollRun $run): void
    {
        abort_unless(Auth::user()?->can('hr.payroll'), 403);

        // The global scope already limits this to the user's company; this is
        // the belt-and-braces check for a run fetched by route binding.
        abort_unless($run->company_id === Auth::user()->company_id, 404);
    }

    private function render(PayrollRun $run, $lines, string $filename)
    {
        $company   = Auth::user()->company;
        $statutory = StatutorySetting::forCompany($run->company_id);

        $pdf = Pdf::loadView('pdf.payslip', [
            'run'        => $run,
            'lines'      => $lines,
            'brandName'  => $company?->brand_name ?: $company?->name,
            // The legal entity as well as the trading name — a payslip records
            // employment by a company, not by a brand.
            'companyName' => $company?->name,
            'companyReg' => $company?->registration_number,
            'address'    => $company?->address,
            'employerTaxNumber' => $statutory->employer_tax_number,
            // A payslip must not present an estimate as a final figure.
            'ratesConfirmed'    => (bool) $run->rates_were_confirmed,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream($filename);
    }
}
