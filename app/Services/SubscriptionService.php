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

        // Company may be soft-deleted — the subscription row outlives it.
        $subscription->company?->update(['trial_ends_at' => null]);

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

    /**
     * Replace a subscription's add-ons with $wanted: [module => ['quantity' =>
     * int, 'unit_price' => float]]. Enforces the pricing rules
     * (docs/pricing-model.md) so no screen can sell what the model forbids:
     *   - legacy plans (modules NULL) already include everything
     *   - Free takes no add-ons
     *   - an add-on the suite already includes is not sold twice
     *   - Basic holds at most `modules.addon_cap_on_basic` flat add-ons
     *   - metered modules bill at least their minimum quantity
     *
     * @throws \Illuminate\Validation\ValidationException  keyed `addons`
     */
    public function syncAddons(Subscription $subscription, array $wanted): void
    {
        $catalogue = (array) config('modules.catalogue');
        $planModules = $subscription->plan->modules;
        $fail = fn (string $message) => throw \Illuminate\Validation\ValidationException::withMessages(['addons' => $message]);

        $wanted = array_filter(
            $wanted,
            fn ($row, $module) => in_array($catalogue[$module]['kind'] ?? null, ['addon', 'metered'], true)
                && ! in_array($module, (array) $planModules, true),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($wanted !== [] && $planModules === null) {
            $fail('This is a legacy plan that already includes every module. Move the company to Free, Basic or Full first.');
        }

        if ($wanted !== [] && ! in_array('basic', (array) $planModules, true)) {
            $fail('The Free plan cannot take add-ons.');
        }

        $flat = array_filter(array_keys($wanted), fn ($m) => $catalogue[$m]['kind'] === 'addon');
        $cap = (int) config('modules.addon_cap_on_basic');
        if (count($flat) > $cap) {
            $fail("Basic can take at most {$cap} add-ons. For more, move the company to the Full suite.");
        }

        foreach ($wanted as $module => $row) {
            $min = (int) ($catalogue[$module]['min_quantity'] ?? 1);
            if ((int) ($row['quantity'] ?? 1) < $min) {
                $fail("{$catalogue[$module]['name']} bills at least {$min} {$catalogue[$module]['unit']}s.");
            }
        }

        $subscription->addons()->whereNotIn('module', array_keys($wanted))->delete();

        foreach ($wanted as $module => $row) {
            $subscription->addons()->updateOrCreate(['module' => $module], [
                'quantity'   => $catalogue[$module]['kind'] === 'addon' ? 1 : (int) $row['quantity'],
                'unit_price' => $row['unit_price'] ?? $catalogue[$module]['price'],
                'ends_at'    => null,
            ]);
        }
    }

    public function canUseFeature(Company $company, string $feature): bool
    {
        $subscription = $this->getActiveSubscription($company);

        // No live subscription: only a grandfathered company (seeded or created
        // by an admin) gets everything. A self-signup company whose
        // subscription expired or was cancelled lands here too, and must not.
        if (!$subscription) {
            return $company->isGrandfathered();
        }

        if (!$subscription->isActive()) {
            return false;
        }

        // Plans with modules (Free / Basic / Full) answer the old feature
        // flags through the module that now carries them.
        $module = config("modules.legacy_features.$feature");
        if ($subscription->plan->modules !== null && $module) {
            return app(Entitlements::class)->allows($company, $module);
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
