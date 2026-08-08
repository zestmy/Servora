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
     * @param  ?Carbon  $overrideFrom  An explicit period, replacing the cycle
     * @param  ?Carbon  $overrideTo    for this run only. See below.
     * @throws \RuntimeException when the run is already approved
     */
    public function generate(
        int $companyId,
        array $accessibleOutletIds,
        Carbon $month,
        ?int $outletId,
        ?int $userId = null,
        ?Carbon $overrideFrom = null,
        ?Carbon $overrideTo = null,
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

        /*
         * The real range this month covers.
         *
         * Three sources, in this order, and the order is the point:
         *
         *   1. AN EXPLICIT RANGE for this run, when the caller gives one. The
         *      case it exists for is the month a company CHANGES its cycle.
         *      Moving from calendar months to 26th–25th makes the last old
         *      month short — 1–25 June, six days lighter — and the first new
         *      one start mid-month. Neither is derivable from a setting,
         *      because a setting describes the steady state and this is the
         *      seam between two of them. Without this the choice was to run
         *      June under the wrong cycle or to change the setting, run, and
         *      change it back, which silently rewrites what "June" meant for
         *      every screen that reads the cycle.
         *
         *   2. THE RANGE AN EXISTING DRAFT WAS BUILT WITH. Regenerating after
         *      approving a few more OT claims must not silently move the
         *      period because somebody edited the setting in between.
         *
         *   3. The company's pay cycle, which is the answer every ordinary
         *      month.
         *
         * The range is stored on the run either way, so a run always says what
         * it covered rather than leaving it to be re-derived from a setting
         * that has since moved on.
         */
        [$from, $to] = match (true) {
            $overrideFrom !== null && $overrideTo !== null => [$overrideFrom, $overrideTo],
            (bool) ($existing?->period_start && $existing?->period_end)
                => [Carbon::parse($existing->period_start), Carbon::parse($existing->period_end)],
            default => \App\Models\CompensationSetting::forCompany($companyId)->cycleFor($month),
        };

        if ($from->gt($to)) {
            throw new \RuntimeException('The period must start before it ends.');
        }

        $data = $this->summary->forMonth($employees, $companyId, $month, $from, $to);

        $statutory = StatutorySetting::forCompany($companyId);

        // Service charge for the same range, ASKED of the existing
        // distribution rather than recomputed. It stays out of
        // CompensationSummary on purpose — that would be a second
        // implementation of the same rules — but a run is a snapshot, so it
        // can copy the answer. Null when no pool was saved for this exact
        // period, which is the normal case for companies that do not levy one.
        $serviceCharge = app(\App\Services\Hr\ServiceChargeDistribution::class)->forPeriod(
            $companyId, $accessibleOutletIds, $from, $to, $outletId,
        );

        $scByEmployee = collect($serviceCharge['rows'] ?? [])
            ->keyBy(fn ($r) => $r['employee']->id);

        $scPerPoint = (float) ($serviceCharge['perPoint'] ?? 0);

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
            ->get(['id', 'name', 'ic_number', 'designation', 'bank_name', 'bank_account_no', 'bank_account_name'])
            ->keyBy('id');

        return DB::transaction(function () use (
            $existing, $companyId, $outletId, $month, $from, $to, $data, $statutory,
            $profiles, $identity, $userId, $scByEmployee, $scPerPoint
        ) {
            $run = $existing ?: new PayrollRun([
                'company_id'   => $companyId,
                'outlet_id'    => $outletId,
                'period_month' => $month->toDateString(),
                'period_start' => $from->toDateString(),
                'period_end'   => $to->toDateString(),
                'reference'    => PayrollRun::nextReference($companyId, $month),
            ]);

            $run->fill([
                'company_id'               => $companyId,
                'outlet_id'                => $outletId,
                'period_month'             => $month->toDateString(),
                // Stored explicitly, not re-derived: a run must be able to say
                // what it actually covered even after the cycle setting moves.
                'period_start'             => $from->toDateString(),
                'period_end'               => $to->toDateString(),
                'status'                   => PayrollRun::DRAFT,
                'total_gross'              => $data['totals']['gross'],
                'total_service_charge'     => round($scByEmployee->sum(fn ($r) => (float) $r['net']), 2),
                'total_net'                => round((float) $data['totals']['net']
                    + $scByEmployee->sum(fn ($r) => (float) $r['net']), 2),
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
                $sc      = $scByEmployee[$row['employee_id']] ?? null;

                // The NET share — what the person is actually paid after the
                // attendance, lateness and special deductions. Gross would
                // overstate it on a payslip, which is the figure staff check.
                $scAmount = $sc ? round((float) $sc['net'], 2) : 0.0;

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
                    // Only when it is genuinely somebody else. Storing a copy
                    // of the employee's own name here would make every line
                    // look like a third-party account to anyone reading them.
                    'bank_account_name'  => $emp?->bankAccountIsThirdParty() ? $emp->bank_account_name : null,
                    'epf_number'         => $profile?->epf_number,
                    'socso_number'       => $profile?->socso_number,
                    'income_tax_number'  => $profile?->income_tax_number,
                    'pay_type'           => $row['pay_type'],
                    // The working behind basic, for hourly staff. Null for
                    // everybody else, which is what lets the payslip show it
                    // without having to ask the pay type.
                    'paid_hours'         => $row['paid_hours'] ?? null,
                    'pay_rate'           => $row['pay_rate'] ?? null,
                    'basic'              => $row['basic'],
                    'allowances'         => $row['allowances'],
                    'deductions'         => $row['deductions'],
                    'ot_hours'           => $row['ot_hours'],
                    'ot_amount'          => $row['ot_amount'],
                    'service_charge'     => $scAmount,
                    // The working, so the payslip can show it without
                    // recomputing: points, rate, and each deduction.
                    'service_charge_detail' => $sc ? [
                        'points'     => (float) $sc['points'],
                        'per_point'  => $scPerPoint,
                        'gross'      => (float) $sc['gross'],
                        'attendance' => (float) $sc['dedAmt'],
                        'lateness'   => (float) $sc['lateAmt'],
                        'special'    => (float) $sc['specialAmt'],
                        'excluded'   => (bool) ($sc['excluded'] ?? false),
                    ] : null,
                    'gross'              => $row['gross'],
                    'epf_employee'       => $row['statutory']['epf_employee'],
                    'epf_employer'       => $row['statutory']['epf_employer'],
                    'socso_employee'     => $row['statutory']['socso_employee'],
                    'socso_employer'     => $row['statutory']['socso_employer'],
                    'eis_employee'       => $row['statutory']['eis_employee'],
                    'eis_employer'       => $row['statutory']['eis_employer'],
                    'pcb'                => $row['statutory']['pcb'],
                    'hrdf_employer'      => $row['statutory']['hrdf_employer'] ?? 0,
                    'zakat'              => $row['statutory']['zakat'] ?? 0,
                    'statutory_employee' => $row['statutory']['employee_total'],
                    'statutory_employer' => $row['statutory']['employer_total'],
                    // Service charge is added AFTER statutory, not before:
                    // EPF, SOCSO and PCB here are computed on salary, and
                    // folding a pool share into the statutory wage would
                    // change every contribution on an assumption this system
                    // has no business making. It IS in net, because it is paid
                    // in the same transfer and net is what the bank file pays.
                    'net'                => round((float) $row['net'] + $scAmount, 2),
                    // NOT in employer cost: the pool is collected from
                    // customers and passes through the company, so counting it
                    // as a cost of employment would overstate the wage bill.
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
