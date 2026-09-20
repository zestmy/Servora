<?php

namespace Tests\Feature;

use App\Livewire\Purchasing\PurchaseRequestForm;
use App\Models\Asset;
use App\Models\Company;
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
 * Request quantities are held to one decimal place.
 *
 * The field steps in halves — half a case or half a tray is a real thing to
 * ask for, and 0.01 steps meant forty clicks to reach it. One decimal is the
 * matching precision: a typed 2.3 still stands, a fat-fingered 2.347 does not
 * reach a supplier. Rounding lives in the component rather than only in the
 * input's step, so it holds whichever way the number arrived.
 */
class PurchaseRequestQuantityTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private UnitOfMeasure $piece;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Qty PR Co', 'slug' => Str::slug('Qty PR Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->piece = UnitOfMeasure::create(['name' => 'Piece', 'abbreviation' => 'pc', 'type' => 'count']);

        $user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(collect([
            'purchasing.view', 'purchasing.requests.create', 'purchasing.requests.edit', 'assets.view',
        ])->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user);
    }

    private function asset(): Asset
    {
        return Asset::create([
            'company_id' => $this->company->id, 'name' => 'STAND MIXER',
            'uom_id' => $this->piece->id, 'unit_cost' => 4000, 'is_active' => true,
        ]);
    }

    public function test_a_typed_quantity_is_held_to_one_decimal(): void
    {
        Livewire::test(PurchaseRequestForm::class)
            ->call('addAsset', $this->asset()->id)
            ->set('lines.0.quantity', 2.347)
            ->assertSet('lines.0.quantity', 2.3);
    }

    public function test_a_half_step_survives_untouched(): void
    {
        Livewire::test(PurchaseRequestForm::class)
            ->call('addAsset', $this->asset()->id)
            ->set('lines.0.quantity', 2.5)
            ->assertSet('lines.0.quantity', 2.5);
    }

    /** Rounding happens on the way in, so what is saved is what was shown. */
    public function test_the_rounded_quantity_is_what_reaches_the_database(): void
    {
        Livewire::test(PurchaseRequestForm::class)
            ->call('addAsset', $this->asset()->id)
            ->set('lines.0.quantity', 4.06)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(4.1, (float) \App\Models\PurchaseRequestLine::firstOrFail()->quantity);
    }
}
