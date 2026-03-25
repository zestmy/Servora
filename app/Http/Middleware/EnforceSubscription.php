<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnforceSubscription
{
    /**
     * Graceful lock: expired subscriptions get read-only access.
     * - GET requests: allowed (can view data, reports, settings)
     * - POST/PUT/DELETE: blocked on data routes (can't create/edit/delete records)
     * - Billing routes: always allowed (so they can upgrade)
     * - A persistent banner is shared with all views
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->company) {
            return $next($request);
        }

        $company = $user->company;

        // Grandfathered companies — no restrictions
        if ($company->isGrandfathered()) {
            View::share('subscriptionExpired', false);
            View::share('subscriptionBanner', null);
            return $next($request);
        }

        // System Admins — no restrictions
        if ($user->hasRole('System Admin')) {
            View::share('subscriptionExpired', false);
            View::share('subscriptionBanner', null);
            return $next($request);
        }

        $subscription = app(SubscriptionService::class)->getActiveSubscription($company);

        // Active or trialing — no restrictions
        if ($subscription && $subscription->isActive()) {
            // Show warning banner if trial is ending soon (3 days or less)
            if ($subscription->isTrial() && $subscription->daysRemaining() <= 3) {
                View::share('subscriptionExpired', false);
                View::share('subscriptionBanner', [
                    'type'    => 'warning',
                    'message' => "Your trial ends in {$subscription->daysRemaining()} day(s). Upgrade now to keep full access.",
                    'action'  => route('billing.index'),
                    'label'   => 'Upgrade',
                ]);
            } else {
                View::share('subscriptionExpired', false);
                View::share('subscriptionBanner', null);
            }
            return $next($request);
        }

        // Expired / cancelled / past due / no subscription — graceful lock
        View::share('subscriptionExpired', true);
        View::share('subscriptionBanner', [
            'type'    => 'expired',
            'message' => 'Your subscription has ended. You can still view your data, but creating or editing records is disabled.',
            'action'  => route('billing.index'),
            'label'   => 'Subscribe Now',
        ]);

        // Allow billing, profile, logout, and GET requests (read-only)
        if ($request->routeIs('billing.*') || $request->routeIs('profile') || $request->routeIs('logout')) {
            return $next($request);
        }

        // Allow all GET requests (viewing data)
        if ($request->isMethod('GET')) {
            return $next($request);
        }

        // Block write operations (POST/PUT/PATCH/DELETE) on Livewire and form submissions
        // Return a friendly error for Livewire requests
        if ($request->hasHeader('X-Livewire')) {
            return response()->json([
                'effects' => [
                    'dispatches' => [[
                        'name' => 'subscription-expired',
                        'params' => [],
                    ]],
                ],
            ], 403);
        }

        // Regular form POST — redirect back with error
        return redirect()->back()->with('error', 'Your subscription has ended. Please subscribe to continue making changes.');
    }
}
