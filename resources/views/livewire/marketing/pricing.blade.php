@php
    // docs/pricing-model.md. Suite prices come from the plans table and add-on
    // prices from config/modules.php, so this page cannot drift from what
    // checkout charges. The calculator below repeats Billing\PriceCalculator's
    // arithmetic in the browser and reads its volume bands, so a change there
    // lands here too; the figure checkout shows is still the one that counts.
    //
    // Every price is in the visitor's currency ($book, Billing\PriceBook):
    // MYR for Malaysia, otherwise what Admin › Currencies sets for their
    // country. Whatever it is, CHIP-IN charges the ringgit equivalent.
    $trialDays = $plans->firstWhere('slug', 'full')?->trial_days ?? 14;
    $catalogue = config('modules.catalogue');
    $addonNames = collect($catalogue)->where('kind', 'addon')->pluck('name');
    $cap = (int) config('modules.addon_cap_on_basic');
    $enterpriseFrom = \App\Services\Billing\PriceCalculator::ENTERPRISE_FROM;

    $bullets = [
        'free'  => [
            '1 outlet, 2 users',
            '150 market list items, 30 recipes',
            'Recipe costing and prep items',
            'Stock counts and daily sales entry',
            'Food-cost reports',
        ],
        'basic' => [
            'Unlimited items, recipes and users',
            'Purchasing: requests, orders, GRN, supplier invoices',
            'Inventory control: transfers, wastage, par levels',
            'Every report, scheduled to your inbox',
            'AI invoice capture — 30 scans per outlet a month',
            'Add up to '.$cap.' add-ons',
        ],
        'full'  => array_merge(['Everything in Basic, plus:'], $addonNames->all()),
    ];

    $addonBlurbs = [
        'labels'          => 'HACCP labels, shelf life, print agent and the staff label app',
        'learn'           => 'Courses, quizzes, live sessions, certificates and the SOP library',
        'audits'          => 'Audit forms, scored audits, corrective actions and schedules',
        'assets'          => 'Register, counts, receipts and disposals',
        'pos_sync'        => 'Sales imported from the POS automatically',
        'ai_insights'     => 'AI analysis, WIP review insights and extra AI allowance',
        'hr'              => 'Roster, clock-in, attendance, leave, OT, payroll, payslips and EA forms',
        'central_kitchen' => 'Production orders and recipes, CK inventory and yield',
    ];
    $addonIcons = [
        'labels' => 'printer', 'learn' => 'academic', 'audits' => 'shield', 'assets' => 'cube',
        'pos_sync' => 'currency', 'ai_insights' => 'sparkles', 'hr' => 'users', 'central_kitchen' => 'clipboard',
    ];

    $suiteMyr = $plans->whereIn('slug', ['basic', 'full'])->mapWithKeys(fn ($p) => [$p->slug => (float) $p->price_monthly]);
    if ($suiteMyr->isEmpty()) {
        $suiteMyr = collect(config('modules.suite_prices'));
    }
    $price = fn (string $key) => $book->module($key, (float) $catalogue[$key]['price']);
    $calc = [
        'suite'  => $suiteMyr->map(fn ($myr, $slug) => $book->suite($slug, (float) $myr)),
        'addons' => collect($catalogue)->filter(fn ($m) => $m['kind'] === 'addon')
            ->map(fn ($m, $k) => ['key' => $k, 'name' => $m['name'], 'price' => $price($k)])->values(),
        'hr'     => array_merge($catalogue['hr'], ['price' => $price('hr')]),
        'ck'     => array_merge($catalogue['central_kitchen'], ['price' => $price('central_kitchen')]),
        'symbol' => $book->symbol(),
        'decimals' => $book->isMyr() || $book->step() >= 1 ? 0 : 2,
        'bands'  => \App\Services\Billing\PriceCalculator::VOLUME_BANDS,
        'cap'    => $cap,
        'enterprise' => $enterpriseFrom,
    ];
@endphp

