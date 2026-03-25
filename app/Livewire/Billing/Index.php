<?php

namespace App\Livewire\Billing;

use App\Models\Plan;
use App\Services\SubscriptionService;
use App\Services\UsageTrackingService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        $company = Auth::user()->company;
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

        return view('livewire.billing.index', compact('subscription', 'plan', 'plans', 'usageMetrics', 'isGrandfathered'))
            ->layout('layouts.app', ['title' => 'Billing & Plan']);
    }
}
