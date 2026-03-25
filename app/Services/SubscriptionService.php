<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use Carbon\Carbon;

class SubscriptionService
{
    public function createTrial(Company $company, Plan $plan, ?string $billingCycle = 'monthly'): Subscription
    {
        $trialEnds = now()->addDays($plan->trial_days);

        $subscription = Subscription::create([
            'company_id'           => $company->id,
            'plan_id'              => $plan->id,
            'status'               => Subscription::STATUS_TRIALING,
            'billing_cycle'        => $billingCycle,
            'trial_ends_at'        => $trialEnds,
            'current_period_start' => now(),
            'current_period_end'   => $trialEnds,
        ]);

        $company->update(['trial_ends_at' => $trialEnds]);

        return $subscription;
    }

    public function activate(Subscription $subscription): Subscription
    {
        $now = now();
        $periodEnd = $subscription->billing_cycle === 'yearly'
            ? $now->copy()->addYear()
            : $now->copy()->addMonth();

        $subscription->update([
            'status'               => Subscription::STATUS_ACTIVE,
            'trial_ends_at'        => null,
            'current_period_start' => $now,
            'current_period_end'   => $periodEnd,
        ]);

        $subscription->company->update(['trial_ends_at' => null]);

        return $subscription->fresh();
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->update([
            'status'       => Subscription::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        return $subscription->fresh();
    }

    public function renew(Subscription $subscription): Subscription
    {
        $now = now();
        $periodEnd = $subscription->billing_cycle === 'yearly'
            ? $now->copy()->addYear()
            : $now->copy()->addMonth();

        $subscription->update([
            'status'               => Subscription::STATUS_ACTIVE,
            'current_period_start' => $now,
            'current_period_end'   => $periodEnd,
            'cancelled_at'         => null,
        ]);

        return $subscription->fresh();
    }

    public function markPastDue(Subscription $subscription): Subscription
    {
        $subscription->update(['status' => Subscription::STATUS_PAST_DUE]);

        return $subscription->fresh();
    }

    public function expire(Subscription $subscription): Subscription
    {
        $subscription->update(['status' => Subscription::STATUS_EXPIRED]);

        return $subscription->fresh();
    }

    public function changePlan(Subscription $subscription, Plan $newPlan): Subscription
    {
        $subscription->update(['plan_id' => $newPlan->id]);

        return $subscription->fresh();
    }

    public function canUseFeature(Company $company, string $feature): bool
    {
        $subscription = $this->getActiveSubscription($company);

        // Grandfathered — no subscription = unlimited
        if (!$subscription) {
            return true;
        }

        if (!$subscription->isActive()) {
            return false;
        }

        return $subscription->plan->hasFeature($feature);
    }

    public function checkUsage(Company $company, string $metric): array
    {
        $subscription = $this->getActiveSubscription($company);

        if (!$subscription) {
            return ['allowed' => true, 'current' => 0, 'limit' => null]; // Grandfathered
        }

        $limit = $subscription->plan->getLimit($metric);
        if ($limit === null) {
            return ['allowed' => true, 'current' => 0, 'limit' => null]; // Unlimited
        }

        $current = $this->getCurrentCount($company, $metric);

        return [
            'allowed' => $current < $limit,
            'current' => $current,
            'limit'   => $limit,
        ];
    }

    public function enforceLimit(Company $company, string $metric): void
    {
        $usage = $this->checkUsage($company, $metric);

        if (!$usage['allowed']) {
            throw new \App\Exceptions\LimitReachedException($metric, $usage['current'], $usage['limit']);
        }
    }

    public function getActiveSubscription(Company $company): ?Subscription
    {
        return Subscription::where('company_id', $company->id)
            ->whereIn('status', [Subscription::STATUS_TRIALING, Subscription::STATUS_ACTIVE, Subscription::STATUS_PAST_DUE])
            ->with('plan')
            ->latest()
            ->first();
    }

    private function getCurrentCount(Company $company, string $metric): int
    {
        return match ($metric) {
            'outlets'     => $company->outlets()->count(),
            'users'       => $company->users()->count(),
            'recipes'     => $company->recipes()->count(),
            'ingredients' => $company->ingredients()->count(),
            'lms_users'   => \App\Models\LmsUser::where('company_id', $company->id)->count(),
            default       => 0,
        };
    }
}
