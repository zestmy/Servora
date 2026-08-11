<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\CompensationSetting;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Services\Hr\CompensationSummary;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * An incomplete month is paid for what was worked, not as a whole one.
 *
 * REPORTED AS: is basic right when the period is not a full cycle — 1–25 July
 * instead of 26 June–25 July — and what about somebody who joined mid-cycle?
 * It was not. `basic` never looked at the period at all, so a monthly employee
 * drew a full salary for a one-day run, a joiner on the 20th was paid the whole
 * month, and a cycle change paid two months of salary for about 1.8 months of
 * work. Every statutory figure followed it, because EPF, SOCSO, EIS and PCB all
 * start from basic.
 *
 * Employment Act 1955 s.60I:  monthly wage ÷ days of the wage period × days
 * eligible.
 *
 * THE DIVISOR IS THE WAGE PERIOD, NOT THE RUN. Dividing by the run's own
 * length is the obvious reading and is wrong exactly where this matters most:
 * a stub run of 1–25 June divided by its own 25 days pays a full month for 25
 * days, and the next run pays another — which is the double-pay this was asked
 * to fix. The numerator narrows to the run; the divisor never does.
 *
 * Daily and hourly staff are NOT pro-rated. They are already paid for what
 * they worked, and pro-rating on top would charge them for the same absence
 * twice.
 */
class IncompleteMonthProrationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Carbon $july;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Prorate Co', 'slug' => Str::slug('Prorate Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $this->july = Carbon::parse('2026-07-01');
    }

    private function cycleStartsOn(int $day): void
    {
        $s = CompensationSetting::forCompany($this->company->id);
        $s->company_id = $this->company->id;
        $s->payroll_cycle_start_day = $day;
        $s->save();
    }

    private function employee(string $type, float $salary, string $join = '2025-01-01'): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => strtoupper($type) . ' ' . $salary, 'is_active' => true,
            'join_date' => $join, 'basic_salary' => $salary, 'pay_type' => $type,
            'employment_status' => 'confirmed', 'employment_status_date' => '2025-06-01',
        ]);
    }

    /** @return array<string, mixed> */
    private function row(Employee $e, ?string $from = null, ?string $to = null): array
    {
        return app(CompensationSummary::class)->forMonth(
            Employee::query()->whereKey($e->id),
            $this->company->id,
            $this->july,
            $from ? Carbon::parse($from) : null,
            $to ? Carbon::parse($to) : null,
        )['rows']->first();
    }

    private function worked(Employee $e, int $fromDay, int $toDay, float $hours = 8): void
    {
        foreach (range($fromDay, $toDay) as $d) {
            AttendanceRecord::create([
                'company_id' => $this->company->id, 'employee_id' => $e->id,
                'outlet_id' => $this->outlet->id,
                'work_date' => sprintf('2026-07-%02d', $d), 'hours' => $hours,
            ]);
        }
    }

    // ── The regression that matters most ──────────────────────────────────

    /** An ordinary full run must be untouched. Every payroll depends on it. */
    public function test_a_full_calendar_month_still_pays_full_salary(): void
    {
        $e = $this->employee('monthly', 3000);

        $row = $this->row($e);

        $this->assertEqualsWithDelta(3000, $row['basic'], 0.01);
        $this->assertNull($row['paid_days'], 'A full month is not a pro-rated one.');
        $this->assertNull($row['period_days']);
    }

    public function test_a_full_custom_cycle_still_pays_full_salary(): void
    {
        $this->cycleStartsOn(26);
        $e = $this->employee('monthly', 3000);

        $this->assertEqualsWithDelta(3000, $this->row($e)['basic'], 0.01);
        // And when the same range is passed explicitly.
        $this->assertEqualsWithDelta(3000, $this->row($e, '2026-06-26', '2026-07-25')['basic'], 0.01);
    }

    /** A period longer than the cycle must not pay more than a month. */
    public function test_a_longer_period_is_capped_at_full_salary(): void
    {
        $e = $this->employee('monthly', 3000);

        $this->assertEqualsWithDelta(3000, $this->row($e, '2026-05-01', '2026-07-31')['basic'], 0.01);
    }

    // ── The reported cases ────────────────────────────────────────────────

    public function test_a_short_period_pays_proportionally(): void
    {
        $e = $this->employee('monthly', 3000);

        // 25 of July's 31 days.
        $row = $this->row($e, '2026-07-01', '2026-07-25');

        $this->assertEqualsWithDelta(3000 / 31 * 25, $row['basic'], 0.01);
        $this->assertSame(25, $row['paid_days']);
        $this->assertSame(31, $row['period_days']);
    }

    public function test_a_mid_cycle_joiner_is_paid_from_their_join_date(): void
    {
        $e = $this->employee('monthly', 3000, '2026-07-20');

        $row = $this->row($e);

        // 20–31 July inclusive is 12 days.
        $this->assertSame(12, $row['paid_days']);
        $this->assertEqualsWithDelta(3000 / 31 * 12, $row['basic'], 0.01);
    }

    public function test_a_mid_cycle_leaver_is_paid_to_their_leaving_date(): void
    {
        $e = $this->employee('monthly', 3000);
        $e->update(['employment_status' => 'resigned', 'employment_status_date' => '2026-07-05']);

        $row = $this->row($e);

        // The leaving date is the last working day, so 1–5 July is 5 days.
        $this->assertSame(5, $row['paid_days']);
        $this->assertEqualsWithDelta(3000 / 31 * 5, $row['basic'], 0.01);
    }

    /** Somebody who joins and leaves inside one period gets only the middle. */
    public function test_a_joiner_who_also_leaves_gets_only_the_overlap(): void
    {
        $e = $this->employee('monthly', 3000, '2026-07-10');
        $e->update(['employment_status' => 'resigned', 'employment_status_date' => '2026-07-20']);

        $row = $this->row($e);

        $this->assertSame(11, $row['paid_days']);           // 10–20 inclusive
        $this->assertEqualsWithDelta(3000 / 31 * 11, $row['basic'], 0.01);
    }

    /** The cycle-change seam adds up instead of paying twice. */
    public function test_a_cycle_change_no_longer_pays_two_months_for_one_and_a_half(): void
    {
        $e = $this->employee('monthly', 3000);

        $summary = app(CompensationSummary::class);

        // The stub: 1–25 June, under calendar cycles (June has 30 days).
        $stub = $summary->forMonth(
            Employee::query()->whereKey($e->id), $this->company->id,
            Carbon::parse('2026-06-01'), Carbon::parse('2026-06-01'), Carbon::parse('2026-06-25'),
        )['rows']->first();

        $this->assertEqualsWithDelta(3000 / 30 * 25, $stub['basic'], 0.01);
        $this->assertLessThan(3000, $stub['basic'],
            'A 25-day stub paying a full month is the double-pay this fixes.');
    }

    // ── Statutory follows basic ───────────────────────────────────────────

    public function test_statutory_is_computed_on_the_prorated_basic(): void
    {
        $s = \App\Models\StatutorySetting::forCompany($this->company->id);
        $s->company_id = $this->company->id;
        $s->fill(['epf_enabled' => true])->save();

        $full   = $this->row($this->employee('monthly', 3000));
        $joiner = $this->row($this->employee('monthly', 3000, '2026-07-20'));

        $this->assertEqualsWithDelta($joiner['basic'], $joiner['epf_wages'], 0.01);
        $this->assertLessThan(
            $full['statutory']['epf_employee'],
            $joiner['statutory']['epf_employee'],
            'Overpaying basic overpaid every contribution computed from it.'
        );
    }

    // ── Daily pay, which was paying one day for a whole month ─────────────

    public function test_daily_staff_are_paid_for_the_days_they_worked(): void
    {
        $e = $this->employee('daily', 100);
        $this->worked($e, 1, 12);

        $row = $this->row($e);

        $this->assertEqualsWithDelta(1200, $row['basic'], 0.01,
            'A daily rate used verbatim paid RM100 for the month.');
        $this->assertSame(12, $row['paid_days']);
        $this->assertEqualsWithDelta(100, $row['pay_rate'], 0.01);
        $this->assertNull($row['period_days'], 'Daily is a count of days, not a fraction of a month.');
    }

    /** A day with no hours on it is a day not worked. */
    public function test_a_zero_hour_day_is_not_a_paid_day(): void
    {
        $e = $this->employee('daily', 100);
        $this->worked($e, 1, 3);
        $this->worked($e, 4, 4, 0);

        $this->assertSame(3, $this->row($e)['paid_days']);
    }

    public function test_daily_staff_with_an_empty_grid_are_paid_nothing_and_flagged(): void
    {
        $e = $this->employee('daily', 100);

        $this->assertEqualsWithDelta(0, $this->row($e)['basic'], 0.01);

        $line = new \App\Models\PayrollRunLine([
            'pay_type' => 'daily', 'basic' => 0, 'paid_days' => 0, 'pay_rate' => 100,
        ]);
        $this->assertSame('no days entered', $line->zeroHourReason());
    }

    /** Paid for what they worked already — never pro-rated on top of it. */
    public function test_daily_and_hourly_joiners_are_not_prorated_twice(): void
    {
        $daily = $this->employee('daily', 100, '2026-07-20');
        $this->worked($daily, 20, 25);
        $this->assertEqualsWithDelta(600, $this->row($daily)['basic'], 0.01);

        $hourly = $this->employee('hourly', 12, '2026-07-20');
        $this->worked($hourly, 20, 23, 7.5);
        $this->assertEqualsWithDelta(360, $this->row($hourly)['basic'], 0.01);
        $this->assertNull($this->row($hourly)['paid_days'], 'Hourly reports hours, not days.');
    }

    // ── Allowances follow the part month ──────────────────────────────────

    private function assign(Employee $e, string $kind, string $calculation, float $amount, string $name): void
    {
        $component = \App\Models\PayComponent::create([
            'company_id' => $this->company->id, 'name' => $name,
            'kind' => $kind, 'calculation' => $calculation, 'is_active' => true,
        ]);

        \App\Models\EmployeePayComponent::create([
            'company_id' => $this->company->id, 'employee_id' => $e->id,
            'pay_component_id' => $component->id, 'amount' => $amount,
            'effective_from' => '2025-01-01',
        ]);
    }

    public function test_a_fixed_allowance_is_prorated_with_the_month(): void
    {
        $e = $this->employee('monthly', 3000, '2026-07-20');
        $this->assign($e, 'allowance', 'fixed', 310, 'Meal');

        $row = $this->row($e);

        $this->assertEqualsWithDelta(310 * 12 / 31, $row['allowances'], 0.01);
        $this->assertTrue($row['components'][0]['prorated'],
            'A reduced allowance beside a full one reads as a mistake unless it says why.');
    }

    /**
     * The trap: a percent-of-basic allowance is computed FROM the pro-rated
     * basic, so scaling it again would apply the fraction twice — a joiner on
     * the 20th would get (12/31)² of it, about an eighth rather than a third.
     */
    public function test_a_percent_of_basic_allowance_is_not_scaled_twice(): void
    {
        $e = $this->employee('monthly', 3000, '2026-07-20');
        $this->assign($e, 'allowance', 'percent_basic', 10, 'Housing');

        $row = $this->row($e);

        $proratedBasic = 3000 * 12 / 31;

        $this->assertEqualsWithDelta($proratedBasic * 0.10, $row['allowances'], 0.01);
        $this->assertNotEqualsWithDelta($proratedBasic * 0.10 * 12 / 31, $row['allowances'], 0.01);
        $this->assertFalse($row['components'][0]['prorated'],
            'It follows basic rather than being scaled, so it is not marked as scaled.');
    }

    /** A loan instalment is not smaller because somebody joined late. */
    public function test_deductions_are_not_prorated(): void
    {
        $e = $this->employee('monthly', 3000, '2026-07-20');
        $this->assign($e, 'deduction', 'fixed', 200, 'Staff Loan');

        $this->assertEqualsWithDelta(200, $this->row($e)['deductions'], 0.01);
    }

    public function test_a_full_month_leaves_every_allowance_whole(): void
    {
        $e = $this->employee('monthly', 3000);
        $this->assign($e, 'allowance', 'fixed', 310, 'Meal');

        $row = $this->row($e);

        $this->assertEqualsWithDelta(310, $row['allowances'], 0.01);
        $this->assertFalse($row['components'][0]['prorated']);
    }

    /** Their basic already follows what they worked; scaling would charge twice. */
    public function test_daily_staff_keep_whole_allowances(): void
    {
        $e = $this->employee('daily', 100, '2026-07-20');
        $this->worked($e, 20, 25);
        $this->assign($e, 'allowance', 'fixed', 310, 'Meal');

        $this->assertEqualsWithDelta(310, $this->row($e)['allowances'], 0.01);
    }

    public function test_statutory_wages_follow_the_scaled_allowances(): void
    {
        $e = $this->employee('monthly', 3000, '2026-07-20');
        $this->assign($e, 'allowance', 'fixed', 310, 'Meal');

        $row = $this->row($e);

        $this->assertEqualsWithDelta($row['basic'] + $row['allowances'], $row['epf_wages'], 0.01);
    }

    // ── How a payslip explains itself ─────────────────────────────────────

    public function test_a_line_can_say_why_a_part_month_was_paid(): void
    {
        $prorated = new \App\Models\PayrollRunLine([
            'pay_type' => 'monthly', 'paid_days' => 12, 'period_days' => 31,
        ]);
        $this->assertTrue($prorated->isProrated());
        $this->assertSame('12 of 31 days', $prorated->prorationLabel());

        // A daily line carries days too, and must not be read as a part month.
        $daily = new \App\Models\PayrollRunLine([
            'pay_type' => 'daily', 'paid_days' => 12, 'period_days' => null,
        ]);
        $this->assertFalse($daily->isProrated());
        $this->assertNull($daily->prorationLabel());
    }
}
