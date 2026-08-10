<?php

namespace Tests\Feature;

use App\Helpers\PermissionRegistry;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Doing something in a module implies being able to see it.
 *
 * Splitting each module's write abilities out of its view ability created a state
 * nobody intended: someone who can record a stock take but cannot open the stock
 * list. Every form's way back answers 403, the module disappears from their
 * sidebar, and the person cannot tell a permission boundary from a broken page.
 * On production that was 11 people holding `roster.create` without `roster.view`,
 * and one holding `hr.employees.manage` without `hr.view`.
 *
 * The rule is resolved in `User::checkPermissionTo()` rather than by handing out
 * extra grants, because grants drift: an admin unticking "view" while leaving
 * "record" ticked would put the gap straight back. Resolved this way it cannot be
 * unticked, and every caller — `can:` middleware, @can, @canDo, can() — sees it.
 *
 * What must stay true is the shape of the rule, and that is what this file pins:
 * it flows one way, it stops at the module boundary, and a denial still beats it.
 */
class PermissionImplicationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name'      => 'Implication Test Co',
            'slug'      => Str::slug('Implication Test Co') . '-' . uniqid(),
            'currency'  => 'MYR',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);

        setPermissionsTeamId($this->company->id);

        foreach ([
            'inventory.view', 'inventory.stock_takes.record',
            'hr.view', 'hr.documents.view', 'hr.documents.manage',
        ] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    private function forget(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user->unsetRelation('roles')->unsetRelation('permissions');
    }

    private function fresh(): User
    {
        $this->forget();
        $user = User::find($this->user->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return $user;
    }

    /** Grants an ability through a role, which is how real access arrives. */
    private function giveViaRole(string ...$abilities): void
    {
        $role = Role::findOrCreate('Implication Test Role', 'web');
        DB::table('roles')->where('id', $role->id)->update(['team_id' => null]);
        $role->refresh();
        $role->syncPermissions($abilities);

        $this->user->assignRole($role);
        $this->forget();
    }

    public function test_recording_in_a_module_grants_the_module_view(): void
    {
        $this->giveViaRole('inventory.stock_takes.record');

        $user = $this->fresh();

        $this->assertTrue($user->can('inventory.stock_takes.record'));
        $this->assertTrue(
            $user->can('inventory.view'),
            'Someone who can record a stock take must be able to open the stock list.'
        );
    }

    public function test_viewing_does_not_grant_the_right_to_record(): void
    {
        $this->giveViaRole('inventory.view');

        $this->assertFalse(
            $this->fresh()->can('inventory.stock_takes.record'),
            'Implication flows one way. Reading a list must never confer writing to it.'
        );
    }

    public function test_an_explicit_denial_beats_the_implication(): void
    {
        $this->giveViaRole('inventory.stock_takes.record');

        $this->user->syncDenials($this->company->id, ['inventory.view']);
        $this->forget();

        $this->assertFalse(
            $this->fresh()->can('inventory.view'),
            'A denial is a considered exception; inheriting the ability back through a side door would make it unenforceable.'
        );
    }

    public function test_denying_the_implier_takes_the_implied_view_with_it(): void
    {
        $this->giveViaRole('inventory.stock_takes.record');

        $this->user->syncDenials($this->company->id, ['inventory.stock_takes.record']);
        $this->forget();

        $user = $this->fresh();

        $this->assertFalse($user->can('inventory.stock_takes.record'));
        $this->assertFalse(
            $user->can('inventory.view'),
            'Take away the work and the view that came with it goes too, or a denial leaves a shadow behind.'
        );
    }

    public function test_implication_stops_at_the_module_boundary(): void
    {
        $this->giveViaRole('hr.documents.manage');

        $user = $this->fresh();

        $this->assertTrue(
            $user->can('hr.documents.view'),
            'Managing company documents implies seeing them.'
        );
        $this->assertFalse(
            $user->can('hr.view'),
            'HR is several modules. Document access must not become employee access.'
        );
    }

    /**
     * The map is derived from the registry's shape, so a module gaining an ability
     * inherits the rule. What must not happen is a WRITE turning up as implied.
     */
    public function test_only_view_abilities_are_ever_implied(): void
    {
        foreach (PermissionRegistry::impliedViews() as $view => $impliers) {
            $this->assertStringEndsWith('.view', $view, "$view is not a view ability and must not be implied.");
            $this->assertNotEmpty($impliers, "$view is listed with nothing implying it.");
            $this->assertNotContains($view, $impliers, "$view cannot imply itself.");
        }
    }
}
