<?php

namespace Tests\Feature;

use App\Livewire\Inventory\Index as InventoryIndex;
use App\Livewire\Inventory\StockTakeForm;
use App\Models\Company;
use App\Models\Department;
use App\Models\Outlet;
use App\Models\Recipe;
use App\Models\StockTake;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The Inventory list's route asks only for inventory.view, and Livewire re-applies
 * nothing stronger to its actions. Every write on it therefore has to authorise
 * itself: a draft stock take is deleted by someone who may record stock takes, not
 * by anyone who can see the list.
 */
class InventoryWritePermissionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Stock Co', 'slug' => Str::slug('Stock Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);
        $this->department = Department::create(['company_id' => $this->company->id, 'name' => 'Kitchen', 'is_active' => true]);
    }

    /** @param array<int, string> $abilities */
    private function user(array $abilities): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(array_map(fn ($a) => Permission::findOrCreate($a, 'web'), $abilities));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function stockTake(string $status): StockTake
    {
        // Summary method: a single total, so a save needs no count lines.
        return StockTake::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'department_id' => $this->department->id,
            'stock_take_date' => now()->toDateString(), 'status' => $status, 'method' => 'summary',
            'total_stock_cost' => 100,
        ]);
    }

    public function test_a_view_only_user_cannot_delete_a_draft_stock_take(): void
    {
        $viewer = $this->user(['inventory.view']);
        $this->actingAs($viewer);
        $draft = $this->stockTake('draft');

        Livewire::actingAs($viewer)->test(InventoryIndex::class)
            ->call('deleteStockTake', $draft->id)
            ->assertForbidden();

        $this->assertNotSoftDeleted($draft);
    }

    public function test_a_stock_taker_can_still_delete_a_draft(): void
    {
        $counter = $this->user(['inventory.view', 'inventory.stock_takes.record']);
        $this->actingAs($counter);
        $draft = $this->stockTake('draft');

        Livewire::actingAs($counter)->test(InventoryIndex::class)
            ->call('deleteStockTake', $draft->id)
            ->assertOk();

        $this->assertSoftDeleted($draft);
    }

    public function test_delete_prep_item_refuses_a_finished_recipe(): void
    {
        $user = $this->user(['inventory.view', 'inventory.prep_items.delete']);
        $this->actingAs($user);
        $uom = UnitOfMeasure::create(['name' => 'Portion', 'abbreviation' => 'ptn', 'type' => 'count']);
        $recipe = Recipe::create([
            'company_id' => $this->company->id, 'name' => 'Nasi Lemak',
            'yield_uom_id' => $uom->id, 'is_prep' => false, 'is_active' => true,
        ]);

        Livewire::actingAs($user)->test(InventoryIndex::class)
            ->call('deletePrepItem', $recipe->id)
            ->assertNotFound();

        $this->assertNotSoftDeleted($recipe);
    }

    public function test_a_completed_stock_take_cannot_be_saved_over_without_reopen(): void
    {
        $counter = $this->user(['inventory.view', 'inventory.stock_takes.record']);
        $this->actingAs($counter);
        $done = $this->stockTake('completed');

        Livewire::actingAs($counter)->test(StockTakeForm::class, ['id' => $done->id])
            ->call('save')
            ->assertForbidden();

        $this->assertSame('completed', $done->fresh()->status);
    }

    public function test_a_draft_can_still_be_saved(): void
    {
        $counter = $this->user(['inventory.view', 'inventory.stock_takes.record']);
        $this->actingAs($counter);
        $draft = $this->stockTake('draft');

        Livewire::actingAs($counter)->test(StockTakeForm::class, ['id' => $draft->id])
            ->set('notes', 'recounted')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertSame('recounted', $draft->fresh()->notes);
    }
}
