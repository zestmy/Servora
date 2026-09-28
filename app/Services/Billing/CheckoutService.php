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
 *
 * Currency: a company is quoted in its own currency (CurrencyResolver), and
 * the subscription keeps that currency and price. CHIP-IN takes MYR only, so
 * the amount due is converted at Bank Negara's rate at the moment of payment
 * — fetched fresh for it — and the payment records the rate it used.
 */
class CheckoutService
{
    public function __construct(
        private PriceCalculator $calculator,
        private SubscriptionService $subscriptions,
        private CurrencyResolver $currencies,
        private ExchangeRates $rates,
    ) {}

    /** The book a company is priced from. */
    public function book(Company $company, bool $freshRate = false): PriceBook
    {
        return $this->currencies->book(
            $this->currencies->forCompany($company, app()->runningInConsole() ? null : request()),
            $freshRate,
        );
    }

    /** @param array<string, int> $addons module => quantity */
    public function quote(Company $company, Plan $plan, int $outlets, array $addons, string $cycle, ?PriceBook $book = null): Quote
    {
        $book ??= $this->book($company);
        $quote = $this->calculator->quote($plan, $outlets, $addons, $cycle, $book);

        if (! $quote->ok()) {
            return $quote;
        }

        return $quote->withCredit($this->credit($company, $book->currency));
    }

    /**
     * The rate CHIP-IN's MYR charge for a quote is worked out at: Bank
     * Negara's for anything but MYR. Null when there is no usable rate —
     * checkout then refuses rather than guess.
     */
    public function charge(Quote $quote, ?Fx $fx = null): ?Fx
    {
        $fx ??= $this->rates->rate($quote->currency);

        return $fx && $fx->currency === $quote->currency ? $fx : null;
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

        // The real-time read: a fresh BNM rate for the charge.
        $book  = $this->book($company, freshRate: true);
        $quote = $this->quote($company, $plan, $outlets, $addons, $cycle, $book);
        if (! $quote->ok()) {
            return ['error' => $quote->error];
        }

        $fx = $this->charge($quote, $book->fx);
        if (! $fx) {
            return ['error' => "We could not get today's Bank Negara rate for {$quote->currency}. Please try again in a few minutes."];
        }

        $catalogue = (array) config('modules.catalogue');
        $checkout = [
            'plan_id'         => $plan->id,
            'billing_cycle'   => $cycle,
            'outlet_quantity' => $outlets,
            'currency'        => $quote->currency,
            'amount'          => $quote->cycleTotal,
            'addons'          => collect($addons)
                ->reject(fn ($q, $module) => in_array($module, (array) $plan->modules, true))
                ->map(fn ($q, $module) => [
                    'quantity'   => $catalogue[$module]['kind'] === 'metered'
                        ? max((int) $q, (int) ($catalogue[$module]['min_quantity'] ?? 1)) : 1,
                    'unit_price' => (float) $book->module($module, (float) $catalogue[$module]['price']),
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

        $description = "Servora {$plan->name} — {$outlets} outlet".($outlets === 1 ? '' : 's').", {$cycle}";
        if (! $fx->isMyr()) {
            $description .= ' ('.$book->format($quote->due(), 2).')';
        }

        $result = app(ChipInService::class)->createPurchase(
            $company, $subscription, $fx->toMyr($quote->due()), 'MYR', $description, $checkout,
            $fx->isMyr() ? null : $fx->toArray($quote->due()),
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
                'currency'        => $c['currency'] ?? 'MYR',
                'amount'          => $c['amount'],
                'pending_change'  => null,
            ]);

            // The first payment fixes the currency this company is priced in.
            $company = $subscription->company;
            if ($company && ! $company->billing_currency) {
                $company->forceFill(['billing_currency' => $c['currency'] ?? 'MYR'])->save();
            }

            $this->subscriptions->syncAddons($subscription->fresh('plan'), $c['addons'] ?? []);

            return $this->subscriptions->activate($subscription->fresh());
        });
    }

    /**
     * Credit for the unused part of the current paid period: its price times
     * the share of the period still to run, in $currency. Trials and legacy
     * rows carry none. A subscription paid in another currency (an admin
     * changed the company's) is carried across through MYR.
     */
    private function credit(Company $company, string $currency = 'MYR'): float
    {
        $live = $this->subscriptions->getActiveSubscription($company);

        if (! $live || ! $live->isActive() || $live->isTrial() || $live->amount === null
            || ! $live->current_period_start || ! $live->current_period_end
            || $live->current_period_end->isPast()) {
            return 0;
        }

        $total = $live->current_period_start->diffInSeconds($live->current_period_end);
        $left  = now()->diffInSeconds($live->current_period_end);

        $credit = $total > 0 ? round((float) $live->amount * $left / $total, 2) : 0;

        $from = $live->currency ?: 'MYR';
        if ($credit > 0 && $from !== $currency) {
            $in  = $this->rates->rate($from);
            $out = $this->rates->rate($currency);

            return $in && $out ? $out->fromMyr($in->toMyr($credit)) : 0;
        }

        return $credit;
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
