<?php

use App\Http\Controllers\Hr\ClockAppController;
use App\Http\Controllers\Hr\ClockSessionController;
use App\Http\Controllers\Hr\KioskController;
use App\Http\Controllers\Hr\StaffPayslipController;
use App\Livewire\Clock\Staff\History as ClockHistory;
use App\Livewire\Clock\Staff\Leave as ClockLeave;
use App\Livewire\Clock\Staff\Payslips as ClockPayslips;
use App\Livewire\Clock\Staff\TimeOff as ClockTimeOff;
use App\Livewire\Clock\Staff\Login as ClockLogin;
use App\Livewire\Clock\Staff\Punch as ClockPunch;
use App\Livewire\Clock\Staff\Roster as ClockRoster;
use Illuminate\Support\Facades\Route;

/*
 * Staff app — {slug}.servora.com.my/staff
 *
 * Mounted the same way as routes/labels-staff.php and for the same reason:
 * the main app owns these paths with no domain constraint, and Laravel
 * matches in registration order, so an unconstrained route registered later
 * would swallow the subdomain. routes/web.php requires both files at its
 * very top.
 *
 * Locally APP_DOMAIN is unset and there is no subdomain to constrain on, so
 * these mount at the same /staff path. Route NAMES are identical either way.
 *
 * THE PREFIX IS /staff, NOT /clock. It stopped being the clock-in app the
 * moment leave and time off moved in — telling somebody to open "clock" to
 * apply for annual leave is asking them to ignore the sign above the door.
 * The route NAMES stay clock.staff.* deliberately: they appear across a dozen
 * views, a controller and a middleware, and renaming them would be a large
 * diff that changes nothing anyone can see.
 */

$domain = config('app.domain');

$group = Route::middleware(['web', 'company.subdomain']);

if ($domain) {
    $group->domain('{companySlug}.' . $domain)->prefix('staff');
} else {
    $group->prefix('staff');
}

$group->group(function () {
    // PWA plumbing. Outside the auth group: a browser fetches the manifest
    // and registers the worker before anyone has signed in.
    Route::get('/manifest.webmanifest', [ClockAppController::class, 'manifest'])
        ->name('clock.staff.manifest');
    Route::get('/sw.js', [ClockAppController::class, 'serviceWorker'])
        ->name('clock.staff.sw');

    Route::get('/login', ClockLogin::class)->name('clock.staff.login');

    /*
     * The outlet kiosk.
     *
     * Alongside the staff app rather than inside it, because it authenticates
     * something else entirely: a registered DEVICE, not a person. Nobody signs
     * in to a kiosk — that is the whole idea of one — so `clock.staff` would
     * bounce every request it ever received straight to the PIN screen.
     */
    Route::get('/kiosk/manifest.webmanifest', [ClockAppController::class, 'kioskManifest'])
        ->name('clock.kiosk.manifest');

    Route::get('/kiosk/pair', [KioskController::class, 'pair'])->name('clock.kiosk.pair');
    Route::post('/kiosk/pair', [KioskController::class, 'storePair'])->name('clock.kiosk.pair.store');

    // The screen itself, reached by an ordinary navigation that carries only
    // the cookie.
    Route::middleware('clock.kiosk')->group(function () {
        Route::get('/kiosk', [KioskController::class, 'screen'])->name('clock.kiosk.screen');
    });

    /*
     * The JSON endpoints, header-authenticated. `:header` refuses the cookie,
     * which is what makes them safe to exempt from CSRF in bootstrap/app.php —
     * and they have to be exempt, because this screen stays open far longer
     * than the session whose token rendered it.
     */
    Route::middleware('clock.kiosk:header')->group(function () {
        Route::post('/kiosk/identify', [KioskController::class, 'identify'])->name('clock.kiosk.identify');
        Route::post('/kiosk/punch', [KioskController::class, 'punch'])->name('clock.kiosk.punch');
        Route::post('/kiosk/ping', [KioskController::class, 'ping'])->name('clock.kiosk.ping');

        /*
         * Enrolment. Same header authentication as the rest, plus a window on
         * the device that a manager opened with a code — see
         * ClockDeviceService::startEnrolment(). The capture route re-checks
         * that window on every call rather than trusting the one that opened
         * it, because this is what writes the root of trust.
         */
        Route::post('/kiosk/enrol/start', [KioskController::class, 'enrolStart'])->name('clock.kiosk.enrol.start');
        Route::post('/kiosk/enrol/stop', [KioskController::class, 'enrolStop'])->name('clock.kiosk.enrol.stop');
        Route::post('/kiosk/enrol/capture', [KioskController::class, 'enrolCapture'])->name('clock.kiosk.enrol.capture');
    });

    Route::middleware('clock.staff')->group(function () {
        // A bare /staff has to land somewhere: it is what gets typed from
        // memory, and what a shared link gets trimmed to.
        Route::redirect('/', '/staff/clock')->name('clock.staff.home');

        Route::get('/clock', ClockPunch::class)->name('clock.staff.punch');
        Route::get('/punches', ClockHistory::class)->name('clock.staff.history');
        Route::get('/roster', ClockRoster::class)->name('clock.staff.roster');
        // The employee's own payslips, from approved and paid runs only.
        // The PDF is a plain controller, not a Livewire action: it streams a
        // document, and Livewire's response is a JSON payload.
        Route::get('/payslips', ClockPayslips::class)->name('clock.staff.payslips');
        Route::get('/payslips/{line}', [StaffPayslipController::class, 'show'])
            ->whereNumber('line')
            ->name('clock.staff.payslip');
        // Self-service leave and time off. Same PIN session as the clock —
        // the people who take leave mostly have a phone, not a manager login.
        // Reached by tapping your own name in the header, which is where a
        // person looks for things about themselves — the tab bar is full, and
        // a seventh tab would drop the grid to four columns.
        Route::get('/account', \App\Livewire\Clock\Staff\Account::class)->name('clock.staff.account');

        Route::get('/leave', ClockLeave::class)->name('clock.staff.leave');
        Route::get('/time-off', ClockTimeOff::class)->name('clock.staff.time-off');
        // A plain form post, not a Livewire action: the control lives in the
        // layout, outside any component root, where wire:click never binds.
        Route::post('/logout', [ClockSessionController::class, 'destroy'])->name('clock.staff.logout');
    });
});

/*
 * The old /clock paths, kept as redirects.
 *
 * Staff have this installed as a PWA whose start_url is /clock, and there are
 * bookmarks and printed QR codes pointing at it. Moving the prefix without
 * these would put a 404 in front of somebody standing at a door trying to
 * clock in — the worst possible moment to find out a URL changed.
 *
 * No `company.subdomain` middleware and no auth: a redirect needs neither,
 * and resolving a company just to send a browser somewhere else is work for
 * nothing. They can be dropped once the installed apps have all relaunched.
 */
$legacy = Route::middleware(['web']);

if ($domain) {
    $legacy->domain('{companySlug}.' . $domain)->prefix('clock');
} else {
    $legacy->prefix('clock-staff');
}

$legacy->group(function () {
    Route::redirect('/', '/staff/clock');
    Route::redirect('/history', '/staff/punches');
    Route::redirect('/login', '/staff/login');
    Route::redirect('/manifest.webmanifest', '/staff/manifest.webmanifest');
    Route::redirect('/sw.js', '/staff/sw.js');
});
