<?php

namespace Tests\Feature;

use App\Livewire\Kitchen\ProductionExecute;
use App\Livewire\Kitchen\ProductionOrderForm;
use App\Livewire\Kitchen\ProductionRecipeForm;
use App\Livewire\Kitchen\ProductionRecipes;
use App\Models\CentralKitchen;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\ProductionOrder;
use App\Models\ProductionRecipe;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kitchen writes check the kitchen role for themselves.
 *
 * The kitchen routes carry only `kitchen.user`, which — unlike a `can:`
 * middleware — Livewire does not re-apply on /livewire/update. So nothing
 * upstream stopped any kitchen member (plain staff included) from deleting a
 * production recipe, scheduling an order, or starting one just by opening it.
 * Rights live on the kitchen_users pivot: managers keep the recipe book,
 * managers and chefs run production.
 */
class KitchenProductionPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private CentralKitchen $kitchen;
    private UnitOfMeasure $uom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'CK Co', 'slug' => Str::slug('CK Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Central Kitchen', 'code' => 'CK', 'is_active' => true,
        ]);

        $this->kitchen = CentralKitchen::create([
            'company_id' => $this->company->id, 'name' => 'CK One', 'code' => 'CK1',
            'outlet_id' => $outlet->id, 'is_active' => true,
        ]);

        $this->uom = UnitOfMeasure::first() ?? UnitOfMeasure::create([
            'name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'weight', 'base_factor' => 1,
        ]);
    }

    private function member(string $role): User
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->kitchen->users()->syncWithoutDetaching([$user->id => ['role' => $role]]);
        setPermissionsTeamId($this->company->id);

        return $user;
    }

    private function recipe(User $creator): ProductionRecipe
    {
        return ProductionRecipe::create([
            'company_id' => $this->company->id, 'kitchen_id' => $this->kitchen->id,
            'name' => 'Sambal Base', 'code' => 'SB-' . Str::random(4),
            'yield_quantity' => 10, 'yield_uom_id' => $this->uom->id,
            'is_active' => true, 'created_by' => $creator->id,
        ]);
    }

    private function order(User $creator, string $status): ProductionOrder
    {
        return ProductionOrder::create([
            'company_id' => $this->company->id, 'kitchen_id' => $this->kitchen->id,
            'order_number' => 'PO-' . Str::random(6), 'status' => $status,
            'production_date' => today()->toDateString(), 'created_by' => $creator->id,
        ]);
    }

    // ── Recipe book: managers only ───────────────────────────────────────

    public function test_a_chef_cannot_delete_or_toggle_a_production_recipe(): void
    {
        $chef   = $this->member('chef');
        $recipe = $this->recipe($chef);

        Livewire::actingAs($chef)->test(ProductionRecipes::class)
            ->call('deleteRecipe', $recipe->id)
            ->assertForbidden();

        Livewire::actingAs($chef)->test(ProductionRecipes::class)
            ->call('toggleActive', $recipe->id)
            ->assertForbidden();

        $fresh = $recipe->fresh();
        $this->assertNotNull($fresh);
        $this->assertTrue((bool) $fresh->is_active);
    }

    public function test_a_manager_can_delete_a_production_recipe(): void
    {
        $manager = $this->member('manager');
        $recipe  = $this->recipe($manager);

        Livewire::actingAs($manager)->test(ProductionRecipes::class)
            ->call('deleteRecipe', $recipe->id)
            ->assertOk();

        $this->assertNull(ProductionRecipe::find($recipe->id));
    }

    public function test_a_chef_cannot_save_an_existing_production_recipe(): void
    {
        $chef   = $this->member('chef');
        $recipe = $this->recipe($chef);

        Livewire::actingAs($chef)->test(ProductionRecipeForm::class, ['id' => $recipe->id])
            ->set('name', 'Renamed')
            ->call('save')
            ->assertForbidden();

        $this->assertSame('Sambal Base', $recipe->fresh()->name);
    }

    public function test_a_new_recipe_cannot_be_filed_under_a_kitchen_the_user_does_not_manage(): void
    {
        $chef = $this->member('chef');

        Livewire::actingAs($chef)->test(ProductionRecipeForm::class)
            ->set('name', 'New Base')
            ->set('kitchen_id', $this->kitchen->id)
            ->set('yield_uom_id', $this->uom->id)
            ->set('yield_quantity', '10')
            ->call('save')
            ->assertHasErrors('kitchen_id');

        $this->assertSame(0, ProductionRecipe::where('name', 'New Base')->count());
    }

    // ── Production: managers and chefs ───────────────────────────────────

    public function test_plain_staff_cannot_schedule_a_production_order(): void
    {
        $staff = $this->member('staff');
        $chef  = $this->member('chef');
        $order = $this->order($chef, 'draft');

        Livewire::actingAs($staff)->test(ProductionOrderForm::class, ['id' => $order->id])
            ->call('save', 'schedule')
            ->assertForbidden();

        $this->assertSame('draft', $order->fresh()->status);
    }

    public function test_plain_staff_opening_a_scheduled_order_does_not_start_it(): void
    {
        $staff = $this->member('staff');
        $chef  = $this->member('chef');
        $order = $this->order($chef, 'scheduled');

        Livewire::actingAs($staff)->test(ProductionExecute::class, ['id' => $order->id])
            ->assertForbidden();

        $this->assertSame('scheduled', $order->fresh()->status);
    }

    public function test_a_chef_opening_a_scheduled_order_starts_it(): void
    {
        $chef  = $this->member('chef');
        $order = $this->order($chef, 'scheduled');

        Livewire::actingAs($chef)->test(ProductionExecute::class, ['id' => $order->id])
            ->assertOk();

        $this->assertSame('in_progress', $order->fresh()->status);
    }

    public function test_plain_staff_cannot_save_progress_on_a_running_order(): void
    {
        $staff = $this->member('staff');
        $chef  = $this->member('chef');
        $order = $this->order($chef, 'in_progress');

        Livewire::actingAs($staff)->test(ProductionExecute::class, ['id' => $order->id])
            ->call('saveProgress')
            ->assertForbidden();
    }
}
