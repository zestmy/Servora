<?php

namespace Tests\Feature;

use App\Livewire\Billing\Checkout;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Two ways a self-signup company got paid features without paying, closed
 * before the free tier makes self-signup the main way in.
 */
class BillingLoopholesTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_active_company_cannot_switch_itself_to_a_dearer_plan_for_free(): void
    {
        [$company, $user] = $this->company('self_signup');
        $basic = Plan::where('slug', 'basic')->firstOrFail();

        $sub = Subscription::create([
            'company_id' => $company->id, 'plan_id' => $basic->id, 'status' => 'active', 'amount' => 180,
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        // Checkout can only START a payment; the plan changes when the
        // webhook says it was paid. With no gateway, nothing changes at all.
        $this->actingAs($user);
        Livewire::test(Checkout::class, ['planSlug' => 'full'])
            ->call('pay')
            ->assertHasErrors('checkout');

        $this->assertSame($basic->id, $sub->fresh()->plan_id);
        $this->assertFalse(app(SubscriptionService::class)->canUseFeature($company, 'analytics'));
    }

    public function test_a_lapsed_self_signup_company_gets_no_features(): void
    {
        $plan = $this->plan('enterprise', 499, ['analytics']);
        [$company] = $this->company('self_signup');

        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'expired',
            'billing_cycle' => 'monthly', 'current_period_end' => now()->subMonth(),
        ]);

        $this->assertFalse(app(SubscriptionService::class)->canUseFeature($company, 'analytics'));
    }

    public function test_a_grandfathered_company_without_a_subscription_keeps_everything(): void
    {
        [$company] = $this->company('seeder');

        $this->assertTrue(app(SubscriptionService::class)->canUseFeature($company, 'analytics'));
    }

    private function plan(string $slug, int $price, array $flags): Plan
    {
        return Plan::create([
            'name' => ucfirst($slug), 'slug' => $slug, 'price_monthly' => $price, 'price_yearly' => $price * 10,
            'currency' => 'MYR', 'feature_flags' => $flags, 'is_active' => true, 'is_public' => true, 'trial_days' => 14,
        ]);
    }

    private function company(string $via): array
    {
        $company = Company::create([
            'name' => 'Bill Co', 'slug' => 'bill-co-' . Str::random(6),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $company->forceFill(['registered_via' => $via])->save();

        $outlet = Outlet::create(['company_id' => $company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id, 'outlet_id' => $outlet->id]);
        $user->companies()->syncWithoutDetaching([$company->id]);

        // Checkout is users.manage-only (BillingPermissionTest); the buyer here
        // is the company's administrator, so the loophole is what gets tested.
        setPermissionsTeamId($company->id);
        $user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('users.manage', 'web'));
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return [$company, $user];
    }
}
