<?php

namespace App\Services\Payroll;

use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Models\StatutorySetting;
use App\Scopes\CompanyScope;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Borang EA (CP8A): a year's remuneration for one employee.
 *
 * The statement every employer must hand each employee by the end of February
 * for the preceding year, so they can file their own return. It is built by
 * summing that year's COMMITTED payroll runs — approved or paid. A draft is a
 * working figure that can still be regenerated, and an EA form issued from one
 * would disagree with the payslips already given out.
 *
 * WHAT THIS SYSTEM CANNOT KNOW, and therefore reports as nil rather than
 * inventing: benefits in kind, the value of living accommodation, tax-exempt
 * allowances, CP38 instalments ordered by LHDN, and relief claimed through a
 * TP1. None of them pass through payroll here. They are printed as zero with a
 * note on the form saying so, because a preparer needs to know which boxes are
 * genuinely nil and which are simply unknown to the system — that distinction
 * is the difference between a correct form and a quietly wrong one.
 */
class EaForm
{
    /**
     * Every employee with committed payroll in the year, newest name order.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forYear(int $companyId, array $accessibleOutletIds, int $year, ?int $outletId = null): Collection
    {
        $runIds = $this->committedRunIds($companyId, $year, $outletId);

        if ($runIds->isEmpty()) {
            return collect();
        }

        $lines = PayrollRunLine::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->whereIn('payroll_run_id', $runIds)
            ->get()
            ->groupBy('employee_id');

        // Employment dates come from the employee record — they are not on the
        // line, and the EA asks for the period of employment in the year.
        $employees = Employee::withoutGlobalScopes()
            ->whereIn('id', $lines->keys()->filter())
            ->whereIn('outlet_id', $accessibleOutletIds ?: [0])
            ->get()
            ->keyBy('id');

        return $lines
            // A line whose employee has been deleted, or who sits outside this
            // user's outlet scope, is dropped rather than rendered nameless.
            ->filter(fn ($group, $employeeId) => isset($employees[$employeeId]))
            ->map(fn ($group, $employeeId) => $this->build($group, $employees[$employeeId], $year))
            ->sortBy('name')
            ->values();
    }

    /** One employee's form, or null when they have no committed payroll that year. */
    public function forEmployee(int $companyId, array $accessibleOutletIds, int $year, int $employeeId, ?int $outletId = null): ?array
    {
        return $this->forYear($companyId, $accessibleOutletIds, $year, $outletId)
            ->firstWhere('employee_id', $employeeId);
    }

    /** Years that have any committed payroll, newest first. */
    public function availableYears(int $companyId): array
    {
        return PayrollRun::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('status', [PayrollRun::APPROVED, PayrollRun::PAID])
            ->selectRaw('DISTINCT YEAR(period_month) as y')
            ->orderByDesc('y')
            ->pluck('y')
            ->map(fn ($y) => (int) $y)
            ->all();
    }

    /** The employer block that heads every form. */
    public function employer(int $companyId): array
    {
        $company   = Company::withoutGlobalScopes()->find($companyId);
        $statutory = StatutorySetting::forCompany($companyId);

        return [
            'name'       => $company?->name,
            'brand'      => $company?->brand_name ?: $company?->name,
            'reg_no'     => $company?->registration_number,
            'address'    => $company?->address,
            'tax_number' => $statutory->employer_tax_number,
            'epf_number' => $statutory->employer_epf_number,
        ];
    }

    private function committedRunIds(int $companyId, int $year, ?int $outletId)
    {
        return PayrollRun::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('status', [PayrollRun::APPROVED, PayrollRun::PAID])
            ->whereYear('period_month', $year)
            ->when($outletId !== null, fn ($q) => $q->where('outlet_id', $outletId))
            ->pluck('id');
    }

    /**
     * @param  Collection<int, PayrollRunLine>  $lines
     */
    private function build(Collection $lines, Employee $employee, int $year): array
    {
        // The most recent line carries the identity as it was at the end of the
        // year, which is what the form should show.
        $latest = $lines->sortByDesc('payroll_run_id')->first();

        $sum = fn (string $col) => round($lines->sum(fn ($l) => (float) $l->{$col}), 2);

        // Part B1(a): gross employment income. Service charge IS employment
        // income and belongs here — leaving it out would understate what the
        // employee has to declare.
        $basic       = $sum('basic');
        $allowances  = $sum('allowances');
        $overtime    = $sum('ot_amount');
        $serviceChg  = $sum('service_charge');
        $grossIncome = round($basic + $allowances + $overtime + $serviceChg, 2);

        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd   = Carbon::create($year, 12, 31)->endOfDay();

        // Period of employment within the year — the join date if they started
        // during it, the leaving date if they left during it.
        $joined = $employee->join_date ? Carbon::parse($employee->join_date) : null;
        $left   = $employee->hasResigned() && $employee->employment_status_date
            ? Carbon::parse($employee->employment_status_date)
            : null;

        return [
            'employee_id' => $employee->id,
            'name'        => $latest->employee_name,
            'staff_id'    => $latest->staff_id,
            'ic_number'   => $latest->ic_number,
            'designation' => $latest->designation,
            'outlet'      => $latest->outlet_name,
            'tax_number'  => $latest->income_tax_number,
            'epf_number'  => $latest->epf_number,
            'socso_number' => $latest->socso_number,

            'from' => $joined && $joined->greaterThan($yearStart) ? $joined : $yearStart,
            'to'   => $left && $left->lessThan($yearEnd) ? $left : $yearEnd,
            'months' => $lines->count(),

            // Part B — income
            'basic'          => $basic,
            'allowances'     => $allowances,
            'overtime'       => $overtime,
            'service_charge' => $serviceChg,
            'gross_income'   => $grossIncome,

            // Part D — deductions
            'pcb'   => $sum('pcb'),
            'zakat' => $sum('zakat'),

            // Part E — contributions
            'epf_employee'   => $sum('epf_employee'),
            'epf_employer'   => $sum('epf_employer'),
            'socso_employee' => $sum('socso_employee'),
            'socso_employer' => $sum('socso_employer'),
            'eis_employee'   => $sum('eis_employee'),

            // Not tracked anywhere in this system. Reported as nil AND named,
            // so a preparer knows to add them by hand rather than assuming the
            // form is complete.
            'not_tracked' => [
                'Benefits in kind (Part B1(b))',
                'Value of living accommodation (Part B1(c))',
                'Tax exempt allowances, perquisites and gifts (Part B3)',
                'CP38 instalment deductions (Part D2)',
                'Relief claimed through TP1 (Part D4)',
            ],
        ];
    }
}
