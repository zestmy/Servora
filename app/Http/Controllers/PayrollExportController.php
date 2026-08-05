<?php

namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Services\Payroll\PayrollExports;
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
    private const TYPES = ['cp39', 'epf', 'socso', 'bank'];

    public function __invoke(PayrollRun $run, string $type): StreamedResponse
    {
        abort_unless(Auth::user()?->can('hr.payroll'), 403);
        abort_unless($run->company_id === Auth::user()->company_id, 404);
        abort_unless(in_array($type, self::TYPES, true), 404);

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

        abort_if($data['rows'] === [], 404, 'Nothing to export: no employee in this run has a figure for it.');

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
