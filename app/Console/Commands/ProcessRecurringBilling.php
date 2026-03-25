<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\ChipInService;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class ProcessRecurringBilling extends Command
{
    protected $signature = 'billing:process-recurring';
    protected $description = 'Process recurring billing for subscriptions due for renewal';

    public function handle(): int
    {
        $subscriptionService = app(SubscriptionService::class);

        // Find subscriptions ending within 3 days
        $dueSoon = Subscription::where('status', Subscription::STATUS_ACTIVE)
            ->where('current_period_end', '<=', now()->addDays(3))
            ->with(['company', 'plan'])
            ->get();

        $this->info("Found {$dueSoon->count()} subscriptions due for renewal.");

        foreach ($dueSoon as $sub) {
            $amount = $sub->billing_cycle === 'yearly'
                ? (float) $sub->plan->price_yearly
                : (float) $sub->plan->price_monthly;

            if ($amount <= 0) {
                continue;
            }

            $this->line("  Processing: {$sub->company->name} — {$sub->plan->name} ({$sub->plan->currency} {$amount})");

            $result = app(ChipInService::class)->createPurchase(
                $sub->company,
                $sub,
                $amount,
                $sub->plan->currency,
            );

            if ($result['success']) {
                $this->info("    Payment created: {$result['purchase_id']}");
            } else {
                $this->warn("    Payment creation failed: {$result['message']}");
            }
        }

        // Mark past due: subscriptions that ended 7+ days ago and still active
        $pastDue = Subscription::where('status', Subscription::STATUS_ACTIVE)
            ->where('current_period_end', '<', now()->subDays(7))
            ->get();

        foreach ($pastDue as $sub) {
            $subscriptionService->markPastDue($sub);
            $this->warn("  Marked past due: {$sub->company->name}");
        }

        // Expire: subscriptions past due for 14+ days
        $expired = Subscription::where('status', Subscription::STATUS_PAST_DUE)
            ->where('current_period_end', '<', now()->subDays(14))
            ->get();

        foreach ($expired as $sub) {
            $subscriptionService->expire($sub);
            $this->error("  Expired: {$sub->company->name}");
        }

        // Expire trials
        $expiredTrials = Subscription::where('status', Subscription::STATUS_TRIALING)
            ->where('trial_ends_at', '<', now())
            ->get();

        foreach ($expiredTrials as $sub) {
            $subscriptionService->expire($sub);
            $this->warn("  Trial expired: {$sub->company->name}");
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
