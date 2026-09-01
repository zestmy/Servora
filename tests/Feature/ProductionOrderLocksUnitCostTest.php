<?php

namespace Tests\Feature;

use App\Livewire\Kitchen\ProductionOrderForm;
use App\Models\CentralKitchen;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\ProductionOrder;
use App\Models\ProductionRecipe;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A production order spends a recipe's cost; it does not set one.
 *
 * What a batch costs is what its ingredients cost, rolled up by the recipe —
 * and those ingredients are priced by purchasing. A typed-over cost here makes
 * the same batch worth two different things depending on which order recorded
 * it, and the figure lands in stock value when the batch completes.
 *
 * The stock forms learned this already; this is the last screen that did not.
 * As there, taking the input out of the markup is the visible half: `lines` is
 * a public Livewire property, so these tests push a cost over the wire the way
 * a crafted request would and assert the stored figure is the server's.
 *
 * @see StockFormsLockUnitCostTest for the same rule on the stock forms.
 */
class ProductionOrderLocksUnitCostTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private CentralKitchen $kitchen;
    private User $user;
    private ProductionRecipe $productionRecipe;
    private Recipe $prepRecipe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Batch Co', 'slug' => Str::slug('Batch Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $kitchenOutlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Central Kitchen',
            'code' => 'CK', 'is_active' => true,
        ]);

        $this->kitchen = CentralKitchen::create([
            'company_id' => $this->company->id, 'name' => 'CK One', 'code' => 'CK1',
            'outlet_id' => $kitchenOutlet->id, 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->sync([$kitchenOutlet->id]);
        $this->kitchen->users()->syncWithoutDetaching([$this->user->id => ['role' => 'chef']]);

        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo(Permission::findOrCreate('kitchen.production.manage', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $uom = UnitOfMeasure::create(['name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'weight']);

        $this->productionRecipe = ProductionRecipe::create([
            'company_id' => $this->company->id, 'kitchen_id' => $this->kitchen->id,
            'name' => 'Sambal Base', 'code' => 'SB1',
            'yield_quantity' => 10, 'yield_uom_id' => $uom->id,
            'total_cost_per_unit' => 4.25,
            'is_active' => true, 'created_by' => $this->user->id,
        ]);

        // cost_per_yield_unit is COMPUTED (total_cost / yield), not a stored
        // number, so the recipe needs a real ingredient behind it: 3 kg of a
        // RM3.00/kg ingredient over a yield of 5 = RM1.80 a unit.
        $garlic = Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'Garlic',
            'base_uom_id' => $uom->id, 'recipe_uom_id' => $uom->id,
            'current_cost' => 3.00, 'is_active' => true,
        ]);

        $this->prepRecipe = Recipe::create([
            'company_id' => $this->company->id, 'name' => 'Garlic Paste',
            'yield_quantity' => 5, 'yield_uom_id' => $uom->id,
        ]);

        RecipeLine::create([
            'recipe_id' => $this->prepRecipe->id, 'ingredient_id' => $garlic->id,
            'quantity' => 3, 'uom_id' => $uom->id, 'waste_percentage' => 0,
        ]);

        $this->prepRecipe->refresh();

        $this->actingAs($this->user);
    }

    private function form()
    {
        return Livewire::actingAs($this->user)->test(ProductionOrderForm::class)
            ->set('kitchen_id', $this->kitchen->id)
            ->set('production_date', today()->toDateString());
    }

    // ── The cost the server writes is the server's ───────────────────────

    public function test_a_production_recipe_line_stores_the_recipe_cost(): void
    {
        $this->form()
            ->call('addProductionRecipe', $this->productionRecipe->id)
            ->set('lines.0.planned_quantity', '10')
            ->set('lines.0.unit_cost', '999')      // what a crafted request sends
            ->call('save', 'draft');

        $line = ProductionOrder::latest('id')->first()->lines()->first();

        $this->assertSame(4.25, round((float) $line->unit_cost, 4));
    }

    public function test_a_prep_item_line_stores_the_recipe_cost(): void
    {
        $this->form()
            ->call('addRecipe', $this->prepRecipe->id)
            ->set('lines.0.planned_quantity', '3')
            ->set('lines.0.unit_cost', '999')
            ->call('save', 'draft');

        $line = ProductionOrder::latest('id')->first()->lines()->first();

        $this->assertSame(1.80, round((float) $line->unit_cost, 4));
    }

    public function test_the_cost_map_cannot_be_written_from_the_client(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        $this->form()
            ->call('addProductionRecipe', $this->productionRecipe->id)
            ->set('lineCosts', ['production:' . $this->productionRecipe->id => 999]);
    }

    public function test_swapping_the_line_source_cannot_smuggle_a_price_past_the_map(): void
    {
        // `source` is part of the key and is itself a public array member, so a
        // request could flip it to miss the map. The miss must fall through to
        // a fresh look-up, not to the number the request supplied.
        $this->form()
            ->call('addProductionRecipe', $this->productionRecipe->id)
            ->set('lines.0.planned_quantity', '10')
            ->set('lines.0.source', 'prep')
            ->set('lines.0.recipe_id', $this->prepRecipe->id)
            ->set('lines.0.unit_cost', '999')
            ->call('save', 'draft');

        $stored = (float) ProductionOrder::latest('id')->first()->lines()->first()->unit_cost;

        $this->assertNotSame(999.0, $stored, 'A submitted price survived by changing the line source.');
        $this->assertSame(1.80, round($stored, 4), 'It should price as the prep recipe it now claims to be.');
    }

    // ── And the markup ───────────────────────────────────────────────────

    public function test_the_form_renders_no_editable_cost_input(): void
    {
        $html = $this->form()->call('addProductionRecipe', $this->productionRecipe->id)->html();

        $this->assertStringNotContainsString('.unit_cost"', $html, 'The price is the recipe\'s, not this form\'s.');
    }

    public function test_every_row_is_keyed_so_the_dom_cannot_reuse_one_for_another(): void
    {
        $html = $this->form()
            ->call('addProductionRecipe', $this->productionRecipe->id)
            ->call('addRecipe', $this->prepRecipe->id)
            ->html();

        preg_match_all('/wire:key="po-line-([^"]+)"/', $html, $m);

        $this->assertCount(2, $m[1], 'Each line needs its own keyed row.');
        $this->assertSame($m[1], array_values(array_unique($m[1])), 'Row keys must be distinct.');
    }

    public function test_a_quantity_is_committed_on_leaving_the_field(): void
    {
        $html = $this->form()->call('addProductionRecipe', $this->productionRecipe->id)->html();

        $this->assertStringContainsString('wire:model.blur="lines.0.planned_quantity"', $html);
    }
}
