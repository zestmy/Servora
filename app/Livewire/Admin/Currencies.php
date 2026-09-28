<?php

namespace App\Livewire\Admin;

use App\Models\AppSetting;
use App\Models\BillingCurrency;
use App\Models\Company;
use App\Models\ExchangeRate;
use App\Models\Plan;
use App\Services\Billing\CurrencyResolver;
use App\Services\Billing\ExchangeRates;
use App\Services\Billing\PriceBook;
use App\Services\GeoIp;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Admin › Currencies: which currencies subscriptions are shown and quoted in,
 * which countries get each, and at what price.
 *
 * MYR is the base and has no row: Malaysia always pays the list price, and
 * every charge is made in MYR because that is all CHIP-IN takes. Any other
 * currency must be one Bank Negara publishes a rate for, since that rate is
 * how its price becomes the ringgit actually charged.
 *
 * A country no currency lists pays the rest-of-the-world currency, or MYR
 * when none is set.
 */
class Currencies extends Component
{
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $code = '';
    public string $name = '';
    public string $symbol = '';
    public string $countries = '';
    public bool $is_rest_of_world = false;
    public string $pricing_mode = BillingCurrency::MODE_CONVERTED;
    public string $rounding = '1';
    public bool $is_active = true;
    public int $sort_order = 0;

    /** Fixed prices: ['suite' => [slug => price], 'module' => [key => price]] as strings. */
    public array $prices = ['suite' => [], 'module' => []];

    // Platform settings
    public string $fx_rate_type = 'middle_rate';
    public string $geoip_provider = 'ipapi';
    public string $geoip_token = '';

    // Company override
    public string $companySearch = '';
    public ?int $overrideCompanyId = null;
    public string $overrideCountry = '';
    public string $overrideCurrency = '';

    public function mount(): void
    {
        $this->fx_rate_type   = app(ExchangeRates::class)->type();
        $this->geoip_provider = (string) AppSetting::get('geoip_provider', 'ipapi');
        $this->geoip_token    = (string) AppSetting::get('geoip_token', '');
    }

    protected function rules(): array
    {
        return [
            'code'             => ['required', 'string', 'size:3', 'not_in:MYR',
                Rule::unique('billing_currencies', 'code')->ignore($this->editingId),
                Rule::exists('exchange_rates', 'currency')],
            'name'             => 'required|string|max:60',
            'symbol'           => 'nullable|string|max:8',
            'countries'        => 'nullable|string|max:2000',
            'pricing_mode'     => 'required|in:converted,fixed',
            'rounding'         => 'required|numeric|min:0.01|max:100000',
            'sort_order'       => 'integer|min:0|max:999',
            'prices.suite.*'   => 'nullable|numeric|min:0',
            'prices.module.*'  => 'nullable|numeric|min:0',
        ];
    }

    protected function messages(): array
    {
        return ['code.exists' => 'Bank Negara does not publish a rate for this currency, so it cannot be converted to MYR at checkout. Refresh rates, or pick another.'];
    }

