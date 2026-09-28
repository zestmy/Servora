<?php

namespace App\Services\Billing;

use App\Models\BillingCurrency;
use App\Models\Company;
use App\Services\GeoIp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Which currency somebody is priced in.
 *
 * Country → the active currency that lists it → else the rest-of-the-world
 * currency, if the admin has set one → else MYR. Malaysia is always MYR:
 * ringgit is the list price and what CHIP-IN charges.
 *
 * A visitor's country comes from their IP (GeoIp). A company's comes from
 * where it signed up (companies.billing_country), and its currency is locked
 * by its first payment (companies.billing_currency) — travelling, or a VPN,
 * does not reprice an account, and a country cannot be picked for a cheaper
 * price. A system admin may override either, and may preview any country on
 * the marketing pages with ?country=XX.
 */
class CurrencyResolver
{
    public function __construct(private GeoIp $geo, private ExchangeRates $rates) {}

    /** @return \Illuminate\Support\Collection<int, BillingCurrency> */
    public function currencies()
    {
        return Cache::remember('billing:currencies', now()->addMinutes(10),
            fn () => BillingCurrency::active()->orderBy('sort_order')->orderBy('code')->get());
    }

    public static function forget(): void
    {
        Cache::forget('billing:currencies');
    }

    public function forCountry(?string $country): string
    {
        $country = strtoupper((string) $country);
        if ($country === 'MY') {
            return 'MYR';
        }

        $currencies = $this->currencies();

        if ($country !== '' && ($match = $currencies->first(fn (BillingCurrency $c) => $c->covers($country)))) {
            return $match->code;
        }

        // Unknown country: priced as Malaysia, not as the rest of the world —
        // most traffic is Malaysian, and a failed lookup is not evidence
        // somebody is abroad.
        if ($country === '') {
            return 'MYR';
        }

        return $currencies->firstWhere('is_rest_of_world', true)?->code ?? 'MYR';
    }

    public function countryFor(Request $request): ?string
    {
        // A system admin previewing another country's prices and page.
        // ?country=MY returns to being themselves.
        if (Auth::user()?->isSystemRole() && $request->hasSession()) {
            $preview = strtoupper((string) $request->query('country'));
            if (preg_match('/^[A-Z]{2}$/', $preview)) {
                $request->session()->put('mk_preview_country', $preview);
            }
            if ($held = $request->session()->get('mk_preview_country')) {
                return $held;
            }
        }

        return $this->geo->country($request);
    }

    public function forRequest(Request $request): string
    {
        return $this->forCountry($this->countryFor($request));
    }

    public function forCompany(Company $company, ?Request $request = null): string
    {
        if ($company->billing_currency && $this->usable($company->billing_currency)) {
            return $company->billing_currency;
        }

        $country = $company->billing_country ?: ($request ? $this->geo->country($request) : null);

        return $this->forCountry($country);
    }

    /**
     * The price book for a currency. One that cannot be priced right now
     * (converted, and BNM has no usable rate) falls back to MYR rather than
     * showing nothing.
     */
    public function book(string $currency, bool $freshRate = false): PriceBook
    {
        if ($currency === 'MYR') {
            return PriceBook::myr();
        }

        $row  = $this->currencies()->firstWhere('code', $currency);
        $book = new PriceBook($currency, $row, $this->rates->rate($currency, $freshRate));

        return $row && $book->available() ? $book : PriceBook::myr();
    }

    private function usable(string $code): bool
    {
        return $code === 'MYR' || $this->currencies()->contains('code', $code);
    }
}
