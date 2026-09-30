<?php

namespace Tests\Feature;

use App\Livewire\Ingredients\Index as MarketList;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The Market List's route asks only for ingredients.view. Bulk delete, the
 * active toggle and the duplicate merge are writes, so each asks for its own
 * ability rather than riding on the page's.
 */
class IngredientBulkActionPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private UnitOfMeasure $kg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Bulk Co', 'slug' => Str::slug('Bulk Co') . '-' . uniqid(),
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

        $this->actingAs($user);

        return $user;
    }

    private function item(string $name): Ingredient
    {
        return Ingredient::create([
            'company_id' => $this->company->id, 'name' => $name,
            'base_uom_id' => $this->kg->id, 'recipe_uom_id' => $this->kg->id,
            'purchase_price' => 10, 'pack_size' => 1, 'yield_percent' => 100,
            'current_cost' => 10, 'is_active' => true,
        ]);
    }

    public function test_a_view_only_user_cannot_bulk_delete(): void
    {
        $viewer = $this->user(['ingredients.view']);
        $flour  = $this->item('Flour');

        Livewire::actingAs($viewer)->test(MarketList::class)
            ->set('selectedIds', [(string) $flour->id])
            ->call('bulkDelete')
            ->assertForbidden();

        $this->assertNotSoftDeleted($flour);
    }

    public function test_a_user_with_delete_can_bulk_delete(): void
    {
        $user  = $this->user(['ingredients.view', 'ingredients.delete']);
        $flour = $this->item('Flour');

        Livewire::actingAs($user)->test(MarketList::class)
            ->set('selectedIds', [(string) $flour->id])
            ->call('bulkDelete')
            ->assertOk();

        $this->assertSoftDeleted($flour);
    }

    public function test_a_view_only_user_cannot_toggle_active(): void
    {
        $viewer = $this->user(['ingredients.view']);
        $flour  = $this->item('Flour');

        Livewire::actingAs($viewer)->test(MarketList::class)
            ->call('toggleActive', $flour->id)
            ->assertForbidden();

        $this->assertTrue($flour->fresh()->is_active);
    }

    public function test_a_manager_can_toggle_active(): void
    {
        $user  = $this->user(['ingredients.view', 'ingredients.manage']);
        $flour = $this->item('Flour');

        Livewire::actingAs($user)->test(MarketList::class)
            ->call('toggleActive', $flour->id)
            ->assertOk();

        $this->assertFalse($flour->fresh()->is_active);
    }

    public function test_a_view_only_user_cannot_scan_or_merge_duplicates(): void
    {
        $viewer = $this->user(['ingredients.view']);

        Livewire::actingAs($viewer)->test(MarketList::class)
            ->call('scanDuplicates')
            ->assertForbidden();

        Livewire::actingAs($viewer)->test(MarketList::class)
            ->call('mergeCluster', 0)
            ->assertForbidden();
    }
}
