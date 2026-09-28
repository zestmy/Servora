<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The visitor's country (ISO 3166 alpha-2), from their IP address.
 *
 * In order: a CF-IPCountry header when the site sits behind Cloudflare (free
 * and exact), then a lookup against the provider chosen in Admin › Currencies
 * — ipapi.co needs no key; ipinfo.io takes a token for higher volume. Each IP
 * is cached for a week, a failure for an hour, so a visitor costs at most one
 * lookup and a provider outage costs nothing but the fallback.
 *
 * Null means "don't know": a private address (local dev, tests), a timeout,
 * or the provider switched off. Callers fall back to Malaysia.
 */
class GeoIp
{
    public const PROVIDERS = ['ipapi' => 'ipapi.co (no key)', 'ipinfo' => 'ipinfo.io (token)', 'none' => 'Off — everyone is priced as Malaysia'];

    public function country(Request $request): ?string
    {
        $header = strtoupper((string) $request->header('CF-IPCountry'));
        if (preg_match('/^[A-Z]{2}$/', $header) && ! in_array($header, ['XX', 'T1'], true)) {
            return $header;
        }

        return $this->lookup((string) $request->ip());
    }

    public function lookup(string $ip): ?string
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        $provider = (string) AppSetting::get('geoip_provider', 'ipapi');
        if ($provider === 'none') {
            return null;
        }

        $key = "geoip:{$provider}:{$ip}";
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached === '' ? null : $cached;
        }

        $country = $this->ask($provider, $ip);
        Cache::put($key, $country ?? '', $country ? now()->addWeek() : now()->addHour());

        return $country;
    }

    private function ask(string $provider, string $ip): ?string
    {
        try {
            $response = match ($provider) {
                'ipinfo' => Http::connectTimeout(2)->timeout(3)
                    ->get("https://ipinfo.io/{$ip}/country", array_filter(['token' => AppSetting::get('geoip_token')])),
                default  => Http::connectTimeout(2)->timeout(3)
                    ->withHeaders(['User-Agent' => 'Servora/1.0'])
                    ->get("https://ipapi.co/{$ip}/country/"),
            };
        } catch (\Throwable $e) {
            Log::info('GeoIP lookup failed', ['provider' => $provider, 'error' => $e->getMessage()]);
            return null;
        }

        $code = strtoupper(trim($response->body()));

        return $response->successful() && preg_match('/^[A-Z]{2}$/', $code) ? $code : null;
    }
}
