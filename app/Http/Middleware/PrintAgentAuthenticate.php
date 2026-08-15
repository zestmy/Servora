<?php

namespace App\Http\Middleware;

use App\Services\Labels\PrintAgentService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the print-agent JSON endpoints behind the agent's device token.
 *
 * Nobody is signed in here — the caller is a native binary on an outlet PC.
 * What is authenticated is the AGENT, and what that buys is the right to
 * collect and ack ONE outlet's print jobs.
 *
 * Header-only by construction, unlike KioskAuthenticate's cookie-or-header
 * split: an agent has no browser and no cookies, so there is no ambient
 * authority for CSRF to protect against — which is what lets these routes
 * be exempt in bootstrap/app.php.
 *
 * Runs after company.subdomain where a domain is configured, so the token
 * is checked against the resolved company: a token lifted from one tenant
 * is dead at another. Locally there is no subdomain and the token hash
 * alone (globally unique) identifies the agent.
 *
 * 401 body is machine-readable on purpose — {status: 'unpaired'} is the
 * agent's cue to stop polling and ask for a new pairing code.
 */
class PrintAgentAuthenticate
{
    /** Container key holding the agent for the rest of the request. */
    public const AGENT_KEY = 'currentPrintAgent';

    public function __construct(private PrintAgentService $agents)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $companyId = app()->bound('currentCompany') ? app('currentCompany')->id : null;

        $agent = $this->agents->resolveFromRequest($request, $companyId);

        if (! $agent) {
            return response()->json([
                'status'  => 'unpaired',
                'message' => 'This agent is not paired. Ask a manager for a pairing code.',
            ], 401);
        }

        app()->instance(self::AGENT_KEY, $agent);

        $this->agents->heartbeat($agent, $request);

        return $next($request);
    }
}
