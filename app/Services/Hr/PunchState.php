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
     * Two kinds of caller, and the difference is how much the screen knows.
     *
     *   'shift' / 'break' — the screen pressed A BUTTON and this works out
     *      what it meant. The staff phone app does this: one primary control
     *      whose label is derived, so a stale tab offering "Clock in" to
     *      somebody already clocked in cannot open a second shift.
     *
     *   'in' / 'out' / 'break_start' / 'break_end' — THE PERSON SAID. Taken
     *      at their word, and that is the point rather than a lapse. The
     *      derived answer is a guess from a rolling day of punches, and when
     *      it guesses wrong the person standing at the door is the one who
     *      knows: a kiosk that decides you must be clocking out, because of
     *      an hours-old punch you have already been paid for, cannot be
     *      argued with. Nothing is skipped by saying so — a second clock-in
     *      on one day is still flagged `duplicate`, a clock-out with nothing
     *      open is still flagged `no_open_punch`, and both still reach a
     *      manager. The difference is that the RECORD now says what the
     *      person meant, which is the thing a manager needs in order to fix
     *      it.
     *
     * A break_end with no break open, or a break_start before clocking in,
     * still returns null: those are not a difference of opinion about the
     * day, they are punches with nothing to attach to.
     */
    public function typeFor(Employee $employee, string $intent = 'shift'): ?string
    {
        return match ($intent) {
            'break' => $this->nextBreakType($employee),

            ClockEvent::TYPE_IN, ClockEvent::TYPE_OUT => $intent,

            ClockEvent::TYPE_BREAK_START => $this->openPunch($employee) && ! $this->onBreak($employee)
                ? ClockEvent::TYPE_BREAK_START
                : null,

            ClockEvent::TYPE_BREAK_END => $this->onBreak($employee)
                ? ClockEvent::TYPE_BREAK_END
                : null,

            default => $this->nextType($employee),
        };
    }

    /**
     * Every punch this person could make right now, for a screen that offers
     * the choice rather than making it.
     *
     * `suggested` is what the record implies and is what the screen should put
     * under the thumb; `available` is false for the two that genuinely have
     * nothing to attach to, so they can be shown greyed rather than hidden —
     * a button that vanishes is a button somebody hunts for.
     *
     * @return array<int, array{type: string, label: string, available: bool, suggested: bool}>
     */
    public function options(Employee $employee): array
    {
        $open       = $this->openPunch($employee) !== null;
        $onBreak    = $this->onBreak($employee);
        $suggested  = $onBreak ? ClockEvent::TYPE_BREAK_END : $this->nextType($employee);

        $rows = [
            [ClockEvent::TYPE_IN,          'Clock IN',    true],
            [ClockEvent::TYPE_OUT,         'Clock OUT',   true],
            [ClockEvent::TYPE_BREAK_START, 'Start break', $open && ! $onBreak],
            [ClockEvent::TYPE_BREAK_END,   'End break',   $onBreak],
        ];

        return array_map(fn ($row) => [
            'type'      => $row[0],
            'label'     => $row[1],
            'available' => $row[2],
            'suggested' => $row[0] === $suggested,
        ], $rows);
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
