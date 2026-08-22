<?php

namespace App\Services;

use App\Models\AppSetting;
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
        /*
         * Cast, do not lean on config()'s default.
         *
         * config('chipin.api_key', '') returns the DEFAULT only when the key
         * is absent from the config array. config/chipin.php defines it as
         * env('CHIPIN_API_KEY'), so with the variable unset the key is present
         * and its value is null — the default never applies, null lands in a
         * `string` property, and merely constructing this service throws a
         * TypeError. Production had the env set so it never showed there; a
         * fresh checkout or a test run fell over on `new ChipInService`.
         */
        $this->baseUrl = (string) (config('chipin.base_url') ?: 'https://gate.chip-in.asia/api/v1');
        $this->apiKey  = (string) config('chipin.api_key');
        $this->brandId = (string) config('chipin.brand_id');
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
                            // round(), not a bare cast: (int) (29.99 * 100) is 2998
                            // in binary floating point, so the customer is billed a
                            // cent less than the invoice says.
                            'price'    => (int) round($amount * 100),
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
     * Is this callback really from CHIP?
     *
     * CHIP signs the raw request body with RSA PKCS#1 v1.5 over a SHA-256
     * digest and sends it base64-encoded in X-Signature. Verification is a
     * public-key check — there is no shared secret in the scheme at all.
     *
     * THIS USED TO BE hash_hmac('sha256', $payload, $secret). That can never
     * equal an RSA signature, so with CHIPIN_WEBHOOK_SECRET set every genuine
     * callback was answered 403 and no payment ever completed; with it unset
     * the caller skipped the check entirely and the endpoint would activate a
     * subscription for anyone who could guess a purchase id. Both halves of
     * that are fixed here and in the controller.
     */
    public function verifyWebhook(string $payload, string $signature): bool
    {
        $publicKey = $this->webhookPublicKey();

        if (! $publicKey || $signature === '') {
            return false;
        }

        $decoded = base64_decode($signature, true);

        if ($decoded === false) {
            return false;
        }

        $key = openssl_pkey_get_public($publicKey);

        if ($key === false) {
            Log::error('CHIP-IN webhook public key could not be parsed', [
                'openssl' => openssl_error_string(),
            ]);

            return false;
        }

        // Strictly 1. openssl_verify() returns -1 on error and 0 on a bad
        // signature, and both are falsy — but only === 1 means verified.
        return openssl_verify($payload, $decoded, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * The configured public key, as a PEM block.
     *
     * Settings win over env so the key can be rotated from Admin > Billing
     * Settings without a deploy. Pasting is forgiving: the portal shows the
     * key with PEM headers, but people routinely copy just the base64 body,
     * and a key that fails to parse looks identical to a bad signature from
     * the outside.
     */
    public function webhookPublicKey(): ?string
    {
        return self::normalisePublicKey(
            (string) (AppSetting::get('chipin_webhook_public_key')
                ?: config('chipin.webhook_public_key', ''))
        );
    }

    /**
     * Turn whatever somebody pasted into a PEM block, or null if it is empty.
     *
     * Static and pure so the settings screen can validate a key BEFORE storing
     * it. Storing first and validating after leaves a broken key in the
     * database behind an error message, which is how an integration ends up
     * silently unverifiable.
     */
    public static function normalisePublicKey(string $key): ?string
    {
        $key = trim($key);

        if ($key === '') {
            return null;
        }

        if (str_contains($key, 'BEGIN PUBLIC KEY') || str_contains($key, 'BEGIN RSA PUBLIC KEY')) {
            return $key;
        }

        $body = chunk_split(preg_replace('/\s+/', '', $key), 64, PHP_EOL);

        return '-----BEGIN PUBLIC KEY-----' . PHP_EOL . $body . '-----END PUBLIC KEY-----' . PHP_EOL;
    }

    /** Whether OpenSSL can actually read this as a public key. */
    public static function isUsablePublicKey(string $key): bool
    {
        $pem = self::normalisePublicKey($key);

        return $pem !== null && openssl_pkey_get_public($pem) !== false;
    }

    /** Whether callbacks can be authenticated at all. */
    public function canVerifyWebhooks(): bool
    {
        return $this->webhookPublicKey() !== null;
    }
}
