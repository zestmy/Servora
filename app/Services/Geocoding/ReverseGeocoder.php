<?php

namespace App\Services\Geocoding;

use App\Models\AppSetting;
use App\Models\GeocodeCache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Turns a coordinate into a street address.
 *
 * TWO PROVIDERS, because the right one depends on whether the company is
 * paying: Nominatim (OpenStreetMap) needs no key and works immediately, and
 * Google needs a key and is better in Malaysia. The provider is a setting, so
 * switching is not a deploy.
 *
 * EVERY LOOKUP GOES THROUGH THE CACHE FIRST. Staff punch from the same doorway
 * every day; without the cache a month of punches is a month of requests for
 * one address. Nominatim's usage policy asks for no more than one request a
 * second and would be within its rights to block us otherwise — the cache is
 * what keeps this courteous, and on Google it is what keeps the bill sane.
 *
 * NOTHING HERE EVER THROWS. It is called from a queued job behind a punch that
 * has already been recorded; a geocoder being down is a missing address, never
 * a lost clock-in.
 */
class ReverseGeocoder
{
    public const PROVIDER_NOMINATIM = 'nominatim';
    public const PROVIDER_GOOGLE    = 'google';

    public const PROVIDERS = [
        self::PROVIDER_NOMINATIM => 'OpenStreetMap / Nominatim (no key, rate limited)',
        self::PROVIDER_GOOGLE    => 'Google Maps (API key required)',
    ];

    /** Seconds to wait on the provider. Short: nothing is waiting on this. */
    private const TIMEOUT = 8;

    public function provider(): string
    {
        $provider = (string) AppSetting::get('geocoding_provider', self::PROVIDER_NOMINATIM);

        return array_key_exists($provider, self::PROVIDERS) ? $provider : self::PROVIDER_NOMINATIM;
    }

    /** Whether the chosen provider has everything it needs to be called. */
    public function isConfigured(): bool
    {
        return $this->provider() === self::PROVIDER_GOOGLE
            ? filled(AppSetting::get('google_maps_api_key'))
            : true;
    }

    /**
     * A street address for a point, or null.
     *
     * @param  bool  $useCache  false only for the settings screen's test button,
     *                          which exists to prove the provider answers.
     */
    public function lookup(?float $latitude, ?float $longitude, bool $useCache = true): ?string
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        $provider = $this->provider();

        if ($useCache) {
            $cached = GeocodeCache::lookup($latitude, $longitude, $provider);
            if ($cached) {
                return $cached->address;
            }
        }

        $address = $this->fetch($provider, $latitude, $longitude);

        if ($useCache) {
            // Cached even when null: "asked, nothing there" stops every later
            // punch at that point asking again.
            GeocodeCache::remember($latitude, $longitude, $provider, $address);
        }

        return $address;
    }

    /** @return array{ok: bool, message: string, address: ?string} */
    public function test(float $latitude, float $longitude): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Google Maps is selected but no API key is set.', 'address' => null];
        }

        try {
            $address = $this->fetch($this->provider(), $latitude, $longitude);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'address' => null];
        }

        return $address
            ? ['ok' => true, 'message' => 'Resolved.', 'address' => $address]
            : ['ok' => false, 'message' => 'The provider answered but returned no address for that point.', 'address' => null];
    }

    private function fetch(string $provider, float $latitude, float $longitude): ?string
    {
        try {
            return $provider === self::PROVIDER_GOOGLE
                ? $this->fromGoogle($latitude, $longitude)
                : $this->fromNominatim($latitude, $longitude);
        } catch (\Throwable $e) {
            // Logged, not thrown: see the class docblock.
            Log::warning('Reverse geocoding failed', [
                'provider' => $provider,
                'error'    => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function fromNominatim(float $latitude, float $longitude): ?string
    {
        // Nominatim's policy requires an identifying User-Agent naming the
        // application and a contact; a generic one gets blocked, and rightly.
        $response = Http::timeout(self::TIMEOUT)
            ->withHeaders([
                'User-Agent' => 'Servora/1.0 (' . (config('app.url') ?: 'https://servora.com.my') . ')',
                'Accept'     => 'application/json',
            ])
            ->get('https://nominatim.openstreetmap.org/reverse', [
                'lat'             => $latitude,
                'lon'             => $longitude,
                'format'          => 'jsonv2',
                'zoom'            => 18,       // building / street level
                'addressdetails'  => 1,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $name = $response->json('display_name');

        return is_string($name) && $name !== '' ? mb_substr($name, 0, 500) : null;
    }

    private function fromGoogle(float $latitude, float $longitude): ?string
    {
        $key = (string) AppSetting::get('google_maps_api_key');
        if ($key === '') {
            return null;
        }

        $response = Http::timeout(self::TIMEOUT)
            ->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'latlng' => $latitude . ',' . $longitude,
                'key'    => $key,
                // Street address first; Google falls back through the list.
                'result_type' => 'street_address|premise|route',
            ]);

        if (! $response->successful()) {
            return null;
        }

        $status = $response->json('status');
        if ($status === 'ZERO_RESULTS') {
            return null;
        }
        if ($status !== 'OK') {
            // A bad key or an exhausted quota is a configuration problem the
            // company can fix, so it is worth naming rather than swallowing.
            Log::warning('Google geocoding returned ' . $status, [
                'error' => $response->json('error_message'),
            ]);

            return null;
        }

        $address = $response->json('results.0.formatted_address');

        return is_string($address) && $address !== '' ? mb_substr($address, 0, 500) : null;
    }
}
