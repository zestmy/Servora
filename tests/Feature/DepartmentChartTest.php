<?php

namespace Tests\Feature;

use App\Livewire\Inventory\Index as StockManagement;
use App\Models\Company;
use App\Models\Department;
use App\Models\Outlet;
use App\Models\StockTake;
use App\Models\User;
use App\Models\WastageRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The interactive department chart on the Stock Takes and Wastage tabs.
 *
 * Same pattern as the Purchases-by-Supplier chart: a bar sets departmentFilter,
 * the same property the dropdown above the table already drives. Department
 * has no free-text alternative the way a supplier does, so the shape is
 * simpler — but "No department" still has to be its own clickable bar, since
 * the filter already supports that value.
 */
class DepartmentChartTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Chart Co', 'slug' => Str::slug('Chart Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo(Permission::findOrCreate('inventory.view', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->user);
    }

    private function department(string $name): Department
    {
        return Department::create(['company_id' => $this->company->id, 'name' => $name, 'is_active' => true]);
    }

    private function stockTake(?Department $dept, float $value): StockTake
    {
        return StockTake::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'department_id' => $dept?->id,
            'status' => 'completed', 'method' => 'summary',
            'stock_take_date' => now(), 'total_stock_cost' => $value, 'total_variance_cost' => 0,
        ]);
    }

    private function wastage(?Department $dept, float $cost): WastageRecord
    {
        return WastageRecord::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'department_id' => $dept?->id,
            'wastage_date' => now(), 'total_cost' => $cost,
        ]);
    }

    private function screen(string $tab)
    {
        return Livewire::test(StockManagement::class)
            ->set('tab', $tab)
            ->call('setQuickRange', 'all_time');
    }

    // ── What the chart is handed ────────────────────────────────────────

    public function test_the_chart_is_only_built_on_stock_takes_and_wastage(): void
    {
        foreach (['purchases', 'staff-meals', 'transfers'] as $tab) {
            $this->assertNull(
                $this->screen($tab)->viewData('departmentChartData'),
                "{$tab} should not build the department chart."
            );
        }
    }

    public function test_bars_are_ranked_biggest_value_first(): void
    {
        $hot = $this->department('Hot Kitchen');
        $bar = $this->department('Bar');
        $this->stockTake($hot, 900);
        $this->stockTake($bar, 300);

        $data = $this->screen('stock-takes')->viewData('departmentChartData');

        $this->assertSame(['Hot Kitchen', 'Bar'], $data['labels']);
        $this->assertEqualsWithDelta([900.0, 300.0], $data['values'], 0.001);
        $this->assertEqualsWithDelta(75.0, $data['shares'][0], 0.01);
        $this->assertEqualsWithDelta(1200.0, $data['total'], 0.001);
    }

    public function test_a_department_carries_its_id_for_the_click_handler(): void
    {
        $hot = $this->department('Hot Kitchen');
        $this->stockTake($hot, 500);

        $data = $this->screen('stock-takes')->viewData('departmentChartData');

        $this->assertSame($hot->id, $data['departmentIds'][0]);
    }

    public function test_no_department_is_its_own_clickable_bar(): void
    {
        $this->stockTake(null, 500);

        $data = $this->screen('stock-takes')->viewData('departmentChartData');

        $this->assertSame('No department', $data['labels'][0]);
        $this->assertSame('none', $data['departmentIds'][0]);
    }

    public function test_past_the_eighth_department_the_rest_fold_into_one_bar(): void
    {
        foreach (range(1, 9) as $i) {
            $dept = $this->department("Dept {$i}");
            $this->stockTake($dept, 1000 - $i);
        }

        $data = $this->screen('stock-takes')->viewData('departmentChartData');

        // 8 named + 1 "Other" bar.
        $this->assertCount(9, $data['labels']);
        // Exactly one department past the cutoff is still one department —
        // its own name and id, not a synthetic "N other" label.
        $this->assertSame('Dept 9', $data['labels'][8]);
        $this->assertNotNull($data['departmentIds'][8]);
    }

    public function test_two_or_more_past_the_cutoff_fold_with_no_id(): void
    {
        foreach (range(1, 10) as $i) {
            $dept = $this->department("Dept {$i}");
            $this->stockTake($dept, 1000 - $i);
        }

        $data = $this->screen('stock-takes')->viewData('departmentChartData');

        $this->assertSame('2 other departments', $data['labels'][8]);
        $this->assertNull($data['departmentIds'][8]);
        $this->assertEqualsWithDelta(990 + 991, $data['values'][8], 0.01);
    }

    public function test_wastage_uses_its_own_amount_column(): void
    {
        $hot = $this->department('Hot Kitchen');
        $this->wastage($hot, 250);

        $data = $this->screen('wastage')->viewData('departmentChartData');

        $this->assertSame(['Hot Kitchen'], $data['labels']);
        $this->assertEqualsWithDelta(250.0, $data['values'][0], 0.001);
        $this->assertSame('wastage record', $data['noun']);
    }

    public function test_stock_takes_reports_its_own_noun(): void
    {
        $hot = $this->department('Hot Kitchen');
        $this->stockTake($hot, 100);

        $data = $this->screen('stock-takes')->viewData('departmentChartData');

        $this->assertSame('stock take', $data['noun']);
    }

    public function test_an_empty_range_hands_the_chart_nothing_to_draw(): void
    {
        $data = $this->screen('stock-takes')->viewData('departmentChartData');

        $this->assertSame([], $data['labels']);
        $this->assertEqualsWithDelta(0.0, $data['total'], 0.001);
    }

    public function test_chart_data_scopes_to_the_screens_own_filters(): void
    {
        $hot = $this->department('Hot Kitchen');
        $bar = $this->department('Bar');
        $this->stockTake($hot, 500);
        $this->stockTake($bar, 300);

        $data = Livewire::test(StockManagement::class)
            ->set('tab', 'stock-takes')
            ->call('setQuickRange', 'all_time')
            ->set('departmentFilter', (string) $hot->id)
            ->viewData('departmentChartData');

        $this->assertSame(['Hot Kitchen'], $data['labels']);
    }

    // ── Clicking a bar ───────────────────────────────────────────────────

    public function test_clicking_a_departments_bar_sets_the_dropdown_filter(): void
    {
        $hot = $this->department('Hot Kitchen');
        $this->stockTake($hot, 500);

        $component = $this->screen('stock-takes')->call('filterByDepartment', $hot->id);

        $component->assertSet('departmentFilter', (string) $hot->id);
    }

    public function test_clicking_the_no_department_bar_sets_the_none_filter(): void
    {
        $this->stockTake(null, 500);

        $component = $this->screen('stock-takes')->call('filterByDepartment', 'none');

        $component->assertSet('departmentFilter', 'none');
    }

    public function test_clicking_the_other_bar_with_no_id_does_nothing(): void
    {
        $component = $this->screen('stock-takes')->call('filterByDepartment', null);

        $component->assertSet('departmentFilter', '');
    }

    public function test_a_click_actually_narrows_the_table_the_same_as_the_dropdown(): void
    {
        $hot = $this->department('Hot Kitchen');
        $bar = $this->department('Bar');
        $this->stockTake($hot, 500);
        $this->stockTake($bar, 300);

        $component = $this->screen('stock-takes')->call('filterByDepartment', $hot->id);

        $this->assertSame(1, $component->viewData('records')->total());
    }

    public function test_a_click_resets_pagination(): void
    {
        $hot = $this->department('Hot Kitchen');
        foreach (range(1, 20) as $i) {
            $this->stockTake($hot, 10);
        }

        $component = $this->screen('stock-takes');
        $component->call('nextPage');
        $this->assertSame(2, $component->viewData('records')->currentPage());

        $component->call('filterByDepartment', $hot->id);
        $this->assertSame(1, $component->viewData('records')->currentPage());
    }
}
