<?php

namespace Tests\Feature;

use App\Livewire\Onboarding\Wizard;
use App\Models\Company;
use App\Models\OnboardingStep;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The onboarding wizard is the welcome page every new customer lands on. It
 * dresses each step in kitchen language; these hold that the dressing
 * renders and follows the step the founder is actually on.
 */
class OnboardingWelcomeTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $founder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Kedai Kopi', 'slug' => 'kedai-kopi-' . Str::random(6),
            'currency' => 'MYR', 'is_active' => true, 'onboarding_completed_at' => null,
        ]);
        $outlet = Outlet::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'M1', 'is_active' => true]);

        $this->founder = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $outlet->id, 'can_view_all_outlets' => true,
        ]);
        $this->founder->companies()->syncWithoutDetaching([$this->company->id]);
        $this->founder->outlets()->syncWithoutDetaching([$outlet->id]);

        setPermissionsTeamId($this->company->id);
        $this->founder->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_the_welcome_opens_on_the_first_step_in_kitchen_language(): void
    {
        Livewire::actingAs($this->founder)->test(Wizard::class)
            ->assertSee('Welcome to Servora!')
            ->assertSee('The doors are open')
            ->assertSee('Step 1 of 4')
            ->assertSee('Mise en place')
            ->assertDontSee('class="ob-confetti"', escape: false);
    }

    public function test_the_last_step_gets_the_confetti(): void
    {
        foreach (array_slice(OnboardingStep::STEPS, 0, 3) as $step) {
            OnboardingStep::create(['company_id' => $this->company->id, 'step' => $step, 'completed_at' => now()]);
        }

        Livewire::actingAs($this->founder)->test(Wizard::class)
            ->assertSet('currentStep', 'explore_features')
            ->assertSee('Step 4 of 4')
            ->assertSee('class="ob-confetti"', escape: false)
            ->assertSee('Open the kitchen');
    }
}
