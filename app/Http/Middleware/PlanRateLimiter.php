<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class PlanRateLimiter
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->company) {
            return $next($request);
        }

        $subscription = app(SubscriptionService::class)->getActiveSubscription($user->company);

        if (!$subscription || !$subscription->plan->api_rate_limit) {
            return $next($request);
        }

        $limit = $subscription->plan->api_rate_limit;
        $key = 'api:' . $user->company_id;

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            $retryAfter = RateLimiter::availableIn($key);

            return response()->json([
                'error'       => 'Rate limit exceeded.',
                'retry_after' => $retryAfter,
                'limit'       => $limit,
            ], 429)->withHeaders([
                'Retry-After'           => $retryAfter,
                'X-RateLimit-Limit'     => $limit,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        RateLimiter::hit($key, 60);

        $response = $next($request);

        return $response->withHeaders([
            'X-RateLimit-Limit'     => $limit,
            'X-RateLimit-Remaining' => max(0, $limit - RateLimiter::attempts($key)),
        ]);
    }
}
