<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Company settings screens refuse an account with no active company.
 *
 * A platform administrator has `users.company_id = null` and no company_user
 * membership — they operate above companies, which is why CompanySwitcher
 * hides the company block for them. EnsureCompanyScope sends an ordinary
 * company-less user back to login but deliberately waves system roles through,
 * because Admin > Companies lives inside the same company-scoped route group.
 *
 * So system roles could reach these screens, and `forCompany(null)` against an
 * `int` parameter is a TypeError. The settings hub never links them there —
 * it branches to systemGroups() — but a typed URL or an old bookmark answered
 * with a 500. Found on production: nine screens did this.
 *
 * A 403 that says why is the fix; the crash was the bug.
 */
class SystemRoleCompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    /** Every screen the production sweep caught. */
    private const COMPANY_SCOPED = [
        \App\Livewire\Settings\Banks::class,
        \App\Livewire\Settings\CertificationTypes::class,
        \App\Livewire\Settings\EmployeeParticulars::class,
        \App\Livewire\Settings\LeaveTypes::class,
        \App\Livewire\Settings\LmsUsers::class,
        \App\Livewire\Settings\PayComponents::class,
        \App\Livewire\Settings\PublicHolidays::class,
        \App\Livewire\Settings\SopExportPanel::class,
        \App\Livewire\Settings\StatutoryRates::class,
    ];

    private User $platformAdmin;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        // Otherwise the handler renders the 403 into a response and the test
        // sees a page instead of the refusal.
        $this->withoutExceptionHandling();

        $this->company = Company::create([
            'name' => 'Real Co', 'slug' => Str::slug('Real Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        // The production shape: no company, no membership.
        $this->platformAdmin = User::factory()->create(['company_id' => null]);

        setPermissionsTeamId(null);
        $role = Role::findOrCreate('Super Admin', 'web');
        $this->platformAdmin->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** The reported crash, across every screen that had it. */
    public function test_every_company_scoped_settings_screen_refuses_a_platform_admin(): void
    {
        $this->assertTrue($this->platformAdmin->isSystemRole(),
            'Fixture must actually be a system role, or this proves nothing.');

        foreach (self::COMPANY_SCOPED as $screen) {
            try {
                Livewire::actingAs($this->platformAdmin)->test($screen);
                $this->fail(class_basename($screen) . ' rendered for an account with no active company.');
            } catch (HttpException | AuthorizationException $e) {
                $status = $e instanceof HttpException ? $e->getStatusCode() : 403;
                $this->assertSame(403, $status, class_basename($screen) . ' should refuse with 403.');
            } catch (\TypeError $e) {
                $this->fail(class_basename($screen) . ' still crashes on a null company: ' . $e->getMessage());
            }
        }
    }

    /** The refusal has to explain itself — a bare 403 teaches nobody. */
    public function test_the_refusal_says_why(): void
    {
        try {
            Livewire::actingAs($this->platformAdmin)->test(\App\Livewire\Settings\StatutoryRates::class);
            $this->fail('Expected a refusal.');
        } catch (HttpException $e) {
            $this->assertStringContainsString('single company', $e->getMessage());
            $this->assertStringContainsString('Admin', $e->getMessage());
        }
    }

    /** An ordinary company user is untouched — the guard narrows nothing else. */
    public function test_a_company_user_still_reaches_the_screens(): void
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);

        setPermissionsTeamId($this->company->id);
        $role = Role::findOrCreate('Company Admin', 'web');
        $user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::COMPANY_SCOPED as $screen) {
            Livewire::actingAs($user)->test($screen)->assertOk();
        }
    }

    /** And the save path still writes, rather than the guard breaking it. */
    public function test_a_company_user_can_still_save_statutory_rates(): void
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);

        setPermissionsTeamId($this->company->id);
        $user->assignRole(Role::findOrCreate('Company Admin', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Livewire::actingAs($user)->test(\App\Livewire\Settings\StatutoryRates::class)
            ->set('hrdf_enabled', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(
            (bool) \App\Models\StatutorySetting::forCompany($this->company->id)->fresh()->hrdf_enabled
        );
    }
}
