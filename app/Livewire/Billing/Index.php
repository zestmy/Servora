<?php

namespace App\Livewire\Billing;

use App\Models\Invoice;
use App\Models\Plan;
use App\Services\CouponService;
use App\Services\SubscriptionService;
use App\Services\UsageTrackingService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public string $couponCode = '';

    public function redeemCoupon(): void
    {
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
        $subscriptionService = app(SubscriptionService::class);

        $subscription = $company ? $subscriptionService->getActiveSubscription($company) : null;
        $plan = $subscription?->plan;
        $plans = Plan::active()->ordered()->get();
        $usage = $company ? app(UsageTrackingService::class)->getCurrentCounts($company) : [];

        // Build usage with limits for display
        $usageMetrics = [];
        $metrics = ['outlets', 'users', 'recipes', 'ingredients', 'lms_users'];
        foreach ($metrics as $metric) {
            $limit = $plan?->getLimit($metric);
            $usageMetrics[] = [
                'label'   => ucfirst(str_replace('_', ' ', $metric)),
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

        return view('livewire.billing.index', compact(
            'subscription', 'plan', 'plans', 'usageMetrics', 'isGrandfathered', 'invoices', 'amountDue'
        ))->layout('layouts.app', ['title' => 'Billing & Plan']);
    }
}
