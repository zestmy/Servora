<?php

namespace App\Services\Hr;

use App\Models\ClockEvent;
use App\Scopes\CompanyScope;
use Carbon\Carbon;

/**
 * Totals the lateness charge each employee has accrued over a period, for
 * the service charge distribution to deduct.
 *
 * One shift can only be charged once. Somebody who taps clock-in three times
 * on a bad morning has one late arrival, not three, so this reduces to the
 * FIRST punch that still counts on each work date and ignores the rest. That
 * rule lives here rather than at write time on purpose: rejecting a punch in
 * review promotes the next one, and the total has to follow.
 */
class LatePenalties
{
    /**
     * @return array<int, array{minutes: int, amount: float, shifts: int}>
     *         keyed by employee_id; employees with nothing to charge are absent.
     */
    public static function forPeriod(int $companyId, ?int $outletId, Carbon $from, Carbon $to): array
    {
        $query = ClockEvent::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            // Late arrivals and break overruns are one shift's charges.
            ->whereIn('type', [ClockEvent::TYPE_IN, ClockEvent::TYPE_BREAK_END])
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->counted()
            // The ordering IS the "first punch wins" rule — reduce() trusts it
            // rather than re-sorting, so the two can never disagree.
            ->orderBy('employee_id')
            ->orderBy('work_date')
            ->orderBy('happened_at')
            ->orderBy('id');

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        return self::reduce($query->get());
    }

    /**
     * The same totals, for a named set of employees regardless of outlet.
     *
     * What the service charge actually needs. A pool pays the people on its
     * list, and since service_charge_outlet_id can put somebody on KLCC's list
     * while they clock in at IOI every day, asking for "KLCC's punches" would
     * search the wrong outlet and come back empty — handing that person a full
     * pool share with their lateness charge silently dropped, which is money.
     *
     * forPeriod() is kept for callers that genuinely mean an outlet's punches.
     *
     * @param  array<int, int>  $employeeIds
     * @return array<int, array{minutes: int, amount: float, shifts: int}>
     */
    public static function forEmployees(int $companyId, array $employeeIds, Carbon $from, Carbon $to): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $query = ClockEvent::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('type', [ClockEvent::TYPE_IN, ClockEvent::TYPE_BREAK_END])
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->counted()
            // Same ordering as forPeriod(): it IS the "first punch wins" rule.
            ->orderBy('employee_id')
            ->orderBy('work_date')
            ->orderBy('happened_at')
            ->orderBy('id');

        return self::reduce($query->get());
    }

    /**
     * Fold clock-in events, oldest first per shift, into per-employee totals.
     *
     * Separated from the query so the rule can be exercised directly.
     *
     * Only the FIRST clock-in on a shift counts — a repeat tap is one late
     * arrival, not two. Every break_end counts, because each carries only the
     * overrun minutes it added rather than the shift's running total, so
     * summing them gives the shift's overrun exactly once.
     *
     * @param  iterable<ClockEvent>  $events  ordered by employee, work date,
     *                                        then time, so the first clock-in
     *                                        seen for a shift is the earliest.
     * @return array<int, array{minutes: int, amount: float, shifts: int}>
     */
    public static function reduce(iterable $events): array
    {
        $totals = [];
        $seen   = [];

        foreach ($events as $event) {
            $workDate = $event->work_date instanceof Carbon
                ? $event->work_date->toDateString()
                : (string) $event->work_date;

            $shiftKey = $event->employee_id . ':' . $workDate;

            if ($event->type === ClockEvent::TYPE_IN) {
                if (isset($seen[$shiftKey])) {
                    continue;
                }

                $seen[$shiftKey] = true;
            }

            $minutes = $event->effectiveLateMinutes();
            $amount  = (float) $event->penalty_amount;

            if ($minutes <= 0 && $amount <= 0) {
                continue;
            }

            $row = $totals[$event->employee_id] ?? ['minutes' => 0, 'amount' => 0.0, 'shifts' => 0];

            $totals[$event->employee_id] = [
                'minutes' => $row['minutes'] + $minutes,
                'amount'  => $row['amount'] + $amount,
                'shifts'  => $row['shifts'] + 1,
            ];
        }

        return $totals;
    }
}
