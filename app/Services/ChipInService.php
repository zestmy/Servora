<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChipInService
{
    private string $baseUrl;
    private string $apiKey;
    private string $brandId;

    public function __construct()
    {
        $this->baseUrl = config('chipin.base_url');
        $this->apiKey  = config('chipin.api_key', '');
        $this->brandId = config('chipin.brand_id', '');
    }

    /**
     * Create a purchase (payment request) via CHIP-IN API.
     */
    public function createPurchase(
        Company $company,
        Subscription $subscription,
        float $amount,
        string $currency = 'MYR',
        ?string $description = null,
    ): array {
        $description = $description ?? "Servora {$subscription->plan->name} — {$subscription->billing_cycle}";

        $callbackUrl = url('/webhooks/chipin');
        $successUrl  = url('/billing?payment=success');
        $failureUrl  = url('/billing?payment=failed');

        // Create a pending payment record
        $payment = Payment::create([
            'company_id'      => $company->id,
            'subscription_id' => $subscription->id,
            'amount'          => $amount,
            'currency'        => $currency,
            'status'          => Payment::STATUS_PENDING,
        ]);

        if (!$this->apiKey) {
            Log::warning('CHIP-IN API key not configured — skipping API call');
            return [
                'success'    => false,
                'payment_id' => $payment->id,
                'message'    => 'CHIP-IN API key not configured.',
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ])->post($this->baseUrl . '/purchases/', [
                'brand_id'    => $this->brandId,
                'client'      => [
                    'email'      => $company->email,
                    'full_name'  => $company->name,
                ],
                'purchase'    => [
                    'currency'    => $currency,
                    'products'    => [
                        [
                            'name'     => $description,
                            'price'    => (int) ($amount * 100), // CHIP-IN uses cents
                            'quantity' => 1,
                        ],
                    ],
                ],
                'success_callback' => $callbackUrl,
                'success_redirect' => $successUrl,
                'failure_redirect' => $failureUrl,
                'send_receipt'     => true,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                $payment->update([
                    'chip_purchase_id' => $data['id'] ?? null,
                    'metadata'         => $data,
                ]);

                return [
                    'success'      => true,
                    'payment_id'   => $payment->id,
                    'checkout_url' => $data['checkout_url'] ?? null,
                    'purchase_id'  => $data['id'] ?? null,
                ];
            }

            Log::warning('CHIP-IN create purchase failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            $payment->update(['status' => Payment::STATUS_FAILED]);

            return [
                'success'    => false,
                'payment_id' => $payment->id,
                'message'    => 'Payment creation failed: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('CHIP-IN exception', ['error' => $e->getMessage()]);

            $payment->update(['status' => Payment::STATUS_FAILED]);

            return [
                'success'    => false,
                'payment_id' => $payment->id,
                'message'    => 'Payment service error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get payment status from CHIP-IN.
     */
    public function getPaymentStatus(string $purchaseId): ?array
    {
        if (!$this->apiKey) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get($this->baseUrl . '/purchases/' . $purchaseId);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('CHIP-IN status check failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Verify webhook signature.
     */
    public function verifyWebhook(string $payload, string $signature): bool
    {
        $secret = config('chipin.webhook_secret');

        if (!$secret) {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }
}
