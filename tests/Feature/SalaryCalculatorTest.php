<?php

namespace Tests\Feature;

use App\Livewire\Marketing\SalaryCalculator;
use App\Models\StatutorySetting;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The public salary calculator.
 *
 * People plan around these numbers, so the tests are worked examples against
 * the published rules rather than against whatever the code currently returns.
 * The rates come from StatutorySetting's own defaults — the same constants a
 * customer's payroll run starts from — so a rate change lands in one place and
 * these tests read it from there rather than repeating it.
 */
class SalaryCalculatorTest extends TestCase
{
    private function figuresFor(array $set): array
    {
        $screen = Livewire::test(SalaryCalculator::class);

        foreach ($set as $property => $value) {
            $screen->set($property, $value);
        }

        return $screen->call('figures')->effects['returns'][0];
    }

    /** KWSP rounds a contribution up to the next whole ringgit. */
    public function test_epf_is_rounded_up_to_the_next_ringgit(): void
    {
        // 11% of 3,050 is 335.50, which a payslip shows as 336.
        $f = $this->figuresFor(['basic' => 3050]);

        $this->assertEqualsWithDelta(336, $f['epf_employee'], 0.001);
    }

    /**
     * The employer's share steps down above the wage threshold — 13% at or
     * below, 12% above. Getting this backwards understates the cost of every
     * senior hire in the company.
     */
    public function test_the_employer_epf_rate_steps_down_above_the_threshold(): void
    {
        $d = StatutorySetting::defaults();

        $below = $this->figuresFor(['basic' => $d['epf_wage_threshold']]);
        $above = $this->figuresFor(['basic' => $d['epf_wage_threshold'] + 1000]);

        $this->assertEqualsWithDelta(
            ceil($d['epf_wage_threshold'] * $d['epf_employer_rate_low'] / 100),
            $below['epf_employer'],
            0.001
        );
        $this->assertEqualsWithDelta(
            ceil(($d['epf_wage_threshold'] + 1000) * $d['epf_employer_rate_high'] / 100),
            $above['epf_employer'],
            0.001
        );
    }

    /** SOCSO and EIS are both capped, so a big salary does not scale them. */
    public function test_socso_and_eis_stop_at_their_ceilings(): void
    {
        $d = StatutorySetting::defaults();

        $atCap  = $this->figuresFor(['basic' => $d['socso_ceiling']]);
        $farOver = $this->figuresFor(['basic' => $d['socso_ceiling'] * 5]);

        $this->assertEqualsWithDelta($atCap['socso_employee'], $farOver['socso_employee'], 0.001);
        $this->assertEqualsWithDelta($atCap['eis_employee'], $farOver['eis_employee'], 0.001);
    }

    public function test_over_sixties_pay_no_socso_or_eis_and_a_different_epf_rate(): void
    {
        $senior = $this->figuresFor(['basic' => 4000, 'isSenior' => true]);

        $this->assertEqualsWithDelta(0, $senior['socso_employee'], 0.001);
        $this->assertEqualsWithDelta(0, $senior['eis_employee'], 0.001);
        $this->assertEqualsWithDelta(0, $senior['epf_employee'], 0.001, 'The senior employee rate is 0%.');
        $this->assertGreaterThan(0, $senior['socso_employer'], 'The employer still contributes.');
    }

    public function test_a_non_citizen_uses_the_foreign_epf_rate(): void
    {
        $d = StatutorySetting::defaults();
        $f = $this->figuresFor(['basic' => 3000, 'isMalaysian' => false]);

        $this->assertEqualsWithDelta(ceil(3000 * $d['epf_employee_rate_foreign'] / 100), $f['epf_employee'], 0.001);
    }

    /** A salary under the reliefs owes nothing — the bands start above them. */
    public function test_a_low_salary_pays_no_pcb(): void
    {
        $this->assertEqualsWithDelta(0, $this->figuresFor(['basic' => 1800])['pcb'], 0.001);
    }

    public function test_reliefs_reduce_the_estimated_pcb(): void
    {
        $single  = $this->figuresFor(['basic' => 9000]);
        $family  = $this->figuresFor(['basic' => 9000, 'category' => 'spouse_not_working', 'children' => 3]);

        $this->assertGreaterThan(0, $single['pcb']);
        $this->assertLessThan($single['pcb'], $family['pcb'], 'Spouse and child reliefs must lower it.');
    }

    /** The number an owner budgets on, and the one a negotiation forgets. */
    public function test_the_employer_cost_is_gross_plus_the_employer_share(): void
    {
        $f = $this->figuresFor(['basic' => 4000]);

        $this->assertEqualsWithDelta(
            $f['gross'] + $f['epf_employer'] + $f['socso_employer'] + $f['eis_employer'],
            $f['employer_cost'],
            0.01
        );
    }

