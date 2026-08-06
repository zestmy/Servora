<?php

namespace App\Services\Hr;

use App\Models\ClockEvent;
use App\Models\Employee;
use App\Scopes\CompanyScope;

/**
 * What a person's next punch would be, read from what is already on record.
 *
 * Extracted from the staff app's Punch screen when the kiosk arrived, and the
 * extraction is the point rather than a tidy-up. Two screens deciding for
 * themselves whether somebody is clocked in is two screens that will
 * eventually disagree — and the way that shows up is a cook who clocked in on
 * the kiosk being offered "Clock in" again by their phone, opening a second
 * shift nobody asked for.
 *
 * Nothing here is a judgement about a punch; that all belongs to
 * ClockInService. This only answers "where in the day is this person", which
 * both the button label and the punch itself have to agree on.
 */
class PunchState
{
    /**
     * The clock-in this person has not yet clocked out of, if any.
     *
     * Looks at the last day of punches rather than at today's date. Somebody
     * finishing an overnight shift at 2am has an open punch from yesterday,
     * and a date-based check would offer to clock them in all over again.
     */
    public function openPunch(Employee $employee): ?ClockEvent
    {
        // Shift punches only. A break neither opens nor closes a shift, so
        // starting one must not make somebody look clocked out.
        $last = $this->recent($employee)
            ->whereIn('type', ClockEvent::SHIFT_TYPES)
            ->first();

        return $last?->type === ClockEvent::TYPE_IN ? $last : null;
    }

    public function nextType(Employee $employee): string
    {
        return $this->openPunch($employee) ? ClockEvent::TYPE_OUT : ClockEvent::TYPE_IN;
    }

    /**
     * The last counted punch of any kind in the past day.
     *
     * Breaks and shift punches interleave, so "what happens next" cannot be
     * read from clock-ins alone.
     */
    public function lastPunch(Employee $employee): ?ClockEvent
    {
        return $this->recent($employee)->first();
    }

    /** Whether the person is mid-break right now. */
    public function onBreak(Employee $employee): bool
    {
        return $this->lastPunch($employee)?->type === ClockEvent::TYPE_BREAK_START;
    }

    /**
     * Which break punch the break button would make, or null when breaks are
     * not available — you cannot start one before clocking in.
     */
    public function nextBreakType(Employee $employee): ?string
    {
        if ($this->onBreak($employee)) {
            return ClockEvent::TYPE_BREAK_END;
        }

        return $this->openPunch($employee) ? ClockEvent::TYPE_BREAK_START : null;
    }

    /**
     * The punch a button press means.
     *
     * The screen says which BUTTON was pressed — the shift one or the break
     * one — and this decides what that means from the record. A stale tab
     * offering "Clock in" to somebody who has already clocked in must not open
     * a second shift.
     */
    public function typeFor(Employee $employee, string $intent = 'shift'): ?string
    {
        return $intent === 'break'
            ? $this->nextBreakType($employee)
            : $this->nextType($employee);
    }

    /**
     * Whether this person punched so recently that another tap is almost
     * certainly the same tap arriving twice.
     *
     * Kiosk-shaped problem. A screen in a doorway sees the same person several
     * times in a minute, and without this the second sighting is a clock-OUT
     * thirty seconds into the shift — which then has to be found and undone by
     * somebody who was not there.
     *
     * SHIFT punches only. A clock-in genuinely can be followed by a break two
     * minutes later, and blocking that would leave somebody unable to take one.
     */
    public function recentShiftPunch(Employee $employee, int $withinMinutes): ?ClockEvent
    {
        if ($withinMinutes < 1) {
            return null;
        }

        return $this->recent($employee)
            ->whereIn('type', ClockEvent::SHIFT_TYPES)
            ->where('happened_at', '>=', now()->subMinutes($withinMinutes))
            ->first();
    }

    /**
     * This employee's punches from the past day, newest first.
     *
     * CompanyScope is dropped and company_id matched by hand: the staff app
     * and the kiosk both run with no authenticated web user, where the scope
     * resolves from the subdomain at best and matches nothing at worst.
     */
    private function recent(Employee $employee)
    {
        return ClockEvent::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->where('happened_at', '>=', now()->subDay())
            ->counted()
            ->orderByDesc('happened_at')
            ->orderByDesc('id');
    }
}
