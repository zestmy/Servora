<?php

namespace Tests\Feature;

use App\Livewire\Reports\Management\WeeklyWipReview;
use App\Models\Company;
use App\Models\Department;
use App\Models\Outlet;
use App\Models\PurchaseRecord;
use App\Models\SalesCategory;
use App\Models\SalesRecord;
use App\Models\SalesRecordLine;
use App\Models\StaffMealRecord;
use App\Models\User;
use App\Models\WastageRecord;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The weekly WIP meeting report: the reviewed week (Mon 7 – Sun 13 Sep 2026)
 * against the week before (31 Aug – 6 Sep), by department and by outlet.
 */
class WeeklyWipReviewTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private Department $kitchen;
    private Department $bar;
    private SalesCategory $food;
    private SalesCategory $drinks;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-16 10:00:00');

        $this->company = Company::create([
            'name' => 'WIP Co', 'slug' => Str::slug('WIP Co') . '-' . uniqid(),
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
        $this->user->givePermissionTo('reports.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->food   = SalesCategory::create(['company_id' => $this->company->id, 'name' => 'Food', 'is_active' => true]);
        $this->drinks = SalesCategory::create(['company_id' => $this->company->id, 'name' => 'Beverage', 'is_active' => true]);

        $this->kitchen = Department::create(['company_id' => $this->company->id, 'name' => 'Kitchen', 'sales_category_id' => $this->food->id, 'sort_order' => 1, 'is_active' => true]);
        $this->bar     = Department::create(['company_id' => $this->company->id, 'name' => 'Bar', 'sales_category_id' => $this->drinks->id, 'sort_order' => 2, 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param  array<int, array{0: SalesCategory, 1: float}>  $lines */
    private function sale(string $date, float $total, array $lines): void
    {
        $record = SalesRecord::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'sale_date' => $date, 'total_revenue' => $total, 'total_cost' => 0,
        ]);

        foreach ($lines as [$category, $amount]) {
            SalesRecordLine::create([
                'sales_record_id' => $record->id, 'sales_category_id' => $category->id,
                'item_name' => $category->name, 'quantity' => 1, 'unit_price' => $amount,
                'unit_cost' => 0, 'total_revenue' => $amount, 'total_cost' => 0,
            ]);
        }
    }

    private function purchase(string $date, float $amount, ?Department $dept): void
    {
        PurchaseRecord::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'department_id' => $dept?->id, 'purchase_date' => $date, 'total_amount' => $amount,
        ]);
    }

    private function seedTwoWeeks(): void
    {
        // Reviewed week.
        $this->sale('2026-09-08', 1500, [[$this->food, 1000], [$this->drinks, 500]]);
        $this->purchase('2026-09-09', 400, $this->kitchen);
        $this->purchase('2026-09-13', 50, null);
        WastageRecord::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'department_id' => $this->kitchen->id, 'wastage_date' => '2026-09-10', 'total_cost' => 20,
        ]);
        StaffMealRecord::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'meal_date' => '2026-09-11', 'total_cost' => 30,
        ]);

        // Week before — including a Z-report total with no lines behind it.
        $this->sale('2026-09-01', 800, [[$this->food, 800]]);
        $this->sale('2026-09-06', 200, []);
        $this->purchase('2026-09-02', 300, $this->kitchen);

        // The current, unfinished week must not leak into the review.
        $this->sale('2026-09-14', 9999, [[$this->food, 9999]]);
    }

    private function report(array $set = []): array
    {
        $c = Livewire::actingAs($this->user)->test(WeeklyWipReview::class);
        foreach ($set as $k => $v) {
            $c->set($k, $v);
        }

        return $c->viewData('report');
    }

    private function kpi(array $report, string $key): array
    {
        return collect($report['kpis'])->firstWhere('key', $key);
    }

    private function dept(array $report, string $name): array
    {
        return collect($report['departments'])->firstWhere('name', $name);
    }

    public function test_it_opens_on_the_last_complete_week(): void
    {
        $report = $this->report();

        $this->assertSame('2026-09-07', $report['current']['start']);
        $this->assertSame('2026-08-31', $report['previous']['start']);
        $this->assertCount(8, $report['weeks']);
    }

    public function test_the_headline_figures_compare_the_week_with_the_one_before(): void
    {
        $this->seedTwoWeeks();
        $report = $this->report();

        $sales = $this->kpi($report, 'sales');
        $this->assertEquals(1500, $sales['current'], 'The unfinished week must not count.');
        $this->assertEquals(1000, $sales['previous']);
        $this->assertEquals(50.0, $sales['change']);

        $this->assertEquals(450, $this->kpi($report, 'purchases')['current']);
        $this->assertEquals(30.0, $this->kpi($report, 'cost_pct')['current'], '450 of 1,500.');
        $this->assertEquals(0.0, $this->kpi($report, 'cost_pct')['change'], '30% against 300 of 1,000.');
        $this->assertEquals(20, $this->kpi($report, 'wastage')['current']);
        $this->assertEquals(30, $this->kpi($report, 'staff_meal')['current']);
    }

    public function test_sales_reach_departments_through_their_sales_category(): void
    {
        $this->seedTwoWeeks();
        $report = $this->report();

        $kitchen = $this->dept($report, 'Kitchen');
        $this->assertEquals(1000, $kitchen['sales']['current']);
        $this->assertEquals(800, $kitchen['sales']['previous']);
        $this->assertEquals(400, $kitchen['purchases']['current']);
        $this->assertEquals(40.0, $kitchen['cost_pct']['current']);
        $this->assertEquals(20, $kitchen['wastage']['current']);

        $this->assertEquals(500, $this->dept($report, 'Bar')['sales']['current']);

        $unassigned = $this->dept($report, 'Unassigned');
        $this->assertEquals(50, $unassigned['purchases']['current'], 'A purchase with no department.');
        $this->assertEquals(200, $unassigned['sales']['previous'], 'A sales total with no lines behind it.');

        $this->assertSame(['Kitchen', 'Bar', 'Unassigned'], array_column($report['departments'], 'name'),
            'The company\'s own department order, Unassigned last.');
    }

    public function test_the_trend_buckets_each_record_into_its_week(): void
    {
        $this->seedTwoWeeks();
        $report = $this->report(['weeks' => '4']);

        $this->assertCount(4, $report['weeks']);
        $this->assertEquals([0, 0, 1000, 1500], $report['totals']['sales']);
        $this->assertEquals([null, null, 30.0, 30.0], $report['totals']['cost_pct']);
    }

    public function test_clicking_a_week_reviews_it_and_the_future_is_out_of_reach(): void
    {
        $this->seedTwoWeeks();

        $c = Livewire::actingAs($this->user)->test(WeeklyWipReview::class)
            ->call('reviewWeek', '2026-09-03');
        $this->assertSame('2026-08-31', $c->get('week'), 'Any day snaps to its Monday.');

        $c->set('week', '2026-12-01');
        $this->assertSame('2026-09-14', $c->get('week'), 'No later than the current week.');
    }

    public function test_the_report_is_listed_in_the_hub_and_opens(): void
    {
        $this->actingAs($this->user)->get(route('reports.hub'))
            ->assertOk()->assertSee('Weekly WIP Review');

        $this->actingAs($this->user)->get(route('reports.weekly-wip-review'))
            ->assertOk()->assertSee('Present');
    }
}
