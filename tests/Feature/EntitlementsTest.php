<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Outlet;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Entitlements;
use App\Services\SubscriptionService;
use App\Support\Navigation\NavMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Plan entitlements (docs/pricing-model.md, config/modules.php).
 *
 * The migration seeds the Free / Basic / Full plans, so these run against the
 * real rows rather than test doubles of them.
 */
class EntitlementsTest extends TestCase
{
    use RefreshDatabase;

    // ── Who gets what ───────────────────────────────────────────────────────

    public function test_a_grandfathered_company_has_everything(): void
    {
        [$company] = $this->company('seeder');

        $this->assertTrue($this->ent()->modules($company));
    }

    public function test_a_self_signup_company_with_no_subscription_is_free(): void
    {
        [$company] = $this->company();

        $this->assertSame([], $this->ent()->modules($company));
        $this->assertFalse($this->ent()->allows($company, 'basic'));
    }

    public function test_a_legacy_plan_keeps_everything(): void
    {
        [$company] = $this->company();
        $legacy = Plan::create(['name' => 'Enterprise', 'slug' => 'enterprise', 'price_monthly' => 499, 'price_yearly' => 4990,
            'currency' => 'MYR', 'feature_flags' => [], 'is_active' => true, 'trial_days' => 14]);
        $this->subscribe($company, $legacy);

        $this->assertTrue($this->ent()->modules($company));
    }

    public function test_a_trial_is_the_whole_product(): void
    {
        [$company] = $this->company();
        $this->subscribe($company, $this->plan('basic'), 'trialing');

        $this->assertTrue($this->ent()->modules($company));
    }

    public function test_basic_is_its_suite_plus_what_was_bought(): void
    {
        [$company] = $this->company();
        $sub = $this->subscribe($company, $this->plan('basic'));

        $this->assertTrue($this->ent()->allows($company, 'basic'));
        $this->assertFalse($this->ent()->allows($company, 'labels'));

        $sub->addons()->create(['module' => 'labels', 'unit_price' => 80]);
        $sub->addons()->create(['module' => 'hr', 'quantity' => 12, 'unit_price' => 3, 'ends_at' => now()->subDay()]);

        $this->assertTrue($this->ent()->allows($company, 'labels'));
        $this->assertFalse($this->ent()->allows($company, 'hr'), 'an ended add-on is gone');
    }

    public function test_full_includes_the_six_add_ons_but_not_hr_or_the_kitchen(): void
    {
        [$company] = $this->company();
        $this->subscribe($company, $this->plan('full'));

        foreach (['basic', 'labels', 'learn', 'audits', 'assets', 'pos_sync', 'ai_insights'] as $module) {
            $this->assertTrue($this->ent()->allows($company, $module), $module);
        }
        $this->assertFalse($this->ent()->allows($company, 'hr'));
        $this->assertFalse($this->ent()->allows($company, 'central_kitchen'));
    }

    public function test_old_feature_flags_answer_through_modules(): void
    {
        [$basicCo] = $this->company();
        $this->subscribe($basicCo, $this->plan('basic'));
        [$fullCo] = $this->company();
        $this->subscribe($fullCo, $this->plan('full'));

        $this->assertFalse(app(SubscriptionService::class)->canUseFeature($basicCo, 'analytics'));
        $this->assertTrue(app(SubscriptionService::class)->canUseFeature($fullCo, 'analytics'));
    }

    // ── The route map ───────────────────────────────────────────────────────

    public function test_routes_map_to_their_modules(): void
    {
        $ent = $this->ent();

        foreach ([
            'clock.staff.home'               => null,
            'clock.staff.photo'              => null,
            'clock.staff.punch'              => 'hr',
            'clock.kiosk.punch'              => 'hr',
            'clock.staff.learn.course'       => 'learn',
            'clock.staff.lms'                => 'learn',
            'clock.staff.actions'            => 'audits',
            'labels.staff.print'             => 'labels',
            'print-agent.jobs'               => 'labels',
            'pos-agent.batches.store'        => 'pos_sync',
            'kitchen.index'                  => 'central_kitchen',
            'reports.stock-count'            => null,
            'reports.po-summary'             => 'basic',
            'reports.audit-trend'            => 'audits',
            'purchasing.index'               => 'basic',
            'purchasing.suppliers.directory' => 'supplier_portal',
            'ingredients.index'              => null,
            'recipes.edit'                   => null,
            'recipes.import'                 => 'basic',
            'inventory.stock-takes.create'   => null,
            'inventory.transfers.create'     => 'basic',
            'settings.suppliers'             => null,
            'billing.index'                  => null,
            'dashboard'                      => null,
        ] as $route => $module) {
            $this->assertSame($module, $ent->moduleForRoute($route), $route);
        }
    }

