<?php

namespace Tests\Feature;

use App\Livewire\Billing\Checkout;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\PriceCalculator;
use App\Services\Entitlements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Per-outlet checkout (docs/pricing-model.md): the arithmetic, the credit for
 * an upgrade, the parked downgrade, the payment carrying its configuration,
 * and the webhook applying exactly that.
 */
class PerOutletCheckoutTest extends TestCase
{
    use RefreshDatabase;

    // ── Arithmetic ──────────────────────────────────────────────────────────

    public function test_the_suite_is_priced_per_outlet_with_marginal_volume_discounts(): void
    {
        $calc = app(PriceCalculator::class);

        $this->assertSame(180.0, $calc->quote($this->plan('basic'), 1, [], 'monthly')->monthly);
        $this->assertSame(900.0, $calc->quote($this->plan('basic'), 5, [], 'monthly')->monthly, 'no discount up to five');
        // 7 × 180 − 2 × 18
        $this->assertSame(1224.0, $calc->quote($this->plan('basic'), 7, [], 'monthly')->monthly);
        // 12 × 180 − 5 × 18 − 2 × 27
        $this->assertSame(2016.0, $calc->quote($this->plan('basic'), 12, [], 'monthly')->monthly);
        $this->assertFalse($calc->quote($this->plan('basic'), 20, [], 'monthly')->ok(), '20+ is quoted by hand');
    }

    public function test_add_ons_minimums_the_cap_and_yearly(): void
    {
        $calc = app(PriceCalculator::class);

        // 180 + 80 (labels) + 10 × 3 (HR bills its minimum of ten)
        $q = $calc->quote($this->plan('basic'), 1, ['labels' => 1, 'hr' => 4], 'monthly');
        $this->assertSame(290.0, $q->monthly);

        // Full includes Labels: not charged again. + one kitchen.
        $q = $calc->quote($this->plan('full'), 2, ['labels' => 1, 'central_kitchen' => 1], 'yearly');
        $this->assertSame(1100.0, $q->monthly);
        $this->assertSame(11000.0, $q->cycleTotal, 'yearly is ten months');

        $this->assertFalse($calc->quote($this->plan('basic'), 1, ['labels' => 1, 'assets' => 1, 'audits' => 1], 'monthly')->ok());
    }

    // ── Buying ──────────────────────────────────────────────────────────────

    public function test_a_free_company_pays_and_the_payment_carries_its_configuration(): void
    {
        [$company] = $this->company();
        Outlet::withoutEvents(fn () => Outlet::create(['company_id' => $company->id, 'name' => 'Two', 'code' => 'TWO', 'is_active' => true]));
        $this->fakeChip();

        $result = app(CheckoutService::class)->start($company, $this->plan('basic'), 2, ['labels' => 1], 'monthly');

        $this->assertSame('https://pay.test/checkout', $result['redirect']);

        $payment = Payment::latest('id')->first();
        $this->assertSame('440.00', $payment->amount); // 2 × 180 + 80
        $this->assertSame(2, $payment->checkout['outlet_quantity']);
        $this->assertEquals(['labels' => ['quantity' => 1, 'unit_price' => 80]], $payment->checkout['addons']);
        $this->assertSame(Subscription::STATUS_INCOMPLETE, $payment->subscription->status);
        $this->assertSame([], app(Entitlements::class)->modules($company), 'nothing unlocks before payment');

        app(CheckoutService::class)->fulfil($payment->fresh());

        $sub = $payment->subscription->fresh();
        $this->assertSame(Subscription::STATUS_ACTIVE, $sub->status);
        $this->assertSame('basic', $sub->plan->slug);
        $this->assertSame('440.00', $sub->amount);
        $this->assertTrue(app(Entitlements::class)->allows($company->fresh(), 'labels'));
    }

    public function test_paying_for_fewer_outlets_than_are_active_is_refused(): void
    {
        [$company] = $this->company();
        Outlet::withoutEvents(fn () => Outlet::create(['company_id' => $company->id, 'name' => 'Two', 'code' => 'TWO', 'is_active' => true]));

        $result = app(CheckoutService::class)->start($company, $this->plan('basic'), 1, [], 'monthly');

        $this->assertStringContainsString('You have 2 active outlets', $result['error']);
    }

