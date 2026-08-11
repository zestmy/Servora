<?php

namespace App\Services\Payroll;

use App\Models\Company;
use App\Models\PayrollRun;
use App\Models\StatutorySetting;
use Barryvdh\DomPDF\PDF;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use Illuminate\Support\Collection;

/**
 * The payslip document, assembled in one place.
 *
 * There were three copies of this call — the manager's download, the emailed
 * attachment, and now the employee's own copy in the Staff Portal — each
 * building the same eight view keys by hand. Three is where that stops being
 * repetition and starts being a hazard: a field added to the template is a
 * field somebody has to remember to add in three files, and the one they miss
 * produces a payslip that is quietly missing a section rather than one that
 * fails loudly.
 *
 * The company is passed IN rather than read from Auth. A staff request has no
 * authenticated user at all, and a queued email has no request.
 */
class PayslipPdf
{
    /**
     * @param  Collection<int, \App\Models\PayrollRunLine>  $lines
     */
    public function make(PayrollRun $run, Collection $lines, ?Company $company = null): PDF
    {
        // Falls back to the run's own company, which is the correct one in
        // every case — the caller may simply already have it loaded.
        $company ??= $run->company;

        $statutory = StatutorySetting::forCompany($run->company_id);

        return PdfFacade::loadView('pdf.payslip', [
            'run'         => $run,
            'lines'       => $lines,
            'brandName'   => $company?->brand_name ?: $company?->name,
            'logoBase64'  => $company?->logoDataUri(),
            // The legal entity as well as the trading name — a payslip records
            // employment by a company, not by a brand.
            'companyName' => $company?->name,
            'companyReg'  => $company?->registration_number,
            'address'     => $company?->address,
            'employerTaxNumber' => $statutory->employer_tax_number,
            // A payslip must not present an estimate as a final figure.
            'ratesConfirmed'    => (bool) $run->rates_were_confirmed,
        ])->setPaper('a4', 'portrait');
    }

    /**
     * `payslip-janane-a-p-kanapathy-pr-2026-07-0001.pdf`.
     *
     * A SLASH IN A FILENAME IS A 500, not a cosmetic problem: Symfony refuses
     * to build a Content-Disposition header containing "/" or "\", so the
     * download throws before a byte is sent. Malaysian names carry the
     * patronymic as "A/L" and "A/P" — anak lelaki, anak perempuan — so this is
     * an ordinary name here, not an exotic input. The payroll screen built its
     * own filename by hand and printed nothing for those employees; it goes
     * through this now, which is why the sanitiser lives on the service beside
     * the document rather than in a controller.
     */
    public function filename(PayrollRun $run, string $employeeName): string
    {
        return 'payslip-' . $this->safe($employeeName, 'employee')
            . '-' . $this->safe($run->reference, 'run') . '.pdf';
    }

    /** Every payslip in a run, as one file. */
    public function runFilename(PayrollRun $run): string
    {
        return 'payslips-' . $this->safe($run->reference, 'run') . '.pdf';
    }

    /**
     * Down to letters, digits and hyphens.
     *
     * Deliberately stricter than stripping the two characters Symfony rejects:
     * a filename also crosses Windows, macOS and whatever the browser does with
     * it, and a colon or a quote is a problem on one of those. The fallback
     * matters because a name of nothing but punctuation would otherwise leave a
     * file called "payslip--.pdf".
     */
    private function safe(?string $value, string $fallback): string
    {
        $safe = trim(preg_replace('/[^A-Za-z0-9]+/', '-', strtolower((string) $value)), '-');

        return $safe !== '' ? $safe : $fallback;
    }
}
