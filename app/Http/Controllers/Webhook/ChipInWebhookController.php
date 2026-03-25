<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\ChipInService;
use App\Services\InvoiceService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChipInWebhookController extends Controller
{
    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        Log::info('CHIP-IN webhook received', ['payload' => $request->all()]);

        $chipInService = app(ChipInService::class);

        // Verify signature if configured
        $signature = $request->header('X-Signature', '');
        if (config('chipin.webhook_secret') && !$chipInService->verifyWebhook($request->getContent(), $signature)) {
            Log::warning('CHIP-IN webhook signature verification failed');
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $data = $request->all();
        $purchaseId = $data['id'] ?? null;
        $status = $data['status'] ?? null;

        if (!$purchaseId) {
            return response()->json(['error' => 'Missing purchase ID'], 400);
        }

        $payment = Payment::where('chip_purchase_id', $purchaseId)->first();

        if (!$payment) {
            Log::warning('CHIP-IN webhook: payment not found', ['purchase_id' => $purchaseId]);
            return response()->json(['error' => 'Payment not found'], 404);
        }

        // Already processed
        if ($payment->isCompleted()) {
            return response()->json(['status' => 'already_processed']);
        }

        if ($status === 'paid' || $status === 'success') {
            // Mark payment as completed
            $payment->update([
                'status'          => Payment::STATUS_COMPLETED,
                'chip_payment_id' => $data['payment']['id'] ?? null,
                'payment_method'  => $data['payment']['method'] ?? null,
                'paid_at'         => now(),
                'metadata'        => $data,
            ]);

            // Activate or renew subscription
            $subscription = $payment->subscription;
            if ($subscription->isTrial() || $subscription->isPastDue()) {
                app(SubscriptionService::class)->activate($subscription);
            } else {
                app(SubscriptionService::class)->renew($subscription);
            }

            // Generate invoice
            app(InvoiceService::class)->createFromPayment($payment);

            Log::info('CHIP-IN payment completed', ['payment_id' => $payment->id, 'company' => $payment->company->name]);
        } elseif ($status === 'failed' || $status === 'expired') {
            $payment->update([
                'status'   => Payment::STATUS_FAILED,
                'metadata' => $data,
            ]);

            Log::info('CHIP-IN payment failed', ['payment_id' => $payment->id]);
        }

        return response()->json(['status' => 'ok']);
    }
}
