<?php

namespace App\Providers;

use App\Mail\EngineMailerTransport;
use App\Models\Ingredient;
use App\Observers\AuditObserver;
use App\Observers\IngredientObserver;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register EngineMailer as a custom mail transport
        $this->app->afterResolving('mail.manager', function ($manager) {
            $manager->extend('enginemailer', function () {
                return new EngineMailerTransport();
            });
        });

        /*
         * Shared for its per-request holiday cache.
         *
         * The HR leave screen asks for a balance for up to fifty employees,
         * and every replacement balance needs the same company-and-year list
         * of public holidays. A fresh instance per resolve would fetch that
         * list fifty times over. The container is rebuilt each request, so
         * the cache cannot outlive the data it was built from.
         */
        $this->app->singleton(\App\Services\Hr\ReplacementHolidays::class);
    }

    public function boot(): void
    {
        /*
         * One reverse-geocoding request a second, company-wide.
         *
         * Nominatim's usage policy asks for no more than that and would be
         * within its rights to block the whole application otherwise. A shift
         * change puts twenty punches on the queue at once, so without this the
         * first busy morning would be the last one that worked. Jobs over the
         * limit are released, not dropped — a delayed address is fine.
         */
        \Illuminate\Support\Facades\RateLimiter::for(
            'geocoding',
            fn () => \Illuminate\Cache\RateLimiting\Limit::perMinute(60)
        );

        /*
         * Payslip emails: 30 a minute.
         *
         * Payroll day queues the entire company at once. Sending a hundred
         * messages in a burst is the fastest way to have a provider treat the
         * sending domain as a spam source, and a payslip arriving four minutes
         * later costs nobody anything.
         */
        \Illuminate\Support\Facades\RateLimiter::for(
            'payslip-email',
            fn () => \Illuminate\Cache\RateLimiting\Limit::perMinute(30)
        );

        /*
         * Resolve the subdomain company on Livewire's update endpoint too.
         *
         * Livewire posts every interaction to /livewire/update, a route it
         * registers itself. That route does NOT inherit the middleware of
         * the page's own route group, so on a company subdomain the initial
         * GET resolved `currentCompany` and every subsequent tap did not —
         * components looked like they were on the main domain and came back
         * empty.
         *
         * This also matters for CompanyScope, which falls back to
         * `currentCompany` when there is no authenticated user: without it,
         * a Livewire request from the staff app would apply no company
         * filter at all.
         *
         * A no-op on the main domain — ResolveCompanyFromSubdomain returns
         * early when the host has no company subdomain.
         */
        Livewire::setUpdateRoute(fn ($handle) => Route::post('/livewire/update', $handle)
            ->middleware(['web', 'company.subdomain']));

        // Keep prep-item costs in sync whenever an ingredient's cost changes.
        Ingredient::observe(IngredientObserver::class);

        // Audit trail: observe every configured business model. class_exists()
        // guards against a stray/renamed entry ever fataling the whole app.
        foreach ((array) config('audit.models', []) as $auditable) {
            if (is_string($auditable) && class_exists($auditable)) {
                $auditable::observe(AuditObserver::class);
            }
        }

        // Super Admin bypasses all permission checks (team-agnostic: with
        // Spatie teams mode, hasRole only sees the active company's rows)
        Gate::before(function ($user, $ability) {
            return method_exists($user, 'hasGlobalRole') && $user->hasGlobalRole('Super Admin') ? true : null;
        });

        // @feature('analytics') ... @endfeature
        Blade::if('feature', function (string $feature) {
            $user = Auth::user();
            if (!$user || !$user->company) {
                return false;
            }

            return app(SubscriptionService::class)->canUseFeature($user->company, $feature);
        });
    }
}
