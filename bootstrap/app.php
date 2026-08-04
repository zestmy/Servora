<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/lms.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'company.scope'       => \App\Http\Middleware\EnsureCompanyScope::class,
            'company.subdomain'   => \App\Http\Middleware\ResolveCompanyFromSubdomain::class,
            'lms.auth'            => \App\Http\Middleware\LmsAuthenticate::class,
            'lms.guest'           => \App\Http\Middleware\LmsGuest::class,
            'onboarding'          => \App\Http\Middleware\EnsureOnboardingComplete::class,
            'check.subscription'  => \App\Http\Middleware\CheckSubscription::class,
            'enforce.subscription' => \App\Http\Middleware\EnforceSubscription::class,
            'check.feature'       => \App\Http\Middleware\CheckFeatureAccess::class,
            'plan.rate_limit'     => \App\Http\Middleware\PlanRateLimiter::class,
            'kitchen.user'        => \App\Http\Middleware\EnsureKitchenUser::class,
            'labels.staff'        => \App\Http\Middleware\LabelStaffAuthenticate::class,
            'clock.staff'         => \App\Http\Middleware\ClockStaffAuthenticate::class,
        ]);

        // Force all non-LMS traffic to the main domain (must run early)
        $middleware->web(prepend: [
            \App\Http\Middleware\EnforceMainDomain::class,
        ]);

        $middleware->web(append: [
            // Spatie teams mode: scope role/permission checks to the active company
            \App\Http\Middleware\SetPermissionsTeamFromCompany::class,
            // Suspended users / suspended companies are logged out on any request
            \App\Http\Middleware\EnsureAccountActive::class,
            // users.last_active_at heartbeat (session-driver-independent).
            // MUST run before SetDisplayTimezone: last_active_at is a naive
            // DATETIME, so stamping it after the display timezone switch would
            // write each user's wall clock on a different basis and make the
            // column incomparable across users.
            \App\Http\Middleware\TouchLastActive::class,
            // Display-only per-user / per-company timezone adjustment. Last,
            // so nothing that persists a timestamp runs under a shifted clock.
            \App\Http\Middleware\SetDisplayTimezone::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
