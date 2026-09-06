<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\Recipe;
use App\Models\StaffMealRecord;
use App\Models\StaffMealRecordLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\StaffMealConsolidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Every item served to staff in a range, merged into one report.
 *
 * The Staff Meal Summary export answers "which outlet fed staff the most".
 * This is the detail behind that number — the same relationship
 * WastageDetailExportTest pins for wastage, applied to what staff ate.
 */
class StaffMealDetailExportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet1;
    private Outlet $outlet2;
    private User $user;
    private UnitOfMeasure $kg;
    private UnitOfMeasure $g;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Meal Detail Co', 'slug' => Str::slug('Meal Detail Co') . '-' . uniqid(),
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

    private function entry(Outlet $outlet, string $date = '2026-08-05', ?string $ref = null): StaffMealRecord
    {
        return StaffMealRecord::create([
            'company_id' => $this->company->id, 'outlet_id' => $outlet->id,
            'meal_date' => $date, 'reference_number' => $ref, 'total_cost' => 0,
        ]);
    }

    private function line(StaffMealRecord $entry, Ingredient $ing, float $qty, float $cost, ?UnitOfMeasure $uom = null): void
    {
        StaffMealRecordLine::create([
            'staff_meal_record_id' => $entry->id, 'ingredient_id' => $ing->id,
            'uom_id' => ($uom ?? $this->kg)->id, 'quantity' => $qty, 'unit_cost' => $cost,
            'total_cost' => round($qty * $cost, 4),
        ]);
    }

    private function consolidate(): array
    {
        $records = StaffMealRecord::with(['lines.ingredient.baseUom', 'lines.ingredient.recipeUom', 'lines.ingredient.ingredientCategory.parent', 'lines.recipe.yieldUom'])
            ->orderBy('id')->get();

        return app(StaffMealConsolidator::class)->consolidate($records);
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

    public function test_one_item_served_on_two_entries_appears_once_with_both_quantities(): void
    {
        $chicken = $this->ingredient('Chicken');

        $this->line($this->entry($this->outlet1), $chicken, 4, 10);
        $this->line($this->entry($this->outlet1), $chicken, 6, 10);

        $report = $this->consolidate();
        $item   = $this->itemNamed($report, 'CHICKEN');

        $this->assertSame(1, $report['itemCount']);
        $this->assertSame(10.0, $item['quantity']);
        $this->assertSame(100.0, $item['value']);
        $this->assertSame(2, $item['lines']);
    }

    public function test_quantities_in_different_units_are_converted_before_they_are_added(): void
    {
        $rice = $this->ingredient('Rice', $this->kg);

        $this->line($this->entry($this->outlet1), $rice, 500, 0.01, $this->g);
        $this->line($this->entry($this->outlet1), $rice, 2, 10, $this->kg);

        $item = $this->itemNamed($this->consolidate(), 'RICE');

        $this->assertSame('kg', $item['uom_abbr']);
        $this->assertSame(2.5, $item['quantity']);
        $this->assertSame(25.0, $item['value']);
    }

    public function test_a_recipe_line_is_merged_under_its_own_recipe_and_category(): void
    {
        $curry = Recipe::create([
            'company_id' => $this->company->id, 'name' => 'Staff Curry',
            'yield_uom_id' => $this->kg->id, 'yield_quantity' => 1, 'is_active' => true,
        ]);

        $entry = $this->entry($this->outlet1);
        StaffMealRecordLine::create([
            'staff_meal_record_id' => $entry->id, 'recipe_id' => $curry->id,
            'uom_id' => $this->kg->id, 'quantity' => 2, 'unit_cost' => 8, 'total_cost' => 16,
        ]);

        $report = $this->consolidate();
        $recipesGroup = collect($report['groups'])->firstWhere('name', 'Recipes');

        $this->assertNotNull($recipesGroup);
        $this->assertSame('STAFF CURRY', $recipesGroup['items'][0]['name']);
        $this->assertSame(16.0, $recipesGroup['items'][0]['value']);
    }

    public function test_the_total_is_the_sum_of_every_group(): void
    {
        $dairy = IngredientCategory::create(['company_id' => $this->company->id, 'name' => 'Dairy']);
        $dry   = IngredientCategory::create(['company_id' => $this->company->id, 'name' => 'Dry Goods']);

        $entry = $this->entry($this->outlet1);
        $this->line($entry, $this->ingredient('Milk', null, $dairy->id), 2, 15);
        $this->line($entry, $this->ingredient('Rice', null, $dry->id), 3, 10);

        $report = $this->consolidate();

        $this->assertSame(60.0, $report['total']);
        $this->assertCount(2, $report['groups']);
    }

    // ── The files ────────────────────────────────────────────────────────

    public function test_the_pdf_downloads_for_the_chosen_range(): void
    {
        $chicken = $this->ingredient('Chicken');
        $this->line($this->entry($this->outlet1), $chicken, 4, 10);

        $response = $this->get(route('inventory.staff-meals.detail', ['from' => '2026-08-01', 'to' => '2026-08-31']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('Staff-Meal-Details-2026-08-01-to-2026-08-31', $response->headers->get('content-disposition'));
    }

    public function test_the_excel_downloads(): void
    {
        $chicken = $this->ingredient('Chicken');
        $this->line($this->entry($this->outlet1), $chicken, 4, 10);

        $response = $this->get(route('inventory.staff-meals.detail-excel', ['from' => '2026-08-01', 'to' => '2026-08-31']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('Staff-Meal-Details-2026-08-01-to-2026-08-31.xlsx', $response->headers->get('content-disposition'));
    }

    public function test_a_range_with_no_meals_still_renders(): void
    {
        $this->get(route('inventory.staff-meals.detail', ['from' => '2026-08-01', 'to' => '2026-08-31']))->assertOk();
    }

    public function test_the_outlet_filter_travels_with_it(): void
    {
        $chicken = $this->ingredient('Chicken');
        $this->line($this->entry($this->outlet1), $chicken, 4, 10);
        $this->line($this->entry($this->outlet2), $chicken, 6, 10);

        $response = $this->get(route('inventory.staff-meals.detail', [
            'from' => '2026-08-01', 'to' => '2026-08-31', 'outlet' => (string) $this->outlet1->id,
        ]));

        $response->assertOk();
    }

    public function test_the_buttons_appear_on_the_staff_meals_tab(): void
    {
        $chicken = $this->ingredient('Chicken');
        $this->line($this->entry($this->outlet1), $chicken, 4, 10);

        $html = \Livewire\Livewire::actingAs($this->user)->test(\App\Livewire\Inventory\Index::class)
            ->set('tab', 'staff-meals')->call('setQuickRange', 'all_time')->html();

        $this->assertStringContainsString('staff-meals-details', $html);
        $this->assertStringContainsString('staff-meals-details.xlsx', $html);
    }
}
