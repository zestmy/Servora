<?php

namespace App\Providers;

use App\Mail\EngineMailerTransport;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

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
    }

    public function boot(): void
    {
        // Super Admin bypasses all permission checks
        Gate::before(function ($user, $ability) {
            return $user->hasRole('Super Admin') ? true : null;
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
