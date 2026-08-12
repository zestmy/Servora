<?php

namespace Tests\Feature;

use App\Livewire\Kitchen\ProductionOrderForm;
use App\Models\CentralKitchen;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\ProductionRecipe;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A production order line can name the branch the batch is for.
 *
 * Same defect as the transfer form, one screen over, and quieter. The
 * destination was `Rule::in(accessibleOutletIds())`, which in kitchen mode is
 * the kitchen's own outlet and nothing else — and that is precisely the
 * destination dispatchToOutlets() throws away, since it never transfers an
 * outlet to itself.
 *
 * So the only choice the dropdown offered was the one that did nothing. The
 * order saved, the batch completed, and the outlet transfer was silently never
 * raised. An empty dropdown at least looks broken; this looked like it worked.
 *
 * @see KitchenTransferDestinationTest for the same bug on the transfer form.
 */
class KitchenProductionDestinationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $kitchenOutlet;
    private Outlet $klcc;
    private Outlet $ioi;
    private CentralKitchen $kitchen;
    private User $kitchenUser;
    private ProductionRecipe $recipe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Prod Co', 'slug' => Str::slug('Prod Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->kitchenOutlet = $this->outlet('CENTRAL KITCHEN', 'CK');
        $this->klcc          = $this->outlet('KLCC', 'KLCC');
        $this->ioi           = $this->outlet('IOI', 'IOI');

        $this->kitchen = CentralKitchen::create([
            'company_id' => $this->company->id, 'name' => 'CK Cheras', 'code' => 'CKC',
            'outlet_id' => $this->kitchenOutlet->id, 'is_active' => true,
        ]);

        // Attached ONLY to the kitchen's outlet — the shape that broke.
        $this->kitchenUser = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => false,
        ]);
        $this->kitchenUser->companies()->syncWithoutDetaching([$this->company->id]);
        $this->kitchenUser->outlets()->sync([$this->kitchenOutlet->id]);
        $this->kitchen->users()->syncWithoutDetaching([$this->kitchenUser->id => ['role' => 'chef']]);

        setPermissionsTeamId($this->company->id);
        Permission::findOrCreate('kitchen.production.manage', 'web');
        $this->kitchenUser->givePermissionTo('kitchen.production.manage');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $uom = UnitOfMeasure::first() ?? UnitOfMeasure::create([
            'name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'weight', 'base_factor' => 1,
        ]);

        $this->recipe = ProductionRecipe::create([
            'company_id' => $this->company->id, 'kitchen_id' => $this->kitchen->id,
            'name' => 'Sambal Base', 'code' => 'SB1', 'yield_quantity' => 10,
            'yield_uom_id' => $uom->id, 'is_active' => true,
            'created_by' => $this->kitchenUser->id,
        ]);
    }

    private function outlet(string $name, string $code): Outlet
    {
        return Outlet::create([
            'company_id' => $this->company->id, 'name' => $name, 'code' => $code, 'is_active' => true,
        ]);
    }

    private function form()
    {
        return Livewire::actingAs($this->kitchenUser)->test(ProductionOrderForm::class)
            ->set('kitchen_id', $this->kitchen->id)
            ->call('addProductionRecipe', $this->recipe->id);
    }

    /** The reported bug. */
    public function test_a_kitchen_user_can_name_a_branch_to_produce_for(): void
    {
        $offered = $this->form()->viewData('outlets')->pluck('name')->sort()->values()->all();

        $this->assertSame(['IOI', 'KLCC'], $offered,
            'A kitchen produces for branches its staff are not attached to.');
    }

    /**
     * The kitchen's own outlet is not offered — dispatchToOutlets() skips a
     * line whose destination is the outlet it transfers FROM, so offering it
     * is offering a no-op. "None" is how you say "keep it in the kitchen".
     */
    public function test_the_kitchens_own_outlet_is_not_offered_as_a_destination(): void
    {
        $this->assertNotContains('CENTRAL KITCHEN',
            $this->form()->viewData('outlets')->pluck('name')->all());
    }

    public function test_a_destination_the_user_cannot_access_saves(): void
    {
        $this->form()
            ->set('lines.0.to_outlet_id', $this->klcc->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('production_order_lines', [
            'production_recipe_id' => $this->recipe->id,
            'to_outlet_id'         => $this->klcc->id,
        ]);
    }

    /** No destination at all is still fine — the stock stays in the kitchen. */
    public function test_a_line_with_no_destination_still_saves(): void
    {
        $this->form()->call('save')->assertHasNoErrors();

        $this->assertDatabaseHas('production_order_lines', [
            'production_recipe_id' => $this->recipe->id, 'to_outlet_id' => null,
        ]);
    }

    // ── What must stay shut ───────────────────────────────────────────────

    public function test_another_companys_outlet_cannot_be_a_destination(): void
    {
        $other = Company::create([
            'name' => 'Other Co', 'slug' => Str::slug('Other Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $foreign = Outlet::withoutGlobalScopes()->create([
            'company_id' => $other->id, 'name' => 'FOREIGN', 'code' => 'FGN', 'is_active' => true,
        ]);

        $this->form()
            ->set('lines.0.to_outlet_id', $foreign->id)
            ->call('save')
            ->assertHasErrors('lines.0.to_outlet_id');
    }

    public function test_a_deleted_outlet_cannot_be_a_destination(): void
    {
        $gone = $this->outlet('CLOSED', 'CLS');
        $gone->delete();

        $this->form()
            ->set('lines.0.to_outlet_id', $gone->id)
            ->call('save')
            ->assertHasErrors('lines.0.to_outlet_id');
    }

    // ── Reopening an order must not lose the destination ──────────────────

    /**
     * An outlet deactivated after the order was written is still shown and
     * still saved. Dropping it from the list would blank the destination the
     * next time anyone pressed Save — a silent edit nobody asked for.
     */
    public function test_a_destination_since_deactivated_is_kept(): void
    {
        $this->form()->set('lines.0.to_outlet_id', $this->klcc->id)->call('save');

        $orderId = \App\Models\ProductionOrder::latest('id')->value('id');
        $this->klcc->update(['is_active' => false]);

        $reopened = Livewire::actingAs($this->kitchenUser)
            ->test(ProductionOrderForm::class, ['id' => $orderId]);

        $this->assertContains('KLCC', $reopened->viewData('outlets')->pluck('name')->all(),
            'A destination already on the order must survive the outlet being deactivated.');

        $reopened->call('save')->assertHasNoErrors();

        $this->assertDatabaseHas('production_order_lines', [
            'production_recipe_id' => $this->recipe->id, 'to_outlet_id' => $this->klcc->id,
        ]);
    }
}