    public function test_an_upgrade_is_charged_the_difference_for_the_rest_of_the_period(): void
    {
        [$company] = $this->company();
        $this->subscribe($company, 'basic', amount: 180, start: now()->subDays(15), end: now()->addDays(15));

        $quote = app(CheckoutService::class)->quote($company, $this->plan('full'), 1, [], 'monthly');

        $this->assertEqualsWithDelta(90, $quote->credit, 0.5);
        $this->assertEqualsWithDelta(310, $quote->due(), 0.5);
    }

    public function test_a_downgrade_waits_for_the_period_it_was_paid_for(): void
    {
        [$company] = $this->company();
        $sub = $this->subscribe($company, 'full', amount: 400, start: now()->subDay(), end: now()->addDays(29));

        $result = app(CheckoutService::class)->start($company, $this->plan('basic'), 1, [], 'monthly');

        $this->assertTrue($result['scheduled']->equalTo($sub->current_period_end));
        $this->assertSame(180.0, (float) $sub->fresh()->pending_change['amount']);
        $this->assertSame('full', $sub->fresh()->plan->slug, 'nothing changes until renewal');
    }

    public function test_renewal_applies_a_parked_change_at_its_price_and_asks_only_once(): void
    {
        [$company] = $this->company();
        $sub = $this->subscribe($company, 'full', amount: 400, start: now()->subDays(29), end: now()->addDay());
        $sub->update(['pending_change' => [
            'plan_id' => $this->plan('basic')->id, 'billing_cycle' => 'monthly',
            'outlet_quantity' => 1, 'amount' => 180, 'addons' => [],
        ]]);
        $this->fakeChip();

        $this->artisan('billing:process-recurring')->assertSuccessful();
        $this->artisan('billing:process-recurring')->assertSuccessful();

        $payments = Payment::where('subscription_id', $sub->id)->get();
        $this->assertCount(1, $payments, 'a second run does not raise a second purchase');
        $this->assertSame('180.00', $payments->first()->amount);

        app(CheckoutService::class)->fulfil($payments->first());
        $this->assertSame('basic', $sub->fresh()->plan->slug);
        $this->assertNull($sub->fresh()->pending_change);
    }

    public function test_legacy_arrangements_are_moved_by_hand(): void
    {
        [$seeded] = $this->company('seeder');

        $this->assertNotNull(app(CheckoutService::class)->blockedReason($seeded));
        $this->assertArrayHasKey('error', app(CheckoutService::class)->start($seeded, $this->plan('basic'), 1, [], 'monthly'));
    }

    public function test_the_checkout_screen_quotes_and_pretickes_a_locked_module(): void
    {
        [$company, $user] = $this->company();
        $this->actingAs($user);

        Livewire::withQueryParams(['unlock' => 'labels'])
            ->test(Checkout::class, ['planSlug' => 'basic'])
            ->assertSet('addons.labels', true)
            ->assertSee('RM260.00') // 180 + 80
            ->set('hr', true)
            ->assertSee('RM290.00') // + 10 × 3
            ->set('billing_cycle', 'yearly')
            ->assertSee('RM2,900.00');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function fakeChip(): void
    {
        config(['chipin.api_key' => 'test-key', 'chipin.brand_id' => 'brand']);
        Http::fake(['*' => Http::response(['id' => 'pur_'.Str::random(6), 'checkout_url' => 'https://pay.test/checkout'])]);
    }

    private function plan(string $slug): Plan
    {
        return Plan::where('slug', $slug)->firstOrFail();
    }

    private function subscribe(Company $company, string $slug, float $amount, $start, $end): Subscription
    {
        return Subscription::create([
            'company_id' => $company->id, 'plan_id' => $this->plan($slug)->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'outlet_quantity' => 1, 'amount' => $amount,
            'current_period_start' => $start, 'current_period_end' => $end,
        ]);
    }

    private function company(string $via = 'self_signup'): array
    {
        $company = Company::create([
            'name' => 'Pay Co', 'slug' => 'pay-co-'.Str::random(6), 'currency' => 'MYR', 'is_active' => true,
            'email' => 'owner@pay.test',
        ]);
        $company->forceFill(['registered_via' => $via, 'onboarding_completed_at' => now()])->save();

        $outlet = Outlet::withoutEvents(fn () => Outlet::create([
            'company_id' => $company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]));
        $user = User::factory()->create(['company_id' => $company->id, 'outlet_id' => $outlet->id]);
        $user->companies()->syncWithoutDetaching([$company->id]);

        return [$company, $user];
    }
}
