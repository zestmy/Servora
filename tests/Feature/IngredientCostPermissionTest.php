<?php

namespace Tests\Feature;

use App\Livewire\Ingredients\Index as MarketList;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\IngredientPriceHistory;
use App\Models\Outlet;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * What an ingredient costs is its own key.
 *
 * Renaming an item or filing it under a category is housekeeping. Changing what
 * it costs moves stock value, every recipe costing built on it and the margin on
 * everything it appears in — and it does so with no invoice behind it, which is
 * the one price change in the product no document can be checked against. Since
 * the stock forms stopped accepting a typed cost, the Market List is the only
 * screen left where a price is set by hand.
 *
 * `ingredients.manage` still gets you the catalogue. `ingredients.cost` is what
 * gets you the price — through the edit modal, the quick-edit grid, or a CSV.
 * A person without it is not turned away; their price edit is simply dropped and
 * the stored figure stands, so they can still fix a name they were asked to fix.
 */
class IngredientCostPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private UnitOfMeasure $kg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Cost Co', 'slug' => Str::slug('Cost Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->kg = UnitOfMeasure::create(['name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'weight']);
    }

    /** @param array<int, string> $abilities */
    private function user(array $abilities): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(array_map(fn ($a) => Permission::findOrCreate($a, 'web'), $abilities));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function flour(): Ingredient
    {
        return Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'Flour',
            'base_uom_id' => $this->kg->id, 'recipe_uom_id' => $this->kg->id,
            'purchase_price' => 10.00, 'pack_size' => 1, 'yield_percent' => 100,
            'current_cost' => 10.00, 'is_active' => true,
        ]);
    }

    // ── The edit modal ───────────────────────────────────────────────────

    public function test_without_the_cost_ability_a_price_edit_is_dropped(): void
    {
        $flour = $this->flour();
        $user  = $this->user(['ingredients.view', 'ingredients.manage']);

        Livewire::actingAs($user)->test(MarketList::class)
            ->call('openEdit', $flour->id)
            ->set('name', 'Flour Premium')
            ->set('purchase_price', '99')
            ->call('save');

        $flour->refresh();

        $this->assertSame('FLOUR PREMIUM', $flour->name, 'The catalogue edit they were allowed must still land.');
        $this->assertSame(10.0, round((float) $flour->purchase_price, 2), 'The price must not move.');
        $this->assertSame(10.0, round((float) $flour->current_cost, 2));
    }

    public function test_with_the_cost_ability_a_price_edit_lands(): void
    {
        $flour = $this->flour();
        $user  = $this->user(['ingredients.view', 'ingredients.manage', 'ingredients.cost']);

        Livewire::actingAs($user)->test(MarketList::class)
            ->call('openEdit', $flour->id)
            ->set('purchase_price', '12')
            ->call('save');

        $flour->refresh();

        $this->assertSame(12.0, round((float) $flour->purchase_price, 2));
        $this->assertSame(12.0, round((float) $flour->current_cost, 2));
    }

    public function test_a_dropped_price_edit_writes_no_price_history(): void
    {
        $flour = $this->flour();
        $user  = $this->user(['ingredients.view', 'ingredients.manage']);

        Livewire::actingAs($user)->test(MarketList::class)
            ->call('openEdit', $flour->id)
            ->set('purchase_price', '99')
            ->call('save');

        // The log diffs stored against SUBMITTED, so an ungated log would record
        // a 10 -> 99 change that never happened.
        $this->assertSame(0, IngredientPriceHistory::where('ingredient_id', $flour->id)->count());
    }

    // ── The quick-edit grid ──────────────────────────────────────────────

    public function test_the_quick_edit_grid_also_drops_a_price_edit(): void
    {
        $flour = $this->flour();
        $user  = $this->user(['ingredients.view', 'ingredients.manage']);

        Livewire::actingAs($user)->test(MarketList::class)
            ->call('enterQuickEdit')
            ->set("editableRows.{$flour->id}.name", 'Flour Grade A')
            ->set("editableRows.{$flour->id}.purchase_price", '77')
            ->call('saveQuickEdit');

        $flour->refresh();

        $this->assertSame('FLOUR GRADE A', $flour->name);
        $this->assertSame(10.0, round((float) $flour->purchase_price, 2));
    }

    public function test_the_quick_edit_grid_honours_the_cost_ability(): void
    {
        $flour = $this->flour();
        $user  = $this->user(['ingredients.view', 'ingredients.manage', 'ingredients.cost']);

        Livewire::actingAs($user)->test(MarketList::class)
            ->call('enterQuickEdit')
            ->set("editableRows.{$flour->id}.purchase_price", '77')
            ->call('saveQuickEdit');

        $this->assertSame(77.0, round((float) $flour->refresh()->purchase_price, 2));
    }

    // ── The CSV import ───────────────────────────────────────────────────

    private function priceCsv(Ingredient $ingredient, string $price): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'prices.csv',
            "ID,Name,Purchase Price\n{$ingredient->id},{$ingredient->name},{$price}\n"
        );
    }

    public function test_an_import_cannot_be_used_to_walk_around_the_cost_gate(): void
    {
        $flour = $this->flour();
        $user  = $this->user(['ingredients.view', 'ingredients.manage', 'ingredients.import']);

        Livewire::actingAs($user)->test(MarketList::class)
            ->set('importFile', $this->priceCsv($flour, '55'))
            ->call('processImport');

        $this->assertSame(10.0, round((float) $flour->refresh()->purchase_price, 2));
    }

    public function test_an_import_with_the_cost_ability_updates_the_price(): void
    {
        $flour = $this->flour();
        $user  = $this->user(['ingredients.view', 'ingredients.manage', 'ingredients.import', 'ingredients.cost']);

        Livewire::actingAs($user)->test(MarketList::class)
            ->set('importFile', $this->priceCsv($flour, '55'))
            ->call('processImport');

        $this->assertSame(55.0, round((float) $flour->refresh()->purchase_price, 2));
    }

    public function test_importing_needs_the_import_ability_at_all(): void
    {
        $flour = $this->flour();

        // The route only asks for ingredients.view, and a Livewire action is its
        // own request — so this action was reachable with view access alone.
        $user = $this->user(['ingredients.view']);

        Livewire::actingAs($user)->test(MarketList::class)
            ->set('importFile', $this->priceCsv($flour, '55'))
            ->call('processImport')
            ->assertForbidden();
    }

    // ── The backfill ─────────────────────────────────────────────────────

    public function test_the_migration_hands_the_new_ability_to_everyone_who_had_the_old_one(): void
    {
        // Splitting an ability must not quietly take access away on deploy:
        // whoever can manage the catalogue today can already set costs today.
        $role = \Spatie\Permission\Models\Role::findOrCreate('Purchasing Manager', 'web');
        $role->givePermissionTo(Permission::findOrCreate('ingredients.manage', 'web'));

        $direct = $this->user(['ingredients.manage']);

        // Re-run the split from a clean slate.
        $cost = Permission::where('name', 'ingredients.cost')->first();
        if ($cost) {
            \Illuminate\Support\Facades\DB::table('role_has_permissions')->where('permission_id', $cost->id)->delete();
            \Illuminate\Support\Facades\DB::table('model_has_permissions')->where('permission_id', $cost->id)->delete();
            \Illuminate\Support\Facades\DB::table('permissions')->where('id', $cost->id)->delete();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertDatabaseMissing('permissions', ['name' => 'ingredients.cost']);

        (require database_path('migrations/2026_09_01_000001_split_ingredient_cost_permission.php'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertDatabaseHas('permissions', ['name' => 'ingredients.cost']);

        setPermissionsTeamId($this->company->id);
        $this->assertTrue(
            $role->fresh()->hasPermissionTo('ingredients.cost'),
            'A role that could manage ingredients must keep being able to set costs.'
        );
        $this->assertTrue(
            $direct->fresh()->hasPermissionTo('ingredients.cost'),
            'A direct grant must be mirrored too.'
        );
    }

    // ── The screen says so ───────────────────────────────────────────────

    public function test_the_price_fields_are_disabled_without_the_cost_ability(): void
    {
        $flour = $this->flour();

        $html = Livewire::actingAs($this->user(['ingredients.view', 'ingredients.manage']))
            ->test(MarketList::class)
            ->call('openEdit', $flour->id)
            ->html();

        $this->assertStringContainsString('Set by whoever holds the cost permission.', $html);

        $withCost = Livewire::actingAs($this->user(['ingredients.view', 'ingredients.manage', 'ingredients.cost']))
            ->test(MarketList::class)
            ->call('openEdit', $flour->id)
            ->html();

        $this->assertStringNotContainsString('Set by whoever holds the cost permission.', $withCost);
    }
}
