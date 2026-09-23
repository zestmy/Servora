<?php

namespace Tests\Feature;

use App\Livewire\Inventory\TransferForm;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\OutletTransfer;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\TransferConsolidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A transfer moves more than Market List stock.
 *
 * Outlets also hand each other prep items, finished dishes and one-off things
 * that are in no catalogue at all. A transfer line now names an ingredient, a
 * recipe, or a free-text custom item — and only the first moves stock on hand.
 */
class TransferOpenItemsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $from;
    private Outlet $to;
    private User $user;
    private UnitOfMeasure $kg;
    private UnitOfMeasure $pcs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Open Items Co', 'slug' => Str::slug('Open Items Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $this->from = Outlet::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
        $this->to   = Outlet::create(['company_id' => $this->company->id, 'name' => 'Branch', 'code' => 'BR', 'is_active' => true]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->from->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->from->id, $this->to->id]);

        setPermissionsTeamId($this->company->id);
        foreach (['inventory.view', 'inventory.transfers.record'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->user->givePermissionTo(['inventory.view', 'inventory.transfers.record']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->kg  = UnitOfMeasure::create(['name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'weight', 'base_unit_factor' => 1000]);
        $this->pcs = UnitOfMeasure::create(['name' => 'Pieces', 'abbreviation' => 'pcs', 'type' => 'count', 'base_unit_factor' => 1]);

        $this->actingAs($this->user);
    }

    private function recipe(string $name, bool $isPrep = false): Recipe
    {
        // Cost per yield unit is derived from the lines: 0.75 kg at RM 10/kg, yield 1 = RM 7.50.
        $recipe = Recipe::create([
            'company_id' => $this->company->id, 'name' => $name, 'yield_uom_id' => $this->pcs->id,
            'yield_quantity' => 1, 'is_active' => true, 'is_prep' => $isPrep,
        ]);
        RecipeLine::create([
            'recipe_id' => $recipe->id, 'ingredient_id' => $this->ingredient('Base item ' . crc32($name))->id,
            'quantity' => 0.75, 'uom_id' => $this->kg->id,
        ]);

        return $recipe->fresh();
    }

    private function ingredient(string $name, bool $isPrep = false): Ingredient
    {
        return Ingredient::create([
            'company_id' => $this->company->id, 'name' => $name, 'base_uom_id' => $this->kg->id, 'recipe_uom_id' => $this->kg->id,
            'current_cost' => 10, 'is_active' => true, 'is_prep' => $isPrep,
        ]);
    }

    private function form()
    {
        return Livewire::test(TransferForm::class)->set('to_outlet_id', (string) $this->to->id);
    }

    public function test_search_lists_market_list_prep_items_and_recipes_separately(): void
    {
        $this->ingredient('Chicken Breast');
        $this->ingredient('Chicken Marinade', isPrep: true);
        $this->recipe('Chicken Rice');
        $this->recipe('Chicken Stock Prep', isPrep: true);   // appears as its prep item, not as a recipe

        $c = $this->form()->set('itemSearch', 'chicken');

        $this->assertSame(['CHICKEN BREAST'], $c->viewData('ingredientResults')->pluck('name')->all());
        $this->assertSame(['CHICKEN MARINADE'], $c->viewData('prepResults')->pluck('name')->all());
        $this->assertSame(['CHICKEN RICE'], $c->viewData('recipeResults')->pluck('name')->all());
    }

    public function test_a_transfer_saves_an_ingredient_a_recipe_and_a_custom_item(): void
    {
        $flour = $this->ingredient('Flour');
        $cake  = $this->recipe('Chocolate Cake');

        $c = $this->form()
            ->call('addIngredient', $flour->id)
            ->call('addRecipe', $cake->id)
            ->set('itemSearch', 'Takeaway boxes')
            ->call('addCustomItem')
            ->set('lines.1.quantity', '2')
            ->set('lines.2.quantity', '50')
            ->set('lines.2.unit_cost', '0.35')
            ->call('save')
            ->assertHasNoErrors();

        $lines = OutletTransfer::firstOrFail()->lines()->orderBy('id')->get();
        $this->assertCount(3, $lines);

        [$ing, $rec, $custom] = $lines->all();

        $this->assertSame($flour->id, $ing->ingredient_id);
        $this->assertNull($ing->recipe_id);

        $this->assertNull($rec->ingredient_id);
        $this->assertSame($cake->id, $rec->recipe_id);
        $this->assertEquals(7.5, (float) $rec->unit_cost, 'A recipe is priced at its cost per yield unit.');

        $this->assertNull($custom->ingredient_id);
        $this->assertNull($custom->recipe_id);
        $this->assertSame('Takeaway boxes', $custom->custom_name);
        $this->assertSame($this->pcs->id, $custom->uom_id);
        $this->assertEquals(0.35, (float) $custom->unit_cost, 'A custom item keeps the cost typed for it.');
    }

    public function test_a_custom_item_needs_a_name(): void
    {
        $this->form()
            ->call('addCustomItem')
            ->call('save')
            ->assertHasErrors('lines.0.custom_name');

        $this->assertSame(0, OutletTransfer::count());
    }

    /** A custom row carrying a stray ingredient_id must not move that stock at a typed price. */
    public function test_a_custom_row_cannot_smuggle_an_ingredient(): void
    {
        $flour = $this->ingredient('Flour');

        $this->form()
            ->set('itemSearch', 'Mystery')
            ->call('addCustomItem')
            ->set('lines.0.ingredient_id', $flour->id)
            ->set('lines.0.unit_cost', '0.01')
            ->call('save')
            ->assertHasNoErrors();

        $line = OutletTransfer::firstOrFail()->lines()->firstOrFail();
        $this->assertNull($line->ingredient_id);
        $this->assertSame('Mystery', $line->custom_name);
    }

    public function test_reopening_a_transfer_restores_every_kind_of_line(): void
    {
        $flour = $this->ingredient('Flour');
        $cake  = $this->recipe('Chocolate Cake');

        $this->form()
            ->call('addIngredient', $flour->id)
            ->call('addRecipe', $cake->id)
            ->set('itemSearch', 'Cake stand')
            ->call('addCustomItem')
            ->set('lines.2.unit_cost', '12')
            ->call('save');

        $c = Livewire::test(TransferForm::class, ['id' => OutletTransfer::firstOrFail()->id]);

        $this->assertSame(['ingredient', 'recipe', 'custom'], array_column($c->get('lines'), 'item_type'));
        $this->assertSame(['FLOUR', 'CHOCOLATE CAKE', 'Cake stand'], array_column($c->get('lines'), 'item_name'));
        $this->assertEquals(12, (float) $c->get('lines')[2]['unit_cost']);
    }

    public function test_the_detail_report_lists_recipes_and_custom_items(): void
    {
        $cake = $this->recipe('Chocolate Cake');

        $this->form()
            ->call('addRecipe', $cake->id)
            ->set('lines.0.quantity', '2')
            ->set('itemSearch', 'Cake stand')
            ->call('addCustomItem')
            ->set('lines.1.unit_cost', '12')
            ->call('save');

        $report = app(TransferConsolidator::class)->consolidate(
            OutletTransfer::with('lines')->get()
        );

        $groups = collect($report['groups'])->keyBy('name');
        $this->assertSame('CHOCOLATE CAKE', $groups['Recipes']['items'][0]['name']);
        $this->assertEquals(15.0, $groups['Recipes']['value']);
        $this->assertSame('Cake stand', $groups['Custom items']['items'][0]['name']);
        $this->assertEquals(27.0, $report['total']);
    }
}
