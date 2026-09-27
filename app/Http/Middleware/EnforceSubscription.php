<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\Subscription;
use App\Services\Entitlements;
use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnforceSubscription
{
    /**
     * What a company's subscription state allows (docs/pricing-model.md):
     * - grandfathered, system roles, active or trialing: everything
     * - past due: read-only until paid
     * - no live subscription: the Free plan — usable, unless it holds more
     *   active outlets than Free covers, which is read-only until fixed
     * Which MODULES each state opens is EnforceModuleAccess's job, not this.
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
        if ($user->isSystemRole()) {
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
                    'message' => "Your trial ends in {$subscription->daysRemaining()} day(s). After that you move to the Free plan — nothing is deleted, but paid modules lock. Upgrade to keep them.",
                    'action'  => route('billing.index'),
                    'label'   => 'Upgrade',
                ]);
            } else {
                View::share('subscriptionExpired', false);
                View::share('subscriptionBanner', null);
            }
            return $next($request);
        }

        // Past due: a paying customer whose renewal failed. Read-only until
        // paid — they chose a paid plan, and it is still theirs to settle.
        if ($subscription) {
            return $this->readOnly($request, $next,
                'Your payment is overdue. You can still view your data, but changes are paused until it is paid.',
                'Pay now');
        }

        /*
         * No live subscription: the Free plan (docs/pricing-model.md). A trial
         * that ends, or a paid plan that lapses, falls to Free rather than to
         * a locked account — the company keeps working inside the Free caps,
         * and the paid modules show locked in the sidebar.
         *
         * The one thing Free cannot absorb is more outlets than it covers:
         * that would be a multi-outlet business running free. Until the extra
         * outlets are archived (Settings › Outlets stays writable for exactly
         * that) or the plan upgraded, the account is read-only.
         */
        $entitlements = app(Entitlements::class);

        if ($entitlements->isOverLimit($company, 'outlets')) {
            $limit = $entitlements->limit($company, 'outlets');

            return $this->readOnly($request, $next,
                "The Free plan covers {$limit} active outlet. Archive the others in Settings › Outlets, or upgrade, to keep making changes.",
                'Upgrade', allow: ['settings.outlets']);
        }

        View::share('subscriptionExpired', false);
        View::share('subscriptionBanner', $this->justMovedToFree($company) ? [
            'type'    => 'warning',
            'message' => "Your trial has ended and you are on the Free plan. Everything you entered is still here; paid modules are locked until you upgrade.",
            'action'  => route('billing.index'),
            'label'   => 'See plans',
        ] : null);

        return $next($request);
    }

    /**
     * Graceful lock: GETs still work (view data, reports, settings); writes
     * are refused except on billing, profile, logout and $allow.
     */
    private function readOnly(Request $request, Closure $next, string $message, string $label, array $allow = []): Response
    {
        View::share('subscriptionExpired', true);
        View::share('subscriptionBanner', [
            'type'    => 'expired',
            'message' => $message,
            'action'  => route('billing.index'),
            'label'   => $label,
        ]);

        if ($request->isMethod('GET')
            || $request->routeIs('billing.*', 'profile', 'logout', ...$allow)) {
            return $next($request);
        }

        // Livewire shows a non-2xx response body in a dialog, so the body
        // IS the message the person reads.
        if ($request->hasHeader('X-Livewire')) {
            return response('<p style="font:16px system-ui;padding:24px">'.e($message).'</p>', 403);
        }

        return redirect()->back()->with('error', $message);
    }

    /** A trial ended in the last fortnight: say where they have landed. */
    private function justMovedToFree(Company $company): bool
    {
        return Subscription::where('company_id', $company->id)
            ->where('status', Subscription::STATUS_EXPIRED)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>=', now()->subDays(14))
            ->exists();
    }
}
