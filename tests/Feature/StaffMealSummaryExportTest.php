<?php

namespace Tests\Feature;

use App\Livewire\Inventory\Index as StockManagement;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\StaffMealRecord;
use App\Models\StaffMealRecordLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The Staff Meals tab's "download by filter" pair and its interactive
 * chart — both group by outlet rather than department, since a staff meal
 * is tagged to the outlet only (StaffMealForm doesn't ask for a department).
 */
class StaffMealSummaryExportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet1;
    private Outlet $outlet2;
    private User $user;
    private UnitOfMeasure $kg;
    private Ingredient $flour;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Staff Meal Export Co', 'slug' => Str::slug('Staff Meal Export Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $this->outlet1 = Outlet::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
        $this->outlet2 = Outlet::create(['company_id' => $this->company->id, 'name' => 'Second', 'code' => 'SEC', 'is_active' => true]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet1->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet1->id, $this->outlet2->id]);

        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo(Permission::findOrCreate('inventory.view', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->kg    = UnitOfMeasure::create(['name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'weight']);
        $this->flour = Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'Flour',
            'base_uom_id' => $this->kg->id, 'recipe_uom_id' => $this->kg->id,
            'current_cost' => 5, 'is_active' => true,
        ]);

        $this->actingAs($this->user);
    }

    private function meal(Outlet $outlet, float $cost, string $date = '2026-08-05'): StaffMealRecord
    {
        $record = StaffMealRecord::create([
            'company_id' => $this->company->id, 'outlet_id' => $outlet->id,
            'meal_date' => $date, 'reference_number' => 'SM-' . uniqid(), 'total_cost' => $cost,
        ]);

        StaffMealRecordLine::create([
            'staff_meal_record_id' => $record->id, 'ingredient_id' => $this->flour->id, 'uom_id' => $this->kg->id,
            'quantity' => $cost / 5, 'unit_cost' => 5, 'total_cost' => $cost,
        ]);

        return $record;
    }

    // ── The exports ──────────────────────────────────────────────────────

    public function test_the_pdf_downloads_for_the_chosen_range(): void
    {
        $this->meal($this->outlet1, 30);

        $response = $this->get(route('inventory.staff-meals.summary', ['from' => '2026-08-01', 'to' => '2026-08-31']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_excel_downloads(): void
    {
        $this->meal($this->outlet1, 30);

        $response = $this->get(route('inventory.staff-meals.summary-excel', ['from' => '2026-08-01', 'to' => '2026-08-31']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_groups_are_ranked_by_outlet_and_biggest_cost_first(): void
    {
        $this->meal($this->outlet1, 30);
        $this->meal($this->outlet2, 15);

        $controller = app(\App\Http\Controllers\StaffMealSummaryController::class);
        $data = $this->invokeLoad($controller, ['from' => '2026-08-01', 'to' => '2026-08-31']);

        $this->assertSame(['Main', 'Second'], array_column($data['groups'], 'name'));
        $this->assertEqualsWithDelta(45.0, $data['totals']['value'], 0.001);
    }

    // ── The chart ─────────────────────────────────────────────────────────

    public function test_the_chart_is_only_built_on_staff_meals(): void
    {
        foreach (['stock-takes', 'wastage', 'transfers', 'purchases'] as $tab) {
            $this->assertNull(
                Livewire::test(StockManagement::class)->set('tab', $tab)->call('setQuickRange', 'all_time')
                    ->viewData('outletChartData'),
                "{$tab} should not build the staff-meal outlet chart."
            );
        }
    }

    public function test_chart_bars_are_ranked_by_outlet(): void
    {
        $this->meal($this->outlet1, 30);
        $this->meal($this->outlet2, 15);

        $data = Livewire::test(StockManagement::class)
            ->set('tab', 'staff-meals')->call('setQuickRange', 'all_time')
            ->viewData('outletChartData');

        $this->assertSame(['Main', 'Second'], $data['labels']);
        $this->assertEqualsWithDelta([30.0, 15.0], $data['values'], 0.001);
        $this->assertSame([$this->outlet1->id, $this->outlet2->id], $data['outletIds']);
        $this->assertSame('staff meal', $data['noun']);
    }

    public function test_clicking_a_bar_sets_the_outlet_dropdown_filter(): void
    {
        $this->meal($this->outlet1, 30);

        $component = Livewire::test(StockManagement::class)
            ->set('tab', 'staff-meals')->call('setQuickRange', 'all_time')
            ->call('filterByOutletChart', $this->outlet1->id);

        $component->assertSet('outletFilter', (string) $this->outlet1->id);
    }

    public function test_clicking_the_other_bar_with_no_id_does_nothing(): void
    {
        $component = Livewire::test(StockManagement::class)
            ->set('tab', 'staff-meals')->call('setQuickRange', 'all_time')
            ->call('filterByOutletChart', null);

        $component->assertSet('outletFilter', '');
    }

    /** Invoke the protected load() the way the __invoke() methods do, without a full HTTP round trip. */
    private function invokeLoad(object $controller, array $query): array
    {
        $request = \Illuminate\Http\Request::create('/', 'GET', $query);
        $method  = new \ReflectionMethod($controller, 'load');
        $method->setAccessible(true);

        return $method->invoke($controller, $request);
    }
}
