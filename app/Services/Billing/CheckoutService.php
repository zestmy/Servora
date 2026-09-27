<?php

namespace App\Services\Billing;

use App\Models\Company;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\ChipInService;
use App\Services\SubscriptionService;
use App\Services\UsageTrackingService;
use Illuminate\Support\Facades\DB;

/**
 * Buying or changing a per-outlet plan (docs/pricing-model.md).
 *
 * quote()  — PriceCalculator's price, less credit for the unused part of a
 *            paid period, so an upgrade charges the difference.
 * start()  — a change that costs something goes to CHIP-IN with the exact
 *            configuration stored on the Payment; one that costs nothing
 *            (a downgrade) is parked on the subscription for the next renewal.
 * fulfil() — the webhook's half: apply what was paid for and start a new
 *            period from today.
 */
class CheckoutService
{
    public function __construct(
        private PriceCalculator $calculator,
        private SubscriptionService $subscriptions,
    ) {}

    /** @param array<string, int> $addons module => quantity */
    public function quote(Company $company, Plan $plan, int $outlets, array $addons, string $cycle): Quote
    {
        $quote = $this->calculator->quote($plan, $outlets, $addons, $cycle);

        if (! $quote->ok()) {
            return $quote;
        }

        return $quote->withCredit($this->credit($company));
    }

    /** Fewer outlets than are active cannot be paid for — archive first. */
    public function minimumOutlets(Company $company): int
    {
        return max(1, app(UsageTrackingService::class)->count($company, 'outlets'));
    }

    /**
     * Why this company cannot use self-serve checkout, or null when it can.
     * Legacy arrangements (grandfathered, or a live flat-price plan) were set
     * up by hand and move by hand.
     */
    public function blockedReason(Company $company): ?string
    {
        if ($company->isGrandfathered()) {
            return 'Your account is on a legacy arrangement. Contact us to move it to the new plans.';
        }

        $live = $this->subscriptions->getActiveSubscription($company);
        if ($live && ! $live->isTrial() && $live->plan?->modules === null) {
            return 'You are on a legacy plan. Contact us and we will move you across, keeping what you have paid for.';
        }

        return null;
    }

    /**
     * @param  array<string, int>  $addons
     * @return array{redirect?: string, scheduled?: \Carbon\Carbon, error?: string}
     */
    public function start(Company $company, Plan $plan, int $outlets, array $addons, string $cycle): array
    {
        if ($reason = $this->blockedReason($company)) {
            return ['error' => $reason];
        }

        if ($outlets < ($min = $this->minimumOutlets($company))) {
            return ['error' => "You have {$min} active outlets. Pay for at least that many, or archive outlets first."];
        }

        $quote = $this->quote($company, $plan, $outlets, $addons, $cycle);
        if (! $quote->ok()) {
            return ['error' => $quote->error];
        }

        $catalogue = (array) config('modules.catalogue');
        $checkout = [
            'plan_id'         => $plan->id,
            'billing_cycle'   => $cycle,
            'outlet_quantity' => $outlets,
            'amount'          => $quote->cycleTotal,
            'addons'          => collect($addons)
                ->reject(fn ($q, $module) => in_array($module, (array) $plan->modules, true))
                ->map(fn ($q, $module) => [
                    'quantity'   => $catalogue[$module]['kind'] === 'metered'
                        ? max((int) $q, (int) ($catalogue[$module]['min_quantity'] ?? 1)) : 1,
                    'unit_price' => (float) $catalogue[$module]['price'],
                ])->all(),
        ];

        $live = $this->subscriptions->getActiveSubscription($company);

        // A paid period whose credit covers the new price: a downgrade. No
        // refund — it takes effect when the period it was paid for ends.
        if ($live && $live->isActive() && ! $live->isTrial() && $quote->due() <= 0) {
            $live->update(['pending_change' => $checkout]);

            return ['scheduled' => $live->current_period_end];
        }

        if (! config('chipin.api_key')) {
            return ['error' => 'Online payment is not switched on yet. Contact us and we will set up your plan.'];
        }

        $subscription = $live ?? $this->incompleteFor($company, $plan, $cycle);

        $result = app(ChipInService::class)->createPurchase(
            $company, $subscription, $quote->due(), $plan->currency ?: 'MYR',
            "Servora {$plan->name} — {$outlets} outlet".($outlets === 1 ? '' : 's').", {$cycle}",
            $checkout,
        );

        if (($result['success'] ?? false) && ! empty($result['checkout_url'])) {
            return ['redirect' => $result['checkout_url']];
        }

        return ['error' => $result['message'] ?? 'Payment could not be started. Please try again.'];
    }

    /** Apply a paid (or renewal-time) configuration. Idempotent on the rows it writes. */
    public function fulfil(Payment $payment): Subscription
    {
        $c = $payment->checkout;
        $subscription = $payment->subscription;

        return DB::transaction(function () use ($c, $subscription) {
            $subscription->update([
                'plan_id'         => $c['plan_id'],
                'billing_cycle'   => $c['billing_cycle'],
                'outlet_quantity' => $c['outlet_quantity'],
                'amount'          => $c['amount'],
                'pending_change'  => null,
            ]);

            $this->subscriptions->syncAddons($subscription->fresh('plan'), $c['addons'] ?? []);

            return $this->subscriptions->activate($subscription->fresh());
        });
    }

    /**
     * Credit for the unused part of the current paid period: its price times
     * the share of the period still to run. Trials and legacy rows carry none.
     */
    private function credit(Company $company): float
    {
        $live = $this->subscriptions->getActiveSubscription($company);

        if (! $live || ! $live->isActive() || $live->isTrial() || $live->amount === null
            || ! $live->current_period_start || ! $live->current_period_end
            || $live->current_period_end->isPast()) {
            return 0;
        }

        $total = $live->current_period_start->diffInSeconds($live->current_period_end);
        $left  = now()->diffInSeconds($live->current_period_end);

        return $total > 0 ? round((float) $live->amount * $left / $total, 2) : 0;
    }

    /**
     * The row a Free company's first payment hangs off. Not live until the
     * webhook activates it; an abandoned one is reused next time.
     */
    private function incompleteFor(Company $company, Plan $plan, string $cycle): Subscription
    {
        $row = Subscription::firstOrNew(['company_id' => $company->id, 'status' => Subscription::STATUS_INCOMPLETE]);
        $row->fill(['plan_id' => $plan->id, 'billing_cycle' => $cycle])->save();

        return $row;
    }
}
