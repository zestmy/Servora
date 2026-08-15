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
            // Sends an app LAUNCH at the clock screen to Home. See the class:
            // it exists because iOS never refetches an installed PWA's manifest.
            'staff.landing'       => \App\Http\Middleware\LandOnStaffHome::class,
            'clock.kiosk'         => \App\Http\Middleware\KioskAuthenticate::class,
            'print.agent'         => \App\Http\Middleware\PrintAgentAuthenticate::class,
        ]);

        /*
         * The kiosk's JSON endpoints authenticate on the X-Kiosk-Token header
         * and refuse the cookie outright (KioskAuthenticate, via=header), so
         * they cannot be driven by ambient authority and have nothing for a
         * CSRF token to protect.
         *
         * They have to be exempt. A kiosk screen is opened once and left up
         * for a fourteen-hour shift; the session whose token the page was
         * rendered with expires hours before the last punch of the night, and
         * a clock-in that fails at 10pm because of a cookie lifetime is the
         * exact failure this feature cannot have.
         *
         * The PAIRING post is deliberately not listed. It is an ordinary form
         * on an ordinary page, submitted within a minute of being loaded, and
         * it is where a device credential is handed out.
         */
        $middleware->validateCsrfTokens(except: [
            'staff/kiosk/identify',
            'staff/kiosk/punch',
            'staff/kiosk/ping',
            'staff/kiosk/enrol/start',
            'staff/kiosk/enrol/stop',
            'staff/kiosk/enrol/capture',
            /*
             * The print agent's POSTs, including pairing. Unlike the kiosk's
             * pairing (a browser form, which stays protected), the agent's
             * caller is a native binary with no cookies at all: header-token
             * auth for the job routes, the single-use pairing code for
             * /pair. Nothing here can be driven by ambient authority. Both
             * mounts are listed because the path differs by environment —
             * agent/* behind a subdomain in production, agent-api/* locally.
             */
            'agent/pair',
            'agent/jobs/*/status',
            'agent/printers',
            'agent-api/pair',
            'agent-api/jobs/*/status',
            'agent-api/printers',
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
