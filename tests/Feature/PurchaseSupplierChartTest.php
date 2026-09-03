<?php

namespace Tests\Feature;

use App\Livewire\Inventory\Index as StockManagement;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\PurchaseCapture;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseSupplierBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The interactive chart on the Purchases tab.
 *
 * Same grouping as the PDF export — PurchaseSupplierBreakdown — but hoverable
 * and clickable: a bar for a linked supplier sets supplierFilter, the same
 * property the dropdown above the table already drives; a hand-typed vendor
 * has no id to filter by, so its bar sets the search box instead. What the
 * chart data itself contains matters more than pixels here, since Chart.js
 * renders it — these tests check the numbers the JS is handed, not the canvas.
 */
class PurchaseSupplierChartTest extends TestCase
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

    private function supplier(string $name): Supplier
    {
        return Supplier::create(['company_id' => $this->company->id, 'name' => $name, 'is_active' => true]);
    }

    private function purchase(array $attributes = []): PurchaseCapture
    {
        return PurchaseCapture::create(array_merge([
            'company_id'    => $this->company->id,
            'outlet_id'     => $this->outlet->id,
            'purchase_date' => now(),
            'amount'        => 100,
            'created_by'    => $this->user->id,
        ], $attributes));
    }

    private function purchasesScreen()
    {
        return Livewire::test(StockManagement::class)
            ->set('tab', 'purchases')
            ->call('setQuickRange', 'all_time');
    }

    // ── What the chart is handed ────────────────────────────────────────

    public function test_the_chart_is_only_built_on_the_purchases_tab(): void
    {
        $component = Livewire::test(StockManagement::class)
            ->set('tab', 'wastage')
            ->call('setQuickRange', 'all_time');

        $this->assertNull($component->viewData('supplierChartData'));
    }

    public function test_bars_are_ranked_biggest_spend_first(): void
    {
        $meat = $this->supplier('Fresh Meats');
        $veg  = $this->supplier('Green Grocer');

        $this->purchase(['supplier_id' => $meat->id, 'amount' => 900]);
        $this->purchase(['supplier_id' => $veg->id, 'amount' => 300]);

        $data = $this->purchasesScreen()->viewData('supplierChartData');

        $this->assertSame(['Fresh Meats', 'Green Grocer'], $data['labels']);
        $this->assertEqualsWithDelta([900.0, 300.0], $data['spend'], 0.001);
        $this->assertEqualsWithDelta(75.0, $data['shares'][0], 0.01);
        $this->assertEqualsWithDelta(1200.0, $data['total'], 0.001);
    }

    public function test_a_linked_supplier_carries_its_id_for_the_click_handler(): void
    {
        $meat = $this->supplier('Fresh Meats');
        $this->purchase(['supplier_id' => $meat->id, 'amount' => 500]);

        $data = $this->purchasesScreen()->viewData('supplierChartData');

        $this->assertSame($meat->id, $data['supplierIds'][0]);
        $this->assertSame('Fresh Meats', $data['names'][0]);
    }

    public function test_a_hand_typed_supplier_carries_a_name_but_no_id(): void
    {
        $this->purchase(['supplier_name' => 'Corner Shop', 'amount' => 500]);

        $data = $this->purchasesScreen()->viewData('supplierChartData');

        $this->assertNull($data['supplierIds'][0]);
        $this->assertSame('Corner Shop', $data['names'][0]);
    }

    public function test_past_the_ninth_supplier_the_rest_fold_into_one_bar(): void
    {
        foreach (range(1, 10) as $i) {
            $supplier = $this->supplier("Supplier {$i}");
            $this->purchase(['supplier_id' => $supplier->id, 'amount' => 1000 - $i]);
        }

        $data = $this->purchasesScreen()->viewData('supplierChartData');

        // 8 named + 1 "Other" bar.
        $this->assertCount(9, $data['labels']);
        $this->assertSame('2 other suppliers', $data['labels'][8]);
        // Folded bar carries neither id nor name — nothing for a click to do.
        $this->assertNull($data['supplierIds'][8]);
        $this->assertNull($data['names'][8]);
        // But the two folded suppliers' spend is still in the total — amounts
        // are 990..999, so the top 8 take 992..999 and the fold is 990+991.
        $this->assertEqualsWithDelta(990 + 991, $data['spend'][8], 0.01);
    }

    public function test_exactly_one_supplier_past_the_cutoff_is_still_clickable(): void
    {
        foreach (range(1, 9) as $i) {
            $supplier = $this->supplier("Supplier {$i}");
            $this->purchase(['supplier_id' => $supplier->id, 'amount' => 1000 - $i]);
        }

        $data = $this->purchasesScreen()->viewData('supplierChartData');

        // One supplier past the cutoff is still one supplier — its own name
        // and id are the "Other" bar's, not a synthetic label.
        $this->assertSame('Supplier 9', $data['labels'][8]);
        $this->assertNotNull($data['supplierIds'][8]);
    }

    public function test_an_empty_range_hands_the_chart_nothing_to_draw(): void
    {
        $data = $this->purchasesScreen()->viewData('supplierChartData');

        $this->assertSame([], $data['labels']);
        $this->assertEqualsWithDelta(0.0, $data['total'], 0.001);
    }

    public function test_the_chart_and_the_pdf_group_suppliers_identically(): void
    {
        // Same grouping class backs both — a linked and a hand-typed capture
        // for the same vendor must land in one bar here exactly as they land
        // in one row in the export.
        $meat = $this->supplier('Fresh Meats');
        $this->purchase(['supplier_id' => $meat->id, 'amount' => 300]);
        $this->purchase(['supplier_name' => 'Fresh Meats', 'amount' => 200]);

        $data = $this->purchasesScreen()->viewData('supplierChartData');

        $this->assertCount(1, $data['labels']);
        $this->assertEqualsWithDelta(500.0, $data['spend'][0], 0.001);
    }

    public function test_chart_data_scopes_to_the_screens_own_filters(): void
    {
        $meat = $this->supplier('Fresh Meats');
        $veg  = $this->supplier('Green Grocer');
        $this->purchase(['supplier_id' => $meat->id, 'amount' => 500]);
        $this->purchase(['supplier_id' => $veg->id, 'amount' => 300]);

        $data = Livewire::test(StockManagement::class)
            ->set('tab', 'purchases')
            ->call('setQuickRange', 'all_time')
            ->set('supplierFilter', (string) $meat->id)
            ->viewData('supplierChartData');

        $this->assertSame(['Fresh Meats'], $data['labels']);
    }

    // ── Clicking a bar ───────────────────────────────────────────────────

    public function test_clicking_a_linked_suppliers_bar_sets_the_dropdown_filter(): void
    {
        $meat = $this->supplier('Fresh Meats');
        $this->purchase(['supplier_id' => $meat->id, 'amount' => 500]);

        $component = $this->purchasesScreen()->call('filterBySupplier', $meat->id, 'Fresh Meats');

        $component->assertSet('supplierFilter', (string) $meat->id);
        $component->assertSet('search', '');
    }

    public function test_clicking_a_hand_typed_suppliers_bar_sets_the_search_box(): void
    {
        $this->purchase(['supplier_name' => 'Corner Shop', 'amount' => 500]);

        $component = $this->purchasesScreen()->call('filterBySupplier', null, 'Corner Shop');

        $component->assertSet('search', 'Corner Shop');
        $component->assertSet('supplierFilter', '');
    }

    public function test_clicking_the_other_bar_with_no_id_or_name_does_nothing(): void
    {
        // The folded "N other suppliers" bar — nothing for a click to narrow
        // the table to, so the filters must be left exactly where they were.
        $component = $this->purchasesScreen()->call('filterBySupplier', null, null);

        $component->assertSet('supplierFilter', '');
        $component->assertSet('search', '');
    }

    public function test_a_click_actually_narrows_the_table_the_same_as_the_dropdown(): void
    {
        $meat = $this->supplier('Fresh Meats');
        $veg  = $this->supplier('Green Grocer');
        $this->purchase(['supplier_id' => $meat->id, 'amount' => 500]);
        $this->purchase(['supplier_id' => $veg->id, 'amount' => 300]);

        $component = $this->purchasesScreen()->call('filterBySupplier', $meat->id, 'Fresh Meats');

        $this->assertSame(1, $component->viewData('records')->total());
    }

    public function test_a_click_resets_pagination(): void
    {
        // filterBySupplier() has to call resetPage() itself: Livewire's
        // updated{Property} hooks only fire for a property the client's own
        // data sync changed, not one an action method set directly — the same
        // reason setQuickRange() calls resetPage() itself for the date filters.
        $meat = $this->supplier('Fresh Meats');
        foreach (range(1, 20) as $i) {
            $this->purchase(['supplier_id' => $meat->id, 'amount' => 10, 'reference_number' => "R{$i}"]);
        }

        $component = $this->purchasesScreen();
        $component->call('nextPage');
        $this->assertSame(2, $component->viewData('records')->currentPage());

        $component->call('filterBySupplier', $meat->id, 'Fresh Meats');
        $this->assertSame(1, $component->viewData('records')->currentPage());
    }

    // ── Consistency with the shared grouping service ────────────────────

    public function test_the_service_backing_the_chart_and_the_export_is_the_same(): void
    {
        $meat = $this->supplier('Fresh Meats');
        $this->purchase(['supplier_id' => $meat->id, 'amount' => 500]);

        $direct = app(PurchaseSupplierBreakdown::class)->summarize(
            PurchaseCapture::where('company_id', $this->company->id)
        );

        $chart = $this->purchasesScreen()->viewData('supplierChartData');

        $this->assertSame($direct[0]['name'], $chart['labels'][0]);
        $this->assertEqualsWithDelta($direct[0]['spend'], $chart['spend'][0], 0.001);
    }
}
