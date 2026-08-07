<?php

namespace App\Services\Payroll;

use App\Models\PayrollRun;
use App\Models\StatutorySetting;
use Illuminate\Support\Collection;

/**
 * Submission and payment files built from a run's locked lines.
 *
 * WHAT THESE ARE, stated plainly because a rejected submission costs a filing
 * deadline: CSV listings carrying the FIELDS each body asks for, built from
 * the figures already computed and approved. They are NOT the fixed-width
 * binary layouts that KWSP i-Akaun, PERKESO ASSIST and LHDN e-CP39 accept for
 * direct upload — those layouts are versioned by each agency, and generating
 * one from a guessed spec produces a file that looks right and is rejected at
 * the counter.
 *
 * Use them to check the figures and to key in or paste, and reconcile against
 * the agency's own portal before submitting. Every file carries a header row
 * saying exactly this, so the caveat travels with the file rather than living
 * only on the screen it was downloaded from.
 */
class PayrollExports
{
    /**
     * PCB — the fields LHDN's CP39 asks for, one row per employee who paid tax.
     *
     * Employees with no PCB are omitted: CP39 is a return of tax deducted, and
     * a page of zeroes is not a return, it is noise.
     */
    public function cp39(PayrollRun $run): array
    {
        $settings = StatutorySetting::forCompany($run->company_id);

        $rows = $this->lines($run)
            ->filter(fn ($l) => (float) $l->pcb > 0)
            ->map(fn ($l) => [
                $l->income_tax_number ?: '',
                $l->ic_number ?: '',
                $l->employee_name,
                number_format((float) $l->pcb, 2, '.', ''),
                // CP38 is a separate instalment order from LHDN; nothing in the
                // system issues one, so it is always nil rather than guessed.
                '0.00',
                $run->period_month->format('m/Y'),
            ])
            ->values()
            ->all();

        return [
            'filename' => "cp39-{$run->reference}.csv",
            'notice'   => 'PCB (CP39) listing for ' . $run->periodLabel()
                . ' — employer file no. ' . ($settings->employer_tax_number ?: 'NOT SET')
                . '. Field listing only, not the LHDN e-CP39 upload layout. Check against LHDN before submitting.',
            'headers'  => ['Income Tax No', 'IC No', 'Name', 'PCB (RM)', 'CP38 (RM)', 'Month'],
            'rows'     => $rows,
        ];
    }

    /** EPF — the employee and employer halves KWSP Form A asks for. */
    public function epf(PayrollRun $run): array
    {
        $settings = StatutorySetting::forCompany($run->company_id);

        $rows = $this->lines($run)
            ->filter(fn ($l) => (float) $l->epf_employee > 0 || (float) $l->epf_employer > 0)
            ->map(fn ($l) => [
                $l->epf_number ?: '',
                $l->ic_number ?: '',
                $l->employee_name,
                number_format((float) $l->gross, 2, '.', ''),
                number_format((float) $l->epf_employee, 2, '.', ''),
                number_format((float) $l->epf_employer, 2, '.', ''),
                number_format((float) $l->epf_employee + (float) $l->epf_employer, 2, '.', ''),
            ])
            ->values()
            ->all();

        return [
            'filename' => "epf-{$run->reference}.csv",
            'notice'   => 'EPF (KWSP) contribution listing for ' . $run->periodLabel()
                . ' — employer no. ' . ($settings->employer_epf_number ?: 'NOT SET')
                . '. Contributions are computed as a percentage of capped wages, NOT from the KWSP'
                . ' wage-band table — reconcile before submitting.',
            'headers'  => ['EPF No', 'IC No', 'Name', 'Wages (RM)', 'Employee (RM)', 'Employer (RM)', 'Total (RM)'],
            'rows'     => $rows,
        ];
    }

