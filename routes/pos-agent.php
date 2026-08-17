<?php

use App\Http\Controllers\Sales\PosAgentController;
use Illuminate\Support\Facades\Route;

/*
 * POS sync agent API — {slug}.servora.com.my/pos-agent
 *
 * The wire surface of the Servora POS Agent (tools/pos-agent): a native
 * binary on the POS terminal pairs here, pings here, uploads sales report
 * files here. JSON only, no pages, no sessions.
 *
 * Registered BEFORE routes/web.php for the same registration-order reason
 * as the staff apps and the print agent; CSRF-exempt in bootstrap/app.php
 * with the same safe-by-construction justification (binary caller, no
 * cookies, header token). Locally (no APP_DOMAIN) mounts at /pos-agent-api.
 */

$domain = config('app.domain');

$group = Route::middleware(['web', 'company.subdomain']);

if ($domain) {
    $group->domain('{companySlug}.' . $domain)->prefix('pos-agent');
} else {
    $group->prefix('pos-agent-api');
}

$group->group(function () {
    Route::post('/pair', [PosAgentController::class, 'pair'])
        ->name('pos-agent.pair');

    Route::middleware('pos.agent')->group(function () {
        // The ping is the heartbeat AND the config channel: the agent
        // adopts pushed settings and logs its recent batch outcomes from
        // the response. Sales sync needs no poll loop — data flows up.
        Route::get('/ping', [PosAgentController::class, 'ping'])
            ->name('pos-agent.ping');
        Route::post('/batches', [PosAgentController::class, 'storeBatch'])
            ->name('pos-agent.batches.store');
    });
});
