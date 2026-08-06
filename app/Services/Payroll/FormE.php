<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Scopes\CompanyScope;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Borang E and CP8D: the EMPLOYER's annual return.
 *
 * Different document from the EA form and often confused with it. The EA goes
 * to each EMPLOYEE by 28 February so they can file their own return; Form E is
 * what the EMPLOYER submits to LHDN by 31 March, and CP8D is the employee
 * listing that accompanies it. Both are built from the same committed payroll
 * as the EA forms, so the three can never disagree about a figure.
 *
 * WHAT CANNOT BE ANSWERED FROM PAYROLL, and is therefore reported as nil with
 * the reason stated rather than guessed: benefits in kind, living
 * accommodation, tax-exempt income, CP38 instalments, TP1 relief, and whether
 * a leaver left the country. That last one is a Form E question this system
 * has no way of knowing, and quietly answering "none" would be a false return.
 */
class FormE
{
    public function __construct(private EaForm $ea)
    {
    }

    /**
     * The whole return: employer block, headcount statistics, and the CP8D rows.
     *
     * @return array<string, mixed>
     */
    public function forYear(int $companyId, array $accessibleOutletIds, int $year, ?int $outletId = null): array
    {
        $forms = $this->ea->forYear($companyId, $accessibleOutletIds, $year, $outletId);

        return [
            'year'       => $year,
            'employer'   => $this->ea->employer($companyId),
            'rows'       => $forms,
            'headcount'  => $this->headcount($companyId, $accessibleOutletIds, $year, $outletId, $forms),
            'totals'     => $this->totals($forms),
            'unanswered' => [
                'Number of employees who ceased employment and left Malaysia (Form E B4) — '
                    . 'departure from the country is not recorded anywhere in this system.',
                'Benefits in kind, value of living accommodation and tax exempt income (CP8D) — '
                    . 'none of these pass through payroll here.',
                'CP38 instalment deductions and relief claimed through TP1 (CP8D) — not processed here.',
            ],
        ];
    }

    /**
     * Form E Part B, as far as payroll can answer it.
     *
     * @param  Collection<int, array<string, mixed>>  $forms
     */
    private function headcount(int $companyId, array $accessibleOutletIds, int $year, ?int $outletId, Collection $forms): array
    {
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd   = Carbon::create($year, 12, 31)->endOfDay();

        $paidIds = $forms->pluck('employee_id')->all();

        $employees = Employee::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->whereIn('outlet_id', $accessibleOutletIds ?: [0])
            ->when($outletId !== null, fn ($q) => $q->where('outlet_id', $outletId))
            ->whereIn('id', $paidIds ?: [0])
            ->get();

        // Ceased DURING the year — a leaving date inside it. Someone who left
        // in a previous year is not on this return at all, because they have
        // no committed payroll in it.
        $ceased = $employees->filter(fn ($e) => $e->hasResigned()
            && $e->employment_status_date
            && Carbon::parse($e->employment_status_date)->between($yearStart, $yearEnd));

        $joined = $employees->filter(fn ($e) => $e->join_date
            && Carbon::parse($e->join_date)->between($yearStart, $yearEnd));

        return [
            // B1 — still employed at the end of the year: everyone paid during
            // it, less those whose leaving date fell inside it.
            'at_year_end' => $employees->count() - $ceased->count(),
            'new'         => $joined->count(),
            'ceased'      => $ceased->count(),
            // B4 — cannot be answered from payroll. Null, not zero: zero is an
            // assertion and this is an absence of information.
            'ceased_left_malaysia' => null,
            // B5 — anyone who actually had tax deducted during the year.
            'subject_to_mtd' => $forms->filter(fn ($f) => $f['pcb'] > 0)->count(),
            'total_paid'     => $forms->count(),
        ];
    }

    /** @param  Collection<int, array<string, mixed>>  $forms */
    private function totals(Collection $forms): array
    {
        $sum = fn (string $key) => round($forms->sum(fn ($f) => (float) $f[$key]), 2);

        return [
            'gross_income'   => $sum('gross_income'),
            'basic'          => $sum('basic'),
            'allowances'     => $sum('allowances'),
            'overtime'       => $sum('overtime'),
            'service_charge' => $sum('service_charge'),
            'pcb'            => $sum('pcb'),
            'zakat'          => $sum('zakat'),
            'epf_employee'   => $sum('epf_employee'),
            'socso_employee' => $sum('socso_employee'),
            'eis_employee'   => $sum('eis_employee'),
        ];
    }

    /**
     * CP8D as rows, in the order LHDN's listing asks for them.
     *
     * @param  Collection<int, array<string, mixed>>  $forms
     * @return array{headers: array<int, string>, rows: array<int, array<int, string>>}
     */
    public function cp8d(Collection $forms, array $employer, int $year): array
    {
        $rows = $forms->values()->map(fn ($f, $i) => [
            (string) ($i + 1),
            $f['name'],
            $f['ic_number'] ?: '',
            $f['tax_number'] ?: '',
            $f['epf_number'] ?: '',
            number_format($f['gross_income'], 2, '.', ''),
            // Not tracked — nil, and the notice above the table says why.
            '0.00',
            '0.00',
            '0.00',
            number_format($f['epf_employee'], 2, '.', ''),
            number_format($f['pcb'], 2, '.', ''),
            '0.00',
            number_format($f['zakat'], 2, '.', ''),
        ])->all();

        return [
            'filename' => "cp8d-{$year}.csv",
            'notice'   => "CP8D employee listing for {$year} — employer's no. (E) "
                . ($employer['tax_number'] ?: 'NOT SET') . '. '
                . 'Field listing only, NOT the LHDN e-Filing upload layout — check against LHDN before submitting. '
                . 'Benefits in kind, living accommodation, tax exempt income, CP38 and TP1 relief are shown as 0.00 '
                . 'because they do not pass through payroll here, which is not the same as knowing they are zero.',
            'headers'  => [
                'No', 'Name', 'IC / Passport No', 'Income Tax No', 'EPF No',
                'Gross Remuneration (RM)',
                'Benefits in Kind (RM)', 'Value of Living Accommodation (RM)', 'Tax Exempt Income (RM)',
                'Employee EPF (RM)', 'MTD / PCB (RM)', 'CP38 (RM)', 'Zakat (RM)',
            ],
            'rows' => $rows,
        ];
    }
}
