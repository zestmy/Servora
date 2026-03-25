<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->company) {
            return $next($request);
        }

        $company = $user->company;

        // Grandfathered companies bypass subscription check
        if ($company->isGrandfathered()) {
            return $next($request);
        }

        // System Admins bypass
        if ($user->hasRole('System Admin')) {
            return $next($request);
        }

        $subscription = app(SubscriptionService::class)->getActiveSubscription($company);

        // No subscription and not grandfathered = expired
        if (!$subscription) {
            return redirect()->route('billing.index')
                ->with('error', 'Your subscription has expired. Please subscribe to continue using Servora.');
        }

        // Expired or cancelled
        if ($subscription->isExpired() || $subscription->isCancelled()) {
            return redirect()->route('billing.index')
                ->with('error', 'Your subscription is ' . $subscription->statusLabel() . '. Please renew to continue.');
        }

        return $next($request);
    }
}
