<?php

namespace Tests\Unit\Hr;

use App\Services\Hr\BreakOverrun;
use PHPUnit\Framework\TestCase;

/**
 * Break overrun decides money, so the arithmetic is pinned down here.
 *
 * The case that drove the design is two breaks under the allowance that add
 * up to more than it — judged individually, neither is over.
 */
class BreakOverrunTest extends TestCase
{
    public function test_a_break_inside_the_allowance_costs_nothing(): void
    {
        $result = BreakOverrun::compute(45, 60);

        $this->assertSame(45, $result['taken']);
        $this->assertSame(0, $result['overrun']);
        $this->assertSame(0, $result['chargeable']);
    }

    public function test_exactly_the_allowance_is_not_an_overrun(): void
    {
        $this->assertSame(0, BreakOverrun::compute(60, 60)['overrun']);
    }

    public function test_the_allowance_applies_to_the_shift_not_each_break(): void
    {
        // Two forty-minute breaks against an hour: neither exceeds the
        // allowance alone, but the shift is twenty minutes over.
        $result = BreakOverrun::compute(80, 60);

        $this->assertSame(20, $result['overrun']);
    }

    public function test_a_later_break_only_charges_what_it_adds(): void
    {
        // First break took 70 of 60 and was charged 10. The second brings the
        // shift to 95, so 35 over in total — but only 25 is new.
        $result = BreakOverrun::compute(95, 60, alreadyCharged: 10);

        $this->assertSame(35, $result['overrun']);
        $this->assertSame(25, $result['chargeable']);
    }

    public function test_charges_across_a_shift_sum_to_the_overrun_exactly_once(): void
    {
        $first  = BreakOverrun::compute(70, 60);
        $second = BreakOverrun::compute(95, 60, alreadyCharged: $first['chargeable']);

        $this->assertSame(35, $first['chargeable'] + $second['chargeable']);
        $this->assertSame($second['overrun'], $first['chargeable'] + $second['chargeable']);
    }

    public function test_a_reduced_earlier_charge_never_bills_backwards(): void
    {
        // A manager cut the first charge to zero. The next break must not
        // quietly reclaim the difference.
        $result = BreakOverrun::compute(65, 60, alreadyCharged: 99);

        $this->assertSame(0, $result['chargeable']);
    }

    public function test_a_zero_allowance_charges_the_whole_break(): void
    {
        $this->assertSame(30, BreakOverrun::compute(30, 0)['overrun']);
    }

    public function test_the_amount_uses_the_same_rate_as_lateness(): void
    {
        $this->assertSame(10.0, BreakOverrun::amount(20, 0.50));
    }

    public function test_a_zero_rate_records_the_overrun_without_charging(): void
    {
        $this->assertSame(0.0, BreakOverrun::amount(20, 0.0));
    }

    public function test_the_shift_cap_is_shared_with_lateness_already_charged(): void
    {
        // Cap RM20 a shift, RM15 already taken for arriving late. A 20-minute
        // overrun at RM1 would be RM20 on its own; only RM5 of the cap is left.
        $this->assertSame(5.0, BreakOverrun::amount(20, 1.00, capPerShift: 20.00, alreadyChargedAmount: 15.00));
    }

    public function test_a_cap_already_reached_charges_nothing_further(): void
    {
        $this->assertSame(0.0, BreakOverrun::amount(30, 1.00, capPerShift: 20.00, alreadyChargedAmount: 20.00));
    }

    public function test_no_chargeable_minutes_means_no_money(): void
    {
        $this->assertSame(0.0, BreakOverrun::amount(0, 5.00));
    }
}