    // ── Currencies ─────────────────────────────────────────────────────────

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $c = BillingCurrency::findOrFail($id);
        $this->resetForm();
        $this->editingId        = $c->id;
        $this->code             = $c->code;
        $this->name             = $c->name;
        $this->symbol           = (string) $c->symbol;
        $this->countries        = implode(', ', (array) $c->countries);
        $this->is_rest_of_world = $c->is_rest_of_world;
        $this->pricing_mode     = $c->pricing_mode;
        $this->rounding         = rtrim(rtrim(number_format($c->rounding, 2, '.', ''), '0'), '.');
        $this->is_active        = $c->is_active;
        $this->sort_order       = (int) $c->sort_order;
        foreach (['suite', 'module'] as $group) {
            foreach ((array) ($c->prices[$group] ?? []) as $key => $value) {
                $this->prices[$group][$key] = (string) $value;
            }
        }
        $this->showModal = true;
    }

    public function updatedCode(string $value): void
    {
        $this->code = strtoupper(trim($value));
        if (! $this->name && ($known = self::KNOWN[$this->code] ?? null)) {
            [$this->name, $this->symbol] = $known;
        }
    }

    /** Fill every fixed price with today's converted price, as a starting point. */
    public function fillFromRate(): void
    {
        $fx = app(ExchangeRates::class)->rate(strtoupper($this->code), fresh: true);
        if (! $fx) {
            $this->addError('code', 'No Bank Negara rate for this currency yet.');
            return;
        }

        $book = new PriceBook(strtoupper($this->code), new BillingCurrency([
            'pricing_mode' => BillingCurrency::MODE_CONVERTED,
            'rounding'     => (float) $this->rounding ?: 1,
        ]), $fx);

        foreach ($this->myrList() as $group => $items) {
            foreach ($items as $key => $item) {
                $this->prices[$group][$key] = (string) ($group === 'suite'
                    ? $book->suite($key, $item['myr']) : $book->module($key, $item['myr']));
            }
        }
    }

    public function save(): void
    {
        $this->code = strtoupper(trim($this->code));
        $this->validate();

        $countries = $this->parseCountries();
        if ($countries === null) {
            return;
        }

        $prices = [];
        foreach (['suite', 'module'] as $group) {
            foreach ((array) ($this->prices[$group] ?? []) as $key => $value) {
                if (is_numeric($value)) {
                    $prices[$group][$key] = round((float) $value, 2);
                }
            }
        }

        if ($this->is_rest_of_world) {
            BillingCurrency::where('id', '!=', $this->editingId)->update(['is_rest_of_world' => false]);
        }

        BillingCurrency::updateOrCreate(['id' => $this->editingId], [
            'code'             => $this->code,
            'name'             => $this->name,
            'symbol'           => $this->symbol ?: null,
            'countries'        => $countries,
            'is_rest_of_world' => $this->is_rest_of_world,
            'pricing_mode'     => $this->pricing_mode,
            'prices'           => $prices ?: null,
            'rounding'         => (float) $this->rounding,
            'is_active'        => $this->is_active,
            'sort_order'       => $this->sort_order,
        ]);

        CurrencyResolver::forget();
        $this->showModal = false;
        session()->flash('success', "{$this->code} saved.");
    }

    public function toggleActive(int $id): void
    {
        $c = BillingCurrency::findOrFail($id);
        $c->update(['is_active' => ! $c->is_active]);
        CurrencyResolver::forget();
    }

    public function delete(int $id): void
    {
        BillingCurrency::whereKey($id)->delete();
        CurrencyResolver::forget();
        session()->flash('success', 'Currency removed. Its countries are now priced in the rest-of-the-world currency.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    // ── Platform settings ──────────────────────────────────────────────────

    public function refreshRates(): void
    {
        $count = app(ExchangeRates::class)->refresh();
        CurrencyResolver::forget();
        $count > 0
            ? session()->flash('success', "Fetched {$count} rates from Bank Negara Malaysia.")
            : session()->flash('error', 'Bank Negara could not be reached. The last good rates are still in use.');
    }

    public function saveSettings(): void
    {
        $this->validate([
            'fx_rate_type'   => ['required', Rule::in(array_keys(ExchangeRates::TYPES))],
            'geoip_provider' => ['required', Rule::in(array_keys(GeoIp::PROVIDERS))],
            'geoip_token'    => 'nullable|string|max:200',
        ]);

        AppSetting::set('billing_fx_rate_type', $this->fx_rate_type);
        AppSetting::set('geoip_provider', $this->geoip_provider);
        AppSetting::set('geoip_token', $this->geoip_token);

        session()->flash('success', 'Settings saved.');
    }

    // ── Company override ───────────────────────────────────────────────────

    public function pickCompany(int $id): void
    {
        $company = Company::findOrFail($id);
        $this->overrideCompanyId = $company->id;
        $this->overrideCountry   = (string) $company->billing_country;
        $this->overrideCurrency  = (string) $company->billing_currency;
        $this->companySearch     = $company->name;
    }

    public function saveOverride(): void
    {
        $this->validate([
            'overrideCompanyId' => 'required|exists:companies,id',
            'overrideCountry'   => ['nullable', 'regex:/^[A-Za-z]{2}$/'],
            'overrideCurrency'  => ['nullable', Rule::in(array_merge(['MYR'], BillingCurrency::pluck('code')->all()))],
        ]);

        Company::whereKey($this->overrideCompanyId)->update([
            'billing_country'  => $this->overrideCountry ? strtoupper($this->overrideCountry) : null,
            'billing_currency' => $this->overrideCurrency ?: null,
        ]);

        session()->flash('success', 'Company billing currency updated. It applies from their next checkout or renewal.');
        $this->reset('overrideCompanyId', 'overrideCountry', 'overrideCurrency', 'companySearch');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /** @return array<string, array<string, array{label: string, myr: float}>> */
    private function myrList(): array
    {
        $plans = Plan::whereIn('slug', ['basic', 'full'])->pluck('price_monthly', 'slug');
        $suites = config('modules.suite_prices');

        $list = ['suite' => [], 'module' => []];
        foreach (['basic' => 'Basic / outlet', 'full' => 'Full / outlet'] as $slug => $label) {
            $list['suite'][$slug] = ['label' => $label, 'myr' => (float) ($plans[$slug] ?? $suites[$slug] ?? 0)];
        }
        foreach ((array) config('modules.catalogue') as $key => $m) {
            if (in_array($m['kind'], ['addon', 'metered'], true)) {
                $list['module'][$key] = [
                    'label' => $m['name'].($m['kind'] === 'metered' ? " / {$m['unit']}" : ''),
                    'myr'   => (float) $m['price'],
                ];
            }
        }

        return $list;
    }

    /** @return array<int, string>|null null after adding an error */
    private function parseCountries(): ?array
    {
        $codes = collect(preg_split('/[\s,;]+/', strtoupper($this->countries)))->filter()->unique()->values();

        if ($bad = $codes->reject(fn ($c) => preg_match('/^[A-Z]{2}$/', $c))->first()) {
            $this->addError('countries', "\"{$bad}\" is not a two-letter country code (ISO 3166, e.g. SG, ID, TH).");
            return null;
        }
        if ($codes->contains('MY')) {
            $this->addError('countries', 'Malaysia is always priced in MYR.');
            return null;
        }

        $taken = BillingCurrency::where('id', '!=', $this->editingId)->get()
            ->flatMap(fn ($c) => collect((array) $c->countries)->mapWithKeys(fn ($cc) => [$cc => $c->code]));
        if ($clash = $codes->first(fn ($c) => $taken->has($c))) {
            $this->addError('countries', "{$clash} is already priced in {$taken[$clash]}. A country can have one currency.");
            return null;
        }

        return $codes->all();
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'code', 'name', 'symbol', 'countries', 'is_rest_of_world', 'sort_order');
        $this->pricing_mode = BillingCurrency::MODE_CONVERTED;
        $this->rounding = '1';
        $this->is_active = true;
        $this->prices = ['suite' => [], 'module' => []];
        $this->resetErrorBag();
    }

    /** Names and symbols for the currencies BNM publishes, so the form fills itself. */
    public const KNOWN = [
        'USD' => ['US dollar', 'US$'], 'SGD' => ['Singapore dollar', 'S$'], 'IDR' => ['Indonesian rupiah', 'Rp'],
        'THB' => ['Thai baht', '฿'], 'PHP' => ['Philippine peso', '₱'], 'VND' => ['Vietnamese dong', '₫'],
        'BND' => ['Brunei dollar', 'B$'], 'KHR' => ['Cambodian riel', '៛'], 'MMK' => ['Myanmar kyat', 'K'],
        'EUR' => ['Euro', '€'], 'GBP' => ['Pound sterling', '£'], 'AUD' => ['Australian dollar', 'A$'],
        'NZD' => ['New Zealand dollar', 'NZ$'], 'CAD' => ['Canadian dollar', 'C$'], 'CHF' => ['Swiss franc', 'CHF '],
        'JPY' => ['Japanese yen', '¥'], 'CNY' => ['Chinese yuan', 'CN¥'], 'HKD' => ['Hong Kong dollar', 'HK$'],
        'TWD' => ['New Taiwan dollar', 'NT$'], 'KRW' => ['South Korean won', '₩'], 'INR' => ['Indian rupee', '₹'],
        'PKR' => ['Pakistani rupee', 'Rs '], 'SAR' => ['Saudi riyal', 'SAR '], 'AED' => ['UAE dirham', 'AED '],
        'EGP' => ['Egyptian pound', 'E£'],
    ];

    public function render()
    {
        $rates = app(ExchangeRates::class);
        $currencies = BillingCurrency::orderBy('sort_order')->orderBy('code')->get();

        // What a Basic and a Full outlet cost in each currency today.
        $myr = $this->myrList();
        $samples = $currencies->mapWithKeys(function (BillingCurrency $c) use ($rates, $myr) {
            $book = new PriceBook($c->code, $c, $rates->rate($c->code));
            return [$c->id => [
                'basic' => $book->suite('basic', $myr['suite']['basic']['myr']),
                'full'  => $book->suite('full', $myr['suite']['full']['myr']),
                'book'  => $book,
                'ok'    => $book->available(),
            ]];
        });

        $matches = strlen($this->companySearch) >= 2 && ! $this->overrideCompanyId
            ? Company::where('name', 'like', '%'.$this->companySearch.'%')->limit(6)->get(['id', 'name', 'billing_country', 'billing_currency'])
            : collect();

        return view('livewire.admin.currencies', [
            'currencies'  => $currencies,
            'samples'     => $samples,
            'available'   => ExchangeRate::orderBy('currency')->get()->keyBy('currency'),
            'lastFetched' => ExchangeRate::max('fetched_at'),
            'myr'         => $myr,
            'rateType'    => $rates->type(),
            'matches'     => $matches,
            'overridden'  => Company::whereNotNull('billing_currency')->where('billing_currency', '!=', 'MYR')
                ->orderBy('name')->limit(50)->get(['id', 'name', 'billing_country', 'billing_currency']),
            'formRate'    => strlen($this->code) === 3 ? $rates->rate(strtoupper($this->code)) : null,
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Currencies']);
    }
}