    /** A typo in the map fails open silently, so every pattern must hit a real route. */
    public function test_every_pattern_in_the_map_matches_a_real_route(): void
    {
        $names = collect(Route::getRoutes()->getRoutesByName())->keys();

        foreach (array_keys(config('modules.routes')) as $pattern) {
            $this->assertTrue($names->contains(fn ($n) => Str::is($pattern, $n)), "No route matches '$pattern'");
        }
    }

    // ── Enforcement ─────────────────────────────────────────────────────────

    public function test_a_free_company_is_sent_to_billing_from_a_paid_screen(): void
    {
        [, $user] = $this->company();
        Gate::before(fn () => true); // permissions are not what is under test

        $this->actingAs($user)->get(route('purchasing.index'))
            ->assertRedirect(route('billing.index'))
            ->assertSessionHas('error', 'Basic suite is not on your plan. Upgrade to use it.');

        $this->actingAs($user)->get(route('ingredients.index'))->assertOk();
    }

    public function test_an_add_on_opens_its_screens(): void
    {
        [$company, $user] = $this->company();
        $sub = $this->subscribe($company, $this->plan('basic'));
        Gate::before(fn () => true);

        $this->actingAs($user)->get(route('labels.print'))->assertRedirect(route('billing.index'));

        $sub->addons()->create(['module' => 'labels', 'unit_price' => 80]);

        $this->actingAs($user)->get(route('labels.print'))->assertOk();
    }

    public function test_the_sidebar_shows_only_what_the_plan_has(): void
    {
        [$company, $user] = $this->company();
        Gate::before(fn () => true);

        $routes = fn () => collect(NavMenu::visible(NavMenu::outlet(), $user->fresh()))
            ->flatMap(fn ($g) => $g['items'])->pluck('route');

        $this->assertContains('ingredients.index', $routes());
        $this->assertNotContains('purchasing.index', $routes());
        $this->assertNotContains('labels.print', $routes());
        $this->assertNotContains('hr.employees', $routes());

        $this->subscribe($company, $this->plan('full'));

        $this->assertContains('purchasing.index', $routes());
        $this->assertContains('labels.print', $routes());
        $this->assertNotContains('hr.employees', $routes(), 'HR is sold separately from both suites');
    }

    // ── Selling add-ons ─────────────────────────────────────────────────────

    public function test_basic_takes_at_most_two_add_ons(): void
    {
        [$company] = $this->company();
        $sub = $this->subscribe($company, $this->plan('basic'));

        $this->svc()->syncAddons($sub, ['labels' => [], 'assets' => [], 'hr' => ['quantity' => 10]]);
        $this->assertSame(3, $sub->addons()->count(), 'HR does not count toward the cap');

        $this->expectException(ValidationException::class);
        $this->svc()->syncAddons($sub->fresh('plan'), ['labels' => [], 'assets' => [], 'audits' => []]);
    }

    public function test_free_takes_no_add_ons(): void
    {
        [$company] = $this->company();
        $sub = $this->subscribe($company, $this->plan('free'));

        $this->expectException(ValidationException::class);
        $this->svc()->syncAddons($sub, ['labels' => []]);
    }

    public function test_hr_bills_at_least_ten_employees(): void
    {
        [$company] = $this->company();
        $sub = $this->subscribe($company, $this->plan('basic'));

        $this->expectException(ValidationException::class);
        $this->svc()->syncAddons($sub, ['hr' => ['quantity' => 4]]);
    }

    public function test_full_is_not_sold_what_it_already_includes(): void
    {
        [$company] = $this->company();
        $sub = $this->subscribe($company, $this->plan('full'));

        $this->svc()->syncAddons($sub, ['labels' => [], 'central_kitchen' => ['quantity' => 1]]);

        $this->assertSame(['central_kitchen'], $sub->addons()->pluck('module')->all());
        $this->assertSame('300.00', $sub->addons()->first()->unit_price, 'list price by default');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function ent(): Entitlements
    {
        return app(Entitlements::class);
    }

    private function svc(): SubscriptionService
    {
        return app(SubscriptionService::class);
    }

    private function plan(string $slug): Plan
    {
        return Plan::where('slug', $slug)->firstOrFail();
    }

    private function subscribe(Company $company, Plan $plan, string $status = 'active'): Subscription
    {
        return Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => $status, 'billing_cycle' => 'monthly',
            'trial_ends_at' => $status === 'trialing' ? now()->addWeek() : null,
            'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ])->load('plan');
    }

    private function company(string $via = 'self_signup'): array
    {
        $company = Company::create([
            'name' => 'Plan Co', 'slug' => 'plan-co-'.Str::random(6), 'currency' => 'MYR', 'is_active' => true,
        ]);
        $company->forceFill(['registered_via' => $via, 'onboarding_completed_at' => now()])->save();

        $outlet = Outlet::create(['company_id' => $company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id, 'outlet_id' => $outlet->id, 'can_view_all_outlets' => true]);
        $user->companies()->syncWithoutDetaching([$company->id]);
        $user->outlets()->syncWithoutDetaching([$outlet->id]);

        return [$company, $user];
    }
}
