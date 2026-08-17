<?php

namespace App\Http\Middleware;

use App\Services\Sales\PosAgentService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the POS sync agent's JSON endpoints behind the agent's device token.
 *
 * PrintAgentAuthenticate's twin — header-only, no cookies, no ambient
 * authority (which is what makes the CSRF exemption in bootstrap/app.php
 * safe by construction), and the same tenant check: on a company subdomain
 * the token must belong to the resolved company.
 */
class PosAgentAuthenticate
{
    /** Container key holding the agent for the rest of the request. */
    public const AGENT_KEY = 'currentPosAgent';

    public function __construct(private PosAgentService $agents)
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
