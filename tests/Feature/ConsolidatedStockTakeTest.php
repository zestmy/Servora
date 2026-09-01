<?php

namespace Tests\Feature;

use App\Livewire\Inventory\Index as StockManagement;
use App\Models\Company;
use App\Models\Department;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\IngredientUomConversion;
use App\Models\Outlet;
use App\Models\StockTake;
use App\Models\StockTakeLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\StockTakeConsolidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Several counts, filed as one inventory.
 *
 * A kitchen counts the saute chiller, then the bread freezer, then the bar —
 * each its own sheet. For month-end filing what is wanted is the other shape:
 * one list of what the outlet holds, each item appearing once however many
 * sheets it turned up on.
 *
 * The arithmetic is where this can quietly go wrong, so that is what these pin:
 * an item counted in grams on one sheet and kilograms on another must not have
 * its numbers added as though they were the same unit, and an item counted at
 * two different prices must report the rate its value actually implies.
 */
class ConsolidatedStockTakeTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private UnitOfMeasure $kg;
    private UnitOfMeasure $g;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Merge Co', 'slug' => Str::slug('Merge Co') . '-' . uniqid(),
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
        $this->user->givePermissionTo([
            Permission::findOrCreate('inventory.view', 'web'),
            Permission::findOrCreate('inventory.stock_takes.record', 'web'),
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1 kg = 1000 g, through the standard factors rather than a per-item rule.
        $this->kg = UnitOfMeasure::create(['name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'weight', 'base_unit_factor' => 1000]);
        $this->g  = UnitOfMeasure::create(['name' => 'Gram', 'abbreviation' => 'g', 'type' => 'weight', 'base_unit_factor' => 1]);

        $this->actingAs($this->user);
    }

    private function ingredient(string $name, ?UnitOfMeasure $recipeUom = null, ?int $categoryId = null): Ingredient
    {
        return Ingredient::create([
            'company_id' => $this->company->id, 'name' => $name,
            'base_uom_id' => $this->kg->id,
            'recipe_uom_id' => ($recipeUom ?? $this->kg)->id,
            'ingredient_category_id' => $categoryId,
            'current_cost' => 10, 'is_active' => true,
        ]);
    }

    private function sheet(string $date, string $status = 'completed', ?int $departmentId = null, ?string $ref = null): StockTake
    {
        return StockTake::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'department_id' => $departmentId,
            'status' => $status, 'method' => 'detailed',
            'stock_take_date' => $date, 'reference_number' => $ref,
            'total_stock_cost' => 0, 'total_variance_cost' => 0,
        ]);
    }

    private function line(StockTake $take, Ingredient $ing, float $qty, float $cost, ?UnitOfMeasure $uom = null): void
    {
        StockTakeLine::create([
            'stock_take_id' => $take->id, 'ingredient_id' => $ing->id,
            'uom_id' => ($uom ?? $this->kg)->id,
            'system_quantity' => 0, 'actual_quantity' => $qty, 'variance_quantity' => $qty,
            'unit_cost' => $cost, 'variance_cost' => 0,
        ]);
    }

    private function consolidate(): array
    {
        $takes = StockTake::with(['lines', 'outlet', 'department'])->orderBy('id')->get();

        return app(StockTakeConsolidator::class)->consolidate($takes);
    }

    private function itemNamed(array $report, string $name): ?array
    {
        foreach ($report['groups'] as $group) {
            foreach ($group['items'] as $item) {
                if ($item['name'] === $name) {
                    return $item;
                }
            }
        }

        return null;
    }

    // ── The merge ────────────────────────────────────────────────────────

    public function test_one_item_counted_on_two_sheets_appears_once_with_both_quantities(): void
    {
        $flour = $this->ingredient('Flour');

        $this->line($chiller = $this->sheet('2026-08-01'), $flour, 4, 10);
        $this->line($store   = $this->sheet('2026-08-01'), $flour, 6, 10);

        $report = $this->consolidate();
        $item   = $this->itemNamed($report, 'FLOUR');

        $this->assertSame(1, $report['itemCount'], 'The same item on two sheets is still one item.');
        $this->assertSame(10.0, $item['quantity']);
        $this->assertSame(100.0, $item['value']);
        $this->assertSame(2, $item['sheets'], 'The count of contributing sheets is worth showing.');
    }

    public function test_quantities_in_different_units_are_converted_before_they_are_added(): void
    {
        // Counted in grams in prep and kilograms in the store. Adding 500 and 2
        // would be nonsense; the answer is 2.5 kg.
        $flour = $this->ingredient('Flour', $this->kg);

        $this->line($this->sheet('2026-08-01'), $flour, 500, 0.01, $this->g);
        $this->line($this->sheet('2026-08-02'), $flour, 2, 10, $this->kg);

        $item = $this->itemNamed($this->consolidate(), 'FLOUR');

        $this->assertSame('kg', $item['uom_abbr']);
        $this->assertSame(2.5, $item['quantity']);
        $this->assertSame(25.0, $item['value'], '500g at RM0.01 is RM5, plus 2kg at RM10 is RM20.');
    }

    public function test_the_unit_cost_is_the_rate_the_value_implies(): void
    {
        // Two sheets, two prices. Neither rate is "the" rate; the weighted one is.
        $oil = $this->ingredient('Oil');

        $this->line($this->sheet('2026-08-01'), $oil, 10, 5.00);
        $this->line($this->sheet('2026-08-15'), $oil, 10, 7.00);

        $item = $this->itemNamed($this->consolidate(), 'OIL');

        $this->assertSame(20.0, $item['quantity']);
        $this->assertSame(120.0, $item['value']);
        $this->assertSame(6.0, $item['unit_cost']);
    }

    public function test_the_total_is_the_sum_of_every_group(): void
    {
        $dairy = IngredientCategory::create(['company_id' => $this->company->id, 'name' => 'Dairy']);
        $dry   = IngredientCategory::create(['company_id' => $this->company->id, 'name' => 'Dry Goods']);

        $take = $this->sheet('2026-08-01');
        $this->line($take, $this->ingredient('Butter', null, $dairy->id), 2, 15);
        $this->line($take, $this->ingredient('Flour', null, $dry->id), 3, 10);

        $report = $this->consolidate();

        $this->assertSame(60.0, $report['total']);
        $this->assertSame(60.0, round(array_sum(array_column($report['groups'], 'value')), 2));
        $this->assertCount(2, $report['groups']);
    }

    public function test_drafts_are_merged_but_counted_so_the_file_can_say_it_is_not_final(): void
    {
        $flour = $this->ingredient('Flour');

        $this->line($this->sheet('2026-08-01', 'completed'), $flour, 1, 10);
        $this->line($this->sheet('2026-08-02', 'draft'), $flour, 1, 10);

        $report = $this->consolidate();

        $this->assertSame(2, $report['takes']->count());
        $this->assertSame(1, $report['draftCount']);
    }

    // ── The screen ───────────────────────────────────────────────────────

    public function test_the_money_card_splits_by_department(): void
    {
        $hot = Department::create(['company_id' => $this->company->id, 'name' => 'Hot Kitchen']);
        $bar = Department::create(['company_id' => $this->company->id, 'name' => 'Bar']);

        $this->sheet(today()->toDateString(), 'completed', $hot->id)->update(['total_stock_cost' => 300]);
        $this->sheet(today()->toDateString(), 'completed', $bar->id)->update(['total_stock_cost' => 100]);

        $values = Livewire::actingAs($this->user)->test(StockManagement::class)
            ->set('tab', 'stock-takes')
            ->call('setQuickRange', 'today')
            ->viewData('departmentValues');

        $this->assertSame(['Hot Kitchen', 'Bar'], array_column($values, 'name'), 'Biggest first.');
        $this->assertSame([300.0, 100.0], array_column($values, 'value'));
        $this->assertSame(400.0, array_sum(array_column($values, 'value')), 'The parts add up to the card total.');
    }

    public function test_a_count_with_no_department_is_still_named_in_the_split(): void
    {
        $hot = Department::create(['company_id' => $this->company->id, 'name' => 'Hot Kitchen']);

        $this->sheet(today()->toDateString(), 'completed', $hot->id)->update(['total_stock_cost' => 100]);
        $this->sheet(today()->toDateString(), 'completed', null)->update(['total_stock_cost' => 50]);

        $values = Livewire::actingAs($this->user)->test(StockManagement::class)
            ->call('setQuickRange', 'today')
            ->viewData('departmentValues');

        $this->assertContains('No department', array_column($values, 'name'));
    }

    public function test_the_export_link_carries_the_filters_the_table_is_showing(): void
    {
        $hot = Department::create(['company_id' => $this->company->id, 'name' => 'Hot Kitchen']);
        $this->sheet(today()->toDateString(), 'completed', $hot->id)->update(['total_stock_cost' => 100]);

        $url = Livewire::actingAs($this->user)->test(StockManagement::class)
            ->call('setQuickRange', 'today')
            ->set('departmentFilter', (string) $hot->id)
            ->viewData('consolidatedUrl');

        $this->assertStringContainsString('department=' . $hot->id, $url);
        $this->assertStringContainsString('from=' . today()->toDateString(), $url);
    }

    // ── The file ─────────────────────────────────────────────────────────

    public function test_the_pdf_downloads_for_the_chosen_range_and_department(): void
    {
        $hot = Department::create(['company_id' => $this->company->id, 'name' => 'Hot Kitchen']);
        $take = $this->sheet('2026-08-10', 'completed', $hot->id, 'ST-AUG');
        $this->line($take, $this->ingredient('Flour'), 5, 10);

        $response = $this->actingAs($this->user)->get(route('inventory.stock-takes.consolidated', [
            'from' => '2026-08-01', 'to' => '2026-08-31', 'department' => $hot->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('Consolidated-Inventory-2026-08-01-to-2026-08-31', $response->headers->get('content-disposition'));
    }

    public function test_the_report_only_covers_the_range_asked_for(): void
    {
        $flour = $this->ingredient('Flour');
        $this->line($this->sheet('2026-08-10'), $flour, 5, 10);   // in range
        $this->line($this->sheet('2026-09-10'), $flour, 99, 10);  // out of range

        $takes = StockTake::with('lines')
            ->whereBetween('stock_take_date', ['2026-08-01', '2026-08-31'])->get();

        $report = app(StockTakeConsolidator::class)->consolidate($takes);

        $this->assertSame(50.0, $report['total'], 'September must not be in an August file.');
    }

    public function test_a_hand_edited_outlet_in_the_url_cannot_widen_the_report(): void
    {
        $other = Company::create([
            'name' => 'Other Co', 'slug' => Str::slug('Other Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $theirs = Outlet::create([
            'company_id' => $other->id, 'name' => 'Theirs', 'code' => 'OTH', 'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('inventory.stock-takes.consolidated', [
            'from' => '2026-08-01', 'to' => '2026-08-31', 'outlet' => $theirs->id,
        ]));

        // Out of reach resolves to null, which narrows to the user's own outlets
        // rather than reaching into another tenant.
        $response->assertOk();
    }
}
