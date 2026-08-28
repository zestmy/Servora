<?php

namespace App\Services\Payroll;

use App\Models\CompensationSetting;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollRunAdjustment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * "Take two days off everybody" — one adjustment per employee, priced per head.
 *
 * ASKED FOR: a way to add or deduct a number of days' salary across a whole
 * run at once, optionally including the fixed allowances, instead of typing
 * one correction per person for a company shutdown or a festive day.
 *
 * IT CREATES ORDINARY PayrollRunAdjustment ROWS, one per employee, and stops.
 * There is no bulk record and nothing new on the line. That is the whole
 * design: the amount differs per person anyway, so rows are needed regardless,
 * and going through the existing table means these are re-applied on every
 * rebuild, itemised on the payslip, listed on the run screen, and editable or
 * removable one at a time by somebody who disagrees with one of them. A
 * separate bulk concept would have had to re-earn every one of those.
 *
 * WHAT IT REFUSES TO GUESS. An employee with no salary on record has no day
 * rate, and a run whose divisor is zero has no arithmetic. Both are SKIPPED
 * AND REPORTED BY NAME rather than silently passed over or quietly priced at
 * zero — a bulk action that says "applied to 40 employees" when it reached 37
 * is the failure mode this whole class has to avoid.
 */
class BulkDayAdjustment
{
    /** Divide a monthly salary by the working-days setting — 26 by default. */
    public const BASIS_WORKING = 'working';

    /** Divide it by the calendar length of the run's own period instead. */
    public const BASIS_CALENDAR = 'calendar';

    public const BASES = [
        self::BASIS_WORKING  => 'Working days',
        self::BASIS_CALENDAR => 'Calendar days in the period',
    ];

    /**
     * Employees this action can be applied to, for one direction.
     *
     * DAILY AND HOURLY STAFF ARE NOT OFFERED FOR A DEDUCTION. Their pay
     * already follows what they worked — basic is the rate times the days or
     * hours on the grid — so deducting a day on top would take the same
     * absence off twice, which is the same reason CompensationSummary does not
     * pro-rate them. They remain available for an ADDITION, where a day's
     * bonus means exactly what it says.
     *
     * @return Collection<int, \App\Models\PayrollRunLine>
     */
    public function candidates(PayrollRun $run, string $direction): Collection
    {
        return $run->lines()
            ->whereNotNull('employee_id')
            ->orderBy('employee_name')
            ->get()
            ->when(
                $direction === PayrollRunAdjustment::DEDUCTION,
                fn ($lines) => $lines->reject(fn ($l) => $l->isHourly() || $l->isDaily())
            )
            ->values();
    }

    /**
     * The divisor a monthly salary is cut into, and what to call it on screen.
     *
     * @return array{0: int, 1: string}
     */
    public function divisor(PayrollRun $run, string $basis): array
    {
        if ($basis === self::BASIS_CALENDAR) {
            $days = $this->periodDays($run);

            return [$days, $days . '-day period'];
        }

        $days = (int) CompensationSetting::forCompany($run->company_id)->monthly_working_days ?: 26;

        return [$days, $days . '-day working month'];
    }

