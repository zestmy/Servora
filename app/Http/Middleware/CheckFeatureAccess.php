<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFeatureAccess
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if (!$user || !$user->company) {
            return $next($request);
        }

        $canUse = app(SubscriptionService::class)->canUseFeature($user->company, $feature);

        if (!$canUse) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'This feature is not available on your current plan.',
                    'feature' => $feature,
                ], 403);
            }

            return redirect()->route('billing.index')
                ->with('error', 'The ' . str_replace('_', ' ', $feature) . ' feature is not available on your current plan. Please upgrade.');
        }

        return $next($request);
    }
}
