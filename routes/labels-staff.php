<?php

use App\Livewire\Labels\Staff\Expiring as StaffExpiring;
use App\Livewire\Labels\Staff\Login as StaffLogin;
use App\Livewire\Labels\Staff\Pin as StaffPin;
use App\Livewire\Labels\Staff\PrintLabels as StaffPrint;
use App\Livewire\Labels\Staff\SetPrint as StaffSetPrint;
use App\Livewire\Labels\Staff\Sets as StaffSets;
use Illuminate\Support\Facades\Route;

/*
 * Staff label app — {slug}.servora.com.my/labels
 *
 * REQUIRED to be registered BEFORE routes/web.php. The main app owns
 * /labels with no domain constraint, so a route registered later would
 * never be reached on the subdomain: Laravel matches in registration order
 * and an unconstrained route matches any host. routes/web.php requires this
 * file at its very top for exactly that reason.
 *
 * The domain constraint is what keeps this off the main domain, where
 * /labels must keep resolving to the manager-facing screens.
 *
 * Locally APP_DOMAIN is unset and there is no subdomain to constrain on, so
 * these mount at /labels-staff instead. Route NAMES are identical either
 * way, so views and redirects don't care which environment they're in.
 */

$domain = config('app.domain');

$group = Route::middleware(['web', 'company.subdomain']);

if ($domain) {
    $group->domain('{companySlug}.' . $domain)->prefix('labels');
} else {
    $group->prefix('labels-staff');
}

$group->group(function () {
    Route::get('/login', StaffLogin::class)->name('labels.staff.login');

    Route::middleware('labels.staff')->group(function () {
        Route::get('/', StaffPrint::class)->name('labels.staff.print');
        Route::get('/sets', StaffSets::class)->name('labels.staff.sets');
        Route::get('/sets/{set}', StaffSetPrint::class)->name('labels.staff.sets.print');
        Route::get('/expiring', StaffExpiring::class)->name('labels.staff.expiring');
        Route::get('/pin', StaffPin::class)->name('labels.staff.pin');
    });
});
