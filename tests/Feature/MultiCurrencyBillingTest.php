<?php

namespace Tests\Feature;

use App\Livewire\Admin\Currencies;
use App\Models\BillingCurrency;
use App\Models\Company;
use App\Models\ExchangeRate;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\CurrencyResolver;
use App\Services\Billing\ExchangeRates;
use App\Services\Billing\PriceBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Subscriptions priced in the visitor's currency and charged in MYR at Bank
 * Negara's rate (Billing\CurrencyResolver, PriceBook, ExchangeRates).
 */
class MultiCurrencyBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        // Never Bank Negara for real. A test that needs rates fakes them.
        Http::preventStrayRequests();
    }

    // ── Rates ───────────────────────────────────────────────────────────────

    public function test_bnm_rates_are_stored_per_unit_and_read_back_per_one(): void
    {
        $this->fakeBnm();

        $this->assertSame(3, app(ExchangeRates::class)->refresh());

        $idr = ExchangeRate::firstWhere('currency', 'IDR');
        $this->assertSame(100, $idr->unit);

        $fx = app(ExchangeRates::class)->rate('IDR');
        $this->assertEqualsWithDelta(0.000269, $fx->myrPerUnit, 1e-9, 'MYR per ONE rupiah, not per hundred');
        $this->assertSame(269.0, $fx->toMyr(1_000_000));

        Http::assertSent(fn ($r) => $r->hasHeader('Accept', 'application/vnd.BNM.API.v1+json'));
    }

    public function test_a_stale_rate_is_not_used_and_bnm_down_keeps_the_last_good_one(): void
    {
        $this->rate('SGD', 3.2);
        Http::fake(['*' => Http::response('', 500)]);

        $this->assertSame(3.2, app(ExchangeRates::class)->rate('SGD', fresh: true)->myrPerUnit, 'BNM down: last good rate');

        ExchangeRate::where('currency', 'SGD')->update(['rate_date' => now()->subDays(10)]);
        $this->assertNull(app(ExchangeRates::class)->rate('SGD'), 'ten days old is nobody\'s agreed price');
    }

    public function test_the_rate_type_is_a_platform_setting(): void
    {
        $this->rate('USD', 4.0, selling: 4.1);
        \App\Models\AppSetting::set('billing_fx_rate_type', 'selling_rate');

        $this->assertSame(4.1, app(ExchangeRates::class)->rate('USD')->myrPerUnit);
    }

    // ── Who gets which currency ────────────────────────────────────────────

    public function test_countries_resolve_to_their_currency_then_the_rest_of_the_world(): void
    {
        $this->currency('SGD', ['SG', 'BN']);
        $resolver = app(CurrencyResolver::class);

        $this->assertSame('SGD', $resolver->forCountry('BN'));
        $this->assertSame('MYR', $resolver->forCountry('FR'), 'no rest-of-world currency: MYR');
        $this->assertSame('MYR', $resolver->forCountry('MY'));
        $this->assertSame('MYR', $resolver->forCountry(null), 'unknown country: priced as Malaysia');

        $this->currency('USD', [], row: true);
        CurrencyResolver::forget();
        $this->assertSame('USD', $resolver->forCountry('FR'));
        $this->assertSame('MYR', $resolver->forCountry('MY'), 'Malaysia is never the rest of the world');
        $this->assertSame('MYR', $resolver->forCountry(null));
    }

    public function test_a_company_keeps_its_locked_currency_wherever_it_logs_in_from(): void
    {
        $this->currency('SGD', ['SG']);
        [$company] = $this->company(['billing_country' => 'SG']);

        $this->assertSame('SGD', app(CurrencyResolver::class)->forCompany($company));

        $company->update(['billing_currency' => 'MYR']);
        $this->assertSame('MYR', app(CurrencyResolver::class)->forCompany($company->fresh()));
    }

    // ── Prices ──────────────────────────────────────────────────────────────

    public function test_converted_prices_round_up_and_fixed_prices_win(): void
    {
        $this->rate('SGD', 3.2);
        $this->currency('SGD', ['SG']);
        $book = app(CurrencyResolver::class)->book('SGD');

        $this->assertSame(57.0, $book->suite('basic', 180), '180 / 3.2 = 56.25, rounded UP');
        $this->assertSame(1.0, $book->module('hr', 3), 'small prices round to a tenth: 0.94 → 1.0');
        $this->assertSame('S$57', $book->format(57));

        BillingCurrency::where('code', 'SGD')->update(['pricing_mode' => 'fixed', 'prices' => ['suite' => ['basic' => 49]]]);
        CurrencyResolver::forget();
        $book = app(CurrencyResolver::class)->book('SGD');
        $this->assertSame(49.0, $book->suite('basic', 180));
        $this->assertSame(125.0, $book->suite('full', 400), 'a blank fixed price converts');
    }

    public function test_a_currency_with_no_rate_falls_back_to_myr(): void
    {
        Http::fake(['*' => Http::response('', 503)]);
        $this->currency('SGD', ['SG']);

        $this->assertTrue(app(CurrencyResolver::class)->book('SGD')->isMyr());
    }

    public function test_the_pricing_page_shows_the_visitors_currency(): void
    {
        $this->rate('SGD', 3.2);
        $this->currency('SGD', ['SG']);

        $this->get('/pricing', ['CF-IPCountry' => 'SG'])
            ->assertOk()
            ->assertSee('S$57', false)
            ->assertSee('(SGD) for Singapore', false);

        $this->get('/pricing', ['CF-IPCountry' => 'MY'])->assertOk()->assertSee('RM180', false)->assertDontSee('S$57', false);
    }

    // ── Paying ──────────────────────────────────────────────────────────────

    public function test_checkout_charges_chip_in_ringgit_at_a_fresh_bnm_rate(): void
    {
        $this->currency('SGD', ['SG']);
        [$company] = $this->company(['billing_country' => 'SG']);
        config(['chipin.api_key' => 'test-key', 'chipin.brand_id' => 'brand']);
        Http::fake([
            'api.bnm.gov.my/*' => Http::response(['data' => [$this->bnmRow('SGD', 1, 3.2)]]),
            '*' => Http::response(['id' => 'pur_'.Str::random(6), 'checkout_url' => 'https://pay.test/checkout']),
        ]);

        $result = app(CheckoutService::class)->start($company, $this->plan('basic'), 1, ['labels' => 1], 'monthly');
        $this->assertSame('https://pay.test/checkout', $result['redirect'] ?? $result['error'] ?? null);

        // S$57 + S$25 (80 / 3.2) = S$82, charged as RM262.40.
        $payment = Payment::latest('id')->first();
        $this->assertSame('MYR', $payment->currency);
        $this->assertSame('262.40', $payment->amount);
        $this->assertSame('SGD', $payment->checkout['currency']);
        $this->assertEquals(82, $payment->checkout['amount']);
        $this->assertEquals(['currency' => 'SGD', 'amount' => 82, 'myr_per_unit' => 3.2, 'myr' => 262.4],
            array_intersect_key($payment->fx, array_flip(['currency', 'amount', 'myr_per_unit', 'myr'])));

        Http::assertSent(fn ($r) => str_contains($r->url(), 'purchases') && $r['purchase']['currency'] === 'MYR'
            && $r['purchase']['products'][0]['price'] === 26240);

        $subscription = app(CheckoutService::class)->fulfil($payment->fresh());
        $this->assertSame('SGD', $subscription->currency);
        $this->assertEquals(82, $subscription->amount, 'the subscription keeps the price the customer agreed');
        $this->assertSame('SGD', $company->fresh()->billing_currency, 'the first payment locks the currency');
    }

    public function test_checkout_refuses_rather_than_guess_without_a_rate(): void
    {
        $this->currency('SGD', ['SG'], mode: 'fixed', prices: ['suite' => ['basic' => 50, 'full' => 120],
            'module' => array_fill_keys(['labels', 'learn', 'audits', 'assets', 'pos_sync', 'ai_insights', 'hr', 'central_kitchen'], 10)]);
        [$company] = $this->company(['billing_country' => 'SG']);
        config(['chipin.api_key' => 'test-key']);
        Http::fake(['*' => Http::response('', 503)]);

        $result = app(CheckoutService::class)->start($company, $this->plan('basic'), 1, [], 'monthly');

        $this->assertStringContainsString("Bank Negara rate for SGD", $result['error']);
        $this->assertSame(0, Payment::count());
    }

    public function test_renewal_reconverts_at_the_day_rate(): void
    {
        [$company] = $this->company(['billing_currency' => 'SGD']);
        $sub = Subscription::create([
            'company_id' => $company->id, 'plan_id' => $this->plan('basic')->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'outlet_quantity' => 1, 'currency' => 'SGD', 'amount' => 57,
            'current_period_start' => now()->subMonth(), 'current_period_end' => now()->addDay(),
        ]);
        config(['chipin.api_key' => 'test-key', 'chipin.brand_id' => 'brand']);
        Http::fake([
            'api.bnm.gov.my/*' => Http::response(['data' => [$this->bnmRow('SGD', 1, 3.3)]]),
            '*' => Http::response(['id' => 'pur_x', 'checkout_url' => 'https://pay.test/x']),
        ]);

        $this->artisan('billing:process-recurring')->assertSuccessful();

        $payment = Payment::where('subscription_id', $sub->id)->first();
        $this->assertSame('188.10', $payment->amount, 'S$57 × 3.3');
        $this->assertSame('SGD', $payment->fx['currency']);
    }

    // ── Admin ───────────────────────────────────────────────────────────────

    public function test_an_admin_adds_a_currency_bnm_publishes_and_countries_stay_unique(): void
    {
        $this->rate('SGD', 3.2);
        $this->rate('USD', 4.1);
        $this->currency('USD', ['US']);

        Livewire::actingAs($this->admin())->test(Currencies::class)
            ->call('openCreate')
            ->set('code', 'SGD')->set('name', 'Singapore dollar')->set('countries', 'sg, us')
            ->call('save')->assertHasErrors('countries')
            ->set('countries', 'SG, MY')->call('save')->assertHasErrors('countries')
            ->set('countries', 'SG, BN')->call('save')->assertHasNoErrors();

        $this->assertSame(['SG', 'BN'], BillingCurrency::firstWhere('code', 'SGD')->countries);

        Livewire::actingAs($this->admin())->test(Currencies::class)
            ->call('openCreate')->set('code', 'XYZ')->set('name', 'Nowhere')->call('save')
            ->assertHasErrors(['code' => 'exists']);
    }

    public function test_the_currencies_screen_is_for_system_admins(): void
    {
        [, $user] = $this->company();

        $this->actingAs($user)->get('/admin/currencies')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/currencies')->assertOk()->assertSee('Everywhere else');
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function fakeBnm(): void
    {
        Http::fake(['api.bnm.gov.my/*' => Http::response(['data' => [
            $this->bnmRow('USD', 1, 4.08),
            $this->bnmRow('IDR', 100, 0.0269),
            $this->bnmRow('SGD', 1, 3.19),
            ['currency_code' => 'XDR', 'unit' => 1, 'rate' => ['date' => null]],
        ]])]);
    }

    private function bnmRow(string $code, int $unit, float $middle): array
    {
        return ['currency_code' => $code, 'unit' => $unit, 'rate' => [
            'date' => now()->toDateString(), 'buying_rate' => $middle * 0.99, 'selling_rate' => $middle * 1.01, 'middle_rate' => $middle,
        ]];
    }

    private function rate(string $code, float $middle, ?float $selling = null): void
    {
        ExchangeRate::updateOrCreate(['currency' => $code], [
            'unit' => 1, 'middle_rate' => $middle, 'selling_rate' => $selling, 'rate_date' => now()->toDateString(), 'fetched_at' => now(),
        ]);
    }

    private function currency(string $code, array $countries, bool $row = false, string $mode = 'converted', ?array $prices = null): BillingCurrency
    {
        CurrencyResolver::forget();

        return BillingCurrency::create([
            'code' => $code, 'name' => $code, 'symbol' => ['SGD' => 'S$', 'USD' => 'US$'][$code] ?? null,
            'countries' => $countries, 'is_rest_of_world' => $row, 'pricing_mode' => $mode, 'prices' => $prices, 'rounding' => 1,
        ]);
    }

    private function plan(string $slug): Plan
    {
        return Plan::where('slug', $slug)->firstOrFail();
    }

    private function company(array $attributes = []): array
    {
        $company = Company::create(array_merge([
            'name' => 'FX Co', 'slug' => 'fx-co-'.Str::random(6), 'currency' => 'MYR', 'is_active' => true, 'email' => 'owner@fx.test',
        ], $attributes));
        $company->forceFill(['registered_via' => 'self_signup', 'onboarding_completed_at' => now()])->save();

        $outlet = Outlet::withoutEvents(fn () => Outlet::create(['company_id' => $company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true]));
        $user = User::factory()->create(['company_id' => $company->id, 'outlet_id' => $outlet->id]);
        $user->companies()->syncWithoutDetaching([$company->id]);

        return [$company, $user];
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['company_id' => null]);
        setPermissionsTeamId(null);
        $admin->assignRole(Role::findOrCreate('Super Admin', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $admin;
    }
}
