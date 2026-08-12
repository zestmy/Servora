<?php

namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Services\Payroll\PayrollExports;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Statutory submission listings and the salary payment file for one run.
 *
 * Only from an APPROVED run. A draft can still be regenerated, and a
 * submission or a payment built from figures that then change is the one
 * mistake in this module that cannot be undone by editing a record.
 */
class PayrollExportController extends Controller
{
    public function __invoke(PayrollRun $run, string $type): StreamedResponse|RedirectResponse
    {
        abort_unless(Auth::user()?->can('hr.payroll'), 403);
        abort_unless($run->company_id === Auth::user()->company_id, 404);
        abort_unless(array_key_exists($type, PayrollExports::TYPES), 404);

        abort_unless(
            $run->isApproved(),
            409,
            'This payroll run is still a draft. Approve it before producing submission or payment files.',
        );

        $exports = app(PayrollExports::class);

        $data = match ($type) {
            'cp39'  => $exports->cp39($run),
            'epf'   => $exports->epf($run),
            'socso' => $exports->socso($run),
            'bank'  => $exports->bankPayment($run),
        };

        /*
         * AN EMPTY LISTING IS NOT A MISSING PAGE.
         *
         * This used to `abort(404)`, and the message went with it: a browser
         * renders a 404 as its own "not found" page, so somebody clicking
         * "PCB (CP39)" on a run where nobody earned enough to pay tax — which
         * is most runs in this industry — was told the page did not exist.
         * Reported exactly that way, as a broken button.
         *
         * A run where no one paid PCB is a correct and common answer, so it is
         * said in words, on the screen they came from, naming the figure that
         * is missing rather than the file that is not there.
         */
        if ($data['rows'] === []) {
            return back()->with('error', sprintf(
                'Nothing to export for %s: no employee on this run has a figure for it.',
                PayrollExports::TYPES[$type],
            ));
        }

        return $this->csv($data);
    }

    /**
     * Streamed so a large run does not build the whole file in memory.
     *
     * The notice is the FIRST row of the file, not a separate readme: a caveat
     * that only exists on the screen the file was downloaded from is lost the
     * moment the file is emailed to someone else.
     */
    private function csv(array $data): StreamedResponse
    {
        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens names with accents correctly rather than
            // as mojibake, which is what every one of these files gets opened in.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [$data['notice']]);
            fputcsv($out, []);
            fputcsv($out, $data['headers']);

            foreach ($data['rows'] as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $data['filename'], [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
