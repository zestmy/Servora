<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\ChipInService;
use App\Services\InvoiceService;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ask CHIP what actually happened to payments we never heard back about.
 *
 * A webhook is a promise, not a guarantee: it can be lost in transit, arrive
 * while the app is in maintenance mode during a deploy, or — as happened here
 * from March to August — be rejected by our own broken signature check. Every
 * one of those leaves the same wreckage: the customer has paid, and Servora
 * shows a pending payment, no invoice, and a subscription still on trial.
 *
 * Nothing was closing that loop. ChipInService::getPaymentStatus() existed and
 * had no callers. This is the caller.
 *
 * Safe to run repeatedly: it only ever moves a payment out of `pending`, it
 * takes the same row lock the webhook does, and the invoice it raises is
 * idempotent.
 */
class ReconcileChipInPayments extends Command
{
    protected $signature = 'chipin:reconcile
                            {--hours=72 : How far back to look}
                            {--all : Ignore the window and check every pending payment}';

    protected $description = 'Settle pending CHIP-IN payments whose webhook never arrived';

    public function handle(ChipInService $chipIn): int
    {
        if (! config('chipin.api_key')) {
            $this->warn('CHIP-IN API key is not configured — nothing to reconcile against.');

            return self::SUCCESS;
        }

        $query = Payment::where('status', Payment::STATUS_PENDING)
            ->whereNotNull('chip_purchase_id');

        if (! $this->option('all')) {
            $query->where('created_at', '>=', now()->subHours((int) $this->option('hours')));
        }

        $pending = $query->orderBy('id')->get();

        if ($pending->isEmpty()) {
            $this->info('No pending CHIP-IN payments to reconcile.');

            return self::SUCCESS;
        }

        $this->info("Checking {$pending->count()} pending payment(s) against CHIP-IN.");

        $settled = $failed = $stillPending = 0;

        foreach ($pending as $payment) {
            $remote = $chipIn->getPaymentStatus($payment->chip_purchase_id);

            if (! $remote) {
                $this->line("  #{$payment->id} — no answer from CHIP-IN, leaving as pending.");
                $stillPending++;

                continue;
            }

            $status = $remote['status'] ?? null;

            if (in_array($status, ['paid', 'success'], true)) {
                $this->settle($payment, $remote);
                $this->line("  #{$payment->id} — paid at CHIP-IN, settled here. Invoice raised.");
                $settled++;

                continue;
            }

            if (in_array($status, ['error', 'expired', 'cancelled', 'failed'], true)) {
                $payment->update(['status' => Payment::STATUS_FAILED, 'metadata' => $remote]);
                $this->line("  #{$payment->id} — {$status} at CHIP-IN, marked failed.");
                $failed++;

                continue;
            }

            $this->line("  #{$payment->id} — still '{$status}' at CHIP-IN.");
            $stillPending++;
        }

        $this->newLine();
        $this->info("Settled {$settled}, failed {$failed}, still pending {$stillPending}.");

        if ($settled > 0) {
            // Worth a log line: a settlement found here means a webhook was
            // lost, and a pattern of them is a problem with the endpoint
            // rather than with any one payment.
            Log::warning("chipin:reconcile settled {$settled} payment(s) whose webhook never arrived.");
        }

        return self::SUCCESS;
    }

    /**
     * The same work the webhook does, so a reconciled payment is
     * indistinguishable from one that arrived normally.
     */
    private function settle(Payment $payment, array $remote): void
    {
        DB::transaction(function () use ($payment, $remote) {
            $payment->refresh();

            // The webhook may have landed between the query and here.
            if ($payment->isCompleted()) {
                return;
            }

            $payment->update([
                'status'          => Payment::STATUS_COMPLETED,
                'chip_payment_id' => $remote['payment']['id'] ?? null,
                'payment_method'  => $remote['payment']['payment_type'] ?? ($remote['payment']['method'] ?? null),
                'paid_at'         => isset($remote['paid_on'])
                    ? now()->parse($remote['paid_on'])
                    : now(),
                'metadata'        => $remote,
            ]);

            $subscription = $payment->subscription;

            if ($subscription) {
                $service = app(SubscriptionService::class);

                $subscription->isTrial() || $subscription->isPastDue()
                    ? $service->activate($subscription)
                    : $service->renew($subscription);
            }

            app(InvoiceService::class)->createFromPayment($payment);
        });
    }
}
