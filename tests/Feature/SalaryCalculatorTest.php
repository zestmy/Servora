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
        $f = $this->figuresFor(['salary' => 3050]);

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

        $below = $this->figuresFor(['salary' => $d['epf_wage_threshold']]);
        $above = $this->figuresFor(['salary' => $d['epf_wage_threshold'] + 1000]);

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

        $atCap  = $this->figuresFor(['salary' => $d['socso_ceiling']]);
        $farOver = $this->figuresFor(['salary' => $d['socso_ceiling'] * 5]);

        $this->assertEqualsWithDelta($atCap['socso_employee'], $farOver['socso_employee'], 0.001);
        $this->assertEqualsWithDelta($atCap['eis_employee'], $farOver['eis_employee'], 0.001);
    }

    public function test_over_sixties_pay_no_socso_or_eis_and_a_different_epf_rate(): void
    {
        $senior = $this->figuresFor(['salary' => 4000, 'isSenior' => true]);

        $this->assertEqualsWithDelta(0, $senior['socso_employee'], 0.001);
        $this->assertEqualsWithDelta(0, $senior['eis_employee'], 0.001);
        $this->assertEqualsWithDelta(0, $senior['epf_employee'], 0.001, 'The senior employee rate is 0%.');
        $this->assertGreaterThan(0, $senior['socso_employer'], 'The employer still contributes.');
    }

    public function test_a_non_citizen_uses_the_foreign_epf_rate(): void
    {
        $d = StatutorySetting::defaults();
        $f = $this->figuresFor(['salary' => 3000, 'isMalaysian' => false]);

        $this->assertEqualsWithDelta(ceil(3000 * $d['epf_employee_rate_foreign'] / 100), $f['epf_employee'], 0.001);
    }

    /** A salary under the reliefs owes nothing — the bands start above them. */
    public function test_a_low_salary_pays_no_pcb(): void
    {
        $this->assertEqualsWithDelta(0, $this->figuresFor(['salary' => 1800])['pcb'], 0.001);
    }

    public function test_reliefs_reduce_the_estimated_pcb(): void
    {
        $single  = $this->figuresFor(['salary' => 9000]);
        $family  = $this->figuresFor(['salary' => 9000, 'category' => 'spouse_not_working', 'children' => 3]);

        $this->assertGreaterThan(0, $single['pcb']);
        $this->assertLessThan($single['pcb'], $family['pcb'], 'Spouse and child reliefs must lower it.');
    }

    /** The number an owner budgets on, and the one a negotiation forgets. */
    public function test_the_employer_cost_is_gross_plus_the_employer_share(): void
    {
        $f = $this->figuresFor(['salary' => 4000]);

        $this->assertEqualsWithDelta(
            $f['gross'] + $f['epf_employer'] + $f['socso_employer'] + $f['eis_employer'],
            $f['employer_cost'],
            0.01
        );
    }

    public function test_take_home_is_gross_less_every_employee_deduction(): void
    {
        $f = $this->figuresFor(['salary' => 7500, 'children' => 1]);

        $this->assertEqualsWithDelta(
            $f['gross'] - ($f['epf_employee'] + $f['socso_employee'] + $f['eis_employee'] + $f['pcb']),
            $f['net'],
            0.01
        );
    }

    /** People plan around this. It must not pretend to be a payslip. */
    public function test_the_page_says_the_pcb_is_an_estimate(): void
    {
        Livewire::test(SalaryCalculator::class)
            ->set('salary', 5000)
            ->assertSee('PCB here is an estimate')
            ->assertSee('check with LHDN before you file');
    }
}
