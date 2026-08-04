<?php

namespace Tests\Unit\Hr;

use App\Models\ClockEvent;
use App\Services\Hr\LatePenalties;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The "one charge per shift" rule, which is what stops a bad morning of
 * repeated taps turning into a repeated deduction.
 */
class LatePenaltiesTest extends TestCase
{
    /** An unsaved event — reduce() never touches the database. */
    private function event(int $employeeId, string $workDate, int $late, float $amount, ?int $override = null): ClockEvent
    {
        $event = new ClockEvent();

        $event->employee_id             = $employeeId;
        $event->work_date               = Carbon::parse($workDate);
        $event->type                    = ClockEvent::TYPE_IN;
        $event->chargeable_late_minutes = $late;
        $event->override_late_minutes   = $override;
        $event->penalty_amount          = $amount;

        return $event;
    }

    public function test_a_single_late_punch_is_totalled(): void
    {
        $totals = LatePenalties::reduce(new Collection([
            $this->event(1, '2026-08-04', 15, 7.50),
        ]));

        $this->assertSame(15, $totals[1]['minutes']);
        $this->assertSame(7.50, $totals[1]['amount']);
        $this->assertSame(1, $totals[1]['shifts']);
    }

    public function test_a_second_punch_on_the_same_shift_is_ignored(): void
    {
        // Somebody tapping the button twice has one late arrival, not two.
        $totals = LatePenalties::reduce(new Collection([
            $this->event(1, '2026-08-04', 15, 7.50),
            $this->event(1, '2026-08-04', 18, 9.00),
        ]));

        $this->assertSame(15, $totals[1]['minutes']);
        $this->assertSame(7.50, $totals[1]['amount']);
        $this->assertSame(1, $totals[1]['shifts']);
    }

    public function test_separate_shifts_accumulate(): void
    {
        $totals = LatePenalties::reduce(new Collection([
            $this->event(1, '2026-08-04', 15, 7.50),
            $this->event(1, '2026-08-05', 10, 5.00),
        ]));

        $this->assertSame(25, $totals[1]['minutes']);
        $this->assertSame(12.50, $totals[1]['amount']);
        $this->assertSame(2, $totals[1]['shifts']);
    }

    public function test_employees_are_kept_apart(): void
    {
        $totals = LatePenalties::reduce(new Collection([
            $this->event(1, '2026-08-04', 15, 7.50),
            $this->event(2, '2026-08-04', 4, 2.00),
        ]));

        $this->assertSame(7.50, $totals[1]['amount']);
        $this->assertSame(2.00, $totals[2]['amount']);
    }

    public function test_a_punctual_punch_produces_no_row_at_all(): void
    {
        // Absent rather than zero: the distribution treats a missing key as
        // "nothing to deduct", and an explicit zero row would only invite a
        // "0 min late" column on a screen nobody asked for it on.
        $totals = LatePenalties::reduce(new Collection([
            $this->event(1, '2026-08-04', 0, 0.0),
        ]));

        $this->assertSame([], $totals);
    }

    public function test_a_managers_override_replaces_the_computed_minutes(): void
    {
        $totals = LatePenalties::reduce(new Collection([
            $this->event(1, '2026-08-04', 30, 5.00, override: 10),
        ]));

        $this->assertSame(10, $totals[1]['minutes']);
        $this->assertSame(5.00, $totals[1]['amount']);
    }

    public function test_an_override_of_zero_still_counts_as_a_charged_shift_when_money_remains(): void
    {
        // A manager who zeroed the minutes but left an amount is an
        // inconsistency worth surfacing, not silently dropping.
        $totals = LatePenalties::reduce(new Collection([
            $this->event(1, '2026-08-04', 30, 5.00, override: 0),
        ]));

        $this->assertSame(0, $totals[1]['minutes']);
        $this->assertSame(5.00, $totals[1]['amount']);
    }
}
