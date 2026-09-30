<?php

namespace App\Livewire\Billing;

use App\Models\Invoice;
use App\Models\Plan;
use App\Services\CouponService;
use App\Services\SubscriptionService;
use App\Services\UsageTrackingService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    public string $couponCode = '';

    /** The module a locked sidebar link was for (?unlock=labels). */
    #[Url]
    public ?string $unlock = null;

    public function redeemCoupon(): void
    {
        // Buying and redeeming change what the whole company pays for. The route
        // carries can:users.manage too; this keeps the action safe on its own.
        abort_unless(Auth::user()?->canDo('users.manage'), 403);

        $this->couponCode = strtoupper(trim($this->couponCode));
        if (! $this->couponCode) {
            $this->addError('couponCode', 'Enter a coupon code.');
            return;
        }

        $company = Auth::user()->company;
        if (! $company) {
            $this->addError('couponCode', 'No company associated with your account.');
            return;
        }

        $service = app(CouponService::class);
        try {
            $coupon = $service->validate($this->couponCode, $company);
            $subscription = $service->redeem($coupon, $company, Auth::id());
            session()->flash('success', 'Coupon redeemed! You now have ' . $coupon->grantLabel() . ' of free access.');
            $this->couponCode = '';
            $this->resetValidation();
        } catch (\Throwable $e) {
            $this->addError('couponCode', $e->getMessage());
        }
    }

    public function render()
    {
        $user = Auth::user();
        $company = $user->company;

        // Every "upgrade" and locked-module link in the product points here,
        // and it is shown to everyone. Only someone who manages users (the
        // company's admin) sees the plan, invoices and checkout; anyone else
        // gets what the module is and who to ask, rather than a 403.
        if (! $user->canDo('users.manage')) {
            $entitlements = app(\App\Services\Entitlements::class);
            $unlockPitch = $this->unlock && $company && ! $entitlements->allows($company, $this->unlock)
                ? ['name' => $entitlements->name($this->unlock), 'pitch' => $entitlements->pitch($this->unlock)]
                : null;

            return view('livewire.billing.ask-admin', compact('unlockPitch'))
                ->layout('layouts.app', ['title' => 'Billing & Plan']);
        }

        $subscriptionService = app(SubscriptionService::class);

        $subscription = $company ? $subscriptionService->getActiveSubscription($company) : null;
        $plan = $subscription?->plan;
        // What is sold: Free / Basic / Full. The flat legacy plans are no
        // longer public, but a company still on one sees it in its own card.
        $plans = Plan::active()->publiclyVisible()->ordered()->get();
        $addons = $subscription ? $subscription->addons()->current()->get() : collect();
        $usage = $company ? app(UsageTrackingService::class)->getCurrentCounts($company) : [];

        // Usage against the limits actually ENFORCED (Entitlements::limit):
        // a Free company has no subscription row, so reading the plan here
        // showed ∞ for caps that were in force. Legacy plans and trials are
        // uncapped and read ∞, which is also what is enforced.
        $usageMetrics = [];
        $labels = ['outlets' => 'Active outlets', 'users' => 'Users', 'recipes' => 'Recipes', 'ingredients' => 'Market list items'];
        $entitlements = app(\App\Services\Entitlements::class);
        foreach ($labels as $metric => $label) {
            $limit = $company ? $entitlements->limit($company, $metric) : null;
            $usageMetrics[] = [
                'label'   => $label,
                'current' => $usage[$metric] ?? 0,
                'limit'   => $limit,
                'percent' => $limit ? min(100, round(($usage[$metric] ?? 0) / max($limit, 1) * 100)) : 0,
            ];
        }

        $isGrandfathered = $company?->isGrandfathered() ?? false;

        // visibleToCustomer: a draft is the platform's working copy — its
        // numbers can still change and it has not been sent, so it must never
        // appear here. See Invoice::scopeVisibleToCustomer().
        $invoices = $company
            ? Invoice::where('company_id', $company->id)
                ->visibleToCustomer()
                ->orderByDesc('issued_at')
                ->orderByDesc('id')
                ->limit(24)
                ->get()
            : collect();

        $amountDue = $invoices->filter->isOutstanding()->sum('total');

        $entitlements = app(\App\Services\Entitlements::class);
        $unlockPitch = $this->unlock && $company && ! $entitlements->allows($company, $this->unlock)
            ? ['name' => $entitlements->name($this->unlock), 'pitch' => $entitlements->pitch($this->unlock)]
            : null;

        // The currency this company is priced in (Admin › Currencies).
        $book = app(\App\Services\Billing\CheckoutService::class)->book(Auth::user()->company);

        return view('livewire.billing.index', compact(
            'subscription', 'plan', 'plans', 'usageMetrics', 'isGrandfathered', 'invoices', 'amountDue', 'unlockPitch', 'addons', 'book'
        ))->layout('layouts.app', ['title' => 'Billing & Plan']);
    }
}
