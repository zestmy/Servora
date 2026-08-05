<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\EmployeeStatutoryProfile;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Models\StatutorySetting;
use App\Scopes\CompanyScope;
use App\Services\Hr\CompensationSummary;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Turn a live month into a locked payroll run.
 *
 * The arithmetic is NOT reimplemented here. CompensationSummary already knows
 * how to price a month — dated pay components, approved OT, statutory — and a
 * second implementation would eventually disagree with the first about what
 * someone is owed. This snapshots what that produces.
 *
 * Regenerating replaces every line, so running payroll again after approving
 * the last few OT claims does the obvious thing. It is refused once the run
 * is approved: at that point the figures are what the company committed to.
 */
class PayrollRunBuilder
{
    public function __construct(private CompensationSummary $summary)
    {
    }

    /**
     * Build or rebuild a run for one month.
     *
     * @param  array<int, int>  $accessibleOutletIds
     * @throws \RuntimeException when the run is already approved
     */
    public function generate(
        int $companyId,
        array $accessibleOutletIds,
        Carbon $month,
        ?int $outletId,
        ?int $userId = null,
    ): PayrollRun {
        $month = $month->copy()->startOfMonth();

        $existing = PayrollRun::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('outlet_id', $outletId)
            ->whereDate('period_month', $month)
            ->first();

        if ($existing && ! $existing->isEditable()) {
            throw new \RuntimeException('This payroll run has been approved and can no longer be regenerated.');
        }

        // The employee scope the figures are computed over. A per-outlet run
        // narrows to that outlet; otherwise it is everything the user may see.
        $employees = Employee::query()
            ->whereIn('outlet_id', $accessibleOutletIds ?: [0])
            ->when($outletId !== null, fn ($q) => $q->where('outlet_id', $outletId));

        $data = $this->summary->forMonth($employees, $companyId, $month);

        $statutory = StatutorySetting::forCompany($companyId);

        // Identity that lives outside the summary — it is payroll paperwork,
        // not pay arithmetic, so CompensationSummary has no reason to carry it.
        $ids = $data['rows']->pluck('employee_id');

        $profiles = EmployeeStatutoryProfile::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->whereIn('employee_id', $ids)
            ->get()
            ->keyBy('employee_id');

        $identity = Employee::withoutGlobalScopes()
            ->whereIn('id', $ids)
            ->get(['id', 'ic_number', 'designation', 'bank_name', 'bank_account_no'])
            ->keyBy('id');

        return DB::transaction(function () use (
            $existing, $companyId, $outletId, $month, $data, $statutory,
            $profiles, $identity, $userId
        ) {
            $run = $existing ?: new PayrollRun([
                'company_id'   => $companyId,
                'outlet_id'    => $outletId,
                'period_month' => $month->toDateString(),
                'reference'    => PayrollRun::nextReference($companyId, $month),
            ]);

            $run->fill([
                'company_id'               => $companyId,
                'outlet_id'                => $outletId,
                'period_month'             => $month->toDateString(),
                'status'                   => PayrollRun::DRAFT,
                'total_gross'              => $data['totals']['gross'],
                'total_net'                => $data['totals']['net'],
                'total_statutory_employee' => $data['totals']['statutory_employee'],
                'total_statutory_employer' => $data['totals']['statutory_employer'],
                'total_employer_cost'      => $data['totals']['employer_cost'],
                'employee_count'           => $data['rows']->count(),
                'generated_by'             => $userId,
                'generated_at'             => now(),
                // Recorded so a run can still be explained after the rates are
                // edited — "why is June's EPF different" has an answer here.
                'rate_snapshot'            => $this->rateSnapshot($statutory),
                'rates_were_confirmed'     => $statutory->rates_confirmed_at !== null,
            ]);
            $run->save();

            // Replaced wholesale rather than diffed: a rebuild must not leave
            // behind a line for someone who has since left the outlet scope.
            PayrollRunLine::withoutGlobalScopes()->where('payroll_run_id', $run->id)->delete();

            foreach ($data['rows'] as $row) {
                $profile = $profiles[$row['employee_id']] ?? null;
                $emp     = $identity[$row['employee_id']] ?? null;

                PayrollRunLine::create([
                    'payroll_run_id'     => $run->id,
                    'company_id'         => $companyId,
                    'employee_id'        => $row['employee_id'],
                    'employee_name'      => $row['name'],
                    'staff_id'           => $row['staff_id'],
                    'ic_number'          => $emp?->ic_number,
                    'designation'        => $emp?->designation,
                    'outlet_name'        => $row['outlet'],
                    'section_name'       => $row['section'],
                    'bank_name'          => $emp?->bank_name,
                    'bank_account_no'    => $emp?->bank_account_no,
                    'epf_number'         => $profile?->epf_number,
                    'socso_number'       => $profile?->socso_number,
                    'income_tax_number'  => $profile?->income_tax_number,
                    'pay_type'           => $row['pay_type'],
                    'basic'              => $row['basic'],
                    'allowances'         => $row['allowances'],
                    'deductions'         => $row['deductions'],
                    'ot_hours'           => $row['ot_hours'],
                    'ot_amount'          => $row['ot_amount'],
                    'gross'              => $row['gross'],
                    'epf_employee'       => $row['statutory']['epf_employee'],
                    'epf_employer'       => $row['statutory']['epf_employer'],
                    'socso_employee'     => $row['statutory']['socso_employee'],
                    'socso_employer'     => $row['statutory']['socso_employer'],
                    'eis_employee'       => $row['statutory']['eis_employee'],
                    'eis_employer'       => $row['statutory']['eis_employer'],
                    'pcb'                => $row['statutory']['pcb'],
                    'statutory_employee' => $row['statutory']['employee_total'],
                    'statutory_employer' => $row['statutory']['employer_total'],
                    'net'                => $row['net'],
                    'employer_cost'      => $row['employer_cost'],
                    'components'         => $row['components']->all(),
                    'ot_by_type'         => $row['ot_by_type'],
                    // An unrated OT figure is a real caveat for the payslip to
                    // carry, so it travels with the statutory notes.
                    'statutory_notes'    => array_values(array_filter(array_merge(
                        $row['statutory']['notes'] ?? [],
                        $row['ot_unrated'] ? ['Overtime could not be priced: no salary on record.'] : [],
                    ))),
                ]);
            }

            return $run->fresh(['lines']);
        });
    }

