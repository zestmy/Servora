<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\Recipe;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WastageRecord;
use App\Models\WastageRecordLine;
use App\Services\WastageConsolidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Every wasted item in a range, merged into one loss report.
 *
 * The Wastage Summary export answers "which department threw out the most".
 * This is the detail behind that number — the same relationship
 * ConsolidatedStockTakeTest pins for stock takes, applied to loss instead of
 * stock on hand. A wastage line can name either an ingredient or a recipe, so
 * both are covered here.
 */
class WastageDetailExportTest extends TestCase
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
            'name' => 'Detail Co', 'slug' => Str::slug('Detail Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);
        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo(Permission::findOrCreate('inventory.view', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->kg = UnitOfMeasure::create(['name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'weight', 'base_unit_factor' => 1000]);
        $this->g  = UnitOfMeasure::create(['name' => 'Gram', 'abbreviation' => 'g', 'type' => 'weight', 'base_unit_factor' => 1]);

        $this->actingAs($this->user);
    }

    private function ingredient(string $name, ?UnitOfMeasure $recipeUom = null, ?int $categoryId = null): Ingredient
    {
        return Ingredient::create([
            'company_id' => $this->company->id, 'name' => $name,
            'base_uom_id' => $this->kg->id, 'recipe_uom_id' => ($recipeUom ?? $this->kg)->id,
            'ingredient_category_id' => $categoryId,
            'current_cost' => 10, 'is_active' => true,
        ]);
    }

    private function note(?Department $dept = null, string $date = '2026-08-05', ?string $ref = null): WastageRecord
    {
        return WastageRecord::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'department_id' => $dept?->id,
            'wastage_date' => $date, 'reference_number' => $ref, 'total_cost' => 0,
        ]);
    }

    private function line(WastageRecord $note, Ingredient $ing, float $qty, float $cost, ?UnitOfMeasure $uom = null, ?string $reason = null): void
    {
        WastageRecordLine::create([
            'wastage_record_id' => $note->id, 'ingredient_id' => $ing->id,
            'uom_id' => ($uom ?? $this->kg)->id, 'quantity' => $qty, 'unit_cost' => $cost,
            'total_cost' => round($qty * $cost, 4), 'reason' => $reason,
        ]);
    }

    private function consolidate(): array
    {
        $records = WastageRecord::with(['lines.ingredient.baseUom', 'lines.ingredient.recipeUom', 'lines.ingredient.ingredientCategory.parent', 'lines.recipe.yieldUom'])
            ->orderBy('id')->get();

        return app(WastageConsolidator::class)->consolidate($records);
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

    public function test_one_item_wasted_on_two_notes_appears_once_with_both_quantities(): void
    {
        $flour = $this->ingredient('Flour');

        $this->line($this->note(), $flour, 4, 10, null, 'Spoiled');
        $this->line($this->note(), $flour, 6, 10, null, 'Dropped');

        $report = $this->consolidate();
        $item   = $this->itemNamed($report, 'FLOUR');

        $this->assertSame(1, $report['itemCount'], 'The same item on two notes is still one item.');
        $this->assertSame(10.0, $item['quantity']);
        $this->assertSame(100.0, $item['value']);
        $this->assertSame(2, $item['lines'], 'The count of contributing notes is worth showing.');
    }

    public function test_quantities_in_different_units_are_converted_before_they_are_added(): void
    {
        $flour = $this->ingredient('Flour', $this->kg);

        $this->line($this->note(), $flour, 500, 0.01, $this->g);
        $this->line($this->note(), $flour, 2, 10, $this->kg);

        $item = $this->itemNamed($this->consolidate(), 'FLOUR');

        $this->assertSame('kg', $item['uom_abbr']);
        $this->assertSame(2.5, $item['quantity']);
        $this->assertSame(25.0, $item['value'], '500g at RM0.01 is RM5, plus 2kg at RM10 is RM20.');
    }

    public function test_the_unit_cost_is_the_rate_the_value_implies(): void
    {
        $oil = $this->ingredient('Oil');

        $this->line($this->note(), $oil, 10, 5.00);
        $this->line($this->note(), $oil, 10, 7.00);

        $item = $this->itemNamed($this->consolidate(), 'OIL');

        $this->assertSame(20.0, $item['quantity']);
        $this->assertSame(120.0, $item['value']);
        $this->assertSame(6.0, $item['unit_cost']);
    }

    public function test_reasons_are_collected_from_every_contributing_line(): void
    {
        $flour = $this->ingredient('Flour');

        $this->line($this->note(), $flour, 4, 10, null, 'Spoiled');
        $this->line($this->note(), $flour, 6, 10, null, 'Dropped');
        $this->line($this->note(), $flour, 2, 10, null, 'Spoiled'); // duplicate reason

        $item = $this->itemNamed($this->consolidate(), 'FLOUR');

        $this->assertEqualsCanonicalizing(['Spoiled', 'Dropped'], $item['reasons']);
    }

    public function test_a_recipe_line_is_merged_under_its_own_recipe_and_category(): void
    {
        $sauce = Recipe::create([
            'company_id' => $this->company->id, 'name' => 'House Sauce',
            'yield_uom_id' => $this->kg->id, 'yield_quantity' => 1, 'is_active' => true,
        ]);

        $note = $this->note();
        WastageRecordLine::create([
            'wastage_record_id' => $note->id, 'recipe_id' => $sauce->id,
            'uom_id' => $this->kg->id, 'quantity' => 2, 'unit_cost' => 8,
            'total_cost' => 16, 'reason' => 'Expired',
        ]);

        $report = $this->consolidate();
        $recipesGroup = collect($report['groups'])->firstWhere('name', 'Recipes');

        $this->assertNotNull($recipesGroup, 'Recipe lines land in their own group.');
        $this->assertSame('HOUSE SAUCE', $recipesGroup['items'][0]['name']);
        $this->assertSame(16.0, $recipesGroup['items'][0]['value']);
    }

    public function test_the_total_is_the_sum_of_every_group(): void
    {
        $dairy = IngredientCategory::create(['company_id' => $this->company->id, 'name' => 'Dairy']);
        $dry   = IngredientCategory::create(['company_id' => $this->company->id, 'name' => 'Dry Goods']);

        $note = $this->note();
        $this->line($note, $this->ingredient('Butter', null, $dairy->id), 2, 15);
        $this->line($note, $this->ingredient('Flour', null, $dry->id), 3, 10);

        $report = $this->consolidate();

        $this->assertSame(60.0, $report['total']);
        $this->assertCount(2, $report['groups']);
    }

    public function test_the_report_only_covers_the_range_asked_for(): void
    {
        $flour = $this->ingredient('Flour');
        $this->line($this->note(null, '2026-08-10'), $flour, 5, 10);
        $this->line($this->note(null, '2026-09-10'), $flour, 99, 10);

        $records = WastageRecord::with(['lines.ingredient.baseUom', 'lines.ingredient.recipeUom', 'lines.ingredient.ingredientCategory.parent', 'lines.recipe.yieldUom'])
            ->whereBetween('wastage_date', ['2026-08-01', '2026-08-31'])->get();

        $report = app(WastageConsolidator::class)->consolidate($records);

        $this->assertSame(50.0, $report['total'], 'September must not be in an August file.');
    }

    // ── The files ────────────────────────────────────────────────────────

    public function test_the_pdf_downloads_for_the_chosen_range(): void
    {
        $flour = $this->ingredient('Flour');
        $this->line($this->note(), $flour, 4, 10);

        $response = $this->get(route('inventory.wastage.detail', ['from' => '2026-08-01', 'to' => '2026-08-31']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('Wastage-Details-2026-08-01-to-2026-08-31', $response->headers->get('content-disposition'));
    }

    public function test_the_excel_downloads(): void
    {
        $flour = $this->ingredient('Flour');
        $this->line($this->note(), $flour, 4, 10);

        $response = $this->get(route('inventory.wastage.detail-excel', ['from' => '2026-08-01', 'to' => '2026-08-31']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('Wastage-Details-2026-08-01-to-2026-08-31.xlsx', $response->headers->get('content-disposition'));
    }

    public function test_a_range_with_no_wastage_still_renders(): void
    {
        $this->get(route('inventory.wastage.detail', ['from' => '2026-08-01', 'to' => '2026-08-31']))->assertOk();
    }

    public function test_the_department_filter_travels_with_it(): void
    {
        $hot = Department::create(['company_id' => $this->company->id, 'name' => 'Hot Kitchen']);
        $bar = Department::create(['company_id' => $this->company->id, 'name' => 'Bar']);
        $flour = $this->ingredient('Flour');

        $this->line($this->note($hot), $flour, 4, 10);
        $this->line($this->note($bar), $flour, 6, 10);

        $response = $this->get(route('inventory.wastage.detail', [
            'from' => '2026-08-01', 'to' => '2026-08-31', 'department' => (string) $hot->id,
        ]));

        $response->assertOk();
    }

    public function test_the_buttons_appear_on_the_wastage_tab(): void
    {
        $flour = $this->ingredient('Flour');
        $this->line($this->note(), $flour, 4, 10);

        $html = \Livewire\Livewire::actingAs($this->user)->test(\App\Livewire\Inventory\Index::class)
            ->set('tab', 'wastage')->call('setQuickRange', 'all_time')->html();

        $this->assertStringContainsString('wastage-details', $html);
        $this->assertStringContainsString('wastage-details.xlsx', $html);
    }
}
