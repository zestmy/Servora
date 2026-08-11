<?php

namespace Tests\Unit\Hr;

use App\Models\AttendanceCode;
use App\Models\Employee;
use App\Models\ServiceChargePeriod;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * How the lateness charge lands in the service charge split.
 *
 * All unsaved models — distribute() is arithmetic over collections it is
 * handed, so this exercises the real method without a database.
 */
class ServiceChargeLatePenaltyTest extends TestCase
{
    private function employee(int $id, float $points): Employee
    {
        $employee = new Employee();
        $employee->id = $id;
        $employee->service_points_entitlement = $points;

        return $employee;
    }

    private function pool(float $amount): ServiceChargePeriod
    {
        $row = new ServiceChargePeriod();
        $row->amount      = $amount;
        $row->mc_percent  = 5;
        $row->abs_percent = 10;

        return $row;
    }

    private function codes(): Collection
    {
        $absent = new AttendanceCode();
        $absent->id         = 1;
        $absent->code       = 'ABS';
        $absent->label      = 'Absent';
        $absent->system_key = 'absent';

        return new Collection([$absent]);
    }

    public function test_no_penalties_leaves_the_split_exactly_as_before(): void
    {
        $result = ServiceChargePeriod::distribute(
            $this->pool(1000), null, new Collection([$this->employee(1, 10.0)]),
            $this->codes(), [], 5.0, 10.0, 10.0
        );

        // 1000 / 10 points = RM100 a point, 10 points = RM1000.
        $this->assertSame(1000.0, $result['rows'][0]['gross']);
        $this->assertSame(1000.0, $result['rows'][0]['net']);
        $this->assertSame(0.0, $result['rows'][0]['lateAmt']);
        $this->assertFalse($result['hasLate']);
    }

    public function test_a_penalty_is_subtracted_from_the_net(): void
    {
        $result = ServiceChargePeriod::distribute(
            $this->pool(1000), null, new Collection([$this->employee(1, 10.0)]),
            $this->codes(), [], 5.0, 10.0, 10.0,
            [1 => ['minutes' => 30, 'amount' => 15.0, 'shifts' => 2]]
        );

        $this->assertSame(1000.0, $result['rows'][0]['gross']);
        $this->assertSame(15.0, $result['rows'][0]['lateAmt']);
        $this->assertSame(30, $result['rows'][0]['lateMins']);
        $this->assertSame(985.0, $result['rows'][0]['net']);
        $this->assertTrue($result['hasLate']);
    }

    public function test_the_penalty_comes_off_after_the_day_based_deduction(): void
    {
        // One absent day at 10% of a RM1000 gross is RM100, leaving RM900,
        // and the RM15 lateness charge comes off that.
        $result = ServiceChargePeriod::distribute(
            $this->pool(1000), null, new Collection([$this->employee(1, 10.0)]),
            $this->codes(), ['1:2026-08-04' => 1], 5.0, 10.0, 10.0,
            [1 => ['minutes' => 30, 'amount' => 15.0, 'shifts' => 1]]
        );

        $this->assertSame(100.0, $result['rows'][0]['dedAmt']);
        $this->assertSame(15.0, $result['rows'][0]['lateAmt']);
        $this->assertSame(885.0, $result['rows'][0]['net']);
    }

    public function test_a_penalty_can_never_push_a_share_below_zero(): void
    {
        // A service charge share is a slice of a pool, not a debt. However
        // late somebody was, they cannot end up owing money back.
        //
        // Pool 100 over 10 points is RM10 a point, so this gross is RM100 —
        // and a RM9,999 charge is clipped to exactly that, not carried over.
        $result = ServiceChargePeriod::distribute(
            $this->pool(100), null, new Collection([$this->employee(1, 10.0)]),
            $this->codes(), [], 5.0, 10.0, 10.0,
            [1 => ['minutes' => 5000, 'amount' => 9999.0, 'shifts' => 20]]
        );

        $this->assertSame(100.0, $result['rows'][0]['gross']);
        $this->assertSame(100.0, $result['rows'][0]['lateAmt']);
        $this->assertSame(0.0, $result['rows'][0]['net']);
        $this->assertSame(0.0, $result['totals']['net']);
    }

    public function test_totals_add_up_across_employees(): void
    {
        $result = ServiceChargePeriod::distribute(
            $this->pool(1000), null,
            new Collection([$this->employee(1, 5.0), $this->employee(2, 5.0)]),
            $this->codes(), [], 5.0, 10.0, 10.0,
            [
                1 => ['minutes' => 10, 'amount' => 5.0,  'shifts' => 1],
                2 => ['minutes' => 20, 'amount' => 10.0, 'shifts' => 1],
            ]
        );

        $this->assertSame(1000.0, $result['totals']['gross']);
        $this->assertSame(15.0, $result['totals']['lateAmt']);
        $this->assertSame(30, $result['totals']['lateMins']);
        $this->assertSame(985.0, $result['totals']['net']);
    }

    public function test_an_employee_with_no_penalty_row_is_untouched(): void
    {
        $result = ServiceChargePeriod::distribute(
            $this->pool(1000), null,
            new Collection([$this->employee(1, 5.0), $this->employee(2, 5.0)]),
            $this->codes(), [], 5.0, 10.0, 10.0,
            [2 => ['minutes' => 20, 'amount' => 10.0, 'shifts' => 1]]
        );

        $this->assertSame(500.0, $result['rows'][0]['net']);
        $this->assertSame(490.0, $result['rows'][1]['net']);
    }
}
