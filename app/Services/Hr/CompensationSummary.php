<?php

namespace App\Services\Hr;

use App\Models\CompensationSetting;
use App\Models\Employee;
use App\Models\EmployeePayComponent;
use App\Models\OvertimeClaim;
use App\Models\StatutorySetting;
use App\Scopes\CompanyScope;
use App\Services\Payroll\StatutoryCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * What each employee is owed for a month: basic, plus the allowances and
 * deductions in force that month, plus approved overtime.
 *
 * Everything is computed FOR THE PERIOD rather than from today's values —
 * an allowance that ended in February must not appear in March, and a salary
 * revision that lands in April must not rewrite March. That is the whole
 * reason employee_pay_components carries dates.
 *
 * NOT INCLUDED: service charge. It is distributed from the attendance grid by
 * ServiceChargePeriod::distribute(), which needs the full month of attendance
 * codes per employee as its input; reproducing that here would mean a second
 * implementation of the same rules, and two service charge figures that can
 * disagree is worse than one figure in one place. The Attendance Record screen
 * remains where service charge is read.
 */
class CompensationSummary
{
    /**
     * Used when no statutory contribution is switched on, so rows keep one
     * shape. Taken from the calculator rather than restated here — two
     * definitions of "no contributions" is two things to keep in step, and a
     * row missing a key crashes the totals rather than reading as zero.
     */
    private const NO_STATUTORY = StatutoryCalculator::NONE;

