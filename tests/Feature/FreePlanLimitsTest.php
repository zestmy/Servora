<?php

namespace Tests\Feature;

use App\Exceptions\LimitReachedException;
use App\Livewire\Settings\Outlets;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\Plan;
use App\Models\Recipe;
use App\Models\Subscription;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Entitlements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Free tier (docs/pricing-model.md): 1 outlet, 150 market list items,
 * 30 recipes, 2 users — and the reverse trial, where a trial or a lapsed plan
 * falls to Free instead of locking the account.
 */
class FreePlanLimitsTest extends TestCase
{
    use RefreshDatabase;

    // ── Caps ────────────────────────────────────────────────────────────────

    public function test_free_caps_recipes_at_thirty(): void
    {
        [$company, $user] = $this->company();
        $this->actingAs($user);

        for ($i = 1; $i <= 30; $i++) {
            $this->recipe($company, "Dish $i");
        }

        $this->expectException(LimitReachedException::class);
        $this->expectExceptionMessage('Your plan includes up to 30 recipes. Upgrade to add more.');
        $this->recipe($company, 'Dish 31');
    }

    public function test_a_second_outlet_on_free_opens_the_plan_limit_dialog(): void
    {
        [$company, $user] = $this->company();
        $this->actingAs($user);
        Gate::before(fn () => true);

        Livewire::test(Outlets::class)
            ->call('openCreate')
            ->set('name', 'Second')->set('code', 'SEC')
            ->call('save')
            ->assertDispatched('plan-limit', message: 'Your plan includes up to 1 active outlet(s). Upgrade to add more.');

        $this->assertSame(1, $company->outlets()->count());
    }

    public function test_an_archived_outlet_frees_its_slot_and_reactivating_it_takes_one(): void
    {
        [$company] = $this->company();
        $archived = Outlet::create(['company_id' => $company->id, 'name' => 'Old', 'code' => 'OLD', 'is_active' => false]);

        $this->assertFalse(app(Entitlements::class)->isOverLimit($company, 'outlets'));

        $this->expectException(LimitReachedException::class);
        $archived->update(['is_active' => true]);
    }

    public function test_free_caps_users_at_two(): void
    {
        [$company, $user] = $this->company();
        User::factory()->create(['company_id' => $company->id]);

        $this->expectException(LimitReachedException::class);
        app(Entitlements::class)->assertCanAdd($company, 'users');
    }

    public function test_paid_trial_legacy_and_grandfathered_companies_are_not_capped(): void
    {
        [$basic] = $this->company();
        $this->subscribe($basic, 'basic');
        [$trial] = $this->company();
        $this->subscribe($trial, 'free', 'trialing');
        [$legacy] = $this->company();
        $this->subscribe($legacy, Plan::create(['name' => 'Starter', 'slug' => 'starter', 'price_monthly' => 99,
            'price_yearly' => 990, 'currency' => 'MYR', 'max_outlets' => 1, 'is_active' => true, 'trial_days' => 14]));
        [$seeded] = $this->company('seeder');

        foreach ([$basic, $trial, $legacy, $seeded] as $company) {
            $this->assertNull(app(Entitlements::class)->limit($company, 'outlets'), $company->registered_via);
            Outlet::create(['company_id' => $company->id, 'name' => 'Two', 'code' => 'TWO', 'is_active' => true]);
        }

        $this->assertSame(2, $basic->outlets()->count());
    }

    // ── Reverse trial ───────────────────────────────────────────────────────

    public function test_an_ended_trial_lands_on_free_not_on_a_locked_account(): void
    {
        [$company, $user] = $this->company();
        $this->subscribe($company, 'full', 'expired', trialEnded: now()->subDay());
        Gate::before(fn () => true);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Your trial has ended and you are on the Free plan')
            ->assertDontSee('changes are paused');

        // Writable: the second outlet is refused by the CAP, not by a lock.
        Livewire::test(Outlets::class)
            ->call('openCreate')->set('name', 'Second')->set('code', 'SEC')->call('save')
            ->assertDispatched('plan-limit');
    }

    public function test_free_with_more_outlets_than_it_covers_is_read_only_until_archived(): void
    {
        [$company, $user] = $this->company();
        // Opened during the trial, when outlets were uncapped.
        $sub = $this->subscribe($company, 'full', 'trialing');
        Outlet::create(['company_id' => $company->id, 'name' => 'Two', 'code' => 'TWO', 'is_active' => true]);
        $sub->update(['status' => 'expired']);
        Gate::before(fn () => true);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('The Free plan covers 1 active outlet');

        $company->outlets()->where('code', 'TWO')->update(['is_active' => false]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('The Free plan covers 1 active outlet');
    }

    public function test_past_due_is_still_read_only(): void
    {
        [$company, $user] = $this->company();
        $this->subscribe($company, 'basic', 'past_due');
        Gate::before(fn () => true);

        $this->actingAs($user)->get(route('dashboard'))->assertSee('Your payment is overdue');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function recipe(Company $company, string $name): Recipe
    {
        $uom = UnitOfMeasure::first() ?? UnitOfMeasure::create([
            'name' => 'Portion', 'abbreviation' => 'ptn', 'type' => 'count', 'base_factor' => 1,
        ]);

        return Recipe::create(['company_id' => $company->id, 'name' => $name, 'yield_uom_id' => $uom->id]);
    }

    private function subscribe(Company $company, Plan|string $plan, string $status = 'active', $trialEnded = null): Subscription
    {
        $plan = is_string($plan) ? Plan::where('slug', $plan)->firstOrFail() : $plan;

        return Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => $status, 'billing_cycle' => 'monthly',
            'trial_ends_at' => $trialEnded ?? ($status === 'trialing' ? now()->addWeek() : null),
            'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);
    }

    private function company(string $via = 'self_signup'): array
    {
        $company = Company::create([
            'name' => 'Free Co', 'slug' => 'free-co-'.Str::random(6), 'currency' => 'MYR', 'is_active' => true,
        ]);
        $company->forceFill(['registered_via' => $via, 'onboarding_completed_at' => now()])->save();

        $outlet = Outlet::withoutEvents(fn () => Outlet::create([
            'company_id' => $company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]));
        $user = User::factory()->create(['company_id' => $company->id, 'outlet_id' => $outlet->id, 'can_view_all_outlets' => true]);
        $user->companies()->syncWithoutDetaching([$company->id]);
        $user->outlets()->syncWithoutDetaching([$outlet->id]);

        return [$company, $user];
    }
}
