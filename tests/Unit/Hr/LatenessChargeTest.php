<?php

namespace Tests\Unit\Hr;

use App\Services\Hr\LatenessCharge;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * The lateness formula decides how much of someone's service charge is taken
 * away, so it is pinned down here rather than trusted to read correctly.
 */
class LatenessChargeTest extends TestCase
{
    private function start(): Carbon
    {
        return Carbon::parse('2026-08-04 09:00:00');
    }

    public function test_arriving_early_costs_nothing(): void
    {
        $result = LatenessCharge::compute(
            $this->start(), Carbon::parse('2026-08-04 08:45:00'), 5, 0.50
        );

        $this->assertSame(0, $result['minutes']);
        $this->assertSame(0, $result['chargeable']);
        $this->assertSame(0.0, $result['amount']);
    }

    public function test_arriving_exactly_on_time_costs_nothing(): void
    {
        $result = LatenessCharge::compute(
            $this->start(), $this->start(), 5, 0.50
        );

        $this->assertSame(0, $result['minutes']);
        $this->assertSame(0.0, $result['amount']);
    }

    public function test_lateness_inside_the_grace_is_recorded_but_not_charged(): void
    {
        $result = LatenessCharge::compute(
            $this->start(), Carbon::parse('2026-08-04 09:05:00'), 5, 0.50
        );

        // Recorded, so a pattern of near-misses is still visible to a manager.
        $this->assertSame(5, $result['minutes']);
        $this->assertSame(0, $result['chargeable']);
        $this->assertSame(0.0, $result['amount']);
    }

    public function test_only_the_minutes_past_grace_are_charged(): void
    {
        $result = LatenessCharge::compute(
            $this->start(), Carbon::parse('2026-08-04 09:20:00'), 5, 0.50
        );

        $this->assertSame(20, $result['minutes']);
        $this->assertSame(15, $result['chargeable']);
        $this->assertSame(7.5, $result['amount']);
    }

    public function test_part_minutes_are_floored_not_rounded_up(): void
    {
        // 09:20:59 is twenty minutes late, not twenty-one. Rounding up would
        // charge for a second.
        $result = LatenessCharge::compute(
            $this->start(), Carbon::parse('2026-08-04 09:20:59'), 0, 1.00
        );

        $this->assertSame(20, $result['minutes']);
        $this->assertSame(20.0, $result['amount']);
    }

    public function test_the_per_shift_cap_is_applied(): void
    {
        $result = LatenessCharge::compute(
            $this->start(), Carbon::parse('2026-08-04 12:00:00'), 5, 0.50, 20.00
        );

        $this->assertSame(180, $result['minutes']);
        $this->assertSame(175, $result['chargeable']);
        // 175 x 0.50 = 87.50, capped at 20.
        $this->assertSame(20.0, $result['amount']);
    }

    public function test_a_zero_rate_tracks_lateness_without_charging(): void
    {
        $result = LatenessCharge::compute(
            $this->start(), Carbon::parse('2026-08-04 10:00:00'), 5, 0.0
        );

        $this->assertSame(60, $result['minutes']);
        $this->assertSame(55, $result['chargeable']);
        $this->assertSame(0.0, $result['amount']);
    }

    public function test_an_overnight_shift_start_is_handled_as_an_absolute_time(): void
    {
        // 22:00 start, clocked in at 22:12 — twelve minutes, not the 708 that
        // a time-of-day comparison against midnight would produce.
        $result = LatenessCharge::compute(
            Carbon::parse('2026-08-04 22:00:00'),
            Carbon::parse('2026-08-04 22:12:00'),
            5, 1.00
        );

        $this->assertSame(12, $result['minutes']);
        $this->assertSame(7.0, $result['amount']);
    }

    public function test_a_negative_grace_cannot_manufacture_extra_late_minutes(): void
    {
        $result = LatenessCharge::compute(
            $this->start(), Carbon::parse('2026-08-04 09:10:00'), -30, 1.00
        );

        $this->assertSame(10, $result['chargeable']);
    }
}
