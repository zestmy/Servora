<?php

use App\Http\Controllers\Hr\ClockAppController;
use App\Http\Controllers\Hr\ClockSessionController;
use App\Livewire\Clock\Staff\History as ClockHistory;
use App\Livewire\Clock\Staff\Leave as ClockLeave;
use App\Livewire\Clock\Staff\TimeOff as ClockTimeOff;
use App\Livewire\Clock\Staff\Login as ClockLogin;
use App\Livewire\Clock\Staff\Punch as ClockPunch;
use Illuminate\Support\Facades\Route;

/*
 * Staff clock-in app — {slug}.servora.com.my/clock
 *
 * Mounted the same way as routes/labels-staff.php and for the same reason:
 * the main app owns these paths with no domain constraint, and Laravel
 * matches in registration order, so an unconstrained route registered later
 * would swallow the subdomain. routes/web.php requires both files at its
 * very top.
 *
 * Locally APP_DOMAIN is unset and there is no subdomain to constrain on, so
 * these mount at /clock-staff instead. Route NAMES are identical either way.
 */

$domain = config('app.domain');

$group = Route::middleware(['web', 'company.subdomain']);

if ($domain) {
    $group->domain('{companySlug}.' . $domain)->prefix('clock');
} else {
    $group->prefix('clock-staff');
}

$group->group(function () {
    // PWA plumbing. Outside the auth group: a browser fetches the manifest
    // and registers the worker before anyone has signed in.
    Route::get('/manifest.webmanifest', [ClockAppController::class, 'manifest'])
        ->name('clock.staff.manifest');
    Route::get('/sw.js', [ClockAppController::class, 'serviceWorker'])
        ->name('clock.staff.sw');

    Route::get('/login', ClockLogin::class)->name('clock.staff.login');

    Route::middleware('clock.staff')->group(function () {
        Route::get('/', ClockPunch::class)->name('clock.staff.punch');
        Route::get('/history', ClockHistory::class)->name('clock.staff.history');
        // Self-service leave and time off. Same PIN session as the clock —
        // the people who take leave mostly have a phone, not a manager login.
        Route::get('/leave', ClockLeave::class)->name('clock.staff.leave');
        Route::get('/time-off', ClockTimeOff::class)->name('clock.staff.time-off');
        // A plain form post, not a Livewire action: the control lives in the
        // layout, outside any component root, where wire:click never binds.
        Route::post('/logout', [ClockSessionController::class, 'destroy'])->name('clock.staff.logout');
    });
});
