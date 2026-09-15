<?php

namespace Tests\Feature;

use App\Livewire\Reports\Management\WeeklyWipReview;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\OvertimeClaim;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Models\SalesRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Overtime claims (weekly and monthly) and payroll labour cost (monthly) on the
 * WIP review — and that both money figures stay behind hr.compensation.
 *
 * "Today" is Wed 16 Sep 2026: the review opens on the week of 7 Sep, or on
 * August in monthly mode. Company settings are the defaults — 26 days, 8 hours,
 * 1.5× on a normal day — so a RM2,600 monthly salary is RM12.50 an hour.
 */
class WipReviewOvertimeAndLabourTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private Employee $aisyah;
    private Employee $bala;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-16 10:00:00');

        $this->company = Company::create([
            'name' => 'Labour Co', 'slug' => Str::slug('Labour Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        Permission::findOrCreate('reports.view', 'web');
        Permission::findOrCreate('hr.compensation', 'web');
        $this->user->givePermissionTo('reports.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->aisyah = $this->employee('NUR AISYAH', 2600);
        $this->bala   = $this->employee('BALA', 2000);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function employee(string $name, float $salary): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => $name, 'is_active' => true, 'join_date' => '2025-01-01',
            'basic_salary' => $salary, 'pay_type' => 'monthly',
        ]);
    }

    private function canSeePay(): void
    {
        $this->user->givePermissionTo('hr.compensation');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function claim(string $date, float $hours, string $status = 'approved', string $settlement = OvertimeClaim::SETTLE_PAYROLL): void
    {
        OvertimeClaim::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'employee_id' => $this->aisyah->id, 'submitted_by' => $this->user->id,
            'claim_date' => $date, 'ot_time_start' => '18:00', 'ot_time_end' => '22:00',
            'total_ot_hours' => $hours, 'hours_taken_off' => 0, 'ot_type' => 'normal_day',
            'reason' => 'Stocktake', 'status' => $status, 'settlement' => $settlement,
        ]);
    }

    private function payrollRun(string $month, string $status, ?Outlet $outlet, array $lines): void
    {
        $run = PayrollRun::create([
            'company_id' => $this->company->id, 'outlet_id' => $outlet?->id,
            'period_month' => $month, 'period_start' => $month,
            'period_end' => Carbon::parse($month)->endOfMonth()->toDateString(),
            'status' => $status, 'reference' => 'PR-' . Str::random(6),
            'generated_at' => now(),
        ]);

        foreach ($lines as [$employee, $employerCost]) {
            PayrollRunLine::create([
                'payroll_run_id' => $run->id, 'company_id' => $this->company->id,
                'employee_id' => $employee->id, 'employee_name' => $employee->name,
                'basic' => $employerCost * 0.8, 'allowances' => $employerCost * 0.05,
                'gross' => $employerCost * 0.9, 'statutory_employer' => $employerCost * 0.1,
                'employer_cost' => $employerCost,
            ]);
        }
    }

    private function sale(string $date, float $total): void
    {
        SalesRecord::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'sale_date' => $date, 'total_revenue' => $total, 'total_cost' => 0,
        ]);
    }

    private function report(array $set = []): array
    {
        $c = Livewire::actingAs($this->user)->test(WeeklyWipReview::class);
        foreach ($set as $k => $v) {
            $c->set($k, $v);
        }

        return $c->viewData('report');
    }

    private function kpi(array $report, string $key): ?array
    {
        return collect($report['kpis'])->firstWhere('key', $key);
    }

    // ── Overtime ──────────────────────────────────────────────────────────

    public function test_weekly_overtime_counts_approved_hours_and_prices_them(): void
    {
        $this->canSeePay();

        $this->claim('2026-09-09', 4);                                        // RM12.50 × 1.5 × 4 = RM75
        $this->claim('2026-09-10', 2, 'approved', OvertimeClaim::SETTLE_TIME_OFF); // hours, no cash
        $this->claim('2026-09-11', 3, 'submitted');                           // awaiting approval
        $this->claim('2026-09-02', 2);                                        // last week: RM37.50

        $report = $this->report();

        $hours = $this->kpi($report, 'ot_hours');
        $this->assertEquals(6, $hours['current'], 'Approved claims only, time off included.');
        $this->assertEquals(2, $hours['previous']);

        $cost = $this->kpi($report, 'ot_cost');
        $this->assertEquals(75, $cost['current'], 'Time-off hours cost nothing; submitted claims are not costed.');
        $this->assertEquals(37.5, $cost['previous']);

        $this->assertEquals(3, $report['overtime']['pending_hours']['current']);

        $klcc = collect($report['outlets'])->firstWhere('name', 'KLCC');
        $this->assertEquals(6, $klcc['ot_hours']['current']);
        $this->assertEquals(75, $klcc['ot_cost']['current']);
    }

    public function test_overtime_cost_and_labour_stay_hidden_without_pay_access(): void
    {
        $this->claim('2026-09-09', 4);
        $this->payrollRun('2026-08-01', PayrollRun::APPROVED, $this->outlet, [[$this->aisyah, 3000]]);

        $weekly = $this->report();
        $this->assertFalse($weekly['can_view_pay']);
        $this->assertEquals(4, $this->kpi($weekly, 'ot_hours')['current'], 'Hours are not pay.');
        $this->assertNull($this->kpi($weekly, 'ot_cost'));
        $this->assertArrayNotHasKey('ot_cost', collect($weekly['outlets'])->firstWhere('name', 'KLCC'));

        $monthly = $this->report(['mode' => 'month']);
        $this->assertNull($monthly['labour'], 'Labour cost is computed for pay viewers only.');
        $this->assertNull($this->kpi($monthly, 'labour_cost'));
    }

    // ── Monthly mode ──────────────────────────────────────────────────────

    public function test_monthly_mode_opens_on_the_last_complete_month(): void
    {
        $report = $this->report(['mode' => 'month']);

        $this->assertSame('month', $report['granularity']);
        $this->assertSame('2026-08-01', $report['current']['start']);
        $this->assertSame('2026-07-01', $report['previous']['start']);
        $this->assertCount(3, $report['periods'], 'A 3-month trend by default.');
    }

    public function test_a_one_month_trend_still_compares_with_the_month_before(): void
    {
        $this->sale('2026-08-10', 10000);
        $this->sale('2026-07-10', 8000);

        $report = $this->report(['mode' => 'month', 'months' => '1']);

        $this->assertCount(1, $report['periods']);
        $this->assertEquals([10000], $report['totals']['sales']);

        $sales = $this->kpi($report, 'sales');
        $this->assertEquals(10000, $sales['current']);
        $this->assertEquals(8000, $sales['previous']);
        $this->assertEquals(25.0, $sales['change']);
    }

    public function test_monthly_overtime_rolls_claims_up_by_month(): void
    {
        $this->canSeePay();
        $this->claim('2026-08-05', 4);
        $this->claim('2026-08-20', 2);
        $this->claim('2026-07-15', 1);

        $report = $this->report(['mode' => 'month']);

        $this->assertEquals(6, $this->kpi($report, 'ot_hours')['current']);
        $this->assertEquals(1, $this->kpi($report, 'ot_hours')['previous']);
        $this->assertEquals(112.5, $this->kpi($report, 'ot_cost')['current']);
    }

    // ── Labour cost ───────────────────────────────────────────────────────

    public function test_labour_cost_comes_from_approved_and_paid_payroll_by_default(): void
    {
        $this->canSeePay();
        $this->sale('2026-08-10', 10000);
        $this->sale('2026-07-10', 10000);

        $this->payrollRun('2026-08-01', PayrollRun::APPROVED, $this->outlet, [[$this->aisyah, 3000]]);
        $this->payrollRun('2026-08-01', PayrollRun::DRAFT, null, [[$this->aisyah, 9999], [$this->bala, 1000]]);
        $this->payrollRun('2026-07-01', PayrollRun::PAID, $this->outlet, [[$this->aisyah, 2500]]);

        $report = $this->report(['mode' => 'month']);

        $labour = $this->kpi($report, 'labour_cost');
        $this->assertEquals(3000, $labour['current'], 'The draft run is left out.');
        $this->assertEquals(2500, $labour['previous']);
        $this->assertEquals(30.0, $this->kpi($report, 'labour_pct')['current']);

        $this->assertSame(1, $report['labour']['drafts_left_out']);
        $this->assertSame(1, $report['labour']['headcount']['current']);

        $employerCost = collect($report['labour']['rows'])->firstWhere('key', 'employer_cost');
        $this->assertEquals(3000, $employerCost['current']);
        $this->assertEquals(20.0, $employerCost['change']);

        $this->assertEquals(3000, collect($report['outlets'])->firstWhere('name', 'KLCC')['labour_cost']['current']);
    }

    public function test_draft_payroll_can_be_included_without_paying_anyone_twice(): void
    {
        $this->canSeePay();
        $this->sale('2026-08-10', 10000);

        $this->payrollRun('2026-08-01', PayrollRun::APPROVED, $this->outlet, [[$this->aisyah, 3000]]);
        $this->payrollRun('2026-08-01', PayrollRun::DRAFT, null, [[$this->aisyah, 9999], [$this->bala, 1000]]);

        $report = $this->report(['mode' => 'month', 'includeDraftPayroll' => true]);

        $this->assertEquals(4000, $this->kpi($report, 'labour_cost')['current'],
            'Aisyah from her approved run (not the draft\'s RM9,999), plus Bala from the draft.');
        $this->assertSame(2, $report['labour']['headcount']['current']);
        $this->assertTrue($report['labour']['draft_used']);
        $this->assertEquals(4000, collect($report['outlets'])->firstWhere('name', 'KLCC')['labour_cost']['current'],
            'A company-wide run is split by each employee\'s outlet.');
    }

    public function test_weekly_mode_has_no_labour_cost(): void
    {
        $this->canSeePay();
        $this->payrollRun('2026-09-01', PayrollRun::APPROVED, $this->outlet, [[$this->aisyah, 3000]]);

        $report = $this->report();

        $this->assertNull($report['labour'], 'Payroll is monthly; spreading it over weeks would invent figures.');
        $this->assertNull($this->kpi($report, 'labour_cost'));
    }

    public function test_the_monthly_deck_renders_with_the_labour_slide(): void
    {
        $this->canSeePay();
        $this->payrollRun('2026-08-01', PayrollRun::APPROVED, $this->outlet, [[$this->aisyah, 3000]]);

        Livewire::actingAs($this->user)->test(WeeklyWipReview::class)
            ->set('mode', 'month')
            ->assertSee('Monthly WIP Review')
            ->assertSee('Labour cost')
            ->assertSee('Include draft payroll')
            ->assertSee('Overtime claims');
    }
}