    public function test_take_home_is_gross_less_every_employee_deduction(): void
    {
        $f = $this->figuresFor(['basic' => 7500, 'children' => 1]);

        $this->assertEqualsWithDelta(
            $f['gross'] - ($f['epf_employee'] + $f['socso_employee'] + $f['eis_employee'] + $f['pcb']),
            $f['net'],
            0.01
        );
    }

    /**
     * Service charge is taxable income but is NOT wages for any of the three
     * funds — a named exclusion. In an F&B payroll it is often the largest line
     * after basic, so treating it as wages is not a rounding error.
     */
    public function test_a_service_charge_moves_the_tax_and_nothing_else(): void
    {
        $without = $this->figuresFor(['basic' => 4000]);
        $with    = $this->figuresFor(['basic' => 4000, 'serviceCharge' => 800]);

        $this->assertEqualsWithDelta($without['epf_employee'], $with['epf_employee'], 0.001);
        $this->assertEqualsWithDelta($without['socso_employee'], $with['socso_employee'], 0.001);
        $this->assertEqualsWithDelta($without['eis_employee'], $with['eis_employee'], 0.001);

        $this->assertEqualsWithDelta(4800, $with['taxable_wage'], 0.01);
        $this->assertEqualsWithDelta(4000, $with['epf_wage'], 0.01);
        $this->assertGreaterThan($without['pcb'], $with['pcb']);
    }

    /**
     * Overtime counts for SOCSO and EIS but never for EPF — the mistake that
     * quietly overstates EPF for everyone who worked a busy month.
     */
    public function test_overtime_moves_socso_but_never_epf(): void
    {
        $without = $this->figuresFor(['basic' => 3000]);
        $with    = $this->figuresFor(['basic' => 3000, 'overtime' => 600]);

        $this->assertEqualsWithDelta($without['epf_employee'], $with['epf_employee'], 0.001);
        $this->assertEqualsWithDelta(3000, $with['epf_wage'], 0.01);
        $this->assertEqualsWithDelta(3600, $with['socso_wage'], 0.01);
        $this->assertGreaterThan($without['socso_employee'], $with['socso_employee']);
    }

    /**
     * The ordinary rate of pay, per the Employment Act: monthly wages over 26
     * days, then over the normal hours in a day.
     */
    public function test_overtime_hours_are_priced_at_the_statutory_multipliers(): void
    {
        // RM 2,600 / 26 days / 8 hours = RM 12.50 an hour.
        $f = $this->figuresFor([
            'basic'   => 2600,
            'otHours' => ['normal' => 4, 'rest_day' => 0, 'public_holiday' => 0],
        ]);

        $this->assertEqualsWithDelta(12.50, $f['hourly_rate'], 0.01);
        // 4 hours at 1.5x = RM 75.00
        $this->assertEqualsWithDelta(75.00, $f['overtime'], 0.01);
    }

    public function test_rest_day_and_public_holiday_carry_higher_multipliers(): void
    {
        $f = $this->figuresFor([
            'basic'   => 2600,
            'otHours' => ['normal' => 0, 'rest_day' => 2, 'public_holiday' => 2],
        ]);

        // 2 x 2.0 x 12.50 = 50.00, plus 2 x 3.0 x 12.50 = 75.00
        $this->assertEqualsWithDelta(125.00, $f['overtime'], 0.01);
    }

    /**
     * The overtime rate is basic salary alone — NOT the EPF/SOCSO wage base.
     *
     * This shipped the other way and was corrected on 2026-08-11: allowances
     * are paid for being there, not for the hours, so folding them into the
     * hourly rate inflates every overtime hour. The two bases genuinely differ,
     * which is what this pins.
     */
    public function test_allowances_do_not_change_the_overtime_hourly_rate(): void
    {
        $bare = $this->figuresFor(['basic' => 2600, 'otHours' => ['normal' => 1]]);

        foreach (['fixed', 'variable', 'reimbursement'] as $type) {
            $with = $this->figuresFor([
                'basic' => 2600,
                'allowances' => [['label' => 'Transport', 'amount' => 800, 'type' => $type]],
                'otHours' => ['normal' => 1],
            ]);

            $this->assertEqualsWithDelta(
                $bare['hourly_rate'],
                $with['hourly_rate'],
                0.01,
                'A ' . $type . ' allowance must not move the overtime rate.'
            );
        }

        // Still on the EPF base, where a fixed allowance does belong.
        $epfBase = $this->figuresFor([
            'basic' => 2600,
            'allowances' => [['label' => 'Transport', 'amount' => 800, 'type' => 'fixed']],
        ]);

        $this->assertEqualsWithDelta(3400, $epfBase['epf_wage'], 0.01);
    }