<div x-data="{ cycle: @entangle('cycle') }">
    {{-- ── 1. Hero ───────────────────────────────────────────────────────── --}}
    <section class="relative overflow-hidden bg-white">
        <div aria-hidden="true" class="mk-grid-bg pointer-events-none absolute inset-0"></div>
        <div aria-hidden="true" class="pointer-events-none absolute left-1/2 top-[-14rem] h-[30rem] w-[56rem] -translate-x-1/2 rounded-full bg-brand-100/50 blur-3xl"></div>

        <div class="relative mx-auto max-w-3xl px-4 pb-14 pt-14 text-center sm:px-6 lg:px-8 lg:pt-20">
            <span class="mk-pill mk-in">
                <x-icon name="sparkles" size="h-4 w-4" />
                {{ $trialDays }}-day trial of everything, then Free for good
            </span>
            <h1 class="display-1 mk-in mt-7 text-gray-950" style="animation-delay:.08s">
                Free to start.<br><span class="mk-accent">Per outlet</span> as you grow.
            </h1>
            <p class="mk-in mx-auto mt-6 max-w-xl text-lg leading-relaxed text-gray-600" style="animation-delay:.16s">
                Every sign-up gets the whole product for {{ $trialDays }} days, then keeps Free for as long as it likes. No card to start.
            </p>

            {{-- Billing cycle. A real radiogroup: keyboard operable, announced as a choice. --}}
            <div class="mk-in mt-9 inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white p-1 shadow-e1"
                 style="animation-delay:.24s" role="radiogroup" aria-label="Billing cycle">
                @foreach ([['monthly', 'Monthly'], ['yearly', 'Yearly']] as [$value, $label])
                    <button type="button" @click="cycle = '{{ $value }}'"
                            role="radio" :aria-checked="cycle === '{{ $value }}'"
                            class="min-h-[40px] rounded-full px-5 text-sm font-semibold transition-colors"
                            :class="cycle === '{{ $value }}' ? 'bg-brand-600 text-white shadow-btn' : 'text-gray-600 hover:text-gray-900'">
                        {{ $label }}
                        @if ($value === 'yearly')
                            <span class="ml-1.5 text-xs font-bold" :class="cycle === 'yearly' ? 'text-brand-50' : 'text-brand-700'">2 months free</span>
                        @endif
                    </button>
                @endforeach
            </div>

            @unless ($book->isMyr())
                <p class="mk-in mx-auto mt-6 flex max-w-xl items-start justify-center gap-2 text-left text-sm text-gray-600" style="animation-delay:.3s">
                    <x-icon name="info" size="h-4 w-4" class="mt-0.5 flex-none text-brand-600" />
                    <span>
                        Prices in {{ $book->name() }} ({{ $book->currency }}){{ $country ? ' for '.\App\Support\Countries::name($country) : '' }}.
                        You pay the equivalent in Malaysian ringgit, at Bank Negara Malaysia's exchange rate on the day.
                    </span>
                </p>
            @endunless
        </div>
    </section>

    {{-- ── 2. Plans ────────────────────────────────────────────────────────
         Equal columns on purpose: a pricing table is one of the few places
         where symmetry aids comparison.
    --}}
    <section class="relative mx-auto max-w-6xl px-4 pb-20 sm:px-6 lg:px-8">
        @if ($plans->isEmpty())
            <div class="empty-state">
                <p class="empty-title">Pricing is being updated</p>
                <p class="empty-body">Plans are not published right now. Start a trial and we will confirm pricing with you directly.</p>
                <a href="{{ route('saas.register') }}" class="btn-primary mt-2">Start free trial</a>
            </div>
        @else
            <div class="grid items-stretch gap-6 md:grid-cols-3">
                @foreach ($plans as $i => $plan)
                    @php
                        $isFull = $plan->slug === 'full';
                        $isFree = (float) $plan->price_monthly === 0.0;
                        $monthly = $isFree ? 0.0 : (float) ($book->suite((string) $plan->slug, (float) $plan->price_monthly) ?? $plan->price_monthly);
                    @endphp

                    <div data-reveal-index="{{ $i }}"
                         @class([
                             'reveal relative flex flex-col rounded-panel p-7 transition-transform duration-300 hover:-translate-y-1',
                             'bg-navy-950 text-white shadow-e4 ring-1 ring-navy-800' => $isFull,
                             'border border-gray-200 bg-white shadow-e1' => ! $isFull,
                         ])>
                        @if ($isFull)
                            <span class="mk-mono absolute -top-3 left-7 rounded-full bg-brand-600 px-3 py-1 text-[10px] font-medium uppercase tracking-wider text-white shadow-btn">
                                Everything
                            </span>
                        @endif

                        <h2 @class(['text-lg font-semibold tracking-tight', 'text-white' => $isFull, 'text-gray-950' => ! $isFull])>{{ $plan->name }}</h2>
                        @if ($plan->description)
                            <p @class(['mt-2 min-h-[2.75rem] text-sm leading-relaxed', 'text-gray-300' => $isFull, 'text-gray-600' => ! $isFull])>{{ $plan->description }}</p>
                        @endif

                        <p class="mt-5 flex items-baseline gap-1.5">
                            {{-- Yearly is ten months' price for twelve. --}}
                            <span @class(['mk-display text-5xl font-semibold tracking-tight tabular-nums', 'text-white' => $isFull, 'text-gray-950' => ! $isFull])
                                  x-text="@js($book->symbol()) + Math.round(cycle === 'yearly' ? {{ $monthly }} * 10 / 12 : {{ $monthly }}).toLocaleString('en-MY')">{{ $book->format($monthly, 0) }}</span>
                            @unless ($isFree)
                                <span @class(['text-sm', 'text-gray-300' => $isFull, 'text-gray-600' => ! $isFull])>/ outlet / month</span>
                            @endunless
                        </p>
                        <p @class(['mt-1 min-h-[1.25rem] text-xs', 'text-gray-300' => $isFull, 'text-gray-600' => ! $isFull])>
                            @if ($isFree)
                                Forever
                            @else
                                <span x-show="cycle === 'yearly'" x-cloak>Billed {{ $book->format($monthly * 10) }} per outlet yearly</span>
                            @endif
                        </p>

                        <a href="{{ route('saas.register') }}"
                           @class(['mt-6 w-full', 'btn-primary' => $isFull, 'btn-secondary' => ! $isFull])>
                            {{ $isFree ? 'Start free' : "Try it free for {$trialDays} days" }}
                        </a>

                        <div @class(['mt-7 flex-1 border-t pt-6', 'border-white/10' => $isFull, 'border-gray-200' => ! $isFull])>
                            <ul @class(['space-y-3 text-sm', 'text-gray-200' => $isFull, 'text-gray-700' => ! $isFull])>
                                @foreach ($bullets[$plan->slug] ?? [] as $line)
                                    <li class="flex items-start gap-2.5">
                                        <x-icon name="check" size="h-5 w-5" stroke="2.2" :class="'mt-px flex-none '.($isFull ? 'text-brand-300' : 'text-brand-600')" />
                                        <span>{{ $line }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endforeach
            </div>

            <p class="mt-8 text-center text-sm text-gray-600">
                More outlets cost less each: 10% off outlets 6–10, 15% off 11–{{ $enterpriseFrom - 1 }}. {{ $enterpriseFrom }} or more? We will quote it.
            </p>
        @endif
    </section>

    {{-- ── 3. Calculator ───────────────────────────────────────────────────
         Billing\PriceCalculator in the browser: suite × outlets, marginal
         volume bands, flat add-ons (none on Full, at most `cap` on Basic),
         metered add-ons at their minimum, yearly = 10 × monthly.
    --}}
    @if ($plans->isNotEmpty())
    <section class="relative overflow-hidden bg-navy-950 py-20 text-white lg:py-28">
        <div aria-hidden="true" class="mk-grid-bg-dark pointer-events-none absolute inset-0"></div>
        <div aria-hidden="true" class="pointer-events-none absolute left-1/2 top-1/4 h-96 w-[48rem] -translate-x-1/2 rounded-full bg-brand-600/20 blur-3xl"></div>

        <div class="relative mx-auto max-w-6xl px-4 sm:px-6 lg:px-8"
             x-data="{
                c: @js($calc),
                suite: 'basic', outlets: 3, picked: [], staff: 0, kitchens: 0,
                get unit() { return Number(this.c.suite[this.suite]) },
                get suiteTotal() { return this.unit * this.outlets },
                get discount() {
                    return this.c.bands.reduce((sum, [from, to, off]) => {
                        const n = Math.max(0, Math.min(this.outlets, to) - from + 1);
                        return sum + this.unit * n * off;
                    }, 0);
                },
                get addonTotal() {
                    if (this.suite === 'full') return 0;
                    return this.c.addons.filter(a => this.picked.includes(a.key)).reduce((s, a) => s + a.price, 0);
                },
                get hrBilled() { return this.staff > 0 ? Math.max(this.staff, this.c.hr.min_quantity) : 0 },
                get hrTotal() { return this.hrBilled * this.c.hr.price },
                get ckTotal() { return this.kitchens * this.c.ck.price },
                get monthly() { return this.suiteTotal - this.discount + this.addonTotal + this.hrTotal + this.ckTotal },
                get shown() { return this.cycle === 'yearly' ? this.monthly * 10 : this.monthly },
                get enterprise() { return this.outlets >= this.c.enterprise },
                toggle(k) {
                    if (this.suite === 'full') return;
                    if (this.picked.includes(k)) { this.picked = this.picked.filter(x => x !== k); return; }
                    if (this.picked.length >= this.c.cap) return;
                    this.picked = [...this.picked, k];
                },
                rm(v) { return this.c.symbol + Number(v).toLocaleString('en-MY', { minimumFractionDigits: this.c.decimals, maximumFractionDigits: this.c.decimals }) },
                fill(v, min, max) { return ((v - min) / (max - min) * 100) + '%' },
             }">
            <div class="mx-auto max-w-3xl text-center">
                <p class="mk-kicker mk-kicker-dark">Price calculator</p>
                <h2 class="display-2 mt-5 text-white">Work out <span class="mk-accent mk-accent-dark">your price</span>.</h2>
                <p class="mx-auto mt-5 max-w-xl text-lg text-gray-300">Outlets, add-ons and staff. The same arithmetic checkout uses.</p>
            </div>

            <div class="mt-12 grid gap-6 lg:grid-cols-5">
                {{-- Inputs --}}
                <div class="space-y-6 rounded-panel border border-white/10 bg-white/[0.04] p-6 backdrop-blur-sm sm:p-8 lg:col-span-3">
                    {{-- Suite --}}
                    <div>
                        <p class="text-sm font-medium text-gray-300">Suite</p>
                        <div class="mt-3 grid grid-cols-2 gap-3" role="radiogroup" aria-label="Suite">
                            @foreach (['basic' => 'Basic', 'full' => 'Full'] as $slug => $name)
                                <button type="button" role="radio" :aria-checked="suite === '{{ $slug }}'" @click="suite = '{{ $slug }}'"
                                        class="min-h-[44px] rounded-surface border px-4 py-3 text-left transition-all"
                                        :class="suite === '{{ $slug }}' ? 'border-brand-400 bg-brand-600/20 ring-1 ring-brand-400' : 'border-white/10 hover:border-white/25'">
                                    <span class="block text-sm font-semibold text-white">{{ $name }}</span>
                                    <span class="block text-xs text-gray-400">{{ $book->format((float) ($calc['suite'][$slug] ?? 0)) }} / outlet / month</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Outlets --}}
                    <div>
                        <div class="flex items-baseline justify-between">
                            <label for="mk-outlets" class="text-sm font-medium text-gray-300">Outlets</label>
                            <span class="mk-display text-3xl font-semibold tabular-nums text-white"><span x-text="outlets"></span><span x-show="enterprise" x-cloak>+</span></span>
                        </div>
                        <input id="mk-outlets" type="range" min="1" max="{{ $enterpriseFrom }}" step="1" x-model.number="outlets"
                               class="mk-range mt-3 bg-white/15" :style="`--mk-fill:${fill(outlets, 1, {{ $enterpriseFrom }})}`">
                        <div class="mk-mono mt-2 flex justify-between text-[11px] text-gray-400">
                            <span>1</span><span>5</span><span>10</span><span>15</span><span>{{ $enterpriseFrom }}+</span>
                        </div>
                    </div>

                    {{-- Add-ons --}}
                    <div>
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="text-sm font-medium text-gray-300">Add-ons <span class="text-gray-400">· per company</span></p>
                            <p class="text-xs text-gray-400" x-show="suite === 'basic'">Pick up to {{ $cap }} <span class="tabular-nums" x-text="'(' + picked.length + '/{{ $cap }})'"></span></p>
                            <p class="text-xs font-semibold text-success-300" x-show="suite === 'full'" x-cloak>All included in Full</p>
                        </div>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            @foreach ($calc['addons'] as $a)
                                <button type="button" @click="toggle('{{ $a['key'] }}')"
                                        :aria-pressed="suite === 'full' || picked.includes('{{ $a['key'] }}')"
                                        :disabled="suite === 'basic' && ! picked.includes('{{ $a['key'] }}') && picked.length >= c.cap"
                                        class="flex min-h-[44px] items-center gap-3 rounded-control border px-3 py-2.5 text-left transition-all disabled:cursor-not-allowed disabled:opacity-40"
                                        :class="(suite === 'full' || picked.includes('{{ $a['key'] }}')) ? 'border-brand-400 bg-brand-600/20' : 'border-white/10 hover:border-white/25'">
                                    <span class="flex h-5 w-5 flex-none items-center justify-center rounded-md border transition-colors"
                                          :class="(suite === 'full' || picked.includes('{{ $a['key'] }}')) ? 'border-brand-400 bg-brand-500 text-white' : 'border-white/25'">
                                        <x-icon name="check" size="h-3 w-3" stroke="3" x-show="suite === 'full' || picked.includes('{{ $a['key'] }}')" />
                                    </span>
                                    <span class="min-w-0 flex-1 text-sm text-white">{{ $a['name'] }}</span>
                                    <span class="text-xs tabular-nums text-gray-400">{{ $book->format((float) $a['price']) }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Metered --}}
                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <div class="flex items-baseline justify-between">
                                <label for="mk-staff" class="text-sm font-medium text-gray-300">HR &amp; Payroll staff</label>
                                <span class="mk-display text-2xl font-semibold tabular-nums text-white" x-text="staff === 0 ? 'Off' : staff"></span>
                            </div>
                            <input id="mk-staff" type="range" min="0" max="200" step="5" x-model.number="staff"
                                   class="mk-range mt-3 bg-white/15" :style="`--mk-fill:${fill(staff, 0, 200)}`">
                            <p class="mt-2 text-[11px] text-gray-400">{{ $book->format((float) $calc['hr']['price']) }} per employee, minimum {{ $calc['hr']['min_quantity'] }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-300">Central kitchens</p>
                            <div class="mt-3 flex items-center gap-3">
                                <button type="button" class="icon-btn border border-white/15 text-white hover:bg-white/10" @click="kitchens = Math.max(0, kitchens - 1)" aria-label="One fewer kitchen">&minus;</button>
                                <span class="mk-display w-8 text-center text-2xl font-semibold tabular-nums text-white" x-text="kitchens">0</span>
                                <button type="button" class="icon-btn border border-white/15 text-white hover:bg-white/10" @click="kitchens = Math.min(5, kitchens + 1)" aria-label="One more kitchen">+</button>
                            </div>
                            <p class="mt-2 text-[11px] text-gray-400">{{ $book->format((float) $calc['ck']['price']) }} per kitchen, on either suite</p>
                        </div>
                    </div>
                </div>

                {{-- Result --}}
                <div class="flex flex-col rounded-panel bg-white p-6 text-gray-950 shadow-e4 sm:p-8 lg:col-span-2" aria-live="polite">
                    <template x-if="enterprise">
                        <div class="flex flex-1 flex-col">
                            <p class="text-sm font-medium text-gray-600">{{ $enterpriseFrom }} outlets or more</p>
                            <p class="mk-display mt-2 text-4xl font-semibold tracking-tight">Let's talk</p>
                            <p class="mt-3 text-sm leading-relaxed text-gray-600">Groups this size get Enterprise pricing, quoted to fit how you run.</p>
                            <div class="flex-1"></div>
                            <a href="{{ route('saas.register') }}" class="btn-primary mt-8 w-full">Start a trial while we quote</a>
                        </div>
                    </template>
                    <template x-if="! enterprise">
                        <div class="flex flex-1 flex-col">
                            <p class="text-sm font-medium text-gray-600" x-text="cycle === 'yearly' ? 'Your price, billed yearly' : 'Your price, per month'"></p>
                            <p class="mk-display mt-2 text-5xl font-semibold tracking-tight tabular-nums sm:text-6xl" x-text="rm(shown)"></p>
                            <p class="mt-1 text-xs text-gray-500" x-show="cycle === 'yearly'">Two months free: <span x-text="rm(monthly * 2)"></span> less than paying monthly.</p>
                            <p class="mt-1 text-xs text-gray-500" x-show="cycle !== 'yearly'">Or <span x-text="rm(monthly * 10)"></span> a year, two months free.</p>

                            <dl class="mt-6 space-y-2.5 border-t border-gray-200 pt-5 text-sm">
                                <div class="flex justify-between gap-3">
                                    <dt class="text-gray-600"><span x-text="suite === 'full' ? 'Full' : 'Basic'"></span> suite · <span x-text="outlets"></span> × <span x-text="rm(unit)"></span></dt>
                                    <dd class="font-medium tabular-nums" x-text="rm(suiteTotal)"></dd>
                                </div>
                                <div class="flex justify-between gap-3 text-success-700" x-show="discount > 0">
                                    <dt>Volume discount</dt>
                                    <dd class="font-medium tabular-nums" x-text="'−' + rm(discount)"></dd>
                                </div>
                                <div class="flex justify-between gap-3" x-show="addonTotal > 0">
                                    <dt class="text-gray-600">Add-ons · <span x-text="picked.length"></span></dt>
                                    <dd class="font-medium tabular-nums" x-text="rm(addonTotal)"></dd>
                                </div>
                                <div class="flex justify-between gap-3" x-show="suite === 'full'">
                                    <dt class="text-gray-600">All six add-ons</dt>
                                    <dd class="font-medium text-success-700">Included</dd>
                                </div>
                                <div class="flex justify-between gap-3" x-show="hrTotal > 0">
                                    <dt class="text-gray-600">HR &amp; Payroll · <span x-text="hrBilled"></span> staff</dt>
                                    <dd class="font-medium tabular-nums" x-text="rm(hrTotal)"></dd>
                                </div>
                                <div class="flex justify-between gap-3" x-show="ckTotal > 0">
                                    <dt class="text-gray-600">Central kitchen · <span x-text="kitchens"></span></dt>
                                    <dd class="font-medium tabular-nums" x-text="rm(ckTotal)"></dd>
                                </div>
                                <div class="flex justify-between gap-3 border-t border-gray-100 pt-2.5">
                                    <dt class="text-gray-600">Per outlet, all in</dt>
                                    <dd class="font-semibold tabular-nums" x-text="rm(monthly / outlets) + ' / mo'"></dd>
                                </div>
                            </dl>

                            <p class="mt-4 rounded-control bg-brand-50 px-3 py-2.5 text-xs leading-relaxed text-brand-800"
                               x-show="suite === 'basic' && picked.length >= c.cap">
                                Want a third add-on? Full includes all six for <span x-text="rm(c.suite.full - c.suite.basic)"></span> more per outlet.
                            </p>

                            <div class="flex-1"></div>
                            <a href="{{ route('saas.register') }}" class="btn-primary btn-lg mt-8 w-full">Try it free for {{ $trialDays }} days</a>
                            <p class="mt-3 text-center text-[11px] text-gray-500">
                                Checkout shows the exact figure before you pay{{ $book->isMyr() ? '' : ', and the ringgit it comes to at Bank Negara\'s rate' }}.
                            </p>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </section>
    @endif

    {{-- ── 4. Add-ons ──────────────────────────────────────────────────── --}}
    <section class="py-20 lg:py-28">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="grid items-end gap-6 lg:grid-cols-2">
                <div>
                    <p class="mk-kicker">Add-ons</p>
                    <h2 class="display-2 mt-5 text-gray-950">Add only <span class="mk-accent">what you run</span>.</h2>
                </div>
                <p class="text-lg leading-relaxed text-gray-600 lg:pb-2">
                    Add-ons are one price per company, however many outlets you have. Full includes all six;
                    Basic takes up to {{ $cap }}. HR and Central Kitchen go on either suite.
                </p>
            </div>

            <ul class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($catalogue as $key => $m)
                    @continue(! in_array($m['kind'], ['addon', 'metered'], true))
                    <li data-reveal-index="{{ $loop->index % 4 }}" class="reveal mk-card flex flex-col p-5">
                        <span class="mk-icon-tile"><x-icon :name="$addonIcons[$key] ?? 'tag'" size="h-5 w-5" /></span>
                        <p class="mt-4 text-sm font-semibold text-gray-950">{{ $m['name'] }}</p>
                        <p class="mt-1 flex-1 text-xs leading-relaxed text-gray-600">{{ $addonBlurbs[$key] ?? '' }}</p>
                        <p class="mt-4 tabular text-xl font-bold text-gray-950">
                            {{ $book->format((float) $price($key)) }}
                            <span class="text-xs font-normal text-gray-600">/ {{ $m['kind'] === 'metered' ? $m['unit'].' / ' : '' }}month</span>
                        </p>
                        @if (($m['min_quantity'] ?? 1) > 1)
                            <p class="mt-1 text-xs text-gray-600">Minimum {{ $m['min_quantity'] }} {{ Str::plural($m['unit']) }}</p>
                        @elseif ($m['kind'] === 'addon')
                            <p class="mt-1 text-xs font-medium text-success-700">Included in Full</p>
                        @else
                            <p class="mt-1 text-xs text-gray-600">On Basic or Full</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- ── 5. FAQ ──────────────────────────────────────────────────────────
         Native <details>: works with no JavaScript, keyboard operable and
         screen-reader correct for free.
    --}}
    <section class="border-t border-gray-200 bg-gray-50/70 py-20 lg:py-28">
        <div class="mx-auto grid max-w-6xl gap-12 px-4 sm:px-6 lg:grid-cols-12 lg:px-8">
            <div class="lg:col-span-4">
                <p class="mk-kicker">FAQ</p>
                <h2 class="display-2 mt-5 text-gray-950">Questions we <span class="mk-accent">get asked</span>.</h2>
            </div>

            @php
                $faqs = [
                    ['Can I change plans later?', 'Yes. An upgrade starts straight away and you pay only the difference for the rest of your period. A downgrade starts when the period you have paid for ends.'],
                    ['What payment methods do you accept?', 'FPX online banking, credit and debit cards, and e-wallets, through our payment partner CHIP-IN.'],
                    ['Is my data secure?', 'Data is encrypted, backed up daily, and every company is fully isolated from every other company on the platform.'],
                    ['Can I export my data?', 'Yes. Every module exports to CSV, and the reports and inventory screens export to Excel and PDF as well. The data is yours and you can take it out at any time.'],
                    ['What happens when my trial ends?', 'You move to the Free plan: nothing is deleted, and recipe costing for one outlet keeps working. Paid modules lock until you upgrade.'],
                    ['Do staff with a PIN count as users?', 'No. Kitchen staff who only use the staff portal, label app or SOP library with a PIN never count as users.'],
                    ['Do you offer custom plans?', 'For larger groups with specific requirements, get in touch and we will put together a plan that fits.'],
                ];
            @endphp

            <div class="border-t border-gray-200 lg:col-span-8">
                @foreach ($faqs as [$q, $a])
                    <details class="mk-faq">
                        <summary>{{ $q }}<span class="mk-faq-plus" aria-hidden="true"></span></summary>
                        <p class="-mt-1 max-w-prose pb-5 text-[15px] leading-relaxed text-gray-600">{{ $a }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── 6. Close ────────────────────────────────────────────────────── --}}
    <section class="relative overflow-hidden bg-navy-950">
        <div aria-hidden="true" class="mk-grid-bg-dark pointer-events-none absolute inset-0"></div>
        <div aria-hidden="true" class="pointer-events-none absolute left-1/2 top-0 h-72 w-[36rem] -translate-x-1/2 rounded-full bg-brand-600/25 blur-3xl"></div>
        <div class="relative mx-auto max-w-3xl px-4 py-24 text-center sm:px-6 lg:px-8">
            <h2 class="display-2 text-white">Try it on <span class="mk-accent mk-accent-dark">your own numbers</span>.</h2>
            <p class="mx-auto mt-5 max-w-prose text-lg leading-relaxed text-gray-300">
                Cost one real recipe and see whether the margin matches what you assumed.
            </p>
            <a href="{{ route('saas.register') }}" class="btn-primary btn-lg group mt-9">
                Start free trial
                <x-icon name="arrow-right" size="h-4 w-4" class="transition-transform group-hover:translate-x-0.5" />
            </a>
        </div>
    </section>
</div>
