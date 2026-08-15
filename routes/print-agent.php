<?php

use App\Http\Controllers\Labels\PrintAgentController;
use Illuminate\Support\Facades\Route;

/*
 * Print agent API — {slug}.servora.com.my/agent
 *
 * The wire surface of the Servora Print Agent (docs/print-agent-plan.md):
 * a native binary on the outlet PC pairs here, polls here, acks here. JSON
 * only, no pages, no sessions.
 *
 * REQUIRED to be registered BEFORE routes/web.php, for the same reason as
 * the staff apps: the main app's routes carry no domain constraint and
 * Laravel matches in registration order.
 *
 * The agent's configured server URL is the tenant subdomain, so
 * company.subdomain resolves the company and the token is verified WITHIN
 * it — a token lifted from one tenant is dead at another. Locally
 * APP_DOMAIN is unset, so these mount at /agent-api and the token hash
 * (globally unique) identifies the agent alone.
 *
 * All POSTs here are CSRF-exempt in bootstrap/app.php, and that is safe by
 * construction rather than by policy: the caller is a binary with no
 * cookies, authenticating on the X-Agent-Token header (or, for /pair, on
 * the single-use pairing code itself). There is no ambient authority for a
 * cross-site request to spend.
 */

$domain = config('app.domain');

$group = Route::middleware(['web', 'company.subdomain']);

if ($domain) {
    $group->domain('{companySlug}.' . $domain)->prefix('agent');
} else {
    $group->prefix('agent-api');
}

$group->group(function () {
    Route::post('/pair', [PrintAgentController::class, 'pair'])
        ->name('print-agent.pair');

    Route::middleware('print.agent')->group(function () {
        // The poll doubles as the heartbeat — no separate ping endpoint.
        Route::get('/jobs', [PrintAgentController::class, 'jobs'])
            ->name('print-agent.jobs');
        Route::post('/jobs/{job}/status', [PrintAgentController::class, 'jobStatus'])
            ->whereNumber('job')
            ->name('print-agent.jobs.status');
        Route::post('/printers', [PrintAgentController::class, 'printers'])
            ->name('print-agent.printers');
    });
});
