<?php

namespace Tests\Feature;

use App\Livewire\Reports\Index as ReportsIndex;
use App\Livewire\Reports\Management\WeeklyWipReview;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LabourCost;
use App\Models\LabourCostTransfer;
use App\Models\Outlet;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Models\User;
use App\Services\Hr\LabourCostTransferLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Confirmed labour cost transfers move cost between outlets in the labour
 * reports: the Reports > Labour tab, the WIP review and the AI analysis
 * context. Company-wide they net to zero; per outlet they shift.
 */
class LabourCostTransferReportsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $home;
    private Outlet $events;
    private User $user;
    private Employee $aisyah;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-16 10:00:00');

        $this->company = Company::create([
            'name' => 'Ledger Co', 'slug' => Str::slug('Ledger Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $this->home   = Outlet::create(['company_id' => $this->company->id, 'name' => 'Home', 'code' => 'HOME', 'is_active' => true]);
        $this->events = Outlet::create(['company_id' => $this->company->id, 'name' => 'Events', 'code' => 'EVT', 'is_active' => true]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->home->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->sync([$this->home->id, $this->events->id]);

        setPermissionsTeamId($this->company->id);
        foreach (['reports.view', 'hr.compensation'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->user->givePermissionTo(['reports.view', 'hr.compensation']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->aisyah = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->home->id, 'name' => 'AISYAH',
            'is_active' => true, 'join_date' => '2025-01-01', 'basic_salary' => 2600, 'pay_type' => 'monthly',
        ]);

        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function transfer(string $start, string $end, float $total, string $status = 'confirmed'): LabourCostTransfer
    {
        $t = LabourCostTransfer::create([
            'company_id' => $this->company->id, 'transfer_number' => 'LCT-' . Str::random(6),
            'to_outlet_id' => $this->events->id, 'transfer_date' => $start, 'purpose' => 'event', 'status' => $status,
        ]);
        $t->lines()->create([
            'employee_id' => $this->aisyah->id, 'employee_name' => 'AISYAH', 'from_outlet_id' => $this->home->id,
            'date_start' => $start, 'date_end' => $end, 'days' => 1, 'daily_rate' => 100,
            'salary_amount' => $total, 'ot_hours' => 0, 'ot_amount' => 0, 'total_amount' => $total,
        ]);

        return $t;
    }

    public function test_the_ledger_counts_confirmed_only_and_spreads_a_stint_across_months(): void
    {
        $this->transfer('2026-08-10', '2026-08-10', 100);
        $this->transfer('2026-08-12', '2026-08-12', 999, 'draft');
        $this->transfer('2026-08-13', '2026-08-13', 999, 'cancelled');
        // 30 Aug – 2 Sep: two days in each month.
        $this->transfer('2026-08-30', '2026-09-02', 400);

        $aug = LabourCostTransferLedger::byOutlet($this->company->id, '2026-08-01', '2026-08-31');
        $this->assertEquals(300, $aug[$this->events->id]['net']);
        $this->assertEquals(-300, $aug[$this->home->id]['net']);

        $sep = LabourCostTransferLedger::byOutlet($this->company->id, '2026-09-01', '2026-09-30');
        $this->assertEquals(200, $sep[$this->events->id]['in']);
        $this->assertEquals(200, $sep[$this->home->id]['out']);

        $this->assertEquals(0, LabourCostTransferLedger::netFor($this->company->id, '2026-08-01', '2026-08-31'),
            'Company-wide a transfer moves cost, it never adds any.');
    }

    public function test_the_labour_tab_moves_cost_between_outlets_and_shows_the_receiver(): void
    {
        LabourCost::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->home->id, 'month' => '2026-08-01',
            'department_type' => 'boh', 'basic_salary' => 5000,
        ]);
        // As MySQL's DATE column holds it; SQLite otherwise keeps the cast's
        // time part and the screen's where('month', ...) never matches.
        \Illuminate\Support\Facades\DB::table('labour_costs')->update(['month' => '2026-08-01']);
        $this->transfer('2026-08-10', '2026-08-11', 250);

        $data = $this->labourTab('2026-08');

        $home   = $data['outlets'][$this->home->id];
        $events = $data['outlets'][$this->events->id] ?? null;

        $this->assertEquals(4750, $home['total'], 'The lending outlet hands the cost off.');
        $this->assertEquals(250, $home['transfer_out']);
        $this->assertNotNull($events, 'An outlet with only borrowed staff still gets a card.');
        $this->assertEquals(250, $events['total']);
        $this->assertEquals(5000, $data['grand_total'], 'Unchanged company-wide.');

        // One outlet selected: its total carries the adjustment.
        $this->assertEquals(4750, $this->labourTab('2026-08', $this->home->id)['grand_total']);
    }

    /**
     * The labour tab's data, loaded directly. Rendering the whole Reports
     * page is not possible on SQLite: other tabs group by MySQL's YEAR().
     */
    private function labourTab(string $period, ?int $outletId = null): array
    {
        $c = new ReportsIndex();
        $c->period   = $period;
        $c->outletId = $outletId;

        $m = new \ReflectionMethod($c, 'loadLabourData');
        $m->setAccessible(true);
        $m->invoke($c);

        return $c->labourData;
    }

    public function test_the_wip_review_moves_payroll_labour_to_the_borrowing_outlet(): void
    {
        $run = PayrollRun::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->home->id,
            'period_month' => '2026-08-01', 'period_start' => '2026-08-01', 'period_end' => '2026-08-31',
            'status' => PayrollRun::APPROVED, 'reference' => 'PR-' . Str::random(6), 'generated_at' => now(),
        ]);
        PayrollRunLine::create([
            'payroll_run_id' => $run->id, 'company_id' => $this->company->id,
            'employee_id' => $this->aisyah->id, 'employee_name' => 'AISYAH',
            'basic' => 2600, 'gross' => 2600, 'statutory_employer' => 400, 'employer_cost' => 3000,
        ]);
        $this->transfer('2026-08-10', '2026-08-12', 300);

        $report = Livewire::test(WeeklyWipReview::class)->set('mode', 'month')->viewData('report');

        $outlets = collect($report['outlets'])->keyBy('name');
        $this->assertEquals(2700, $outlets['Home']['labour_cost']['current']);
        $this->assertEquals(300, $outlets['Events']['labour_cost']['current']);
        $this->assertEquals(3000, collect($report['kpis'])->firstWhere('key', 'labour_cost')['current'], 'Unchanged company-wide.');

        $homeOnly = Livewire::test(WeeklyWipReview::class)->set('mode', 'month')
            ->set('outletFilter', (string) $this->home->id)->viewData('report');
        $this->assertEquals(2700, collect($homeOnly['kpis'])->firstWhere('key', 'labour_cost')['current']);
        $this->assertEquals(-300, collect($homeOnly['labour']['rows'])->firstWhere('key', 'transfers')['current']);
    }
}
