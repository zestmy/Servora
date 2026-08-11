<?php

namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Services\Payroll\PayslipPdf;
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

        return $this->render($run, $lines, app(PayslipPdf::class)->runFilename($run));
    }

    /** One employee's payslip. */
    public function single(PayrollRun $run, PayrollRunLine $line)
    {
        $this->authorise($run);

        // The line has to belong to the run in the URL, or a guessed id would
        // print someone else's pay under this run's heading.
        abort_if($line->payroll_run_id !== $run->id, 404);

        // The filename comes from the service, which is where the sanitising
        // lives. Built by hand here, it replaced spaces and nothing else — so
        // an employee named "JANANE A/P KANAPATHY" produced a filename with a
        // slash in it, and Symfony refuses to put that in a Content-Disposition
        // header. Every payslip for those employees was a 500.
        return $this->render(
            $run,
            collect([$line]),
            app(PayslipPdf::class)->filename($run, $line->employee_name)
        );
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
        return app(PayslipPdf::class)
            ->make($run, $lines, Auth::user()->company)
            ->stream($filename);
    }
}
