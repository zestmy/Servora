<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="alert-danger mb-4">{{ session('error') }}</div>
    @endif

    <x-page-header title="Currencies" eyebrow="Billing"
                   subtitle="Which currency visitors and customers see prices in, by country. Every charge is made in Malaysian ringgit through CHIP-IN, converted at Bank Negara Malaysia's rate at the moment of payment.">
        <x-slot:actions>
            <button type="button" wire:click="openCreate" class="btn-primary">+ Add currency</button>
        </x-slot:actions>
    </x-page-header>

    {{-- How countries resolve --}}
    <div class="card mb-6 p-5">
        <div class="grid gap-4 text-sm md:grid-cols-3">
            <div>
                <p class="font-semibold text-gray-900">Malaysia</p>
                <p class="mt-1 text-gray-600">Always MYR at the list price (Admin › Plans and the module catalogue).</p>
            </div>
            <div>
                <p class="font-semibold text-gray-900">Listed countries</p>
                <p class="mt-1 text-gray-600">The currency below whose country list includes the visitor's country.</p>
            </div>
            <div>
                <p class="font-semibold text-gray-900">Everywhere else</p>
                <p class="mt-1 text-gray-600">
                    @php $row = $currencies->firstWhere('is_rest_of_world', true); @endphp
                    @if ($row)
                        <span class="font-semibold text-gray-900">{{ $row->code }}</span>, the rest-of-the-world currency.
                    @else
                        MYR — no rest-of-the-world currency is set.
                    @endif
                    A visitor whose country cannot be detected sees MYR.
                </p>
            </div>
        </div>
    </div>

    {{-- Currencies --}}
    <div class="card mb-6 overflow-hidden">
        <table class="table-surface min-w-full">
            <thead>
                <tr>
                    <th class="px-5 py-3 text-left">Currency</th>
                    <th class="px-5 py-3 text-left">Countries</th>
                    <th class="px-5 py-3 text-left">Pricing</th>
                    <th class="px-5 py-3 text-right">Basic / outlet</th>
                    <th class="px-5 py-3 text-right">Full / outlet</th>
                    <th class="px-5 py-3 text-right">BNM rate</th>
                    <th class="px-5 py-3 text-center">Status</th>
                    <th class="px-5 py-3 text-center w-40">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr class="bg-gray-50/60">
                    <td class="px-5 py-3"><span class="font-mono font-bold text-gray-900">MYR</span> <span class="text-xs text-gray-600">Malaysian ringgit · base</span></td>
                    <td class="px-5 py-3 text-gray-700">MY</td>
                    <td class="px-5 py-3 text-gray-600">List price</td>
                    <td class="px-5 py-3 text-right tabular-nums">RM{{ number_format($myr['suite']['basic']['myr'], 0) }}</td>
                    <td class="px-5 py-3 text-right tabular-nums">RM{{ number_format($myr['suite']['full']['myr'], 0) }}</td>
                    <td class="px-5 py-3 text-right text-gray-500">1</td>
                    <td class="px-5 py-3 text-center"><span class="badge-success">Always on</span></td>
                    <td></td>
                </tr>
                @forelse ($currencies as $c)
                    @php $s = $samples[$c->id]; $rate = $available[$c->code] ?? null; @endphp
                    <tr wire:key="cur-{{ $c->id }}" class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <span class="font-mono font-bold text-gray-900">{{ $c->code }}</span>
                            <span class="text-xs text-gray-600">{{ $c->name }}</span>
                            @if ($c->is_rest_of_world)
                                <span class="badge-info ml-1">Rest of world</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-gray-700">
                            {{ collect($c->countries)->take(8)->implode(', ') ?: '—' }}{{ count((array) $c->countries) > 8 ? ' +'.(count($c->countries) - 8) : '' }}
                        </td>
                        <td class="px-5 py-3 text-gray-600">
                            {{ $c->pricing_mode === 'fixed' ? 'Fixed' : 'Converted' }}
                            <span class="text-xs text-gray-500">· rounds to {{ rtrim(rtrim(number_format($c->rounding, 2), '0'), '.') }}</span>
                        </td>
                        <td class="px-5 py-3 text-right tabular-nums">{{ $s['basic'] !== null ? $s['book']->format($s['basic']) : '—' }}</td>
                        <td class="px-5 py-3 text-right tabular-nums">{{ $s['full'] !== null ? $s['book']->format($s['full']) : '—' }}</td>
                        <td class="px-5 py-3 text-right text-xs tabular-nums text-gray-600">
                            @if ($rate)
                                RM{{ number_format($rate->{$rateType} ?? $rate->middle_rate, 4) }} / {{ $rate->unit }}
                                <span class="block text-gray-500">{{ $rate->rate_date->format('d M') }}</span>
                            @else
                                <span class="text-danger-600">No rate</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-center">
                            @if (! $c->is_active)
                                <span class="badge-neutral">Off</span>
                            @elseif (! $s['ok'])
                                <span class="badge-warning" title="No usable BNM rate: these countries see MYR until one arrives">No rate · MYR</span>
                            @else
                                <span class="badge-success">Live</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-center text-xs">
                            <button wire:click="toggleActive({{ $c->id }})" class="mr-1 text-gray-600 hover:text-gray-900">{{ $c->is_active ? 'Disable' : 'Enable' }}</button>
                            <button wire:click="openEdit({{ $c->id }})" class="mr-1 font-medium text-brand-600 hover:text-brand-800">Edit</button>
                            <button wire:click="delete({{ $c->id }})" data-confirm-delete="Remove {{ $c->code }}? Its countries fall back to the rest-of-the-world currency. Companies already paying in it keep it until changed below."
                                    class="text-danger-600 hover:text-danger-700">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-8 text-center text-gray-600">No other currencies yet. Everyone is priced in MYR.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Rates and detection --}}
        <div class="card p-5">
            <h3 class="text-base font-semibold text-gray-900">Exchange rates and detection</h3>
            <p class="mt-1 text-sm text-gray-600">
                Rates from <a href="https://apikijangportal.bnm.gov.my/" target="_blank" rel="noopener" class="text-brand-700 underline">Bank Negara Malaysia's API</a>,
                refreshed hourly and again at every checkout.
                Last fetched: <span class="font-medium text-gray-900">{{ $lastFetched ? \Carbon\Carbon::parse($lastFetched)->diffForHumans() : 'never' }}</span>.
            </p>
            <button type="button" wire:click="refreshRates" wire:loading.attr="disabled" class="btn-secondary btn-sm mt-3">
                <span wire:loading.remove wire:target="refreshRates">Refresh rates now</span>
                <span wire:loading wire:target="refreshRates">Fetching…</span>
            </button>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Rate used for checkout</label>
                    <select wire:model="fx_rate_type" class="input mt-1">
                        @foreach (\App\Services\Billing\ExchangeRates::TYPES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="help">Middle is the neutral rate. Selling protects you when a currency is falling.</p>
                </div>
                <div>
                    <label class="label">Country detection</label>
                    <select wire:model.live="geoip_provider" class="input mt-1">
                        @foreach (\App\Services\GeoIp::PROVIDERS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="help">A Cloudflare CF-IPCountry header is used first when present.</p>
                </div>
                @if ($geoip_provider === 'ipinfo')
                    <div class="sm:col-span-2">
                        <label class="label">ipinfo.io token</label>
                        <input type="text" wire:model="geoip_token" class="input mt-1 font-mono" autocomplete="off">
                    </div>
                @endif
            </div>
            <button type="button" wire:click="saveSettings" class="btn-primary btn-sm mt-4">Save settings</button>

            <p class="mt-5 border-t border-gray-100 pt-4 text-xs text-gray-600">
                Preview as another country: open
                <a href="{{ route('pricing', ['country' => 'SG']) }}" target="_blank" class="font-mono text-brand-700 underline">/pricing?country=SG</a>
                (any two-letter code; system admins only). <span class="font-mono">?country=MY</span> switches back.
            </p>
        </div>

        {{-- Company override --}}
        <div class="card p-5">
            <h3 class="text-base font-semibold text-gray-900">A company's billing currency</h3>
            <p class="mt-1 text-sm text-gray-600">
                A company is priced by the country it signed up from, and its first payment locks the currency.
                Change either here — it applies from the next checkout or renewal.
            </p>

            <div class="relative mt-4">
                <label class="label">Company</label>
                <input type="text" wire:model.live.debounce.300ms="companySearch" wire:keydown="$set('overrideCompanyId', null)"
                       placeholder="Search by name…" class="input mt-1">
                @if ($matches->isNotEmpty())
                    <ul class="absolute z-20 mt-1 w-full overflow-hidden rounded-control border border-gray-200 bg-white shadow-e3">
                        @foreach ($matches as $m)
                            <li>
                                <button type="button" wire:click="pickCompany({{ $m->id }})" class="flex w-full justify-between px-3 py-2 text-left text-sm hover:bg-gray-50">
                                    <span>{{ $m->name }}</span>
                                    <span class="text-xs text-gray-500">{{ $m->billing_country ?: '—' }} · {{ $m->billing_currency ?: 'auto' }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @if ($overrideCompanyId)
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Billing country</label>
                        <input type="text" wire:model="overrideCountry" maxlength="2" placeholder="e.g. SG" class="input mt-1 uppercase">
                        <x-input-error :messages="$errors->get('overrideCountry')" class="mt-1" />
                    </div>
                    <div>
                        <label class="label">Billing currency</label>
                        <select wire:model="overrideCurrency" class="input mt-1">
                            <option value="">Automatic, from the country</option>
                            <option value="MYR">MYR</option>
                            @foreach ($currencies as $c)
                                <option value="{{ $c->code }}">{{ $c->code }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <button type="button" wire:click="saveOverride" class="btn-primary btn-sm mt-4">Save for this company</button>
            @endif

            @if ($overridden->isNotEmpty())
                <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-500">Companies priced outside MYR</p>
                <ul class="mt-2 divide-y divide-gray-100 text-sm">
                    @foreach ($overridden as $o)
                        <li class="flex justify-between py-2">
                            <button type="button" wire:click="pickCompany({{ $o->id }})" class="text-left text-gray-900 hover:text-brand-700">{{ $o->name }}</button>
                            <span class="font-mono text-xs text-gray-600">{{ $o->billing_country ?: '—' }} · {{ $o->billing_currency }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- Modal --}}
    @if ($showModal)
    @teleport('body')
    <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4">
        <div class="absolute inset-0 bg-gray-900/50" wire:click="closeModal"></div>
        <div class="relative z-10 my-8 w-full max-w-2xl rounded-panel bg-white p-6 shadow-e4">
            <h3 class="text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit '.$code : 'Add a currency' }}</h3>

            <div class="mt-5 space-y-4">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="label">Code *</label>
                        <select wire:model.live="code" class="input mt-1 font-mono" @disabled($editingId)>
                            <option value="">Pick…</option>
                            @foreach ($available as $cc => $r)
                                @continue($cc === 'MYR' || $cc === 'SDR')
                                <option value="{{ $cc }}">{{ $cc }}</option>
                            @endforeach
                        </select>
                        @if ($available->isEmpty())
                            <p class="help text-warning-700">No rates yet — close this and press "Refresh rates now".</p>
                        @endif
                        <x-input-error :messages="$errors->get('code')" class="mt-1" />
                    </div>
                    <div>
                        <label class="label">Name *</label>
                        <input type="text" wire:model="name" class="input mt-1" placeholder="Singapore dollar">
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>
                    <div>
                        <label class="label">Symbol</label>
                        <input type="text" wire:model="symbol" class="input mt-1" placeholder="S$">
                    </div>
                </div>

                @if ($formRate)
                    <p class="rounded-control bg-gray-50 px-3 py-2 text-xs text-gray-600">
                        Today: 1 {{ $code }} = RM{{ number_format($formRate->myrPerUnit, 6) }}
                        ({{ \App\Services\Billing\ExchangeRates::TYPES[$formRate->type] ?? 'Middle' }} rate, {{ $formRate->date->format('d M Y') }})
                    </p>
                @endif

                <div>
                    <label class="label">Countries</label>
                    <textarea wire:model="countries" rows="2" class="input mt-1 font-mono uppercase" placeholder="SG, BN"></textarea>
                    <p class="help">Two-letter ISO codes, comma separated. Leave empty for a rest-of-the-world-only currency.</p>
                    <x-input-error :messages="$errors->get('countries')" class="mt-1" />
                </div>

                <label class="flex items-start gap-2.5">
                    <input type="checkbox" wire:model="is_rest_of_world" class="mt-0.5 rounded border-gray-300 text-brand-600">
                    <span class="text-sm text-gray-700">
                        <span class="font-medium text-gray-900">Rest of the world.</span>
                        Every country not listed under any currency is priced in this one. Only one currency can be this.
                    </span>
                </label>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Pricing</label>
                        <div class="mt-1 space-y-2">
                            <label class="flex items-start gap-2 text-sm">
                                <input type="radio" wire:model.live="pricing_mode" value="converted" class="mt-0.5 border-gray-300 text-brand-600">
                                <span><span class="font-medium text-gray-900">Converted</span> — the MYR list at today's rate, rounded up</span>
                            </label>
                            <label class="flex items-start gap-2 text-sm">
                                <input type="radio" wire:model.live="pricing_mode" value="fixed" class="mt-0.5 border-gray-300 text-brand-600">
                                <span><span class="font-medium text-gray-900">Fixed</span> — your own price list; a blank item converts</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="label">Round up to</label>
                        <input type="number" step="0.01" min="0.01" wire:model="rounding" class="input mt-1">
                        <p class="help">1 for whole units; 1000 suits IDR, 10 suits THB.</p>
                        <x-input-error :messages="$errors->get('rounding')" class="mt-1" />
                    </div>
                </div>

                @if ($pricing_mode === 'fixed')
                    <div class="rounded-surface border border-gray-200 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-gray-900">Price list in {{ $code ?: '…' }} <span class="font-normal text-gray-500">(per month)</span></p>
                            <button type="button" wire:click="fillFromRate" class="btn-ghost btn-sm" @disabled(! $code)>Fill from today's rate</button>
                        </div>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            @foreach ($myr as $group => $items)
                                @foreach ($items as $key => $item)
                                    <div wire:key="price-{{ $group }}-{{ $key }}">
                                        <label class="text-xs font-medium text-gray-700">{{ $item['label'] }}</label>
                                        <div class="mt-1 flex items-center gap-2">
                                            <input type="number" step="0.01" min="0" wire:model="prices.{{ $group }}.{{ $key }}" class="input py-1.5 text-sm tabular-nums" placeholder="converts">
                                            <span class="w-20 flex-none text-right text-[11px] text-gray-500">RM{{ number_format($item['myr'], 0) }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-brand-600"> Active
                    </label>
                    <div class="flex items-center gap-2">
                        <label class="text-sm text-gray-700">Order</label>
                        <input type="number" wire:model="sort_order" class="input w-24 py-1.5">
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" wire:click="closeModal" class="btn-secondary">Cancel</button>
                <button type="button" wire:click="save" class="btn-primary">Save</button>
            </div>
        </div>
    </div>
    @endteleport
    @endif
</div>
