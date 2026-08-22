<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\ChipInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The CHIP-IN payment callback.
 *
 * This endpoint is public, unauthenticated, and hands out paid subscriptions.
 * It was not authenticating anything:
 *
 *   - Verification only ran when a shared secret was configured, and
 *     CHIPIN_WEBHOOK_SECRET was EMPTY on production. An unsigned POST carrying
 *     a known purchase id would have completed the payment, activated the
 *     subscription and raised an invoice.
 *   - The check itself used hash_hmac() while CHIP signs with RSA, so filling
 *     that variable in would not have fixed it — it would have refused every
 *     genuine callback instead.
 *
 * These tests sign with a real RSA key, so they exercise the actual crypto
 * rather than a stub.
 */
class ChipInWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Subscription $subscription;
    private Payment $payment;

    /*
     * Throwaway 2048-bit RSA keypairs, checked in as fixtures.
     *
     * Generated once rather than per run because openssl_pkey_new() needs an
     * openssl.cnf that the Windows PHP build here does not ship — key
     * GENERATION fails with "configuration file routines::no such file",
     * while signing and verifying with an existing key work fine. Fixtures
     * also make the test deterministic and instant.
     *
     * These sign nothing but test payloads and are not credentials for
     * anything. ATTACKER_PRIVATE_KEY is a second, unrelated pair used to
     * forge a callback the endpoint must reject.
     */
    private const TEST_PRIVATE_KEY = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQC5MoclooNfKa1F
jvznlr+K68OJgY56nuKNCPwwcChQ+gyResNhe1SBjt/EdHbIwid2e51xDQpm/oe5
Foln1bn5aZjCUOjj9yngqz/r4szaFP2Ez4JXcBXzyYBIaY6lWgMWRlbDeH0VtvnY
kx3qGJeg65mqDFqQDvtprpKODE6P/bXZpDNwfrfETTTP9Z1KRJGpTB+OrlaR/B4r
FHKtJFbFRrmfItIxpnP5+bu1ZoPjPYcDkUcVPkWpXzPwrl+JrnG8dAOylO3YwXGQ
9w0BiArqCktVcu4au8srEpFl2NXByHLYLNArAIAvMtPkIlJpTL/p4pFr2/xvRMoE
kUTZ+Wz5AgMBAAECggEABg9Gj4dPod6e3CircwbyVcD03RQThcTa4BdADDQxR4QF
AwIXsEH9L/+NKBQQ+mzq5mQxULyG33ua1IYtoQuD2iq3vDzyP9pLoR2tWeIqBoU7
DdxN1R9WYouQcl4c1CF7uh/7UQpJWntqFalrqEgddv8agC9HC9Fnrcuv4Sy7Kdgq
rISZ+fcUlgJDcDxOad5gZWG8Qdq/XPJioVnjTU3zMOmh0GKvh1WlsBwBy62o4ZfM
TXixjFqP5VD5SS975Z08tl1IpmOGbOmtKVDWSrka8Kn9+MP5FT5bkGAuvpAJYs8Y
VOz8iuJHM7U/DW5g1UKyVkBRvTWI2zmmHW5/LrZucQKBgQDtbZY/gj5KFBwZEHE8
OakQE7+tou6CthB+AY6+xJDd665yX5Nsnkrs6p2Yz4MjH2GqSQmesxKikXnF0rVW
OiMLqFSj81IxH72QVcyNyqCxDeIMxJufAitqCavxOnLwfwWzOAF+G+WTP4CiUfuA
deSuf9jaR8/MzHEqL1cfoxNYEQKBgQDHrwotlZLmhao+JSvt1xrx4IFAUmM0sxO2
wcMbSQ0uwouclucymNfRC9g+z7A+2GaoCLXyIEnH95L8Du2j9WFOsosGbb8gLOh5
BsHnxWYGmoUjJtnschAYsoZxC0P/RlCK8iG+cY6gWraf29k4VZCd0FRrfnJmMUda
wsEbrwNuaQKBgQCZETx7JzGXOo2+zu3hwN6wwbqia9dOp6fMRJ7NeBZZLBdkHyAB
N6/gO4VsveOyYgnp6XptOM97xUP3eFd2BrcPTe97X2QOzYK9qcLdatPcMbIZPyuB
ALoSe7fBJkhxqcJ3/1RfBAcmvhrlCuuUruzGXx/j4cYjJ26RnsGRYOYYsQKBgQC9
j97irVbaflPCUTllvUm4Cv/Ily3UjpgNa94TXgMku80bp2nt74kZy9vKrRFMZ9T0
eeh35c0FB3NC080nVD+/HOG8BZ1mJxu+IPsdUpjrde8kErLYsuOy/m+Ai0hO42p8
rSX5jAXxFoy+L1AEGb6DAo3RyiVB/FAXykDWTgu82QKBgQCdlGH1ehqvecZeJR2+
8UGuGmkiT4eS9YnaNrl0Lxw6kNpwIT1hWNDQdvRyORwDaIWCxgwYvY1dhRkfVnYf
vftnGdLiuSubvcQ9zmugk9M3XbL495LdpjzMJJEObflE3hLrcYukCPY+XyH0WbNB
wKnuCovkTB8BI4yIt8arf0zjNQ==
-----END PRIVATE KEY-----
PEM;

    private const TEST_PUBLIC_KEY = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAuTKHJaKDXymtRY7855a/
iuvDiYGOep7ijQj8MHAoUPoMkXrDYXtUgY7fxHR2yMIndnudcQ0KZv6HuRaJZ9W5
+WmYwlDo4/cp4Ks/6+LM2hT9hM+CV3AV88mASGmOpVoDFkZWw3h9Fbb52JMd6hiX
oOuZqgxakA77aa6SjgxOj/212aQzcH63xE00z/WdSkSRqUwfjq5WkfweKxRyrSRW
xUa5nyLSMaZz+fm7tWaD4z2HA5FHFT5FqV8z8K5fia5xvHQDspTt2MFxkPcNAYgK
6gpLVXLuGrvLKxKRZdjVwchy2CzQKwCALzLT5CJSaUy/6eKRa9v8b0TKBJFE2fls
+QIDAQAB
-----END PUBLIC KEY-----
PEM;

    private const ATTACKER_PRIVATE_KEY = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC/uux+jYt6WlcZ
2pw4NBiMRCX5T0IH4bMjUBkGvIXaIHGnQo+/riIsaMKfodRNH0r8ItJMr+1zvLQW
tTK8NjqRNhRmxTzNjTd4QawxqSfQL9EtRto6NyVfmzDfUR8cd6afSL0yzv0Ej8Jo
oyLjugmVdMufLJyvXY5p2Bz+lPH2gB+1WEaFaJzbbMJhnglUhurbaLvLp/hTzBI+
ofa7Jeq+pgBSqH5YHVTvjY2FIAhmp6HeXRrbLAPxKHbBT+9Zzo+YUaiuTKxP6HiY
+pKXv2DwM+TId2se0YD8e1/oFSxc64fIImEoi5fRgxdLjTKyzRrweAnGljRu9l6v
oYM0iAHDAgMBAAECggEAMKBqaaBhjxsJezuoIlMIJM8F6IizAQe7pMmkR0KaGhvf
Z2Hozl8OOMArGEx1aUf1/yOfvaZi7VcfP6EeKIECcqDKJNSCWsmll2DkmMXDnLf7
7+VH5Lrmxiw4hXLwFoq8HirXHWNE5ZrGXj590070Lk0sdYbdiFaMj4ipteG1ymP8
+E9MiBIa1TWi3l0D/yrZ9bmuDRZtMhDxpyr4s7TffJO85hgRGCjx0oX9D5k5XP/s
l6FAPNQq4bvY1uVouae4sUrrRKHw/uYDKh1GetbrwgiuQ3ZYc4yif4oFFxc70RGq
5NiYes8PMxrnaKa2ArDXbai/60DywHsCWxAwrEtnNQKBgQDkaj8feg5EtbY8eAy1
e+o0m/QEkVrRM6ne+oCKTzPc5Ob8fc01ruaSov1r9arGxhtWr0/Xjxzh5m879J97
S3k9+7urFLH6S/Gt0+W9SuOp5kNKfmo3A+Of+4hvSAtHOaqD34AaOx5n8RN4JGgz
Q0+mRotr9xGp9biI3SpJ9vsOVQKBgQDW4oQsh4UGJrzYJGtlK5djfj8k7Ck3WfMX
NcwdhAxPo85JQeTKoAS6CVpM+/dypb3e1uUJG60PJT3oJmx140dOORq21AcNZX7T
s26R6nVE0fqETww6EzI7Ka24+CGxMslvzi57VVVv+eit3FgZKG3AdqI9gOTXFzSY
wsVInoq3twKBgQC2g4sizLXAA261jLui/HvdQ8xNJhRqW5zd9k5ltfncBO/pS2CB
B9tnymMzM98c68mGj5j9xnYur6GsR8BzlZAwjgicIHJCbRKVcl79zWxzIvIcAT0/
7pShDi0rtmaEqDhvHVTQIPMf3QtQkc7NP3jShUX8pDRyGU0113tmFLrw7QKBgEwL
w8zidOA1a1VuphcasyvBrOULMpblHVNUdZToe1bf2IwyZp6SvLl9v9hIv0xyfVxU
Xp+3jKo0etEib/XUZhK3wM19AbXq+lQ/Rt0axT+CtA6IzwHqczdey50PKxVDrOgF
Zou9KkxDe+WzefYFSbn4AfiUGehIzRNWsmy9tsfHAoGBAJT8v0ZU/2zyhkgrgVRv
/nihONzJqJIsrBxcyeBGNcgV/atEG6niWI6I3UjAPN5A7XHZba4FD98bA/GeLY2x
WS+3NorYxviNZ/D68Vc2CthQwP4K4z0wVB8cAj9I0eO2JVGOAJrEHaGJa784HREi
243wGZhSVf48WbLRJDs3+OG7
-----END PRIVATE KEY-----
PEM;

    private \OpenSSLAsymmetricKey $privateKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->privateKey = openssl_pkey_get_private(self::TEST_PRIVATE_KEY);

        AppSetting::set('chipin_webhook_public_key', self::TEST_PUBLIC_KEY);

        $this->company = Company::create([
            'name' => 'Kopitiam Group', 'slug' => 'kopitiam-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true, 'email' => 'billing@kopitiam.test',
        ]);

        $plan = Plan::create([
            'name' => 'Growth', 'slug' => 'growth-' . uniqid(),
            'price_monthly' => 129.00, 'price_yearly' => 1290.00, 'currency' => 'MYR',
            'is_active' => true, 'is_public' => true, 'trial_days' => 14,
        ]);

        $this->subscription = Subscription::create([
            'company_id'    => $this->company->id,
            'plan_id'       => $plan->id,
            'status'        => Subscription::STATUS_TRIALING,
            'billing_cycle' => 'monthly',
            'trial_ends_at' => now()->addDays(7),
        ]);

        $this->payment = Payment::create([
            'company_id'       => $this->company->id,
            'subscription_id'  => $this->subscription->id,
            'amount'           => 129.00,
            'currency'         => 'MYR',
            'status'           => Payment::STATUS_PENDING,
            'chip_purchase_id' => 'e2df4196-8bca-429d-b1d2-9a993f528956',
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function postCallback(array $payload, ?string $signature = null, array $headers = [])
    {
        $body = json_encode($payload);

        if ($signature === null) {
            openssl_sign($body, $raw, $this->privateKey, OPENSSL_ALGO_SHA256);
            $signature = base64_encode($raw);
        }

        return $this->call(
            'POST',
            route('webhooks.chipin'),
            [], [], [],
            array_merge([
                'HTTP_X_SIGNATURE'  => $signature,
                'HTTP_X_EVENT_TYPE' => 'purchase.paid',
                'CONTENT_TYPE'      => 'application/json',
            ], $headers),
            $body
        );
    }

    private function paidPayload(int $cents = 12900): array
    {
        return [
            'id'       => $this->payment->chip_purchase_id,
            'status'   => 'paid',
            'currency' => 'MYR',
            'payment'  => ['id' => 'pay_123', 'amount' => $cents, 'payment_type' => 'fpx'],
        ];
    }

    // ── The happy path, which never once worked in production ──────────────

    public function test_a_properly_signed_paid_callback_settles_everything(): void
    {
        $this->postCallback($this->paidPayload())->assertOk();

        $this->payment->refresh();
        $this->assertSame(Payment::STATUS_COMPLETED, $this->payment->status);
        $this->assertNotNull($this->payment->paid_at);
        $this->assertSame('fpx', $this->payment->payment_method);

        // Trial converts to a paid period.
        $this->assertSame(Subscription::STATUS_ACTIVE, $this->subscription->refresh()->status);
        $this->assertNull($this->subscription->trial_ends_at);

        // And the customer gets an invoice.
        $this->assertSame(1, Invoice::count());
        $this->assertSame(Invoice::STATUS_PAID, Invoice::first()->status);
    }

    // ── Authentication ─────────────────────────────────────────────────────

    public function test_an_unsigned_callback_is_refused(): void
    {
        $this->postCallback($this->paidPayload(), signature: '')->assertForbidden();

        $this->assertSame(Payment::STATUS_PENDING, $this->payment->refresh()->status);
    }

    public function test_a_callback_signed_by_the_wrong_key_is_refused(): void
    {
        $attacker = openssl_pkey_get_private(self::ATTACKER_PRIVATE_KEY);
        openssl_sign(json_encode($this->paidPayload()), $raw, $attacker, OPENSSL_ALGO_SHA256);

        $this->postCallback($this->paidPayload(), signature: base64_encode($raw))->assertForbidden();

        $this->assertSame(Payment::STATUS_PENDING, $this->payment->refresh()->status);
    }

    public function test_a_tampered_body_is_refused(): void
    {
        // Sign the real payload, then send a different one under that signature.
        openssl_sign(json_encode($this->paidPayload()), $raw, $this->privateKey, OPENSSL_ALGO_SHA256);

        $this->postCallback(
            array_merge($this->paidPayload(), ['status' => 'paid', 'id' => 'someone-elses-purchase']),
            signature: base64_encode($raw)
        )->assertForbidden();
    }

    /**
     * The fail-open hole. `if ($secret && ! verify())` meant an installation
     * with nothing configured accepted anything at all.
     */
    public function test_with_no_public_key_configured_the_endpoint_refuses_rather_than_trusts(): void
    {
        AppSetting::set('chipin_webhook_public_key', null);
        config(['chipin.webhook_public_key' => null]);

        $response = $this->call(
            'POST', route('webhooks.chipin'), [], [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($this->paidPayload())
        );

        $response->assertStatus(503);
        $this->assertSame(Payment::STATUS_PENDING, $this->payment->refresh()->status);
        $this->assertSame(0, Invoice::count());
    }

    // ── Money ──────────────────────────────────────────────────────────────

    public function test_a_short_payment_does_not_buy_a_subscription(): void
    {
        // RM 1.00 against an RM 129.00 payment.
        $this->postCallback($this->paidPayload(cents: 100))->assertStatus(422);

        $this->assertSame(Payment::STATUS_PENDING, $this->payment->refresh()->status);
        $this->assertSame(Subscription::STATUS_TRIALING, $this->subscription->refresh()->status);
        $this->assertSame(0, Invoice::count());
    }

    public function test_a_payment_in_the_wrong_currency_is_refused(): void
    {
        $payload = array_merge($this->paidPayload(), ['currency' => 'USD']);

        $this->postCallback($payload)->assertStatus(422);

        $this->assertSame(Payment::STATUS_PENDING, $this->payment->refresh()->status);
    }

    // ── Retries and failures ───────────────────────────────────────────────

    public function test_a_repeated_delivery_does_not_renew_twice(): void
    {
        $this->postCallback($this->paidPayload())->assertOk();
        $firstPeriodEnd = $this->subscription->refresh()->current_period_end;

        $this->postCallback($this->paidPayload())->assertOk();

        $this->assertEquals(
            $firstPeriodEnd->timestamp,
            $this->subscription->refresh()->current_period_end->timestamp,
            'A retried callback extended the subscription a second time.'
        );
        $this->assertSame(1, Invoice::count(), 'A retried callback raised a duplicate invoice.');
    }

    public function test_a_failed_purchase_marks_the_payment_failed(): void
    {
        $payload = ['id' => $this->payment->chip_purchase_id, 'status' => 'expired'];

        $this->postCallback($payload, headers: ['HTTP_X_EVENT_TYPE' => 'purchase.expired'])->assertOk();

        $this->assertSame(Payment::STATUS_FAILED, $this->payment->refresh()->status);
        $this->assertSame(Subscription::STATUS_TRIALING, $this->subscription->refresh()->status);
    }

    public function test_an_unknown_purchase_is_a_404_not_a_crash(): void
    {
        $this->postCallback(['id' => 'never-heard-of-it', 'status' => 'paid'])->assertNotFound();
    }

    // ── The key itself ─────────────────────────────────────────────────────

    public function test_a_key_pasted_without_pem_headers_still_works(): void
    {
        $bare = trim(str_replace(
            ['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----'],
            '',
            self::TEST_PUBLIC_KEY
        ));

        AppSetting::set('chipin_webhook_public_key', $bare);

        $this->postCallback($this->paidPayload())->assertOk();
    }

    public function test_a_key_openssl_cannot_read_is_rejected_rather_than_stored(): void
    {
        $this->assertFalse(ChipInService::isUsablePublicKey('not a key at all'));
        $this->assertTrue(ChipInService::isUsablePublicKey(self::TEST_PUBLIC_KEY));
    }

    /**
     * The gateway is asked for the right number of cents.
     *
     * createPurchase() used `(int) ($amount * 100)`, which TRUNCATES a binary
     * float: RM 79.99 becomes 7998 cents and the customer is charged a cent
     * less than their invoice says. It is not a rare edge — 1,145 of the first
     * 20,000 cent values (5.7%) are affected, including 79.99, 129.95 and
     * 299.90.
     */
    public function test_the_gateway_is_asked_for_the_right_number_of_cents(): void
    {
        $this->assertSame(7998, (int) (79.99 * 100), 'The old truncating cast, for the record.');
        $this->assertSame(7999, (int) round(79.99 * 100), 'What the customer is actually being billed.');

        $this->assertStringContainsString(
            '(int) round($amount * 100)',
            file_get_contents(app_path('Services/ChipInService.php')),
            'createPurchase() must round to cents, not truncate.'
        );
    }
}