    /**
     * The rates this run was computed under. Only the figures that change an
     * amount — enough to explain a difference, not a copy of the whole table.
     */
    private function rateSnapshot(StatutorySetting $s): array
    {
        return [
            'epf_enabled'            => (bool) $s->epf_enabled,
            'socso_enabled'          => (bool) $s->socso_enabled,
            'eis_enabled'            => (bool) $s->eis_enabled,
            'pcb_enabled'            => (bool) $s->pcb_enabled,
            'epf_employee_rate'      => (float) $s->epf_employee_rate,
            'epf_employer_rate_low'  => (float) $s->epf_employer_rate_low,
            'epf_employer_rate_high' => (float) $s->epf_employer_rate_high,
            'epf_wage_threshold'     => (float) $s->epf_wage_threshold,
            'socso_employee_rate'    => (float) $s->socso_employee_rate,
            'socso_employer_rate'    => (float) $s->socso_employer_rate,
            'socso_ceiling'          => (float) $s->socso_ceiling,
            'eis_employee_rate'      => (float) $s->eis_employee_rate,
            'eis_employer_rate'      => (float) $s->eis_employer_rate,
            'eis_ceiling'            => (float) $s->eis_ceiling,
            'confirmed_at'           => $s->rates_confirmed_at?->toDateTimeString(),
        ];
    }
}
