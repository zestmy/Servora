<?php

namespace Tests\Feature;

use App\Livewire\Kitchen\ProductionRecipeForm;
use App\Models\CentralKitchen;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\ProductionRecipe;
use App\Models\RecipePriceClass;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A production recipe costs the way an outlet recipe does.
 *
 * The kitchen's form asked for packaging and labels as two flat numbers typed
 * into the costing panel, and for one selling price. Two flat numbers cannot
 * answer "what is the box costing us this month" — they are a figure somebody
 * typed, not a cost that moves with the ingredient. And a panel with live
 * inputs in it is not a summary: the same number could be set in two places.
 *
 * So packaging is a list of ingredients costed like any other, extra costs are
 * named rows that can be a value or a percentage, the summary is derived, and
 * a price exists per price class — the outlet recipes' own classes, because
 * "Delivery" means the same thing whoever cooked the item.
 */
class ProductionRecipeCostingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private CentralKitchen $kitchen;
    private User $user;
    private UnitOfMeasure $each;
    private Ingredient $beef;
    private Ingredient $box;
    private RecipePriceClass $dineIn;
    private RecipePriceClass $delivery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Kitchen Co', 'slug' => Str::slug('Kitchen Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $outlet = \App\Models\Outlet::create([
            'company_id' => $this->company->id, 'name' => 'CK', 'code' => 'CK', 'is_active' => true,
        ]);

        $this->kitchen = CentralKitchen::create([
            'company_id' => $this->company->id, 'name' => 'CK Cheras', 'code' => 'CKC',
            'outlet_id' => $outlet->id, 'is_active' => true,
        ]);

        $this->each = UnitOfMeasure::first() ?? UnitOfMeasure::create([
            'name' => 'Each', 'abbreviation' => 'ea', 'type' => 'count', 'base_factor' => 1,
        ]);

        // RM10 each, so the arithmetic below is checkable by hand.
        $this->beef = $this->ingredient('Beef', 'BEEF', 10);
        $this->box  = $this->ingredient('Box', 'BOX', 2);

        $this->dineIn = RecipePriceClass::create([
            'company_id' => $this->company->id, 'name' => 'Dine In', 'sort_order' => 1, 'is_default' => true,
        ]);
        $this->delivery = RecipePriceClass::create([
            'company_id' => $this->company->id, 'name' => 'Delivery', 'sort_order' => 2, 'is_default' => false,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->sync([$outlet->id]);
        $this->kitchen->users()->syncWithoutDetaching([$this->user->id => ['role' => 'chef']]);

        setPermissionsTeamId($this->company->id);
        foreach (['recipes.view', 'recipes.manage', 'reports.view', 'kitchen.production.manage'] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        $this->user->givePermissionTo(Permission::all()->pluck('name')->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function ingredient(string $name, string $code, float $price): Ingredient
    {
        return Ingredient::create([
            'company_id' => $this->company->id, 'name' => $name, 'code' => $code,
            'base_uom_id' => $this->each->id, 'recipe_uom_id' => $this->each->id,
            'purchase_price' => $price, 'pack_size' => 1, 'yield_percent' => 100, 'is_active' => true,
        ]);
    }

    /** A form with one beef line, ready for the case under test to add to. */
    private function form()
    {
        return Livewire::actingAs($this->user)->test(ProductionRecipeForm::class)
            ->set('name', 'Rendang Base')
            ->set('kitchen_id', $this->kitchen->id)
            ->set('yield_uom_id', $this->each->id)
            ->set('yield_quantity', '10')
            ->call('addIngredient', $this->beef->id);
    }

    // ── Packaging is an ingredient now ────────────────────────────────────

    public function test_packaging_is_costed_from_its_own_ingredient_lines(): void
    {
        $c = $this->form()->call('addPackaging', $this->box->id);

        // One box at RM2.
        $this->assertEqualsWithDelta(2.0, (float) $c->get('packaging_cost'), 0.0001);

        // Two boxes, and the cost follows — which a typed number never did.
        $c->set('packagingLines.0.quantity', '2');
        $this->assertEqualsWithDelta(4.0, (float) $c->get('packaging_cost'), 0.0001);
    }

    public function test_packaging_lines_are_saved_apart_from_ingredient_lines(): void
    {
        $this->form()
            ->call('addPackaging', $this->box->id)
            ->set('classPrices.' . $this->dineIn->id, '5')
            ->call('save')
            ->assertHasNoErrors();

        $recipe = ProductionRecipe::latest('id')->first();

        $this->assertSame(1, $recipe->ingredientLines()->count());
        $this->assertSame(1, $recipe->packagingLines()->count());
        $this->assertSame($this->box->id, $recipe->packagingLines()->first()->ingredient_id);
    }

    // ── Extra costs ───────────────────────────────────────────────────────

    public function test_an_extra_cost_can_be_a_flat_value(): void
    {
        $c = $this->form()->call('addExtraCostRow')
            ->set('extraCosts.0.label', 'Gas')
            ->set('extraCosts.0.type', 'value')
            ->set('extraCosts.0.amount', '3');

        $this->assertEqualsWithDelta(3.0, (float) $c->get('extra_cost_total'), 0.0001);
    }

    /** A percentage is OF THE RAW COST, so two extras cannot depend on order. */
    public function test_an_extra_cost_can_be_a_percentage_of_raw_material(): void
    {
        $c = $this->form()->call('addExtraCostRow')
            ->set('extraCosts.0.label', 'Labour')
            ->set('extraCosts.0.type', 'percent')
            ->set('extraCosts.0.amount', '10');

        // Raw is one beef at RM10, so 10% is RM1.
        $this->assertEqualsWithDelta(1.0, (float) $c->get('extra_cost_total'), 0.0001);
    }

    public function test_a_half_typed_extra_cost_row_is_not_stored(): void
    {
        $this->form()
            ->call('addExtraCostRow')
            ->set('extraCosts.0.label', '')
            ->set('extraCosts.0.amount', '3')
            ->set('classPrices.' . $this->dineIn->id, '5')
            ->call('save');

        $this->assertSame([], ProductionRecipe::latest('id')->first()->extra_costs ?? []);
    }

    // ── The summary is derived ────────────────────────────────────────────

    /** Cost/unit = (raw + packaging + label + extras) / yield. */
    public function test_the_summary_adds_up(): void
    {
        $c = $this->form()
            ->call('addPackaging', $this->box->id)   // 2
            ->set('label_cost', '1')                 // 1
            ->call('addExtraCostRow')
            ->set('extraCosts.0.label', 'Gas')
            ->set('extraCosts.0.amount', '2');       // 2

        // (10 + 2 + 1 + 2) / 10 yield = 1.5
        $this->assertEqualsWithDelta(1.5, (float) $c->get('total_cost_per_unit'), 0.0001);
    }

    public function test_the_margin_is_quoted_against_the_default_price_class(): void
    {
        $c = $this->form()
            ->set('classPrices.' . $this->dineIn->id, '4')
            ->set('classPrices.' . $this->delivery->id, '9');

        // Raw 10 over yield 10 = 1.00/unit, against the DEFAULT class at 4.
        $this->assertEqualsWithDelta(3.0, (float) $c->get('margin'), 0.0001);
        $this->assertEqualsWithDelta(75.0, (float) $c->get('margin_percent'), 0.01);
    }

    // ── Prices per class ──────────────────────────────────────────────────

    public function test_a_price_is_stored_for_each_class_that_has_one(): void
    {
        $this->form()
            ->set('classPrices.' . $this->dineIn->id, '4')
            ->set('classPrices.' . $this->delivery->id, '9')
            ->call('save')
            ->assertHasNoErrors();

        $prices = ProductionRecipe::latest('id')->first()->prices()->pluck('selling_price', 'recipe_price_class_id');

        $this->assertEqualsWithDelta(4.0, (float) $prices[$this->dineIn->id], 0.0001);
        $this->assertEqualsWithDelta(9.0, (float) $prices[$this->delivery->id], 0.0001);
    }

    /** A class left at zero is not stored, so adding one later backfills nothing. */
    public function test_an_unpriced_class_is_not_stored(): void
    {
        $this->form()->set('classPrices.' . $this->dineIn->id, '4')->call('save');

        $recipe = ProductionRecipe::latest('id')->first();

        $this->assertSame(1, $recipe->prices()->count());
        $this->assertNull($recipe->prices()->where('recipe_price_class_id', $this->delivery->id)->first());
    }

    // ── What downstream still depends on ──────────────────────────────────

    /**
     * Production orders price their lines off total_cost_per_unit, and the
     * legacy columns are still written from the values the new sections
     * derive, so nothing downstream had to change.
     */
    public function test_the_legacy_costing_columns_are_still_written(): void
    {
        $this->form()
            ->call('addPackaging', $this->box->id)
            ->set('classPrices.' . $this->dineIn->id, '4')
            ->call('save')
            ->assertHasNoErrors();

        $recipe = ProductionRecipe::latest('id')->first();

        $this->assertEqualsWithDelta(2.0, (float) $recipe->packaging_cost_per_unit, 0.0001);
        $this->assertEqualsWithDelta(4.0, (float) $recipe->selling_price_per_unit, 0.0001);
        $this->assertEqualsWithDelta(1.2, (float) $recipe->total_cost_per_unit, 0.0001);
    }

    /** Reopening shows what was saved, in the right two lists. */
    public function test_reopening_splits_the_lines_back_out(): void
    {
        $this->form()
            ->call('addPackaging', $this->box->id)
            ->call('addExtraCostRow')
            ->set('extraCosts.0.label', 'Gas')
            ->set('extraCosts.0.amount', '2')
            ->set('classPrices.' . $this->delivery->id, '7')
            ->call('save');

        $id = ProductionRecipe::latest('id')->value('id');
        $re = Livewire::actingAs($this->user)->test(ProductionRecipeForm::class, ['id' => $id]);

        $this->assertCount(1, $re->get('lines'));
        $this->assertCount(1, $re->get('packagingLines'));
        $this->assertSame('Gas', $re->get('extraCosts')[0]['label']);
        $this->assertSame('7', (string) $re->get('classPrices')[$this->delivery->id]);
    }
}
