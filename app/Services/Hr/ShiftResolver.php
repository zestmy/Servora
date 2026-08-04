<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\Roster;
use App\Models\RosterEntry;
use App\Scopes\CompanyScope;
use Carbon\Carbon;

/**
 * Works out which rostered shift a punch belongs to.
 *
 * Harder than "today's row" for two reasons. Overnight shifts mean a punch at
 * 1am usually belongs to YESTERDAY's roster line, and payroll has to agree —
 * the shift's own start date is the business day, not the calendar date the
 * clock happened to show. And someone can arrive well before or well after
 * their start, so the shift has to be found by proximity rather than by
 * assuming punctuality.
 *
 * Only APPROVED rosters are consulted. A draft roster is a plan someone is
 * still editing, and a lateness charge computed against a start time that a
 * manager then changes is indefensible.
 */
class ShiftResolver
{
    /**
     * How far back a punch may reach to claim a shift. Wide on purpose: it
     * decides only which shift the person MEANT, and being early is then
     * judged separately against the company's early window. Too narrow and
     * an eager arrival resolves to no shift at all, which loses the link to
     * the roster entirely.
     */
    private const INTENT_LOOKBACK_HOURS = 18;

    /** How long after a shift ends a clock-out still belongs to it. */
    private const CLOCK_OUT_GRACE_HOURS = 6;

    /**
     * The shift this punch belongs to, or null if none fits.
     *
     * @return array{entry: RosterEntry, start: Carbon, end: Carbon}|null
     */
    public function resolve(Employee $employee, Carbon $at, string $type): ?array
    {
        $candidates = $this->candidateEntries($employee, $at);

        $best     = null;
        $bestGap  = null;

        foreach ($candidates as $entry) {
            $window = $this->windowFor($entry);

            if (! $window) {
                continue;
            }

            [$start, $end] = $window;

            $from = $type === 'out' ? $start : $start->copy()->subHours(self::INTENT_LOOKBACK_HOURS);
            $to   = $type === 'out' ? $end->copy()->addHours(self::CLOCK_OUT_GRACE_HOURS) : $end;

            if ($at->lt($from) || $at->gt($to)) {
                continue;
            }

            // Clocking in is judged against the start, clocking out against
            // the end — otherwise a long shift's out-punch would resolve to
            // the following day's shift, whose start is nearer.
            $anchor = $type === 'out' ? $end : $start;
            $gap    = abs($anchor->diffInSeconds($at, false));

            if ($bestGap === null || $gap < $bestGap) {
                $best    = ['entry' => $entry, 'start' => $start, 'end' => $end];
                $bestGap = $gap;
            }
        }

        return $best;
    }

    /**
     * Rostered working days either side of the punch.
     *
     * A one-day margin covers both an overnight shift started yesterday and
     * a pre-midnight punch for a shift dated tomorrow.
     */
    private function candidateEntries(Employee $employee, Carbon $at)
    {
        return RosterEntry::query()
            ->whereHas('roster', fn ($q) => $q
                ->withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $employee->company_id)
                ->where('status', Roster::STATUS_APPROVED))
            ->where('employee_id', $employee->id)
            ->where('is_off_day', false)
            ->whereNotNull('shift_start')
            ->whereNotNull('shift_end')
            ->whereBetween('day_date', [
                $at->copy()->subDay()->toDateString(),
                $at->copy()->addDay()->toDateString(),
            ])
            ->get();
    }

    /**
     * Absolute start and end of an entry's shift.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public function windowFor(RosterEntry $entry): ?array
    {
        if (! $entry->day_date || ! $entry->shift_start || ! $entry->shift_end) {
            return null;
        }

        try {
            $startTime = Carbon::parse((string) $entry->shift_start);
            $endTime   = Carbon::parse((string) $entry->shift_end);
        } catch (\Throwable $e) {
            return null;
        }

        $date  = Carbon::parse($entry->day_date->format('Y-m-d'));
        $start = $date->copy()->setTime($startTime->hour, $startTime->minute, $startTime->second);
        $end   = $date->copy()->setTime($endTime->hour, $endTime->minute, $endTime->second);

        // Same convention as RosterEntry::calculateHoursWorked: an end at or
        // before the start is the next morning, not a zero-length shift.
        if ($end->lte($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }
}
