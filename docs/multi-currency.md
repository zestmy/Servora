# Multi-currency pricing and country landing pages

Built 2026-09-29. Subscriptions are shown and quoted in the visitor's own
currency and always **charged in MYR**, because CHIP-IN takes nothing else.

## Who is priced in what

`App\Services\Billing\CurrencyResolver`

| Situation | Currency |
|---|---|
| Malaysia | MYR, always — the list price |
| A country listed on an active currency (Admin › Currencies) | that currency |
| Any other known country | the rest-of-the-world currency, or MYR if none is set |
| Country could not be detected | MYR (a failed lookup is not evidence someone is abroad) |
| A company | `companies.billing_currency` once its first payment locks it; before that, the country it signed up from (`companies.billing_country`, captured at sign-up) |

A system admin can override a company's country or currency on Admin › Currencies,
and can preview any country's prices on the marketing pages with `?country=XX`
(`?country=MY` switches back).

Country detection (`App\Services\GeoIp`): a Cloudflare `CF-IPCountry` header if
present, otherwise ipapi.co (no key) or ipinfo.io (token), chosen on
Admin › Currencies. Cached per IP for a week, failures for an hour.

## Prices

`App\Services\Billing\PriceBook` — one currency's prices for the suites and every
catalogue module.

- **Converted**: the MYR list ÷ BNM rate, rounded **up** to the currency's
  rounding step (1 for SGD/USD, 1000 suits IDR). Amounts under ten steps round
  to a tenth of a step, so RM3 per employee is not rounded up by a third.
- **Fixed**: the admin's own price list; a blank item converts.
- A converted currency with no usable rate falls back to MYR rather than showing
  nothing. Volume bands, the Basic add-on cap and yearly = 10 months apply in
  every currency.

## Rates and charging

`App\Services\Billing\ExchangeRates` reads `https://api.bnm.gov.my/public/exchange-rate`.
BNM answers **404 unless `Accept: application/vnd.BNM.API.v1+json`** exactly. Rates
are MYR per `unit` (100 for THB, IDR, JPY…); `Fx::myrPerUnit` is per ONE.

- Stored in `exchange_rates`; refreshed hourly (`billing:refresh-rates`) and
  **fresh at every checkout and renewal**. BNM down → last good rate. A rate
  older than 7 days is never used: checkout refuses instead of guessing.
- Rate type (middle / selling / buying) is a setting on Admin › Currencies, middle by default.
- Checkout (`CheckoutService::start`): quote in the company's currency, subtract
  credit, convert what is due to MYR, send CHIP-IN MYR. `payments.fx` records the
  foreign amount, rate, rate type and date; the invoice line repeats it.
- The subscription keeps the currency and price the customer agreed
  (`subscriptions.currency` / `amount`). Renewal (`billing:process-recurring`)
  re-converts that price at the day's rate.

## Country landing pages

`App\Models\LandingPage`, Admin › Country Pages. The marketing home page in
another language, at `/{slug}` (`/id`, `/th`, `/zh-tw`; route `marketing.landing`,
registered last, and a slug an existing route uses is refused).

- Every visible string on the home page comes from `App\Support\Marketing\HomeCopy`
  through `$t('key')` in the view. A page overrides keys; a blank key shows English.
  **New home-page copy goes into HomeCopy first** — `CountryLandingPageTest` fails
  on a `$t()` key HomeCopy does not have.
- Placeholders (`:days`, `:basic`, `:full`, `:hr`, `:ck`, `:addon`) are filled with
  real figures in the visitor's currency and must survive translation; save refuses
  a translation that drops one.
- "Draft blank boxes with AI" uses the OpenRouter key from Settings › API Keys, one
  batch of 40 strings per request, looped from the browser (prod kills requests at 60 s).
- Auto-redirect sends guests from the page's countries from `/` to it; "View in
  English" (`?lang=en`) sets a year-long cookie that stops it. `hreflang` alternates
  link every published page.
- Language follows the page, money follows the visitor: `/id` opened from Singapore
  shows Indonesian copy with SGD prices, because that is what checkout will charge.
- The product screens drawn on the page stay English — the product is English.

## Defaults chosen without a decision (confirm with Affandy)

- Currency locks at the first payment; only an admin changes it afterwards.
- Unknown country → MYR, not the rest-of-the-world currency.
- Middle rate by default; converted prices round up.
- ipapi.co as the default detector (free tier ~1,000 lookups/day; switch to ipinfo
  with a token, or put the site behind Cloudflare, before traffic grows).
