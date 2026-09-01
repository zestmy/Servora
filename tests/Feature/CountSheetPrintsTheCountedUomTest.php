<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\IngredientUomConversion;
use App\Models\Outlet;
use App\Models\StockTake;
use App\Models\StockTakeLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The sheet must ask for the unit the count is recorded in.
 *
 * It printed the ingredient's BASE uom — what the item is bought in. A stock
 * take records in the RECIPE uom, so for anything bought by the kilogram and
 * counted in grams the paper asked for one thing and the form expected another.
 * Whoever filled it in wrote a number a thousand times off, and since the line
 * is priced per counted unit the value came back wrong by the same factor.
 *
 * The paper twin of the draft-pricing bug: both came from reading a unit off
 * the ingredient instead of off the line.
 *
 * @see StockTakeDraftKeepsCountUomPricingTest
 */
class CountSheetPrintsTheCountedUomTest extends TestCase
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
            'name' => 'Sheet Co', 'slug' => Str::slug('Sheet Co') . '-' . uniqid(),
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

        $this->kg = UnitOfMeasure::create(['name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'weight', 'base_unit_factor' => 1000]);
        $this->g  = UnitOfMeasure::create(['name' => 'Gram', 'abbreviation' => 'g', 'type' => 'weight', 'base_unit_factor' => 1]);
    }

    /** Bought by the kilogram, counted by the gram — the shape that broke. */
    private function flour(): Ingredient
    {
        $flour = Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'Flour',
            'base_uom_id' => $this->kg->id, 'recipe_uom_id' => $this->g->id,
            'current_cost' => 3.00, 'is_active' => true,
        ]);

        IngredientUomConversion::create([
            'ingredient_id' => $flour->id,
            'from_uom_id' => $this->kg->id, 'to_uom_id' => $this->g->id, 'factor' => 1000,
        ]);

        return $flour;
    }

    private function sheetFor(Ingredient $ingredient, ?UnitOfMeasure $lineUom = null): StockTake
    {
        $take = StockTake::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'status' => 'draft', 'method' => 'detailed',
            'stock_take_date' => today()->toDateString(), 'reference_number' => 'ST-1',
            'total_stock_cost' => 0, 'total_variance_cost' => 0,
        ]);

        StockTakeLine::create([
            'stock_take_id' => $take->id, 'ingredient_id' => $ingredient->id,
            'uom_id' => ($lineUom ?? $this->g)->id,
            'system_quantity' => 0, 'actual_quantity' => 0, 'variance_quantity' => 0,
            'unit_cost' => 0.003, 'variance_cost' => 0,
        ]);

        return $take;
    }

    /** The UOM column of the rendered sheet, without the PDF wrapper. */
    private function renderedUoms(StockTake $take): array
    {
        $take->load(['lines.uom', 'lines.ingredient.baseUom', 'lines.ingredient.recipeUom',
            'lines.ingredient.ingredientCategory.parent', 'outlet', 'department', 'createdBy']);

        $html = view('pdf.stock-take-count-sheet', [
            'stockTake' => $take,
            'company'   => Company::find($this->company->id),
            'groupedLines' => $take->lines->groupBy(fn () => 'All'),
        ])->render();

        preg_match_all('/<td class="center">\s*([A-Za-z]*)\s*<\/td>/', $html, $m);

        return array_values(array_filter(array_map('trim', $m[1])));
    }

    public function test_the_sheet_asks_for_the_unit_the_count_is_recorded_in(): void
    {
        $take = $this->sheetFor($this->flour());

        $this->assertContains('g', $this->renderedUoms($take),
            'The line is counted in grams, so the sheet must say grams.');
    }

    public function test_the_sheet_does_not_ask_for_the_unit_the_item_is_bought_in(): void
    {
        $take = $this->sheetFor($this->flour());

        $this->assertNotContains('kg', $this->renderedUoms($take),
            'kg is the purchase unit. Printing it asks for a number a thousand times off.');
    }

    public function test_a_line_counted_in_the_purchase_unit_still_prints_that_unit(): void
    {
        // Where the two units are the same thing, nothing should change.
        $take = $this->sheetFor($this->flour(), $this->kg);

        $this->assertContains('kg', $this->renderedUoms($take));
    }

    public function test_the_sheet_still_downloads(): void
    {
        $take = $this->sheetFor($this->flour());

        $response = $this->actingAs($this->user)
            ->get(route('inventory.stock-takes.count-sheet', $take->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
