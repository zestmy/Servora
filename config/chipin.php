<?php

return [
    'brand_id'       => env('CHIPIN_BRAND_ID'),
    'api_key'        => env('CHIPIN_API_KEY'),
    'base_url'       => env('CHIPIN_BASE_URL', 'https://gate.chip-in.asia/api/v1'),
    'sandbox'        => env('CHIPIN_SANDBOX', true),

    /*
     * The webhook's PUBLIC KEY, not a shared secret.
     *
     * CHIP signs every callback with RSA PKCS#1 v1.5 over a SHA-256 digest of
     * the raw body and sends it base64-encoded in X-Signature. Verification is
     * openssl_verify() against the public key the merchant portal shows when
     * the webhook is created — there is no HMAC and no shared secret anywhere
     * in the scheme.
     *
     * This is normally set in the admin UI (Admin > Billing Settings) rather
     * than here, because it is a multi-line PEM block that a .env file handles
     * badly and because rotating it should not need a deploy. The env var is
     * the fallback, and AppSetting wins when both are present.
     */
    'webhook_public_key' => env('CHIPIN_WEBHOOK_PUBLIC_KEY'),

    /*
     * Retired. CHIPIN_WEBHOOK_SECRET fed an hash_hmac() check that could never
     * match a real CHIP signature — see ChipInService::verifyWebhook(). It is
     * read here only so a deployment that still sets it can be detected and
     * warned about rather than silently ignored.
     */
    'legacy_webhook_secret' => env('CHIPIN_WEBHOOK_SECRET'),
];
