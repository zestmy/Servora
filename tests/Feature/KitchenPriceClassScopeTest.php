<?php

namespace Tests\Feature;

use App\Livewire\Kitchen\ProductionRecipeForm;
use App\Livewire\Recipes\Form as OutletRecipeForm;
use App\Livewire\Settings\PriceClasses;
use App\Models\CentralKitchen;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\RecipePriceClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The Central Kitchen prices on its own classes.
 *
 * Production recipes were given the outlet recipes' classes when they gained a
 * Selling Price section, on the reasoning that "Delivery" means the same thing
 * whoever cooked the item. It does not: a kitchen sells to its own branches
 * and to wholesale, an outlet sells to diners, and one list forces both sets
 * of names onto both screens.
 *
 * Same mechanism recipe_categories already uses for this exact split — a scope
 * column, not a second table — so the two stay one thing with two lists rather
 * than two things that drift.
 */
class KitchenPriceClassScopeTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private CentralKitchen $kitchen;
    private User $user;
    private RecipePriceClass $dineIn;
    private RecipePriceClass $wholesale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Scope Co', 'slug' => Str::slug('Scope Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $this->kitchen = CentralKitchen::create([
            'company_id' => $this->company->id, 'name' => 'CK', 'code' => 'CK',
            'outlet_id' => $this->outlet->id, 'is_active' => true,
        ]);

        $this->dineIn = RecipePriceClass::create([
            'company_id' => $this->company->id, 'scope' => RecipePriceClass::SCOPE_OUTLET,
            'name' => 'Dine In', 'sort_order' => 1, 'is_default' => true,
        ]);

        $this->wholesale = RecipePriceClass::create([
            'company_id' => $this->company->id, 'scope' => RecipePriceClass::SCOPE_KITCHEN,
            'name' => 'Wholesale', 'sort_order' => 1, 'is_default' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->sync([$this->outlet->id]);
        $this->kitchen->users()->syncWithoutDetaching([$this->user->id => ['role' => 'chef']]);

        setPermissionsTeamId($this->company->id);
        foreach (['recipes.view', 'recipes.manage', 'recipes.price', 'reports.view'] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        $this->user->givePermissionTo(Permission::all()->pluck('name')->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Put the session into kitchen mode, the way the workspace switch does. */
    private function inKitchen(): void
    {
        session(['workspace_mode' => 'kitchen', 'active_kitchen_id' => $this->kitchen->id]);
    }

    // ── The two forms see different lists ─────────────────────────────────

    public function test_the_kitchen_recipe_form_offers_only_kitchen_classes(): void
    {
        $this->inKitchen();

        $classes = Livewire::actingAs($this->user)->test(ProductionRecipeForm::class)
            ->viewData('priceClasses')->pluck('name')->all();

        $this->assertSame(['Wholesale'], $classes,
            'A kitchen sells to branches and wholesale, not to diners.');
    }

    public function test_the_outlet_recipe_form_offers_only_outlet_classes(): void
    {
        $classes = Livewire::actingAs($this->user)->test(OutletRecipeForm::class)
            ->viewData('priceClasses')->pluck('name')->all();

        $this->assertSame(['Dine In'], $classes);
    }

    // ── The settings screen edits whichever workspace you are in ──────────

    public function test_the_settings_screen_lists_the_workspaces_own_classes(): void
    {
        $outletView = Livewire::actingAs($this->user)->test(PriceClasses::class)
            ->viewData('priceClasses')->pluck('name')->all();
        $this->assertSame(['Dine In'], $outletView);

        $this->inKitchen();

        $kitchenView = Livewire::actingAs($this->user)->test(PriceClasses::class)
            ->viewData('priceClasses')->pluck('name')->all();
        $this->assertSame(['Wholesale'], $kitchenView);
    }

    public function test_a_class_created_in_the_kitchen_belongs_to_the_kitchen(): void
    {
        $this->inKitchen();

        Livewire::actingAs($this->user)->test(PriceClasses::class)
            ->call('openCreate')
            ->set('name', 'Branch Transfer')
            ->set('sort_order', '2')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('recipe_price_classes', [
            'name' => 'Branch Transfer', 'scope' => RecipePriceClass::SCOPE_KITCHEN,
        ]);

        // And it did not appear on the outlet side.
        $this->assertSame(['Dine In'],
            RecipePriceClass::outletScope()->ordered()->pluck('name')->all());
    }

    /** A default is per list: marking one must not unset the other's. */
    public function test_a_kitchen_default_does_not_unset_the_outlet_default(): void
    {
        $this->inKitchen();

        Livewire::actingAs($this->user)->test(PriceClasses::class)
            ->call('openCreate')
            ->set('name', 'Branch Transfer')
            ->set('sort_order', '2')
            ->set('is_default', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue((bool) $this->dineIn->fresh()->is_default,
            'The outlet default must survive a kitchen default being set.');
        $this->assertFalse((bool) $this->wholesale->fresh()->is_default,
            'But the kitchen default it replaces must be cleared.');
    }

    /** Editing across the divide is refused, not silently permitted. */
    public function test_the_kitchen_cannot_edit_an_outlet_class(): void
    {
        $this->inKitchen();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::actingAs($this->user)->test(PriceClasses::class)
            ->call('openEdit', $this->dineIn->id);
    }

    // ── Existing data ─────────────────────────────────────────────────────

    /** Everything that existed before the split is outlet, as it always was. */
    public function test_classes_from_before_the_split_are_outlet(): void
    {
        $legacy = RecipePriceClass::create([
            'company_id' => $this->company->id, 'name' => 'Legacy', 'sort_order' => 9,
        ]);

        $this->assertSame(RecipePriceClass::SCOPE_OUTLET, $legacy->fresh()->scope,
            'The column defaults to outlet, so nothing pre-existing moves.');
    }
}