    /**
     * @param  Builder  $employees  already scoped to the outlets wanted
     * @return array{
     *     rows: Collection,
     *     totals: array<string, float>,
     *     from: Carbon, to: Carbon,
     *     settings: CompensationSetting,
     * }
     */
    public function forMonth(Builder $employees, int $companyId, Carbon $month, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $settings = CompensationSetting::forCompany($companyId);

        // The range defaults to the company's pay CYCLE, not the calendar
        // month: a company closing payroll on the 25th counts attendance,
        // approved OT and dated allowances over 26 Jul – 25 Aug for the month
        // it calls August. Callers may still pass an explicit range for a
        // one-off period.
        if ($from === null || $to === null) {
            [$from, $to] = $settings->cycleFor($month);
        }

        $from = $from->copy()->startOfDay();
        $to   = $to->copy()->endOfDay();

        // Built once for the whole run: the rates are company-wide, only the
        // employee's own profile varies.
        $statutorySettings = StatutorySetting::forCompany($companyId);
        $calculator = $statutorySettings->anyEnabled()
            ? new StatutoryCalculator($statutorySettings)
            : null;

        // Staff who were employed for any part of the month: a leaver is still
        // owed for the days they worked, which is the point of the period.
        $staff = (clone $employees)
            ->employedDuring($from->toDateString())
            ->with('outlet:id,name', 'section:id,name')
            ->orderBy('name')
            ->get();

        $assignments = EmployeePayComponent::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->whereIn('employee_id', $staff->pluck('id'))
            ->overlapping($from->toDateString(), $to->toDateString())
            ->with('component')
            ->get()
            ->groupBy('employee_id');

        // Approved claims only: a pending claim is not yet an amount owed.
        $otHours = OvertimeClaim::withoutGlobalScope(CompanyScope::class)
            ->whereIn('employee_id', $staff->pluck('id'))
            ->where('status', 'approved')
            // Claims approved as TIME OFF are not an amount owed at all —
            // they were settled in hours the moment they were approved. They
            // never reach a payslip, and PayrollRun::settleOvertime() likewise
            // leaves them alone so they stay available to take.
            ->where('settlement', OvertimeClaim::SETTLE_PAYROLL)
            ->whereBetween('claim_date', [$from->toDateString(), $to->toDateString()])
            // Hours already taken as TIME OFF are subtracted, not paid. An hour
            // of overtime is either paid or taken off, never both — this
            // subtraction is the only thing preventing the double count, and
            // it is why time-off approval writes hours_taken_off onto the claim.
            ->selectRaw('employee_id, ot_type, SUM(total_ot_hours - hours_taken_off) as hours')
            ->groupBy('employee_id', 'ot_type')
            ->get()
            ->groupBy('employee_id');

        /*
         * Hours worked, for anybody paid by them.
         *
         * Read from the attendance grid rather than from the roster or the
         * clock, and that is a deliberate choice rather than the easy one. The
         * roster is a plan — it pays a shift somebody left two hours early. The
         * clock is the truth but an incomplete one: a missing clock-out is a
         * review item today, and making it a payroll blocker would stop a run
         * over one forgotten punch. The grid is where a human has already
         * looked at both and written down what to pay for.
         *
         * One query for the whole outlet, keyed by employee. Rows carrying a
         * code rather than hours (MC, absent) contribute nothing here — that is
         * what makes a blank or coded day unpaid.
         */
        $hoursByEmployee = \App\Models\AttendanceRecord::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->whereIn('employee_id', $staff->pluck('id'))
            ->whereNotNull('hours')
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('employee_id, SUM(hours) as total')
            ->groupBy('employee_id')
            ->pluck('total', 'employee_id');

        /*
         * DAYS worked, for anybody paid by them. The same grid, the same
         * reasoning, counted rather than summed.
         *
         * A day counts when hours were recorded against it AND they are above
         * zero. Zero hours on a row is somebody who did not work that day —
         * paying a day rate for it would pay for the absence.
         */
        $daysByEmployee = \App\Models\AttendanceRecord::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->whereIn('employee_id', $staff->pluck('id'))
            ->where('hours', '>', 0)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('employee_id, COUNT(*) as total')
            ->groupBy('employee_id')
            ->pluck('total', 'employee_id');

        /*
         * THE DIVISOR FOR AN INCOMPLETE MONTH — the length of the company's
         * NORMAL wage period for this month, not the length of this run.
         *
         * Employment Act 1955 s.60I prices an incomplete month as
         *     monthly wage ÷ days of the wage period × days eligible
         * and the wage period is the company's cycle: 31 for a calendar July,
         * 30 for a 26th-to-25th one.
         *
         * Taking the RUN's length instead would be the obvious reading and is
         * wrong in the one case this most needs to be right. A cycle change
         * produces a stub run — 1–25 June, say — and dividing by its own 25
         * days pays a full month's salary for 25 days of work; the next run
         * then pays another full month, so the company pays two months for
         * about 1.8. Dividing by the wage period pays 25/30 and the seam adds
         * up. The numerator is narrowed to the run; the divisor never is.
         */
        // Rounded before casting, never truncated: diffInDays returns a float,
        // and (int) on a 29.999999 would quietly shorten the wage period by a
        // day — which would then overpay everybody on an incomplete month.
        [$cycleFrom, $cycleTo] = $settings->cycleFor($month);
        $wagePeriodDays = max(1, (int) round($cycleFrom->copy()->startOfDay()->diffInDays($cycleTo->copy()->startOfDay())) + 1);

        // Year to date from COMMITTED runs, for the PCB MTD formula. One query
        // for everyone rather than one per employee inside the map.
        $ytd = $calculator
            ? app(\App\Services\Payroll\YearToDate::class)
                // Keyed on the LABELLED month, not the cycle start: with a
                // 26th-to-25th cycle the August run starts in July, and
                // year-to-date must mean "the runs before August", not
                // "the runs before July".
                ->forMonth($companyId, $staff->pluck('id')->all(), $month->copy()->startOfMonth())
            : collect();

        $rows = $staff->map(function (Employee $employee) use ($assignments, $otHours, $settings, $calculator, $from, $to, $ytd, $hoursByEmployee, $daysByEmployee, $wagePeriodDays) {
            /*
             * BASIC, and what basic_salary means depends on the pay type.
             *
             * MONTHLY (and no pay type recorded, which behaves as monthly) —
             * the figure itself, pro-rated when the person was not employed for
             * the whole wage period. See below.
             *
             * DAILY — a RATE. The month's basic is that rate times the days
             * actually worked on the attendance grid. Before this it was used
             * verbatim, so somebody on RM100 a day was paid RM100 for the
             * month, with EPF, SOCSO, EIS and PCB all computed on RM100.
             *
             * HOURLY — a RATE, times the hours on the grid. Same fault, fixed
             * earlier for the same reason.
             *
             * Daily or hourly with nothing entered comes to zero, which is
             * correct and is also why the payroll screen lists who that is
             * before a run: zero is indistinguishable from "nobody has filled
             * the grid in yet", and the two need very different responses.
             */
            $isHourly = $employee->pay_type === 'hourly';
            $isDaily  = $employee->pay_type === 'daily';

            $paidHours = $isHourly ? round((float) ($hoursByEmployee[$employee->id] ?? 0), 2) : null;
            $workedDays = $isDaily ? (int) ($daysByEmployee[$employee->id] ?? 0) : null;

            // The contractual rate as it is written on the record — per hour
            // for hourly staff, per day for daily. Null for monthly, whose
            // basic_salary is a total rather than a rate.
            $unitRate = ($isHourly || $isDaily) && $employee->basic_salary !== null
                ? (float) $employee->basic_salary
                : null;

            /*
             * PRO-RATING AN INCOMPLETE MONTH, for monthly staff only.
             *
             * Daily and hourly staff need none of this — they are already paid
             * for exactly what they worked, and pro-rating on top would deduct
             * for the same absence twice.
             *
             * The days counted are the overlap of THREE ranges: the period
             * being run, the time since they joined, and the time up to their
             * leaving date. Somebody who joined on the 20th is paid from the
             * 20th; somebody who left on the 5th is paid to the 5th; somebody
             * employed throughout is paid in full.
             *
             * Both ends are INCLUSIVE — a leaving date is the last working day,
             * which is the same rule Employee::resignationTookEffect() applies.
             */
            $eligibleFrom = $employee->join_date && $employee->join_date->gt($from)
                ? $employee->join_date->copy()->startOfDay()
                : $from->copy()->startOfDay();

            $employedUntil = $employee->employedUntil();
            $eligibleTo = $employedUntil && $employedUntil->lt($to)
                ? $employedUntil->copy()->startOfDay()
                : $to->copy()->startOfDay();

            // Whole days, rounded before casting for the same reason as the
            // wage period above.
            $daysEligible = $eligibleTo->lt($eligibleFrom)
                ? 0
                : (int) round($eligibleFrom->diffInDays($eligibleTo)) + 1;

            /*
             * Capped at the wage period, never above it. A run deliberately
             * made LONGER than the cycle — two months in one period — must not
             * quietly pay 2× basic off the back of this arithmetic; that is a
             * decision for whoever is running it, not a side effect. Full
             * salary is the ceiling.
             */
            $isMonthly     = ! $isHourly && ! $isDaily;
            $isProrated    = $isMonthly && $daysEligible < $wagePeriodDays;
            $salary        = $employee->basic_salary !== null ? (float) $employee->basic_salary : 0.0;

            $basic = match (true) {
                $isHourly   => round((float) $paidHours * (float) $unitRate, 2),
                $isDaily    => round((float) $workedDays * (float) $unitRate, 2),
                $isProrated => round($salary / $wagePeriodDays * $daysEligible, 2),
                default     => $salary,
            };

            $components = ($assignments[$employee->id] ?? collect())
                ->filter(fn ($a) => $a->component !== null)
                ->map(fn ($a) => [
                    'name'     => $a->component->name,
                    'kind'     => $a->component->kind,
                    'amount'   => $a->component->resolveAmount((float) $a->amount, $basic),
                    'taxable'  => (bool) $a->component->is_taxable,
                    'epf'      => (bool) $a->component->epf_applicable,
                    'socso'    => (bool) $a->component->socso_applicable,
                ])
                ->values();

            $allowancesOnly = $components->where('kind', 'allowance');
            $allowances = round($allowancesOnly->sum('amount'), 2);
            $deductions = round(abs($components->where('kind', 'deduction')->sum('amount')), 2);

            $rate    = $settings->hourlyRate($employee->basic_salary !== null ? (float) $employee->basic_salary : null, $employee->pay_type);
            $otRows  = $otHours[$employee->id] ?? collect();
            $otTotal = 0.0;
            $otByType = [];

            foreach ($otRows as $row) {
                $hours = round((float) $row->hours, 2);
                $amount = $rate !== null ? round($hours * $rate * $settings->multiplierFor($row->ot_type), 2) : null;
                $otByType[$row->ot_type] = ['hours' => $hours, 'amount' => $amount];
                $otTotal += $amount ?? 0;
            }

            // Statutory wages are earnings-based: a uniform deduction does not
            // reduce what EPF is owed on. Each allowance says what it counts
            // towards, because a travelling allowance is commonly outside EPF
            // and SOCSO wages while still being part of gross pay.
            $epfWages     = round($basic + $allowancesOnly->where('epf', true)->sum('amount') + $otTotal, 2);
            $socsoWages   = round($basic + $allowancesOnly->where('socso', true)->sum('amount') + $otTotal, 2);
            $taxablePay   = round($basic + $allowancesOnly->where('taxable', true)->sum('amount') + $otTotal, 2);
            $gross        = round($basic + $allowances + $otTotal, 2);

            $statutory = $calculator?->for(
                $employee, $epfWages, $socsoWages, $taxablePay, $to, null,
                $ytd[$employee->id] ?? null,
            ) ?? self::NO_STATUTORY;

            return [
                'employee_id' => $employee->id,
                'name'        => $employee->name,
                'staff_id'    => $employee->staff_id,
                'outlet'      => $employee->outlet?->name,
                'section'     => $employee->section?->name,
                'pay_type'    => $employee->pay_type,
                'basic'       => $basic,
                // The working behind `basic`, for the payslip and for anyone
                // checking it. Each is null for everybody it does not describe,
                // which is what lets the payslip show the right one without
                // having to ask the pay type:
                //   paid_hours + pay_rate   hourly    "37.5 h × RM12.00"
                //   paid_days  + pay_rate   daily     "12 d × RM100.00"
                //   paid_days  + period_days monthly  "12 / 31 days"
                'paid_hours'  => $paidHours,
                'paid_days'   => $isDaily ? $workedDays : ($isProrated ? $daysEligible : null),
                'period_days' => $isProrated ? $wagePeriodDays : null,
                'pay_rate'    => $unitRate,
                'hourly_rate' => $rate,
                'components'  => $components,
                'allowances'  => $allowances,
                'deductions'  => $deductions,
                'ot_hours'    => round(collect($otByType)->sum('hours'), 2),
                'ot_by_type'  => $otByType,
                'ot_amount'   => round($otTotal, 2),
                // Salary is unknown, so the OT figure would be a guess. Flagged
                // rather than silently reported as zero.
                'ot_unrated'  => $rate === null && $otRows->isNotEmpty(),
                'epf_wages'   => $epfWages,
                'socso_wages' => $socsoWages,
                'taxable_pay' => $taxablePay,
                'statutory'   => $statutory,
                // Gross is what is earned; net is what reaches the bank after
                // both the company's own deductions and the statutory ones.
                'gross'       => round($gross - $deductions, 2),
                'net'         => round($gross - $deductions - $statutory['employee_total'], 2),
                'employer_cost' => round($gross - $deductions + $statutory['employer_total'], 2),
            ];
        });

        return [
            'rows'   => $rows,
            'totals' => [
                'basic'      => round($rows->sum('basic'), 2),
                'allowances' => round($rows->sum('allowances'), 2),
                'deductions' => round($rows->sum('deductions'), 2),
                'ot_amount'  => round($rows->sum('ot_amount'), 2),
                'ot_hours'   => round($rows->sum('ot_hours'), 2),
                'gross'      => round($rows->sum('gross'), 2),
                'epf_employee'   => round($rows->sum(fn ($r) => $r['statutory']['epf_employee']), 2),
                'epf_employer'   => round($rows->sum(fn ($r) => $r['statutory']['epf_employer']), 2),
                'socso_employee' => round($rows->sum(fn ($r) => $r['statutory']['socso_employee']), 2),
                'socso_employer' => round($rows->sum(fn ($r) => $r['statutory']['socso_employer']), 2),
                'eis_employee'   => round($rows->sum(fn ($r) => $r['statutory']['eis_employee']), 2),
                'eis_employer'   => round($rows->sum(fn ($r) => $r['statutory']['eis_employer']), 2),
                'pcb'            => round($rows->sum(fn ($r) => $r['statutory']['pcb']), 2),
                'hrdf_employer'  => round($rows->sum(fn ($r) => $r['statutory']['hrdf_employer'] ?? 0), 2),
                'statutory_employee' => round($rows->sum(fn ($r) => $r['statutory']['employee_total']), 2),
                'statutory_employer' => round($rows->sum(fn ($r) => $r['statutory']['employer_total']), 2),
                'net'           => round($rows->sum('net'), 2),
                'employer_cost' => round($rows->sum('employer_cost'), 2),
            ],
            'from'      => $from,
            'to'        => $to,
            'settings'  => $settings,
            'statutory' => $statutorySettings,
        ];
    }
}
