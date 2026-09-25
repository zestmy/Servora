<?php

namespace App\Services\Hr;

use App\Models\AttendanceCode;
use App\Models\AttendanceRecord;
use App\Models\ClockEvent;
use App\Models\ServiceChargePeriod;
use App\Scopes\CompanyScope;
use Carbon\Carbon;

/**
 * What the attendance record says about each employee over a period, for the
 * allowances that depend on it (PayComponent::ATTENDANCE_CALCULATIONS).
 *
 *   working_days  days marked with a code that counts as a working day, or
 *                 with hours above zero (hourly staff have no code on a day
 *                 they worked). Drives a per-working-day allowance.
 *   mc_days       days marked as medical leave — AttendanceCode::isMedicalLeave(),
 *                 the same reading the service charge deduction uses.
 *   absent_days   days marked with the Absent system code.
 *   late_days     shifts whose first clock-in was late after grace, and not
 *                 waived. A waiver is a manager deciding the lateness does
 *                 not count against somebody, and it would be odd for it to
 *                 cost them an allowance while forgiving the fee.
 *   manual_late_minutes
 *                 lateness typed by hand into a service charge pool, for
 *                 outlets that do not use the clock. See below for which
 *                 pools count.
 *
 * Any of the last four forfeits an attendance allowance.
 */
class AttendanceAllowanceFacts
{
    public const EMPTY = [
        'working_days' => 0, 'mc_days' => 0, 'absent_days' => 0, 'late_days' => 0, 'manual_late_minutes' => 0,
    ];

    /**
     * @param  array<int, int>  $employeeIds
     * @return array<int, array{working_days: int, mc_days: int, absent_days: int, late_days: int, manual_late_minutes: int}>
     *         keyed by employee_id; employees with nothing recorded are absent.
     */
    public static function forEmployees(int $companyId, array $employeeIds, Carbon $from, Carbon $to): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $codes = AttendanceCode::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->get();

        $workingIds = $codes->where('counts_as_working_day', true)->pluck('id')->all();
        $mcIds      = $codes->filter(fn ($c) => $c->isMedicalLeave())->pluck('id')->all();
        $absentIds  = $codes->where('system_key', 'absent')->pluck('id')->all();

        $facts = [];
        $bump = function (int $employeeId, string $key) use (&$facts) {
            $facts[$employeeId] ??= self::EMPTY;
            $facts[$employeeId][$key]++;
        };

        // Whole days at both ends, the same as the hours and days queries in
        // CompensationSummary: a DATE column compares differently against a
        // date string under SQLite than under MySQL.
        $records = AttendanceRecord::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('work_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['employee_id', 'attendance_code_id', 'hours']);

        foreach ($records as $record) {
            $codeId = $record->attendance_code_id;

            if (in_array($codeId, $workingIds, true) || (float) $record->hours > 0) {
                $bump($record->employee_id, 'working_days');
            }
            if (in_array($codeId, $mcIds, true)) {
                $bump($record->employee_id, 'mc_days');
            }
            if (in_array($codeId, $absentIds, true)) {
                $bump($record->employee_id, 'absent_days');
            }
        }

        /*
         * Late arrivals: the FIRST counted clock-in of each shift, the same
         * rule LatePenalties applies — a repeat tap is not a second late
         * arrival, and rejecting a punch in review promotes the next one.
         * Break overruns are not lateness for this purpose.
         */
        $clockIns = ClockEvent::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->whereIn('employee_id', $employeeIds)
            ->where('type', ClockEvent::TYPE_IN)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->counted()
            ->orderBy('employee_id')
            ->orderBy('work_date')
            ->orderBy('happened_at')
            ->orderBy('id')
            ->get();

        $seen = [];
        foreach ($clockIns as $event) {
            $workDate = $event->work_date instanceof Carbon
                ? $event->work_date->toDateString()
                : (string) $event->work_date;

            $shiftKey = $event->employee_id . ':' . $workDate;
            if (isset($seen[$shiftKey])) {
                continue;
            }
            $seen[$shiftKey] = true;

            if ($event->effectiveLateMinutes() > 0 && ! $event->latenessWaived()) {
                $bump($event->employee_id, 'late_days');
            }
        }

        /*
         * Lateness noted by hand on a service charge pool.
         *
         * A pool holds one total per person for its whole period, with no
         * dates, so it cannot be split across payroll cycles. Each pool
         * belongs to the cycle its period ENDS in: a pool reaching back into
         * the previous cycle would otherwise forfeit the allowance twice for
         * one entry. Every outlet's pool counts — somebody redirected to
         * another outlet's pool is still the same person being late.
         */
        $pools = ServiceChargePeriod::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->whereNotNull('manual_late_minutes')
            ->whereBetween('period_to', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['id', 'company_id', 'manual_late_minutes']);

        $wanted = array_flip($employeeIds);
        foreach ($pools as $pool) {
            foreach (($pool->manual_late_minutes ?? []) as $employeeId => $minutes) {
                $employeeId = (int) $employeeId;
                $minutes    = max(0, (int) $minutes);

                if ($minutes > 0 && isset($wanted[$employeeId])) {
                    $facts[$employeeId] ??= self::EMPTY;
                    $facts[$employeeId]['manual_late_minutes'] += $minutes;
                }
            }
        }

        return $facts;
    }

    /**
     * Why an attendance allowance was forfeited, for the payslip line —
     * "1 MC, 2 late". Null when it was kept.
     */
    public static function forfeitReason(array $facts): ?string
    {
        $parts = array_filter([
            $facts['mc_days'] > 0     ? $facts['mc_days'] . ' MC' : null,
            $facts['absent_days'] > 0 ? $facts['absent_days'] . ' absent' : null,
            $facts['late_days'] > 0   ? $facts['late_days'] . ' late' : null,
            ($facts['manual_late_minutes'] ?? 0) > 0
                ? $facts['manual_late_minutes'] . ' min late (service charge)'
                : null,
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }
}
