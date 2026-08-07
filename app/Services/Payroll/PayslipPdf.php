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

    /** `payslip-siti-aminah-PR-2026-07-0001.pdf` — safe on every filesystem. */
    public function filename(PayrollRun $run, string $employeeName): string
    {
        $safe = trim(preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($employeeName)), '-');

        return "payslip-{$safe}-{$run->reference}.pdf";
    }
}
