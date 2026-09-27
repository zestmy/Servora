<?php

namespace App\Services;

use App\Http\Middleware\PosAgentAuthenticate;
use App\Http\Middleware\PrintAgentAuthenticate;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Which modules a company may use (docs/pricing-model.md, config/modules.php).
 *
 * The one place that answers "is this company allowed X". Routes ask through
 * EnforceModuleAccess, the sidebar through NavMenu, views through @module.
 *
 * Resolution, first match wins:
 *   platform switch (supplier_portal) → the switch, for every company alike
 *   grandfathered company            → everything
 *   no live subscription             → Free: core only
 *   legacy plan (modules = NULL)     → everything; nobody loses a screen
 *   trialing                         → everything: the trial is the whole product
 *   otherwise                        → the plan's modules + current add-ons
 *
 * Resolve a fresh instance per request (it is not a singleton): the answer is
 * memoised per company for the instance's life, and a long-lived memo would
 * outlast the subscription change it is supposed to reflect.
 */
class Entitlements
{
    /** @var array<int, array<int, string>|true> company id => modules, or true for everything */
    private array $memo = [];

    public function __construct(private SubscriptionService $subscriptions) {}

    /**
     * The instance for the current request, so views asking several times
     * (a tab bar, a links grid) share one lookup. Lives on the request, which
     * is exactly as long as its answer stays true.
     */
    public static function current(): self
    {
        $request = request();

        if (! $request->attributes->has(self::class)) {
            $request->attributes->set(self::class, app(self::class));
        }

        return $request->attributes->get(self::class);
    }

    /** allows() for the company the current request acts for. */
    public function allowsHere(string $module): bool
    {
        return $this->allows($this->companyForRequest(request()), $module);
    }

    /** allowsRoute() for the company the current request acts for. */
    public function allowsRouteHere(string $routeName): bool
    {
        return $this->allowsRoute($this->companyForRequest(request()), $routeName);
    }

    public function allows(?Company $company, string $module): bool
    {
        if ($this->isSwitch($module)) {
            return (bool) config('modules.'.$module);
        }

        // Nothing to judge against (a public page, a job): not ours to refuse.
        if (! $company) {
            return true;
        }

        $modules = $this->modules($company);

        return $modules === true || in_array($module, $modules, true);
    }

    /**
     * @return array<int, string>|true  true when the company has everything
     */
    public function modules(Company $company): array|true
    {
        return $this->memo[$company->id] ??= $this->resolve($company);
    }

    /** The module a route belongs to, or null for core. */
    public function moduleForRoute(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }

        foreach ((array) config('modules.routes', []) as $pattern => $module) {
            if (Str::is($pattern, $routeName)) {
                return $module;
            }
        }

        return null;
    }

    public function allowsRoute(?Company $company, ?string $routeName): bool
    {
        $module = $this->moduleForRoute($routeName);

        return $module === null || $this->allows($company, $module);
    }

    public function name(string $module): string
    {
        return config("modules.catalogue.$module.name", Str::headline($module));
    }

    public function isSwitch(string $module): bool
    {
        return in_array($module, (array) config('modules.switches', []), true);
    }

    /**
     * The company a request acts for, whichever door it came in by: a company
     * subdomain (staff apps, agents in production), the signed-in manager, the
     * training portal, a paired agent, or the subdomain company remembered in
     * the session (the staff apps locally, where there is no subdomain).
     */
    public function companyForRequest(Request $request): ?Company
    {
        if (app()->bound('currentCompany')) {
            return app('currentCompany');
        }

        if ($user = Auth::guard('web')->user()) {
            return $user->company;
        }

        if ($lms = Auth::guard('lms')->user()) {
            return $lms->company;
        }

        foreach ([PrintAgentAuthenticate::AGENT_KEY, PosAgentAuthenticate::AGENT_KEY] as $key) {
            if (app()->bound($key) && ($companyId = app($key)->company_id ?? null)) {
                return Company::find($companyId);
            }
        }

        if ($id = $request->hasSession() ? $request->session()->get('subdomain_company_id') : null) {
            return Company::find($id);
        }

        if ($slug = $request->route('companySlug')) {
            return Company::where('slug', $slug)->first();
        }

        return null;
    }

    private function resolve(Company $company): array|true
    {
        if ($company->isGrandfathered()) {
            return true;
        }

        $subscription = $this->subscriptions->getActiveSubscription($company);

        if (! $subscription) {
            return [];
        }

        if ($subscription->plan?->modules === null || $subscription->isTrial()) {
            return true;
        }

        return array_values(array_unique(array_merge(
            $subscription->plan->modules,
            $subscription->addons()->current()->pluck('module')->all(),
        )));
    }
}
