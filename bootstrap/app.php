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
            'onboarding'          => \App\Http\Middleware\EnsureOnboardingComplete::class,
            'check.subscription'  => \App\Http\Middleware\CheckSubscription::class,
            'check.feature'       => \App\Http\Middleware\CheckFeatureAccess::class,
            'plan.rate_limit'     => \App\Http\Middleware\PlanRateLimiter::class,
        ]);

        // Apply subdomain resolution globally on web routes
        $middleware->web(append: [
            \App\Http\Middleware\ResolveCompanyFromSubdomain::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
