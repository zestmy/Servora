<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Regression cover for permission denials — "this role, but not that one bit".
 *
 * Denials cannot be enforced the obvious way, and the obvious way looks like it works.
 * Spatie registers its own `$gate->before()` that returns TRUE the moment a user holds a
 * permission, and Laravel takes the FIRST non-null before-callback — so a denial checked
 * in our own Gate::before never runs when Spatie's is registered first. `Gate::after` is
 * no escape either: it merges with `$result ??= $afterResult`, so it cannot overturn a
 * true.
 *
 * What actually carries the behaviour is `User::checkPermissionTo()`, aliased out of the
 * HasRoles trait and wrapped. Returning false there turns Spatie's `?: null` into null,
 * the Gate falls through to normal resolution, and the ability is denied everywhere.
 *
 * That is a subtle, load-bearing four-line override with no obvious connection to the
 * feature it implements. Someone tidying up the trait alias, or "simplifying" the wrapper
 * back to a plain Gate::before, would silently re-grant every denied permission — and
 * nothing else in the suite would notice. Hence this file.
 */
class PermissionDenialTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->makeCompany('Denial Test Co');
        $this->user    = $this->makeUser($this->company);

        setPermissionsTeamId($this->company->id);

        // A role that grants two abilities, so the tests can show that denying one
        // leaves the other alone.
        Permission::findOrCreate('purchasing.view', 'web');
        Permission::findOrCreate('sales.view', 'web');

        // A GLOBAL role (team_id NULL), like the system presets. Spatie stamps the
        // current team onto a role it creates, which would scope this one to the first
        // company and make the cross-company test meaningless — the role would be absent
        // there for the wrong reason.
        $role = Role::findOrCreate('Denial Test Role', 'web');
        \Illuminate\Support\Facades\DB::table('roles')->where('id', $role->id)->update(['team_id' => null]);
        $role->refresh();

        $role->syncPermissions(['purchasing.view', 'sales.view']);

        $this->user->assignRole($role);
        $this->forget();
    }

    private function makeCompany(string $name): Company
    {
        return Company::create([
            'name'      => $name,
            'slug'      => \Illuminate\Support\Str::slug($name) . '-' . uniqid(),
            'currency'  => 'MYR',
            'is_active' => true,
        ]);
    }

    private function makeUser(Company $company): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->companies()->syncWithoutDetaching([$company->id]);

        return $user;
    }

    /** Spatie caches aggressively; a stale cache would make these tests lie. */
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

    public function test_a_role_grant_is_effective_before_any_denial(): void
    {
        $user = $this->fresh();

        $this->assertTrue($user->canDo('purchasing.view'));
        $this->assertTrue($user->can('purchasing.view'));
    }

    public function test_a_denial_beats_a_role_grant_on_every_check_path(): void
    {
        $this->user->syncDenials($this->company->id, ['purchasing.view']);
        $user = $this->fresh();

        // All three are separate code paths into the same decision, and the wrapper has
        // to hold for all of them — canDo() is ours, can() is Laravel's, and Gate::allows
        // is what route middleware and @can ultimately call.
        $this->assertFalse($user->canDo('purchasing.view'), 'canDo() ignored the denial');
        $this->assertFalse($user->can('purchasing.view'), 'can() ignored the denial');
        $this->assertFalse(Gate::forUser($user)->allows('purchasing.view'), 'Gate ignored the denial');
    }

    public function test_a_denial_is_surgical_and_leaves_the_rest_of_the_role_intact(): void
    {
        $this->user->syncDenials($this->company->id, ['purchasing.view']);
        $user = $this->fresh();

        $this->assertFalse($user->canDo('purchasing.view'));
        $this->assertTrue($user->canDo('sales.view'), 'denying one ability took another with it');
        $this->assertTrue(
            $user->hasRole('Denial Test Role'),
            'the user should keep the role — a denial is not a role change'
        );
    }

    public function test_a_denial_beats_a_direct_grant_too(): void
    {
        $this->user->givePermissionTo('purchasing.view');
        $this->user->syncDenials($this->company->id, ['purchasing.view']);

        $this->assertFalse($this->fresh()->canDo('purchasing.view'));
    }

    /**
     * The failure mode that made a dual-read cutover unsafe in Phase 1, kept here as a
     * property: what a denial removes must stay removed even when the grant is still
     * sitting in the tables.
     */
    public function test_lifting_a_denial_restores_access(): void
    {
        $this->user->syncDenials($this->company->id, ['purchasing.view']);
        $this->assertFalse($this->fresh()->canDo('purchasing.view'));

        $this->user->syncDenials($this->company->id, []);
        $this->assertTrue($this->fresh()->canDo('purchasing.view'));
    }

    public function test_a_denial_applies_only_to_the_company_it_was_made_in(): void
    {
        $other = $this->makeCompany('Other Co');
        $this->user->companies()->syncWithoutDetaching([$other->id]);

        setPermissionsTeamId($other->id);
        $this->user->unsetRelation('roles')->unsetRelation('permissions');
        $this->user->assignRole(Role::findByName('Denial Test Role', 'web'));

        // Denied in the first company only.
        $this->user->syncDenials($this->company->id, ['purchasing.view']);

        setPermissionsTeamId($this->company->id);
        $this->assertFalse($this->fresh()->canDo('purchasing.view'), 'denial did not apply in its own company');

        setPermissionsTeamId($other->id);
        $this->assertTrue($this->fresh()->canDo('purchasing.view'), 'denial leaked into another company');
    }

    /**
     * Denials are a company-level instrument, not a way to clip a platform account.
     * canDo() short-circuits for system roles before the permission is ever consulted.
     */
    public function test_a_denial_does_not_clip_a_system_account(): void
    {
        $role = Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole($role);
        $this->user->syncDenials($this->company->id, ['purchasing.view']);

        $this->assertTrue(
            $this->fresh()->canDo('purchasing.view'),
            'a denial clipped a Super Admin, which is a company-level instrument reaching platform level'
        );
    }

    /**
     * The integration-level proof: `can:` route middleware must refuse too.
     *
     * This is the assertion that would fail if someone moved the check back into our own
     * Gate::before, because Spatie's callback would answer true first and the middleware
     * would let the request through.
     */
    public function test_route_middleware_refuses_a_denied_ability(): void
    {
        Route::middleware(['web', 'auth'])->get('/__denial-probe', fn () => 'reached')
            ->middleware('can:purchasing.view');

        $this->actingAs($this->fresh())->get('/__denial-probe')->assertOk();

        $this->user->syncDenials($this->company->id, ['purchasing.view']);
        $this->forget();

        $this->actingAs($this->fresh())->get('/__denial-probe')->assertForbidden();
    }

    /**
     * Guards the seam itself. If the trait alias is removed, this override disappears and
     * every denial silently stops working — with no other test noticing.
     */
    public function test_the_check_permission_to_override_is_still_in_place(): void
    {
        $method = new \ReflectionMethod(User::class, 'checkPermissionTo');

        $this->assertSame(
            User::class,
            $method->getDeclaringClass()->getName(),
            'User::checkPermissionTo() is no longer overridden. Denials are enforced through '
            . 'that wrapper — Spatie\'s own Gate::before answers first, so moving the check '
            . 'anywhere else silently re-grants every denied permission.'
        );
    }
}
