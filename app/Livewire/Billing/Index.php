<?php

namespace App\Livewire\Billing;

use App\Models\Plan;
use App\Models\Referral;
use App\Services\ReferralService;
use App\Services\SubscriptionService;
use App\Services\UsageTrackingService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public bool $copiedLink = false;

    public function generateReferralCode(): void
    {
        app(ReferralService::class)->generateCode(Auth::user());
        session()->flash('referral_success', 'Referral link generated!');
    }

    public function markCopied(): void
    {
        $this->copiedLink = true;
    }

    public function render()
    {
        $user = Auth::user();
        $company = $user->company;
        $subscriptionService = app(SubscriptionService::class);

        $subscription = $subscriptionService->getActiveSubscription($company);
        $plan = $subscription?->plan;
        $plans = Plan::active()->ordered()->get();
        $usage = app(UsageTrackingService::class)->getCurrentCounts($company);

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

        $isGrandfathered = $company->isGrandfathered();

        // Referral data
        $referralCode = \App\Models\ReferralCode::where('referrer_type', 'user')
            ->where('referrer_id', $user->id)
            ->first();

        $referralStats = null;
        if ($referralCode) {
            $referrals = Referral::where('referral_code_id', $referralCode->id)
                ->with('referredCompany')
                ->latest()
                ->get();

            $totalEarned = \App\Models\Commission::whereHas('referral', fn ($q) => $q->where('referral_code_id', $referralCode->id))
                ->where('status', '!=', 'rejected')
                ->sum('amount');

            $referralStats = [
                'clicks'      => $referralCode->total_clicks,
                'signups'     => $referralCode->total_signups,
                'conversions' => $referralCode->total_conversions,
                'earned'      => $totalEarned,
                'referrals'   => $referrals,
            ];
        }

        return view('livewire.billing.index', compact(
            'subscription', 'plan', 'plans', 'usageMetrics', 'isGrandfathered',
            'referralCode', 'referralStats'
        ))->layout('layouts.app', ['title' => 'Billing & Plan']);
    }
}