    /**
     * What this would do, without doing it.
     *
     * The same code path the apply below runs on, so the figures on the
     * confirmation cannot disagree with the figures that get written.
     *
     * @param  array<int, int>  $employeeIds
     * @return array{rows: Collection, skipped: Collection, total: float, divisorLabel: string}
     */
    public function preview(
        PayrollRun $run,
        array $employeeIds,
        float $days,
        string $direction,
        string $basis,
        bool $includeAllowances,
    ): array {
        [$divisor, $divisorLabel] = $this->divisor($run, $basis);

        $settings = CompensationSetting::forCompany($run->company_id);

        $lines = $this->candidates($run, $direction)
            ->whereIn('employee_id', $employeeIds)
            ->keyBy('employee_id');

        // Read fresh rather than off the line: the line snapshots what was
        // PAID, and a day's salary is a question about the contract.
        $employees = Employee::withoutGlobalScopes()
            ->whereIn('id', $lines->keys())
            ->get()
            ->keyBy('id');

        $rows    = collect();
        $skipped = collect();

        foreach ($lines as $employeeId => $line) {
            $employee = $employees[$employeeId] ?? null;

            if (! $employee) {
                $skipped->push(['name' => $line->employee_name, 'reason' => 'employee record no longer exists']);
                continue;
            }

            $dayRate = $settings->dailyRate(
                $employee->basic_salary !== null ? (float) $employee->basic_salary : null,
                $employee->pay_type,
                $divisor,
            );

            if ($dayRate === null) {
                $skipped->push(['name' => $line->employee_name, 'reason' => 'no salary on record']);
                continue;
            }

            /*
             * ALLOWANCES PER DAY, taken from the LINE rather than the contract.
             *
             * The line already carries what this run actually pays them,
             * including any pro-rating a part month applied — so a joiner who
             * is receiving two thirds of their travelling allowance this month
             * has two thirds of it in a day of it, which is the answer that
             * matches their payslip. Asking the components again would rebuild
             * that calculation and eventually disagree with it.
             *
             * The same divisor as the salary above, deliberately: a day is one
             * thing, and cutting the two halves of a day's pay differently is
             * a figure nobody can reconcile.
             */
            $allowancePerDay = $includeAllowances
                ? (float) $line->allowances / $divisor
                : 0.0;

            $amount = round($days * ($dayRate + $allowancePerDay), 2);

            if ($amount < 0.01) {
                $skipped->push(['name' => $line->employee_name, 'reason' => 'comes to nothing']);
                continue;
            }

            $rows->push([
                'employee_id'  => $employeeId,
                'name'         => $line->employee_name,
                'pay_type'     => $employee->pay_type,
                'day_rate'     => round($dayRate, 2),
                'allowance'    => round($allowancePerDay, 2),
                'amount'       => $amount,
                'note'         => $this->note($days, $dayRate + $allowancePerDay, $includeAllowances, $divisorLabel),
            ]);
        }

        return [
            'rows'         => $rows,
            'skipped'      => $skipped,
            'total'        => round($rows->sum('amount'), 2),
            'divisorLabel' => $divisorLabel,
        ];
    }

    /**
     * Write the adjustments. Returns the same shape as the preview, so the
     * caller can report exactly who was reached and who was not.
     *
     * @param  array<int, int>  $employeeIds
     * @return array{rows: Collection, skipped: Collection, total: float, divisorLabel: string}
     */
    public function apply(
        PayrollRun $run,
        array $employeeIds,
        float $days,
        string $direction,
        string $basis,
        bool $includeAllowances,
        string $label,
        bool $affectsStatutory,
        ?int $userId,
        ?string $note = null,
    ): array {
        $result = $this->preview($run, $employeeIds, $days, $direction, $basis, $includeAllowances);

        foreach ($result['rows'] as $row) {
            PayrollRunAdjustment::create([
                'company_id'        => $run->company_id,
                'payroll_run_id'    => $run->id,
                'employee_id'       => $row['employee_id'],
                'label'             => $label,
                'amount'            => $row['amount'],
                'direction'         => $direction,
                'affects_statutory' => $affectsStatutory,
                // The working, kept on the row itself. Each of these is an
                // ordinary adjustment from here on and nothing else records
                // that it was priced as days — so "why is this RM230.76"
                // has an answer on the row rather than in somebody's memory.
                'notes'             => trim(($note ? $note . ' — ' : '') . $row['note']),
                'created_by'        => $userId,
            ]);
        }

        return $result;
    }

    /** The calendar length of the run's own period, both ends inclusive. */
    private function periodDays(PayrollRun $run): int
    {
        if (! $run->period_start || ! $run->period_end) {
            return Carbon::parse($run->period_month)->daysInMonth;
        }

        $from = Carbon::parse($run->period_start)->startOfDay();
        $to   = Carbon::parse($run->period_end)->startOfDay();

        return max(1, (int) round($from->diffInDays($to)) + 1);
    }

    /** e.g. "2 days x RM115.38/day (basic + allowances, 26-day working month)". */
    private function note(float $days, float $perDay, bool $includeAllowances, string $divisorLabel): string
    {
        return sprintf(
            '%s days x RM%s/day (%s, %s)',
            rtrim(rtrim(number_format($days, 2), '0'), '.'),
            number_format($perDay, 2),
            $includeAllowances ? 'basic + allowances' : 'basic only',
            $divisorLabel,
        );
    }
}
