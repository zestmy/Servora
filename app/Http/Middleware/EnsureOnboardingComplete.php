<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->company) {
            return $next($request);
        }

        $company = $user->company;

        // Skip for grandfathered companies
        if ($company->isGrandfathered()) {
            return $next($request);
        }

        // Skip if onboarding is already complete
        if ($company->onboarding_completed_at) {
            return $next($request);
        }

        // Allow access to the onboarding route itself
        if ($request->routeIs('onboarding') || $request->routeIs('profile') || $request->routeIs('logout')) {
            return $next($request);
        }

        // System Admins skip onboarding
        if ($user->hasRole('System Admin')) {
            return $next($request);
        }

        return redirect()->route('onboarding');
    }
}
