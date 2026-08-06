<?php

namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Services\Payroll\FormE;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Borang E (the employer's return) and the CP8D listing that goes with it.
 *
 * Both from committed payroll, so Form E, CP8D and every EA form issued for
 * the same year describe one set of figures.
 */
class FormEController extends Controller
{
    public function __invoke(Request $request, string $format = 'pdf')
    {
        abort_unless(Auth::user()?->can('hr.payroll'), 403);
        abort_unless(in_array($format, ['pdf', 'cp8d'], true), 404);

        $user = Auth::user();
        $year = (int) $request->input('year', now()->subYear()->year);

        abort_unless($year >= 2000 && $year <= (int) now()->year + 1, 404, 'That is not a payroll year.');

        $outletId = $request->filled('outlet') ? (int) $request->input('outlet') : null;

        if ($outletId !== null && ! in_array($outletId, $user->accessibleOutletIds(), true)) {
            abort(403, 'You do not have access to that outlet.');
        }

        $service = app(FormE::class);
        $data    = $service->forYear($user->company_id, $user->accessibleOutletIds(), $year, $outletId);

        abort_if($data['rows']->isEmpty(), 404, 'No approved payroll for ' . $year . '.');

        if ($format === 'cp8d') {
            return $this->csv($service->cp8d($data['rows'], $data['employer'], $year));
        }

        // If ANY run that year went out on unconfirmed rates, the caveat
        // applies to the year's totals.
        $ratesConfirmed = ! PayrollRun::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->whereIn('status', [PayrollRun::APPROVED, PayrollRun::PAID])
            ->whereYear('period_month', $year)
            ->where('rates_were_confirmed', false)
            ->exists();

        return Pdf::loadView('pdf.form-e', $data + ['ratesConfirmed' => $ratesConfirmed])
            ->setPaper('a4', 'portrait')
            ->stream("form-e-{$year}.pdf");
    }

    /** Same shape as the statutory exports: caveat first, BOM for Excel. */
    private function csv(array $data): StreamedResponse
    {
        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [$data['notice']]);
            fputcsv($out, []);
            fputcsv($out, $data['headers']);

            foreach ($data['rows'] as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $data['filename'], ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