    /** SOCSO and EIS together — PERKESO collects both on one form. */
    public function socso(PayrollRun $run): array
    {
        $settings = StatutorySetting::forCompany($run->company_id);

        $rows = $this->lines($run)
            ->filter(fn ($l) => (float) $l->socso_employee > 0 || (float) $l->socso_employer > 0
                || (float) $l->eis_employee > 0 || (float) $l->eis_employer > 0)
            ->map(fn ($l) => [
                $l->socso_number ?: '',
                $l->ic_number ?: '',
                $l->employee_name,
                number_format((float) $l->gross, 2, '.', ''),
                number_format((float) $l->socso_employee, 2, '.', ''),
                number_format((float) $l->socso_employer, 2, '.', ''),
                number_format((float) $l->eis_employee, 2, '.', ''),
                number_format((float) $l->eis_employer, 2, '.', ''),
            ])
            ->values()
            ->all();

        return [
            'filename' => "socso-eis-{$run->reference}.csv",
            'notice'   => 'SOCSO and EIS contribution listing for ' . $run->periodLabel()
                . ' — employer code ' . ($settings->employer_socso_number ?: 'NOT SET')
                . '. Computed as a percentage of the capped wage, NOT from the PERKESO contribution'
                . ' table — reconcile before submitting.',
            'headers'  => ['SOCSO No', 'IC No', 'Name', 'Wages (RM)', 'SOCSO Employee (RM)',
                           'SOCSO Employer (RM)', 'EIS Employee (RM)', 'EIS Employer (RM)'],
            'rows'     => $rows,
        ];
    }

    /**
     * Salary payment listing — account, name, amount.
     *
     * DELIBERATELY GENERIC. Every Malaysian bank's bulk transfer upload has its
     * own layout (Maybank2u Biz, CIMB BizChannel and Public Bank all differ in
     * field order, delimiter and header), and inventing one produces a file the
     * portal rejects. This carries the fields they all need in a form that can
     * be pasted or mapped; a bank-specific writer needs that bank's published
     * spec or a sample file.
     *
     * Anyone with no account number is EXCLUDED from the rows and reported
     * separately — a payment file with a blank account is how a transfer fails
     * silently for one person in a batch of fifty.
     */
    public function bankPayment(PayrollRun $run): array
    {
        $lines = $this->lines($run)->filter(fn ($l) => (float) $l->net > 0);

        [$payable, $unpayable] = $lines->partition(fn ($l) => (bool) $l->bank_account_no);

        $rows = $payable
            ->map(fn ($l) => [
                $l->bank_account_no,
                $l->bank_name ?: '',
                // THE ACCOUNT HOLDER, not the employee, on the rare line where
                // those differ. Banks match the name against the account and
                // reject the pair when they disagree, so sending the employee's
                // name to a spouse's or a parent's account fails the transfer —
                // one line out of fifty, on payday, for a reason the file gave
                // no hint of. Blank is the normal case and falls back to them.
                $l->bank_account_name ?: $l->employee_name,
                $l->ic_number ?: '',
                number_format((float) $l->net, 2, '.', ''),
                // A reference the employee will recognise on their statement.
                'SALARY ' . strtoupper($run->period_month->format('M Y')),
                // Last, and outside every bank's layout, so mapping the first
                // six columns is unchanged. It exists so the file can still be
                // reconciled against the payroll run by hand when the Name
                // column is a spouse nobody in the office recognises.
                $l->employee_name,
            ])
            ->values()
            ->all();

        $total = $payable->sum(fn ($l) => (float) $l->net);

        // Called out rather than left to be discovered in the CSV. The Name
        // column no longer matches the Employee column on these lines, which
        // looks exactly like a bug to whoever checks the file before uploading
        // it — and the one thing they must not do is "correct" it back.
        $thirdParty = $payable->filter(fn ($l) => filled($l->bank_account_name));

        return [
            'filename' => "salary-payment-{$run->reference}.csv",
            'notice'   => 'Salary payment listing for ' . $run->periodLabel()
                . ' — ' . count($rows) . ' payment(s), RM ' . number_format($total, 2) . ' total.'
                . ($unpayable->isNotEmpty()
                    ? ' EXCLUDED for having no bank account: ' . $unpayable->pluck('employee_name')->join(', ') . '.'
                    : '')
                . ($thirdParty->isNotEmpty()
                    ? ' Paid to an account in someone else\'s name (correct, not an error): '
                        . $thirdParty->map(fn ($l) => $l->employee_name . ' → ' . $l->bank_account_name)->join('; ') . '.'
                    : '')
                . ' Generic field listing — map to your bank\'s own bulk transfer layout.',
            'headers'  => ['Account No', 'Bank', 'Name', 'IC No', 'Amount (RM)', 'Reference', 'Employee'],
            'rows'     => $rows,
            'excluded' => $unpayable->pluck('employee_name')->all(),
            'total'    => round($total, 2),
        ];
    }

    /** @return Collection<int, \App\Models\PayrollRunLine> */
    private function lines(PayrollRun $run): Collection
    {
        return $run->lines()->orderBy('employee_name')->get();
    }
}
