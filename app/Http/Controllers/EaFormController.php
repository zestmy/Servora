<?php

namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Services\Payroll\EaForm;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Borang EA as a PDF — one employee, or everyone for the year.
 *
 * Always built from committed payroll, never from live figures: an EA form is
 * a document an employee files their tax return from, and it has to agree with
 * the payslips they were given.
 */
class EaFormController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(Auth::user()?->can('hr.payroll'), 403);

        $user = Auth::user();
        $year = (int) $request->input('year', now()->subYear()->year);

        abort_unless($year >= 2000 && $year <= (int) now()->year + 1, 404, 'That is not a payroll year.');

        $outletId = $request->filled('outlet') ? (int) $request->input('outlet') : null;

        // Re-checked rather than trusted: the outlet arrives in the query string.
        if ($outletId !== null && ! in_array($outletId, $user->accessibleOutletIds(), true)) {
            abort(403, 'You do not have access to that outlet.');
        }

        $service = app(EaForm::class);

        $forms = $service->forYear($user->company_id, $user->accessibleOutletIds(), $year, $outletId);

        // One employee, when asked for. Filtered from the same set rather than
        // queried separately, so a single form and the batch cannot disagree.
        if ($request->filled('employee')) {
            $employeeId = (int) $request->input('employee');
            $forms = $forms->where('employee_id', $employeeId)->values();

            abort_if($forms->isEmpty(), 404, 'That employee has no approved payroll for ' . $year . '.');
        }

        abort_if($forms->isEmpty(), 404, 'No approved payroll for ' . $year . '.');

        // Whether the rates were confirmed for the year's runs. If ANY run went
        // out on unconfirmed rates the caveat applies to the year's totals.
        $ratesConfirmed = ! PayrollRun::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->whereIn('status', [PayrollRun::APPROVED, PayrollRun::PAID])
            ->whereYear('period_month', $year)
            ->where('rates_were_confirmed', false)
            ->exists();

        $filename = $forms->count() === 1
            ? 'ea-' . $year . '-' . preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($forms->first()['name'])) . '.pdf'
            : 'ea-forms-' . $year . '.pdf';

        $pdf = Pdf::loadView('pdf.ea-form', [
            'forms'          => $forms,
            'year'           => $year,
            'employer'       => $service->employer($user->company_id),
            'ratesConfirmed' => $ratesConfirmed,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream($filename);
    }
}
