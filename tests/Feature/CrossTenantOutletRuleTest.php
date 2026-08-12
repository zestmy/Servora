<?php

namespace Tests\Feature;

use App\Livewire\Labels\Printers;
use App\Livewire\Settings\KitchenManagement;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * No screen accepts another company's outlet id.
 *
 * `exists:outlets,id` reads the table directly, so it bypasses CompanyScope
 * and passes any id that exists anywhere. Found across the central kitchen
 * surface while fixing the transfer and production destination dropdowns —
 * the dropdowns were company-scoped, the rules behind them were not, and a
 * Livewire property is client-supplied whatever the <select> offered.
 *
 * The Kitchen Settings one was not merely untidy: the base outlet is written
 * onto the kitchen and then every assigned kitchen user is inserted into
 * outlet_user for it, so a foreign id there handed this company's users
 * membership of another tenant's outlet.
 */
class CrossTenantOutletRuleTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private Outlet $foreign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Ours', 'slug' => Str::slug('Ours') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'OURS', 'code' => 'OUR', 'is_active' => true,
        ]);

        $other = Company::create([
            'name' => 'Theirs', 'slug' => Str::slug('Theirs') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->foreign = Outlet::withoutGlobalScopes()->create([
            'company_id' => $other->id, 'name' => 'THEIRS', 'code' => 'THR', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        foreach (['settings.kitchens', 'labels.print', 'labels.manage'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->user->givePermissionTo(['settings.kitchens', 'labels.print', 'labels.manage']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * The escalation. A forged base outlet used to be written onto the kitchen
     * and then joined every kitchen member to it.
     */
    public function test_a_kitchen_cannot_be_based_at_another_companys_outlet(): void
    {
        Livewire::actingAs($this->user)->test(KitchenManagement::class)
            ->call('openCreate')
            ->set('name', 'Forged Kitchen')
            ->set('outlet_id', $this->foreign->id)
            ->set('assignedUserIds', [$this->user->id])
            ->call('save')
            ->assertHasErrors('outlet_id');

        $this->assertDatabaseMissing('central_kitchens', ['name' => 'Forged Kitchen']);

        $this->assertSame(0,
            DB::table('outlet_user')
                ->where('outlet_id', $this->foreign->id)->where('user_id', $this->user->id)->count(),
            'A rejected base outlet must not have handed anyone membership of it.'
        );
    }

    public function test_a_kitchen_cannot_serve_another_companys_outlet(): void
    {
        Livewire::actingAs($this->user)->test(KitchenManagement::class)
            ->call('openCreate')
            ->set('name', 'Reaching Kitchen')
            ->set('outlet_id', $this->outlet->id)
            ->set('servedOutletIds', [$this->foreign->id])
            ->call('save')
            ->assertHasErrors('servedOutletIds.0');
    }

    /** Our own outlet still works — the rule narrows, it does not block. */
    public function test_a_kitchen_at_our_own_outlet_saves(): void
    {
        Livewire::actingAs($this->user)->test(KitchenManagement::class)
            ->call('openCreate')
            ->set('name', 'Real Kitchen')
            ->set('outlet_id', $this->outlet->id)
            ->set('servedOutletIds', [$this->outlet->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('central_kitchens', [
            'name' => 'Real Kitchen', 'outlet_id' => $this->outlet->id,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_a_label_printer_cannot_belong_to_another_companys_outlet(): void
    {
        Livewire::actingAs($this->user)->test(Printers::class)
            ->call('openCreate')
            ->set('name', 'Forged Printer')
            ->set('outlet_id', $this->foreign->id)
            ->call('save')
            ->assertHasErrors('outlet_id');

        $this->assertDatabaseMissing('label_printers', ['name' => 'Forged Printer']);
    }

    public function test_a_label_printer_at_our_own_outlet_saves(): void
    {
        Livewire::actingAs($this->user)->test(Printers::class)
            ->call('openCreate')
            ->set('name', 'Real Printer')
            ->set('outlet_id', $this->outlet->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('label_printers', ['name' => 'Real Printer']);
    }

    /**
     * The rule set is one shared trait now, so this pins the contract rather
     * than each caller: soft-deleted outlets are out too.
     */
    public function test_a_soft_deleted_outlet_is_not_accepted(): void
    {
        $gone = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'GONE', 'code' => 'GON', 'is_active' => true,
        ]);
        $gone->delete();

        Livewire::actingAs($this->user)->test(KitchenManagement::class)
            ->call('openCreate')
            ->set('name', 'Ghost Kitchen')
            ->set('outlet_id', $gone->id)
            ->call('save')
            ->assertHasErrors('outlet_id');
    }
}
