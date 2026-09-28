<?php

namespace App\Services\Billing;

use App\Models\AppSetting;
use App\Models\ExchangeRate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ringgit exchange rates from Bank Negara Malaysia's public API.
 *
 * CHIP-IN charges in MYR only, so every price quoted in another currency is
 * turned into ringgit here at the moment of payment. BNM publishes MYR per
 * `unit` of each currency (unit is 100 for the small ones: THB, IDR, JPY…)
 * a few times a business day, as a buying, selling and middle rate. The rate
 * type used is a platform setting, middle by default.
 *
 * The last good rate for each currency is kept in exchange_rates, so a BNM
 * outage does not stop checkout. A rate older than STALE_AFTER_DAYS is not
 * used at all — that is a price nobody agreed to.
 */
class ExchangeRates
{
    public const ENDPOINT = 'https://api.bnm.gov.my/public/exchange-rate';

    /** How old a stored rate may be before a read goes back to BNM. */
    public const FRESH_MINUTES = 30;

    /** Long weekends and public holidays: BNM does not publish, so allow a week. */
    public const STALE_AFTER_DAYS = 7;

    public const TYPES = ['middle_rate' => 'Middle', 'selling_rate' => 'Selling', 'buying_rate' => 'Buying'];

    /** Fetch every rate BNM publishes and store it. Returns how many were stored. */
    public function refresh(): int
    {
        try {
            // BNM answers 404 unless Accept names its versioned media type
            // exactly — a plain application/json is refused.
            $response = Http::accept('application/vnd.BNM.API.v1+json')
                ->connectTimeout(5)->timeout(10)
                ->get(self::ENDPOINT);
        } catch (\Throwable $e) {
            Log::warning('BNM exchange rates: request failed', ['error' => $e->getMessage()]);
            return 0;
        }

        if (! $response->successful()) {
            Log::warning('BNM exchange rates: HTTP '.$response->status());
            return 0;
        }

        $stored = 0;
        foreach ((array) $response->json('data', []) as $row) {
            $code = strtoupper((string) ($row['currency_code'] ?? ''));
            $rate = (array) ($row['rate'] ?? []);

            if (strlen($code) !== 3 || empty($rate['date']) || ! is_numeric($rate['middle_rate'] ?? null)) {
                continue;
            }

            ExchangeRate::updateOrCreate(['currency' => $code], [
                'unit'         => max(1, (int) ($row['unit'] ?? 1)),
                'buying_rate'  => is_numeric($rate['buying_rate'] ?? null) ? $rate['buying_rate'] : null,
                'selling_rate' => is_numeric($rate['selling_rate'] ?? null) ? $rate['selling_rate'] : null,
                'middle_rate'  => $rate['middle_rate'],
                'rate_date'    => $rate['date'],
                'fetched_at'   => now(),
            ]);
            $stored++;
        }

        return $stored;
    }

    /**
     * MYR for one unit of $currency, or null when there is no usable rate.
     *
     * $fresh: go to BNM first unless a rate was fetched in the last few
     *   minutes. Checkout passes it — that is the "real-time" read. Display
     *   reads do not, so a marketing page never waits on BNM; the hourly
     *   refresh keeps them current.
     */
    public function rate(string $currency, bool $fresh = false): ?Fx
    {
        $currency = strtoupper($currency);
        if ($currency === 'MYR') {
            return Fx::myr();
        }

        $row = ExchangeRate::firstWhere('currency', $currency);

        $maxAge = $fresh ? 5 : self::FRESH_MINUTES;
        if ((! $row || $row->fetched_at->lt(now()->subMinutes($maxAge))) && $this->mayAttempt($fresh)) {
            $this->refresh();
            $row = ExchangeRate::firstWhere('currency', $currency);
        }

        if (! $row || $row->rate_date->lt(now()->subDays(self::STALE_AFTER_DAYS)->startOfDay())) {
            return null;
        }

        $type  = $this->type();
        $value = $row->{$type} ?? $row->middle_rate;

        return $value > 0 ? new Fx($currency, $value / $row->unit, $row->rate_date, $row->{$type} ? $type : 'middle_rate') : null;
    }

    /** @return Collection<int, ExchangeRate> every currency BNM has published, by code */
    public function all(): Collection
    {
        return ExchangeRate::orderBy('currency')->get();
    }

    public function type(): string
    {
        $type = (string) AppSetting::get('billing_fx_rate_type', 'middle_rate');

        return array_key_exists($type, self::TYPES) ? $type : 'middle_rate';
    }

    /**
     * One trip to BNM per minute for a checkout read, one per five otherwise,
     * however many visitors arrive at once — so an outage is not answered by
     * every request hammering the API and waiting out its timeout.
     */
    private function mayAttempt(bool $fresh): bool
    {
        return $fresh
            ? Cache::add('bnm:fx:attempt:checkout', true, now()->addMinute())
            : Cache::add('bnm:fx:attempt', true, now()->addMinutes(5));
    }
}
