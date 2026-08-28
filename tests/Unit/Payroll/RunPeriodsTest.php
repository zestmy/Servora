<?php

namespace Tests\Unit\Payroll;

use App\Services\Payroll\RunPeriods;
use Carbon\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The arithmetic of "which dates does this input use", on its own.
 *
 * No database: RunPeriods is a resolution rule over date pairs, and the rule
 * is what has to be right. The half that touches a run is covered by
 * PayrollRunComponentPeriodsTest.
 *
 * THE RULE: null inherits. An ordinary run gives every input the run's own
 * period, which is what every run written before this existed does and what
 * every ordinary run written after it will do.
 */
class RunPeriodsTest extends TestCase
{
    private function master(): RunPeriods
    {
        return new RunPeriods(Carbon::parse('2026-07-26'), Carbon::parse('2026-08-25'));
    }

    // ── Inheritance ───────────────────────────────────────────────────────

    public function test_every_input_inherits_the_run_period_by_default(): void
    {
        $periods = $this->master();

        foreach (RunPeriods::COMPONENTS as $component) {
            [$from, $to] = $periods->for($component);

            $this->assertSame('2026-07-26', $from->toDateString(), $component);
            $this->assertSame('2026-08-25', $to->toDateString(), $component);
            $this->assertFalse($periods->isCustom($component), $component);
        }

        $this->assertFalse($periods->hasAny());
        $this->assertSame([], $periods->customComponents());
    }

    public function test_an_inherited_input_stores_null_rather_than_a_copy(): void
    {
        // A copy would work until somebody edited the run's period and left
        // three stale pairs behind claiming to be what it covered.
        $this->assertSame([
            'attendance_from' => null, 'attendance_to' => null,
            'overtime_from' => null, 'overtime_to' => null,
            'service_charge_from' => null, 'service_charge_to' => null,
        ], $this->master()->columns());
    }

    // ── Overrides ─────────────────────────────────────────────────────────

    public function test_one_input_moves_without_touching_the_others(): void
    {
        $periods = new RunPeriods(
            Carbon::parse('2026-07-26'),
            Carbon::parse('2026-08-25'),
            [RunPeriods::SERVICE_CHARGE => [Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31')]],
        );

        $this->assertSame('2026-08-01', $periods->serviceCharge()[0]->toDateString());
        $this->assertSame('2026-08-31', $periods->serviceCharge()[1]->toDateString());

        // The other two, and the master, are exactly where they were.
        $this->assertSame('2026-07-26', $periods->overtime()[0]->toDateString());
        $this->assertSame('2026-07-26', $periods->attendance()[0]->toDateString());
        $this->assertSame('2026-07-26', $periods->master()[0]->toDateString());

        $this->assertTrue($periods->isCustom(RunPeriods::SERVICE_CHARGE));
        $this->assertFalse($periods->isCustom(RunPeriods::OVERTIME));
        $this->assertSame([RunPeriods::SERVICE_CHARGE], $periods->customComponents());
    }

    /**
     * Reaching into an earlier month is the POINT — overtime approved a cycle
     * late is the case this feature exists for — so a sub-period is never
     * clamped to the run. What stops the same hours being paid twice is the
     * paid_at guard in CompensationSummary, not a clamp here.
     */
    public function test_a_sub_period_is_not_clamped_to_the_run(): void
    {
        $periods = new RunPeriods(
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31'),
            [RunPeriods::OVERTIME => [Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30')]],
        );

        $this->assertSame('2026-06-01', $periods->overtime()[0]->toDateString());
        $this->assertSame('2026-06-30', $periods->overtime()[1]->toDateString());
    }

    public function test_a_null_override_is_the_same_as_not_passing_one(): void
    {
        $periods = new RunPeriods(
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31'),
            [RunPeriods::ATTENDANCE => null],
        );

        $this->assertFalse($periods->isCustom(RunPeriods::ATTENDANCE));
        $this->assertNull($periods->columns()['attendance_from']);
    }

    // ── Day boundaries ────────────────────────────────────────────────────

    /** A range has to cover its last DAY, not stop at midnight on it. */
    public function test_ranges_span_whole_days_at_both_ends(): void
    {
        $periods = new RunPeriods(
            Carbon::parse('2026-08-01 09:30'),
            Carbon::parse('2026-08-31 09:30'),
            [RunPeriods::OVERTIME => [Carbon::parse('2026-07-01 14:00'), Carbon::parse('2026-07-31 14:00')]],
        );

        $this->assertSame('00:00:00', $periods->master()[0]->format('H:i:s'));
        $this->assertSame('23:59:59', $periods->master()[1]->format('H:i:s'));
        $this->assertSame('00:00:00', $periods->overtime()[0]->format('H:i:s'));
        $this->assertSame('23:59:59', $periods->overtime()[1]->format('H:i:s'));
    }

    public function test_the_monthly_option_is_the_calendar_month(): void
    {
        [$from, $to] = RunPeriods::monthOf(Carbon::parse('2026-02-14'));

        $this->assertSame('2026-02-01', $from->toDateString());
        $this->assertSame('2026-02-28', $to->toDateString());
    }

    // ── Refusals ──────────────────────────────────────────────────────────

    /**
     * Loud rather than quiet: a typo that returned the master would look like
     * it worked, and would be found on a payslip rather than here.
     */
    public function test_an_unknown_input_is_refused_rather_than_given_the_master(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown payroll run period 'ot'");

        $this->master()->for('ot');
    }

    public function test_a_backwards_sub_period_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Overtime must start before it ends.');

        new RunPeriods(
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31'),
            [RunPeriods::OVERTIME => [Carbon::parse('2026-08-31'), Carbon::parse('2026-08-01')]],
        );
    }

    public function test_a_backwards_run_period_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RunPeriods(Carbon::parse('2026-08-31'), Carbon::parse('2026-08-01'));
    }

    public function test_an_unknown_input_cannot_be_smuggled_in_as_an_override(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RunPeriods(
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31'),
            ['bonus' => [Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31')]],
        );
    }

    // ── Labels ────────────────────────────────────────────────────────────

    public function test_only_a_custom_input_carries_a_label(): void
    {
        $periods = new RunPeriods(
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31'),
            [RunPeriods::OVERTIME => [Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31')]],
        );

        $this->assertSame('1 Jul – 31 Jul 2026', $periods->label(RunPeriods::OVERTIME));
        $this->assertNull($periods->label(RunPeriods::ATTENDANCE),
            'An inherited input has nothing of its own to say.');
    }

    public function test_a_label_spanning_two_years_names_both(): void
    {
        $periods = new RunPeriods(
            Carbon::parse('2027-01-01'),
            Carbon::parse('2027-01-31'),
            [RunPeriods::ATTENDANCE => [Carbon::parse('2026-12-01'), Carbon::parse('2027-01-15')]],
        );

        $this->assertSame('1 Dec 2026 – 15 Jan 2027', $periods->label(RunPeriods::ATTENDANCE));
    }
}
