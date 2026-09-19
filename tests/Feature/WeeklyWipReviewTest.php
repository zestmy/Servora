<?php

namespace Tests\Feature;

use App\Livewire\Reports\Management\WeeklyWipReview;
use App\Models\Company;
use App\Models\Department;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\OutletTransfer;
use App\Models\OutletTransferLine;
use App\Models\PurchaseCapture;
use App\Models\UnitOfMeasure;
use App\Models\PurchaseRecord;
use App\Models\SalesCategory;
use App\Models\SalesRecord;
use App\Models\SalesRecordLine;
use App\Models\SalesTarget;
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

    private function kpi(array $report, string $key): ?array
    {
        return collect($report['kpis'])->firstWhere('key', $key);
    }

    private function dept(array $report, string $name): ?array
    {
        return collect($report['departments'])->firstWhere('name', $name);
    }

    public function test_it_opens_on_the_last_complete_week(): void
    {
        $report = $this->report();

        $this->assertSame('2026-09-07', $report['current']['start']);
        $this->assertSame('2026-08-31', $report['previous']['start']);
        $this->assertCount(8, $report['periods']);
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
        $purchases = $this->kpi($report, 'purchases');
        $this->assertEquals(30.0, $purchases['share']['current'], '450 of 1,500, carried on the purchases tile.');
        $this->assertEquals(0.0, $purchases['share']['change'], '30% against 300 of 1,000.');
        $this->assertNull($this->kpi($report, 'cost_pct'), 'No separate percentage tile any more.');
        $this->assertNull($this->kpi($report, 'sales')['share'], 'Sales is not a share of itself.');
        $this->assertEquals(20, $this->kpi($report, 'wastage')['current']);
        $this->assertEquals(30, $this->kpi($report, 'staff_meal')['current']);

        // Every cost carries its share of sales beside it.
        $this->assertEquals(1.3, $this->kpi($report, 'wastage')['share']['current'], '20 of 1,500.');
        $this->assertEquals(2.0, $this->kpi($report, 'staff_meal')['share']['current'], '30 of 1,500.');
        $klcc = collect($report['outlets'])->firstWhere('name', 'KLCC');
        $this->assertEquals(2.0, $klcc['staff_meal_pct']['current']);
        $this->assertEquals(1.3, $klcc['wastage_pct']['current']);
        $this->assertSame([null, null, 0.0, 2.0], array_slice($report['totals']['staff_meal_pct'], -4),
            'No sales is "—", not 0%; a week with sales and no staff meals is a real 0%.');
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

        // The chart is in percentages of each department's own sales.
        $chart = $report['charts']['departments'];
        $this->assertSame(['Kitchen', 'Bar'], $chart['labels'], 'Only departments with sales to measure against.');
        $this->assertEquals([40.0, 0.0], $chart['cost_pct'], '400 of Kitchen\'s 1,000; nothing bought for the bar.');
        $this->assertSame([37.5, null], $chart['cost_pct_prev'], '300 of 800 last week; the bar sold nothing then.');
        $this->assertEquals([2.0, 0.0], $chart['wastage_pct']);
        $this->assertSame([0.0, null], $chart['wastage_pct_prev'], 'Nothing wasted last week; the bar sold nothing then.');
        $this->assertSame(['Unassigned'], $chart['left_out'], 'No sales this week, so no percentage to draw.');

        // Each slide's chart draws only departments with a bar to show.
        $this->assertSame(['Kitchen'], $chart['cost']['labels'], 'Nothing bought for the bar in either week.');
        $this->assertEquals([40.0], $chart['cost']['current']);
        $this->assertSame([37.5], $chart['cost']['previous']);
        $this->assertEquals([400], $chart['cost']['amount']);
        $this->assertSame(['Kitchen'], $chart['waste']['labels'], 'Nothing wasted at the bar.');
        $this->assertEquals([2.0], $chart['waste']['current']);
    }

    /**
     * REPORTED AS: purchases not shown. Stock Management > Purchases saves to
     * purchase_captures; only goods received against a PO reach
     * purchase_records, which was all this report read.
     */
    public function test_sales_by_category_compares_the_week_with_the_one_before(): void
    {
        $this->seedTwoWeeks();
        SalesCategory::create(['company_id' => $this->company->id, 'name' => 'Retail', 'is_active' => true]);
        SalesCategory::create(['company_id' => $this->company->id, 'name' => 'Old menu', 'is_active' => false]);
        // Five sen between a record's total and its lines: rounding, not a row.
        $this->sale('2026-09-10', 100.05, [[$this->food, 100]]);
        $report = $this->report();

        $rows = collect($report['categories']['rows'])->keyBy('name');

        $this->assertEquals(1100, $rows['Food']['current']);
        $this->assertEquals(800, $rows['Food']['previous']);
        $this->assertEquals(300, $rows['Food']['variance']);
        $this->assertEquals(37.5, $rows['Food']['change']);
        $this->assertEquals(500, $rows['Beverage']['current']);
        $this->assertFalse($rows->has('Retail'), 'A category that sold nothing in either week is left off.');
        $this->assertFalse($rows->has('Old menu'));
        $this->assertSame(['Beverage', 'Food', 'Uncategorised'], $rows->keys()->all(),
            'The 5 sen rounding this week does not make an Uncategorised row; the RM 200 total last week does.');
        $this->assertEquals(200, $rows['Uncategorised']['previous'], 'A sales total with no lines behind it.');

        $total = $report['categories']['total'];
        $this->assertEquals(1600.05, $total['current']);
        $this->assertEquals(1000, $total['previous']);
        $this->assertEquals(600.05, $total['variance']);
        $this->assertEqualsWithDelta($total['current'], collect($report['categories']['rows'])->sum('current'), 1, 'Rows add up to total sales, give or take rounding.');
        $this->assertEquals($total['previous'], collect($report['categories']['rows'])->sum('previous'));

        Livewire::actingAs($this->user)->test(WeeklyWipReview::class)
            ->assertSee('Weekly sales by category')
            ->assertSee('Uncategorised')
            // By outlet: no outlet moved stock or claimed overtime, so no columns for them.
            ->assertDontSee('Transfers in')
            ->assertDontSee('OT hours');

        $pdf = view('pdf.wip-review', [
            'report' => app(\App\Services\Reports\WeeklyWipReview::class)->build(
                $this->company->id, [$this->outlet->id], Carbon::parse('2026-09-07'), 8, 'week',
            ),
            'company' => $this->company, 'scopeLabel' => 'KLCC',
        ])->render();
        $this->assertStringContainsString('Weekly sales by category', $pdf);
        $this->assertStringNotContainsString('Retail', $pdf);
        $this->assertStringNotContainsString('Transfers in', $pdf);
        $this->assertStringContainsString('Purchases by department', $pdf);
        $this->assertStringContainsString('Wastage by department', $pdf);
    }

    public function test_purchases_keyed_in_stock_management_are_counted(): void
    {
        PurchaseCapture::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'department_id' => $this->bar->id, 'supplier_name' => 'Drinks Co',
            'purchase_date' => '2026-09-10', 'amount' => 120,
        ]);
        $this->purchase('2026-09-09', 400, $this->kitchen); // received against a PO

        $report = $this->report();

        $this->assertEquals(520, $this->kpi($report, 'purchases')['current'],
            'Captured and PO-received purchases are added together, as the COGS report does.');
        $this->assertEquals(120, $this->dept($report, 'Bar')['purchases']['current']);
        $this->assertEquals(400, $this->dept($report, 'Kitchen')['purchases']['current']);
        $this->assertEquals(520, collect($report['outlets'])->firstWhere('name', 'KLCC')['purchases']['current']);
    }

    /**
     * REPORTED AS: two departments on one sales category — the second showed
     * no "% of dept sales". The category's sales went to the first department
     * only, leaving the other with RM0 sales to divide by.
     */
    public function test_departments_sharing_a_sales_category_are_each_measured_against_its_sales(): void
    {
        $pastry = Department::create([
            'company_id' => $this->company->id, 'name' => 'Pastry',
            'sales_category_id' => $this->food->id, 'sort_order' => 3, 'is_active' => true,
        ]);

        $this->sale('2026-09-08', 1000, [[$this->food, 1000]]);
        $this->purchase('2026-09-09', 200, $pastry);
        WastageRecord::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'department_id' => $pastry->id, 'wastage_date' => '2026-09-10', 'total_cost' => 50,
        ]);

        $report = $this->report();

        $pastryRow = $this->dept($report, 'Pastry');
        $this->assertEquals(1000, $pastryRow['sales']['current'], 'The shared Food sales, not RM0.');
        $this->assertEquals(20.0, $pastryRow['cost_pct']['current'], '200 of 1,000.');
        $this->assertEquals(5.0, $pastryRow['wastage_pct']['current'], '50 of 1,000 — the missing figure.');
        $this->assertSame(['Kitchen'], $pastryRow['shared_with']);

        $kitchen = $this->dept($report, 'Kitchen');
        $this->assertEquals(1000, $kitchen['sales']['current']);
        $this->assertSame(['Pastry'], $kitchen['shared_with']);
        $this->assertSame([], $this->dept($report, 'Unassigned')['shared_with'] ?? [], 'Unassigned shares with nobody.');

        $this->assertEquals(1000, $this->kpi($report, 'sales')['current'], 'Total sales still count the sale once.');

        Livewire::actingAs($this->user)->test(WeeklyWipReview::class)
            ->assertSee('sales shared with Kitchen')
            ->assertSee('Purchases by department')
            ->assertSee('Wastage by department')
            ->assertSee('5.0%');
    }

    public function test_the_trend_buckets_each_record_into_its_week(): void
    {
        $this->seedTwoWeeks();
        $report = $this->report(['weeks' => '4']);

        $this->assertCount(4, $report['periods']);
        $this->assertEquals([0, 0, 1000, 1500], $report['totals']['sales']);
        $this->assertEquals([null, null, 30.0, 30.0], $report['totals']['cost_pct']);
    }

    public function test_a_two_week_trend_is_just_the_week_and_the_one_before(): void
    {
        $this->seedTwoWeeks();
        $report = $this->report(['weeks' => '2']);

        $this->assertSame(['2026-08-31', '2026-09-07'], array_column($report['periods'], 'start'));
        $this->assertEquals([1000, 1500], $report['totals']['sales']);
        $this->assertEquals(50.0, $this->kpi($report, 'sales')['change'],
            'The comparison is unchanged by the shorter trend.');
    }

    public function test_an_unoffered_trend_length_falls_back_to_eight_weeks(): void
    {
        $this->assertCount(8, $this->report(['weeks' => '3'])['periods']);
    }

    public function test_clicking_a_week_reviews_it_and_the_future_is_out_of_reach(): void
    {
        $this->seedTwoWeeks();

        $c = Livewire::actingAs($this->user)->test(WeeklyWipReview::class)
            ->call('reviewPeriod', '2026-09-03');
        $this->assertSame('2026-08-31', $c->get('week'), 'Any day snaps to its Monday.');

        $c->set('week', '2026-12-01');
        $this->assertSame('2026-09-14', $c->get('week'), 'No later than the current week.');
    }

    // ── Stock transfers ───────────────────────────────────────────────────

    private ?UnitOfMeasure $kg = null;
    private ?Ingredient $flour = null;

    private function secondOutlet(): Outlet
    {
        $ioi = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'IOI', 'code' => 'IOI', 'is_active' => true,
        ]);
        $this->user->outlets()->sync([$this->outlet->id, $ioi->id]);

        return $ioi;
    }

    private function transfer(Outlet $from, Outlet $to, string $date, string $status, float $qty, float $unitCost): void
    {
        $this->kg ??= UnitOfMeasure::create(['name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'weight']);
        $this->flour ??= Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'Flour',
            'base_uom_id' => $this->kg->id, 'recipe_uom_id' => $this->kg->id,
            'current_cost' => $unitCost, 'is_active' => true,
        ]);

        $transfer = OutletTransfer::unguarded(fn () => OutletTransfer::create([
            'company_id' => $this->company->id, 'from_outlet_id' => $from->id, 'to_outlet_id' => $to->id,
            'transfer_number' => 'TR-' . Str::random(8), 'status' => $status, 'transfer_date' => $date,
        ]));

        OutletTransferLine::unguarded(fn () => OutletTransferLine::create([
            'outlet_transfer_id' => $transfer->id, 'ingredient_id' => $this->flour->id,
            'quantity' => $qty, 'uom_id' => $this->kg->id, 'unit_cost' => $unitCost,
        ]));
    }

    public function test_stock_transfers_are_valued_on_their_lines_and_split_by_outlet(): void
    {
        $ioi = $this->secondOutlet();

        $this->transfer($this->outlet, $ioi, '2026-09-09', 'received', 3, 10);   // RM30
        $this->transfer($this->outlet, $ioi, '2026-09-10', 'draft', 5, 10);      // nothing moved yet
        $this->transfer($this->outlet, $ioi, '2026-09-11', 'cancelled', 5, 10);  // never will
        $this->transfer($ioi, $this->outlet, '2026-09-02', 'in_transit', 2, 10); // last week, RM20

        $report = $this->report();

        $transfers = $this->kpi($report, 'transfers');
        $this->assertEquals(30, $transfers['current'], 'Drafts and cancelled transfers do not count.');
        $this->assertEquals(20, $transfers['previous']);
        $this->assertEquals(50.0, $transfers['change']);
        $this->assertNull($transfers['up_is_good'], 'Moving stock is neither good nor bad news.');

        $klcc   = collect($report['outlets'])->firstWhere('name', 'KLCC');
        $ioiRow = collect($report['outlets'])->firstWhere('name', 'IOI');

        $this->assertEquals(30, $klcc['transfers_out']['current']);
        $this->assertEquals(20, $klcc['transfers_in']['previous']);
        $this->assertEquals(30, $ioiRow['transfers_in']['current']);
        $this->assertEquals(0, $ioiRow['transfers_out']['current']);
        $this->assertEquals([0, 0, 0, 0, 0, 0, 20, 30], $report['totals']['transfers']);

        // Stock moved, so By outlet carries the transfer columns.
        Livewire::actingAs($this->user)->test(WeeklyWipReview::class)
            ->assertSee('Transfers in')
            ->assertSee('Transfers out');
    }

    public function test_an_outlet_filter_shows_only_its_own_side_of_a_transfer(): void
    {
        $ioi = $this->secondOutlet();
        $this->transfer($this->outlet, $ioi, '2026-09-09', 'received', 3, 10);

        $report = $this->report(['outletFilter' => (string) $ioi->id]);

        $this->assertEquals(30, $this->kpi($report, 'transfers')['current']);
        $this->assertSame(['IOI'], array_column($report['outlets'], 'name'),
            'The sending outlet is outside the filter, so it has no row.');
    }

    // ── Sales performance (weekly) ────────────────────────────────────────

    private function mealSale(string $date, string $period, float $amount, int $pax): void
    {
        SalesRecord::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'sale_date' => $date, 'meal_period' => $period, 'pax' => $pax,
            'total_revenue' => $amount, 'total_cost' => 0,
        ]);
    }

    public function test_sales_performance_lays_out_both_weeks_by_day_and_meal_period(): void
    {
        $this->mealSale('2026-09-07', 'breakfast', 540, 10);    // Mon, week 37
        $this->mealSale('2026-09-07', 'lunch', 1818.25, 30);    // Mon
        $this->mealSale('2026-09-08', 'lunch', 1785.55, 20);    // Tue
        $this->mealSale('2026-08-31', 'breakfast', 989.55, 12); // Mon, week 36
        $this->mealSale('2026-08-31', 'lunch', 4507.50, 40);

        $sp = $this->report()['sales_performance'];

        $this->assertSame('Week 37', $sp['current']['label']);
        $this->assertSame('Week 36', $sp['previous']['label']);
        $this->assertSame(['Breakfast', 'Lunch'], array_column($sp['current']['lines'], 'label'),
            'Only meal periods that traded, in the offered order.');

        $lunch = $sp['current']['lines'][1];
        $this->assertEquals(1818.25, $lunch['days'][1]);
        $this->assertEquals(1785.55, $lunch['days'][2]);
        $this->assertEquals(0, $lunch['days'][7]);
        $this->assertEquals(3603.80, $lunch['total']);
        $this->assertEquals(87.0, $lunch['share'], '3,603.80 of the week\'s 4,143.80.');

        $this->assertEquals(2358.25, $sp['current']['days'][1]);
        $this->assertEquals(4143.80, $sp['current']['total']);
        $this->assertEquals(5497.05, $sp['previous']['total']);

        $monday = $sp['variance']['days'][0];
        $this->assertEquals(-3138.80, $monday['amount']);
        $this->assertEquals(-57.1, $monday['change']);
        $this->assertEquals(-1353.25, $sp['variance']['total']['amount']);
        $this->assertEquals(-24.6, $sp['variance']['total']['change']);
    }

    public function test_month_to_date_compares_sales_covers_and_average_check(): void
    {
        $this->mealSale('2026-09-07', 'lunch', 4000, 50);   // 1st–13th Sep 2026
        $this->mealSale('2026-09-14', 'lunch', 999, 9);     // after the reviewed week
        $this->mealSale('2026-08-05', 'lunch', 5000, 50);   // 1st–13th Aug
        $this->mealSale('2026-08-20', 'lunch', 999, 9);     // later in August
        $this->mealSale('2025-09-03', 'dinner', 3000, 40);  // 1st–13th Sep 2025

        $mtd = collect($this->report()['sales_performance']['mtd'])->keyBy('key');

        $this->assertSame('1st – 13th September 2026', $mtd['this_month']['range']);
        $this->assertEquals(4000, $mtd['this_month']['sales']);
        $this->assertSame(50, $mtd['this_month']['covers']);
        $this->assertEquals(80.0, $mtd['this_month']['avg_check']);

        $this->assertSame('1st – 13th August 2026', $mtd['last_month']['range']);
        $this->assertEquals(5000, $mtd['last_month']['sales'], 'Only the same days of August.');
        $this->assertEquals(100.0, $mtd['last_month']['avg_check']);
        $this->assertEquals(-20.0, $mtd['last_month']['sales_change']);
        $this->assertEquals(0.0, $mtd['last_month']['covers_change']);
        $this->assertEquals(-1000, $mtd['last_month']['variance']);

        $this->assertEquals(3000, $mtd['last_year']['sales']);
        $this->assertEquals(75.0, $mtd['last_year']['avg_check']);
        $this->assertEquals(33.3, $mtd['last_year']['sales_change']);
        $this->assertEquals(25.0, $mtd['last_year']['covers_change']);
        $this->assertEquals(1000, $mtd['last_year']['variance']);

        // On screen, and in the PDF.
        Livewire::actingAs($this->user)->test(WeeklyWipReview::class)
            ->assertSee('Sales performance')->assertSee('MTD same month last year');

        $pdf = view('pdf.wip-review', [
            'report' => $this->report(), 'company' => $this->company, 'scopeLabel' => 'KLCC',
        ])->render();
        $this->assertStringContainsString('Sales performance', $pdf);
        $this->assertStringContainsString('1st – 13th August 2026', $pdf);
    }

    /** The figures from the meeting's own sheet: RM114,038.55 by the 13th against RM300,000. */
    public function test_the_sales_forecast_carries_the_daily_average_over_the_days_left(): void
    {
        $this->mealSale('2026-09-05', 'lunch', 114038.55, 1869);
        SalesTarget::create([
            'company_id' => $this->company->id, 'outlet_id' => null,
            'period' => '2026-09', 'type' => 'monthly', 'target_revenue' => 300000,
        ]);

        $fc = $this->report()['sales_performance']['forecast'];

        $this->assertSame('September 2026', $fc['month_label']);
        $this->assertSame(30, $fc['days_in_month']);
        $this->assertSame(13, $fc['mtd_days']);
        $this->assertSame(17, $fc['days_left']);
        $this->assertEquals(300000, $fc['target']);
        $this->assertSame('company', $fc['target_source']);
        $this->assertEquals(-185961.45, $fc['balance']);
        $this->assertEquals(8772.20, $fc['avg_daily']);
        $this->assertEquals(149127.33, $fc['remaining']);
        $this->assertEquals(263165.88, $fc['forecast']);
        $this->assertEquals(87.7, $fc['forecast_vs_target']);
        $this->assertEquals(10938.91, $fc['needed_daily'], '185,961.45 over the 17 days left.');

        Livewire::actingAs($this->user)->test(WeeklyWipReview::class)
            ->assertSee('Sales forecast — September 2026')
            ->assertSee('(185,961.45)')
            ->assertSee('263,165.88');
    }

    public function test_an_outlet_target_is_preferred_and_a_missing_target_is_said(): void
    {
        $this->mealSale('2026-09-05', 'lunch', 13000, 100);

        $none = $this->report()['sales_performance']['forecast'];
        $this->assertNull($none['target']);
        $this->assertNull($none['balance']);
        $this->assertEquals(30000, $none['forecast'], 'The forecast stands without a target: 1,000 a day for 30 days.');

        SalesTarget::create(['company_id' => $this->company->id, 'outlet_id' => null, 'period' => '2026-09', 'type' => 'monthly', 'target_revenue' => 300000]);
        SalesTarget::create(['company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'period' => '2026-09', 'type' => 'monthly', 'target_revenue' => 40000]);

        $fc = $this->report()['sales_performance']['forecast'];
        $this->assertEquals(40000, $fc['target'], 'With one outlet in view, its own target.');
        $this->assertSame('outlet', $fc['target_source']);
        $this->assertEquals(75.0, $fc['forecast_vs_target']);
    }

    /** Both views offer the PDF, and the PDF breaks wastage down by department. */
    public function test_weekly_and_monthly_both_download_a_pdf_with_wastage_by_department(): void
    {
        $this->seedTwoWeeks();

        foreach (['week' => [], 'month' => ['mode' => 'month', 'month' => '2026-09']] as $mode => $set) {
            $c = Livewire::actingAs($this->user)->test(WeeklyWipReview::class);
            foreach ($set as $k => $v) {
                $c->set($k, $v);
            }

            $c->assertSee('Download PDF')
              ->assertSee(route('reports.weekly-wip-review.pdf', $mode === 'month'
                  ? ['mode' => 'month', 'month' => '2026-09', 'months' => 3]
                  : ['mode' => 'week', 'week' => '2026-09-07', 'weeks' => 8]));

            $pdf = view('pdf.wip-review', [
                'report' => $c->viewData('report'), 'company' => $this->company, 'scopeLabel' => 'KLCC',
            ])->render();

            $this->assertStringContainsString('Wastage by department', $pdf, "{$mode} PDF");
            $this->assertStringContainsString('Kitchen', $pdf);
        }

        $this->actingAs($this->user)
            ->get(route('reports.weekly-wip-review.pdf', ['mode' => 'month', 'month' => '2026-09', 'months' => 3]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_sales_performance_is_weekly_only(): void
    {
        $this->assertNull($this->report(['mode' => 'month'])['sales_performance']);
    }

    public function test_the_report_is_listed_in_the_hub_and_opens(): void
    {
        $this->actingAs($this->user)->get(route('reports.hub'))
            ->assertOk()->assertSee('WIP Review (Weekly / Monthly)');

        $this->actingAs($this->user)->get(route('reports.weekly-wip-review'))
            ->assertOk()->assertSee('Present');
    }
}
