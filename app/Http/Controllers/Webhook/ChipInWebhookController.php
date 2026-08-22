<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\ChipInService;
use App\Services\InvoiceService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The CHIP-IN payment callback.
 *
 * This endpoint is public, unauthenticated and grants paid subscriptions, so
 * the signature check is the only thing standing between it and anybody who
 * can guess a purchase id. It was not doing that job at all.
 *
 *   1. It FAILED OPEN, and that is how production actually ran.
 *      `if ($secret && ! verify())` skips verification entirely when no secret
 *      is configured — and CHIPIN_WEBHOOK_SECRET was empty on production. Any
 *      unsigned POST carrying a known purchase id would have completed the
 *      payment, activated the subscription and raised an invoice.
 *
 *   2. Even configured, it could never have worked. It verified with
 *      hash_hmac() while CHIP signs with RSA, so setting the secret would have
 *      turned the hole into a wall: every genuine callback refused instead.
 *      Broken open and broken closed at the same time, depending on one env
 *      var nobody had reason to look at.
 *
 * Both are fixed: verification is RSA against the merchant portal's public key
 * (ChipInService::verifyWebhook), and a callback that cannot be verified —
 * including because no key is configured — is refused.
 */
class ChipInWebhookController extends Controller
{
    public function handle(Request $request, ChipInService $chipIn): \Illuminate\Http\JsonResponse
    {
        // Headers and ids only. The body carries the payer's name and email,
        // and this used to log the whole thing at info level on every call.
        Log::info('CHIP-IN webhook received', [
            'event'       => $request->header('X-Event-Type'),
            'purchase_id' => $request->input('id'),
            'status'      => $request->input('status'),
        ]);

        if (! $chipIn->canVerifyWebhooks()) {
            Log::error('CHIP-IN webhook refused: no webhook public key configured. '
                . 'Set it in Admin > Billing Settings — payments cannot complete until it is.');

            return response()->json(['error' => 'Webhook verification is not configured'], 503);
        }

        if (! $chipIn->verifyWebhook($request->getContent(), (string) $request->header('X-Signature', ''))) {
            Log::warning('CHIP-IN webhook signature verification failed', [
                'purchase_id' => $request->input('id'),
            ]);

            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $data       = $request->all();
        $purchaseId = $data['id'] ?? null;

        if (! $purchaseId) {
            return response()->json(['error' => 'Missing purchase ID'], 400);
        }

        // The event name is the reliable signal; `status` is the purchase
        // object's own field and older payloads only carried that.
        $event  = (string) $request->header('X-Event-Type', '');
        $status = $data['status'] ?? null;

        $paid = $event === 'purchase.paid'
            || in_array($status, ['paid', 'success'], true);

        $failed = in_array($event, ['purchase.cancelled', 'purchase.expired'], true)
            || in_array($status, ['failed', 'expired', 'cancelled'], true);

        return DB::transaction(function () use ($purchaseId, $data, $paid, $failed, $status) {
            // lockForUpdate: CHIP retries, and two deliveries arriving together
            // both passed the isCompleted() check before either had written —
            // which renewed the subscription twice and moved the period end out
            // by two months for one payment.
            $payment = Payment::where('chip_purchase_id', $purchaseId)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                Log::warning('CHIP-IN webhook: payment not found', ['purchase_id' => $purchaseId]);

                return response()->json(['error' => 'Payment not found'], 404);
            }

            if ($payment->isCompleted()) {
                return response()->json(['status' => 'already_processed']);
            }

            if ($paid) {
                if (! $this->amountMatches($payment, $data)) {
                    // Do NOT complete. Underpayment must not buy a full period,
                    // and a mismatch is more likely a bug or an attack than a
                    // customer who paid the wrong amount by accident.
                    Log::error('CHIP-IN webhook: paid amount does not match the payment record', [
                        'payment_id' => $payment->id,
                        'expected'   => (float) $payment->amount,
                        'received'   => $this->callbackAmount($data),
                        'currency'   => $data['currency'] ?? null,
                    ]);

                    return response()->json(['error' => 'Amount mismatch'], 422);
                }

                $this->complete($payment, $data);

                return response()->json(['status' => 'ok']);
            }

            if ($failed) {
                $payment->update([
                    'status'   => Payment::STATUS_FAILED,
                    'metadata' => $data,
                ]);

                Log::info('CHIP-IN payment failed', ['payment_id' => $payment->id, 'status' => $status]);
            }

            return response()->json(['status' => 'ok']);
        });
    }

    /**
     * Settle the payment, move the subscription on, and raise the invoice.
     */
    private function complete(Payment $payment, array $data): void
    {
        $payment->update([
            'status'          => Payment::STATUS_COMPLETED,
            'chip_payment_id' => $data['payment']['id'] ?? null,
            'payment_method'  => $data['payment']['payment_type'] ?? ($data['payment']['method'] ?? null),
            'paid_at'         => now(),
            'metadata'        => $data,
        ]);

        // Null-safe: subscription_id is nullable now that an invoice can be
        // settled by hand without one. A gateway payment always has one, but
        // this must not 500 if a row is ever stitched up differently.
        $subscription = $payment->subscription;

        if ($subscription) {
            $service = app(SubscriptionService::class);

            $subscription->isTrial() || $subscription->isPastDue()
                ? $service->activate($subscription)
                : $service->renew($subscription);
        }

        // Idempotent — returns the existing invoice on a retry.
        app(InvoiceService::class)->createFromPayment($payment);

        Log::info('CHIP-IN payment completed', [
            'payment_id' => $payment->id,
            'company_id' => $payment->company_id,
        ]);
    }

    /**
     * Does the amount CHIP says was paid match what we asked for?
     *
     * CHIP reports money in minor units (cents), so the comparison is done in
     * cents to keep floating point out of it. A callback that does not carry
     * an amount at all is accepted rather than rejected — some payload shapes
     * omit it, and refusing on a missing field would break the happy path to
     * defend against something that has not happened.
     */
    private function amountMatches(Payment $payment, array $data): bool
    {
        $received = $this->callbackAmount($data);

        if ($received === null) {
            return true;
        }

        $currency = $data['currency'] ?? null;

        if ($currency && strtoupper((string) $currency) !== strtoupper((string) $payment->currency)) {
            return false;
        }

        return $received === (int) round(((float) $payment->amount) * 100);
    }

    /** The paid amount in cents, or null when the payload does not say. */
    private function callbackAmount(array $data): ?int
    {
        foreach ([$data['payment']['amount'] ?? null, $data['purchase']['total'] ?? null, $data['total'] ?? null] as $value) {
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }
}