    /**
     * A day not worked is a day not earned, and EPF, SOCSO, EIS and PCB all
     * follow the wages actually payable rather than the contractual figure.
     */
    public function test_unpaid_leave_comes_off_basic_and_lowers_every_contribution(): void
    {
        $full  = $this->figuresFor(['basic' => 2600]);
        $short = $this->figuresFor(['basic' => 2600, 'unpaidLeaveDays' => 2]);

        // RM 2,600 over 26 days is RM 100 a day.
        $this->assertEqualsWithDelta(100.00, $short['daily_rate'], 0.01);
        $this->assertEqualsWithDelta(200.00, $short['unpaid_leave'], 0.01);
        $this->assertEqualsWithDelta(2400.00, $short['paid_basic'], 0.01);
        $this->assertEqualsWithDelta(2400.00, $short['epf_wage'], 0.01);

        $this->assertLessThan($full['epf_employee'], $short['epf_employee']);
        $this->assertLessThan($full['socso_employee'], $short['socso_employee']);
        $this->assertLessThan($full['net'], $short['net']);
    }

    /** The contractual rate is what an hour is worth, whatever was drawn. */
    public function test_unpaid_leave_does_not_change_the_overtime_rate(): void
    {
        $full  = $this->figuresFor(['basic' => 2600, 'otHours' => ['normal' => 4]]);
        $short = $this->figuresFor(['basic' => 2600, 'unpaidLeaveDays' => 3, 'otHours' => ['normal' => 4]]);

        $this->assertEqualsWithDelta($full['hourly_rate'], $short['hourly_rate'], 0.01);
        $this->assertEqualsWithDelta($full['overtime'], $short['overtime'], 0.01);
    }

    /** More unpaid days than the month holds must not create negative pay. */
    public function test_unpaid_leave_cannot_take_more_than_the_basic(): void
    {
        $f = $this->figuresFor(['basic' => 2600, 'unpaidLeaveDays' => 40]);

        $this->assertEqualsWithDelta(2600, $f['unpaid_leave'], 0.01);
        $this->assertEqualsWithDelta(0, $f['paid_basic'], 0.01);
    }

    /** Overtime from hours is still overtime: SOCSO and EIS, never EPF. */
    public function test_overtime_from_hours_stays_out_of_the_epf_base(): void
    {
        $f = $this->figuresFor(['basic' => 2600, 'otHours' => ['normal' => 8]]);

        $this->assertEqualsWithDelta(2600, $f['epf_wage'], 0.01);
        $this->assertGreaterThan(2600, $f['socso_wage']);
    }

    public function test_a_lump_sum_is_added_to_the_hours_rather_than_replacing_them(): void
    {
        $f = $this->figuresFor([
            'basic'    => 2600,
            'otHours'  => ['normal' => 4],
            'overtime' => 100,
        ]);

        $this->assertEqualsWithDelta(175.00, $f['overtime'], 0.01);
    }

    public function test_a_fixed_allowance_counts_for_everything(): void
    {
        $f = $this->figuresFor([
            'basic' => 3000,
            'allowances' => [['label' => 'Transport', 'amount' => 500, 'type' => 'fixed']],
        ]);

        $this->assertEqualsWithDelta(3500, $f['epf_wage'], 0.01);
        $this->assertEqualsWithDelta(3500, $f['socso_wage'], 0.01);
        $this->assertEqualsWithDelta(3500, $f['taxable_wage'], 0.01);
    }

    public function test_a_variable_allowance_is_taxed_but_is_not_statutory_wages(): void
    {
        $f = $this->figuresFor([
            'basic' => 3000,
            'allowances' => [['label' => 'Incentive', 'amount' => 500, 'type' => 'variable']],
        ]);

        $this->assertEqualsWithDelta(3000, $f['epf_wage'], 0.01);
        $this->assertEqualsWithDelta(3000, $f['socso_wage'], 0.01);
        $this->assertEqualsWithDelta(3500, $f['taxable_wage'], 0.01);
    }

    /** Money back for something already spent is neither wages nor income. */
    public function test_a_reimbursement_counts_for_nothing_but_still_reaches_the_pocket(): void
    {
        $f = $this->figuresFor([
            'basic' => 3000,
            'allowances' => [['label' => 'Petrol claim', 'amount' => 200, 'type' => 'reimbursement']],
        ]);

        $this->assertEqualsWithDelta(3000, $f['epf_wage'], 0.01);
        $this->assertEqualsWithDelta(3000, $f['taxable_wage'], 0.01);
        $this->assertEqualsWithDelta(3200, $f['gross'], 0.01, 'It is still paid out, so take-home includes it.');
    }

    /** People plan around this. It must not pretend to be a payslip. */
    public function test_the_page_says_the_pcb_is_an_estimate(): void
    {
        Livewire::test(SalaryCalculator::class)
            ->set('basic', 5000)
            ->assertSee('PCB here is an estimate')
            ->assertSee('check with LHDN before you file');
    }
}
