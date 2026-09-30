<?php

namespace Tests\Feature;

use App\Livewire\Onboarding\Wizard;
use App\Livewire\Settings\Users;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * An admin manages their own company's people and nobody else's.
 *
 * Settings › Users loaded every target with User::findOrFail($id). User has no
 * company scope and the id arrives from the browser (?edit= and every action's
 * argument), so a Company Admin could open, re-password or delete any account
 * in any company. Onboarding had no gate at all: any staff account could open
 * it and invite a new Company Admin.
 */
class UserManagementTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;
    private Company $companyB;
    private User $adminA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->companyA, $outletA] = $this->company('Alpha');
        [$this->companyB, $outletB] = $this->company('Bravo');

        $this->adminA = $this->member($this->companyA, $outletA, ['users.manage']);
        $this->userB  = $this->member($this->companyB, $outletB, [], 'victim@bravo.test');
    }

    private function company(string $name): array
    {
        $company = Company::create([
            'name' => $name, 'slug' => Str::slug($name) . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $outlet = Outlet::create(['company_id' => $company->id, 'name' => 'Main', 'code' => 'M' . $company->id, 'is_active' => true]);

        return [$company, $outlet];
    }

    private function member(Company $company, Outlet $outlet, array $permissions, ?string $email = null): User
    {
        $user = User::factory()->create([
            'company_id' => $company->id, 'outlet_id' => $outlet->id, 'can_view_all_outlets' => true,
            ...($email ? ['email' => $email] : []),
        ]);
        $user->companies()->syncWithoutDetaching([$company->id]);
        $user->outlets()->syncWithoutDetaching([$outlet->id]);

        setPermissionsTeamId($company->id);
        foreach ($permissions as $p) {
            $user->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function asAdminA()
    {
        setPermissionsTeamId($this->companyA->id);

        return Livewire::actingAs($this->adminA)->test(Users::class);
    }

    public function test_another_companys_user_cannot_be_opened_for_editing(): void
    {
        $this->asAdminA()
            ->call('openEdit', $this->userB->id)
            ->assertSet('editingId', null)
            ->assertSet('email', '');
    }

    public function test_another_companys_user_cannot_be_repassworded_through_a_forged_editing_id(): void
    {
        $before = $this->userB->fresh()->password;

        $this->asAdminA()
            ->set('editingId', $this->userB->id)
            ->set('name', 'Owned')
            ->set('email', 'victim@bravo.test')
            ->set('password', 'attacker-password')
            ->call('save')
            ->assertForbidden();

        $fresh = $this->userB->fresh();
        $this->assertSame($before, $fresh->password);
        $this->assertFalse(Hash::check('attacker-password', $fresh->password));
        $this->assertSame($this->companyB->id, (int) $fresh->company_id);
    }

    public function test_another_companys_user_cannot_be_deleted(): void
    {
        $this->asAdminA()->call('delete', $this->userB->id);

        $this->assertNotNull(User::find($this->userB->id));
    }

    public function test_an_admin_can_still_edit_their_own_member(): void
    {
        $own = $this->member($this->companyA, Outlet::where('company_id', $this->companyA->id)->first(), [], 'own@alpha.test');

        $this->asAdminA()
            ->call('openEdit', $own->id)
            ->assertSet('editingId', $own->id)
            ->assertSet('email', 'own@alpha.test');
    }

    public function test_onboarding_is_closed_to_staff_without_user_management(): void
    {
        $this->companyA->update(['onboarding_completed_at' => null]);
        $staff = $this->member($this->companyA, Outlet::where('company_id', $this->companyA->id)->first(), ['recipes.view']);

        setPermissionsTeamId($this->companyA->id);
        Livewire::actingAs($staff)->test(Wizard::class)->assertRedirect(route('dashboard'));
    }

    /** The actions check on every request, not only when the page loads. */
    public function test_onboarding_actions_refuse_once_the_gate_closes(): void
    {
        $this->companyA->update(['onboarding_completed_at' => null]);

        setPermissionsTeamId($this->companyA->id);
        $wizard = Livewire::actingAs($this->adminA)->test(Wizard::class);

        $this->companyA->update(['onboarding_completed_at' => now()]);
        $this->adminA->unsetRelation('company');

        $wizard->set('invites', [['name' => 'Evil', 'email' => 'evil@x.test', 'role' => 'Company Admin']])
            ->call('saveInviteTeam')
            ->assertForbidden();

        $this->assertNull(User::where('email', 'evil@x.test')->first());
    }

    public function test_onboarding_is_closed_once_complete(): void
    {
        $this->companyA->update(['onboarding_completed_at' => now()]);

        setPermissionsTeamId($this->companyA->id);
        Livewire::actingAs($this->adminA)->test(Wizard::class)->assertRedirect(route('dashboard'));
    }

    public function test_the_founder_can_still_onboard(): void
    {
        $this->companyA->update(['onboarding_completed_at' => null]);

        setPermissionsTeamId($this->companyA->id);
        Livewire::actingAs($this->adminA)->test(Wizard::class)->assertNoRedirect();
    }
}
