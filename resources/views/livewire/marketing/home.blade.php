{{--
    Servora marketing home.

    ── STYLE ────────────────────────────────────────────────────────────────
    Space Grotesk display type, a faint grid behind the hero, mono kickers and
    product screens built in markup that move on their own. The marketing
    classes (`mk-*`) live in the MARKETING section of resources/css/app.css
    and only apply under the `.mk` body class the marketing layout sets.

    ── MOCKS ────────────────────────────────────────────────────────────────
    Every product screen on this page is an illustration drawn in HTML with
    made-up figures, and is aria-hidden. The claim it illustrates is always
    in the copy beside it, and every claim is something the product does
    today: check docs/03-modules.md before adding one.

    ── IMAGES ───────────────────────────────────────────────────────────────
    Photography is self-hosted from public/images/marketing as WebP with a
    JPEG fallback. A module card takes a photo with `'photo' => 'images/
    marketing/<name>'`; card art is 1200x560 (15:7).

    ── WORDS ────────────────────────────────────────────────────────────────
    Every visible string comes from $t('key'), backed by
    App\Support\Marketing\HomeCopy. A country landing page (Admin › Country
    Pages) overrides those keys, so this one view is every language's home
    page. New copy goes into HomeCopy first. Prices come from $book, the
    visitor's currency (Billing\PriceBook).

    ── CLAIMS ───────────────────────────────────────────────────────────────
    The three testimonials are carried over verbatim from the previous
    version. They are unattributed to real, verifiable customers. Either
    replace them with real quotes and permission, or remove the section.
    Malaysian advertising rules treat invented testimonials as deceptive.
--}}
@php
    $supplierPortal = config('modules.supplier_portal');
    $catalogue = config('modules.catalogue');
    $suitePrices = config('modules.suite_prices');

    // Prices in the visitor's currency, for the copy's :placeholders.
    $myrSuite = fn (string $slug) => (float) ($plans->firstWhere('slug', $slug)?->price_monthly ?? $suitePrices[$slug]);
    $vars = [
        ':days'  => $trialDays,
        ':basic' => $book->format((float) $book->suite('basic', $myrSuite('basic'))),
        ':full'  => $book->format((float) $book->suite('full', $myrSuite('full'))),
        ':hr'    => $book->format((float) $book->module('hr', (float) $catalogue['hr']['price'])),
        ':ck'    => $book->format((float) $book->module('central_kitchen', (float) $catalogue['central_kitchen']['price'])),
        ':addon' => $book->format((float) collect($catalogue)->filter(fn ($m) => $m['kind'] === 'addon')
            ->map(fn ($m, $k) => $book->module($k, (float) $m['price']))->min()),
    ];
    $t = fn (string $key, array $extra = []) => strtr($copy[$key] ?? $key, $extra + $vars);

    // The areas of the features page, in the same order under the same
    // titles. span = lg column span out of 6: 3+3 / 2+2+2 / 3+3 / 3+3 /
    // 2+2+2 / 6 (or 3+3 with the supplier portal on). No filler tile.
            $modules = [
                ['icon' => 'ingredient', 'title' => $t('modules.costing'), 'span' => 'lg:col-span-3', 'tone' => 'photo', 'tag' => $t('tag.free'),
                 'photo' => 'images/marketing/recipe-costing', 'alt' => 'A recipe and its quantities written out by hand on a notepad',
                 'desc' => $t('modules.costing_desc')],
                ['icon' => 'sparkles',   'title' => $t('modules.ai'), 'span' => 'lg:col-span-3', 'tone' => 'brand', 'tag' => $t('tag.basic'),
                 'desc' => $t('modules.ai_desc')],
                ['icon' => 'cart',       'title' => $t('modules.purchasing'), 'span' => 'lg:col-span-2', 'tone' => 'plain', 'tag' => $t('tag.basic'),
                 'desc' => $t('modules.purchasing_desc')],
                ['icon' => 'database',   'title' => $t('modules.inventory'), 'span' => 'lg:col-span-2', 'tone' => 'plain', 'tag' => $t('tag.basic'),
                 'desc' => $t('modules.inventory_desc')],
                ['icon' => 'clipboard',  'title' => $t('modules.kitchen'), 'span' => 'lg:col-span-2', 'tone' => 'plain', 'tag' => $t('tag.per_kitchen'),
                 'desc' => $t('modules.kitchen_desc')],
                ['icon' => 'printer',    'title' => $t('modules.labels'), 'span' => 'lg:col-span-3', 'tone' => 'navy', 'tag' => $t('tag.addon'),
                 'desc' => $t('modules.labels_desc')],
                ['icon' => 'currency',   'title' => $t('modules.sales'), 'span' => 'lg:col-span-3', 'tone' => 'plain', 'tag' => $t('tag.basic_addon'),
                 'desc' => $t('modules.sales_desc')],
                ['icon' => 'chart',      'title' => $t('modules.reports'), 'span' => 'lg:col-span-3', 'tone' => 'photo', 'tag' => $t('tag.basic'),
                 'photo' => 'images/marketing/reports-pnl', 'alt' => 'An income statement showing revenue, cost of goods and gross profit',
                 'desc' => $t('modules.reports_desc')],
                ['icon' => 'users',      'title' => $t('modules.hr'), 'span' => 'lg:col-span-3', 'tone' => 'plain', 'tag' => $t('tag.per_employee'),
                 'desc' => $t('modules.hr_desc')],
                ['icon' => 'academic',   'title' => $t('modules.learn'), 'span' => 'lg:col-span-2', 'tone' => 'plain', 'tag' => $t('tag.addon'),
                 'desc' => $t('modules.learn_desc')],
                ['icon' => 'shield',     'title' => $t('modules.audits'), 'span' => 'lg:col-span-2', 'tone' => 'plain', 'tag' => $t('tag.addon'),
                 'desc' => $t('modules.audits_desc')],
                ['icon' => 'cube',       'title' => $t('modules.assets'), 'span' => 'lg:col-span-2', 'tone' => 'plain', 'tag' => $t('tag.addon'),
                 'desc' => $t('modules.assets_desc')],
                ['icon' => 'building',   'title' => $t('modules.control'), 'span' => $supplierPortal ? 'lg:col-span-3' : 'lg:col-span-6', 'tone' => 'plain', 'tag' => $t('tag.every_plan'),
                 'desc' => $t('modules.control_desc')],
                ...($supplierPortal ? [
                ['icon' => 'device',     'title' => $t('modules.suppliers'), 'span' => 'lg:col-span-3', 'tone' => 'plain', 'tag' => $t('tag.every_plan'),
                 'desc' => $t('modules.suppliers_desc')],
                ] : []),
            ];
@endphp
<div>

    {{-- ── 1. Hero ───────────────────────────────────────────────────────── --}}
    <section class="relative overflow-hidden bg-white">
        <div aria-hidden="true" class="mk-grid-bg pointer-events-none absolute inset-0"></div>
        <div aria-hidden="true"
             class="pointer-events-none absolute left-1/2 top-[-12rem] h-[34rem] w-[60rem] -translate-x-1/2 rounded-full
                    bg-brand-100/50 blur-3xl"></div>

        <div class="relative mx-auto max-w-6xl px-4 pt-14 sm:px-6 lg:px-8 lg:pt-20">
            <div class="mx-auto max-w-4xl text-center">
                <a href="{{ route('pricing') }}" class="mk-pill mk-in transition-colors hover:border-brand-300">
                    <x-icon name="sparkles" size="h-4 w-4" />
                    <span class="hidden sm:inline">{{ $t('hero.pill_long') }}</span>
                    <span class="sm:hidden">{{ $t('hero.pill_short') }}</span>
                    <x-icon name="arrow-right" size="h-3.5 w-3.5" />
                </a>

                <h1 class="display-1 mk-in mt-7 text-gray-950" style="animation-delay:.08s">
                    {{ $t('hero.title') }}<br>
                    <span class="mk-accent">{{ $t('hero.title_accent') }}</span>.
                </h1>

                @php
                    $heroChips = [
                        ['ingredient', $t('chip.costing')], ['cart', $t('chip.purchasing')], ['database', $t('chip.inventory')],
                        ['printer', $t('chip.labels')], ['users', $t('chip.hr')], ['clipboard', $t('chip.audits')],
                    ];
                @endphp
                <ul class="mk-in mt-7 flex flex-wrap justify-center gap-2" style="animation-delay:.16s" aria-label="Servora">
                    @foreach ($heroChips as [$icon, $label])
                        <li class="mk-chip">
                            <x-icon :name="$icon" size="h-4 w-4" class="text-brand-600" />
                            {{ $label }}
                        </li>
                    @endforeach
                </ul>

                <p class="mk-in mx-auto mt-7 max-w-2xl text-lg leading-relaxed text-gray-600" style="animation-delay:.24s">
                    {{ $t('hero.lead') }}
                </p>

                <div class="mk-in mt-9 flex flex-wrap items-center justify-center gap-3" style="animation-delay:.32s">
                    <a href="{{ route('saas.register') }}" class="btn-primary btn-lg group">
                        {{ $t('cta.trial') }}
                        <x-icon name="arrow-right" size="h-4 w-4" class="transition-transform group-hover:translate-x-0.5" />
                    </a>
                    <a href="#tour" class="btn-secondary btn-lg">
                        <x-icon name="play" size="h-4 w-4" />
                        {{ $t('hero.see') }}
                    </a>
                </div>

                <ul class="mk-in mt-6 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-gray-600" style="animation-delay:.4s">
                    @foreach ([$t('hero.point_1'), $t('hero.point_2'), $t('hero.point_3')] as $point)
                        <li class="flex items-center gap-1.5">
                            <x-icon name="check" size="h-4 w-4" stroke="2.4" class="text-brand-600" />
                            {{ $point }}
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- The dashboard. Illustrative figures; see the MOCKS note above. --}}
            <div class="mk-in relative mx-auto mt-14 max-w-5xl" style="animation-delay:.5s" aria-hidden="true"
                 x-data="{ on: false }" x-intersect.once="on = true">

                <div class="mk-window">
                    <div class="mk-window-bar">
                        <i></i><i></i><i></i>
                        <span class="mk-mono ml-3 truncate rounded-control bg-white px-3 py-1 text-[11px] text-gray-500 ring-1 ring-gray-200">
                            app.servora.com.my/dashboard
                        </span>
                    </div>

                    <div class="flex text-left">
                        {{-- Sidebar --}}
                        <div class="hidden w-48 flex-none flex-col gap-1 bg-navy-900 p-3 md:flex">
                            <div class="mb-3 flex items-center gap-2 px-2 py-1.5">
                                <span class="flex h-6 w-6 items-center justify-center rounded-md bg-brand-600 text-[11px] font-bold text-white">S</span>
                                <span class="text-sm font-semibold text-white">Servora</span>
                            </div>
                            @foreach ([['home', 'Dashboard', true], ['ingredient', 'Recipes', false], ['cart', 'Purchasing', false], ['database', 'Inventory', false], ['printer', 'Labels', false], ['users', 'HR', false], ['clipboard', 'Audits', false], ['chart', 'Reports', false]] as [$icon, $label, $active])
                                <span @class([
                                    'flex items-center gap-2.5 rounded-control px-2.5 py-2 text-[12px] font-medium',
                                    'bg-brand-600 text-white' => $active,
                                    'text-gray-300' => ! $active,
                                ])>
                                    <x-icon :name="$icon" size="h-4 w-4" />
                                    {{ $label }}
                                </span>
                            @endforeach
                        </div>

                        {{-- Body --}}
                        <div class="min-w-0 flex-1 bg-gray-50/60 p-4 sm:p-6">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-base font-semibold text-gray-950 sm:text-lg">Good morning, Chef Aina</p>
                                    <p class="text-[11px] text-gray-500">Bangsar outlet · month to date</p>
                                </div>
                                <span class="hidden items-center gap-1.5 rounded-full bg-success-50 px-2.5 py-1 text-[11px] font-semibold text-success-700 ring-1 ring-success-200 sm:inline-flex">
                                    <span class="relative flex h-1.5 w-1.5"><span class="mk-ping absolute inline-flex h-full w-full rounded-full bg-success-500"></span><span class="relative h-1.5 w-1.5 rounded-full bg-success-500"></span></span>
                                    Live
                                </span>
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                                @foreach ([
                                    ['Revenue MTD', 184320, 0, 'RM ', '', '+8.4% vs last month', 'text-success-700'],
                                    ['Food cost', 29.4, 1, '', '%', 'Target 31% · on track', 'text-success-700'],
                                    ['Open POs', 7, 0, '', '', '3 arriving today', 'text-gray-500'],
                                    ['Wastage this week', 412, 0, 'RM ', '', '−12% vs last week', 'text-success-700'],
                                ] as [$label, $value, $dec, $pre, $post, $foot, $footClass])
                                    <div class="rounded-surface border border-gray-200 bg-white p-3 shadow-e1">
                                        <p class="text-[11px] font-medium text-gray-500">{{ $label }}</p>
                                        <p class="mt-1 text-lg font-bold tabular-nums text-gray-950 sm:text-xl">
                                            {{ $pre }}<span x-effect="on && mkCount($el, {{ $value }}, {{ $dec }})">{{ number_format($value, $dec) }}</span>{{ $post }}
                                        </p>
                                        <p class="mt-0.5 text-[10px] font-medium {{ $footClass }}">{{ $foot }}</p>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-3 grid gap-3 lg:grid-cols-5">
                                {{-- Chart --}}
                                <div class="rounded-surface border border-gray-200 bg-white p-4 shadow-e1 lg:col-span-3">
                                    <div class="flex items-center justify-between">
                                        <p class="text-xs font-semibold text-gray-900">Sales vs food cost, last 8 weeks</p>
                                        <span class="flex items-center gap-3 text-[10px] text-gray-500">
                                            <span class="flex items-center gap-1"><i class="h-2 w-2 rounded-sm bg-brand-600"></i>Sales</span>
                                            <span class="flex items-center gap-1"><i class="h-2 w-2 rounded-sm bg-brand-200"></i>Food cost</span>
                                        </span>
                                    </div>
                                    <div class="mt-4 flex h-32 items-end gap-2 sm:gap-3">
                                        @foreach ([[62, 20], [70, 22], [58, 19], [76, 23], [81, 24], [74, 21], [88, 25], [94, 26]] as $i => [$s, $c])
                                            <div class="flex flex-1 items-end gap-0.5">
                                                <div x-show="on" class="mk-grow flex-1 rounded-t-sm bg-brand-600" style="height: {{ $s * 1.28 }}px; animation-delay: {{ $i * 70 }}ms"></div>
                                                <div x-show="on" class="mk-grow flex-1 rounded-t-sm bg-brand-200" style="height: {{ $c * 1.28 }}px; animation-delay: {{ $i * 70 + 120 }}ms"></div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- AI review, typed out --}}
                                <div class="rounded-surface border border-brand-200 bg-brand-50/60 p-4 shadow-e1 lg:col-span-2"
                                     x-data="{
                                        full: 'Food cost fell 1.6 points after the chicken switch. Thursday dinner is your weakest shift; consider a set menu. Wastage on prepped sambal is up, so batch it smaller.',
                                        shown: '',
                                        start() {
                                            let i = 0;
                                            const step = () => { this.shown = this.full.slice(0, ++i); if (i < this.full.length) setTimeout(step, 22); else setTimeout(() => { i = 0; this.shown = ''; setTimeout(step, 400); }, 5000); };
                                            step();
                                        }
                                     }"
                                     x-intersect.once="start()">
                                    <p class="flex items-center gap-1.5 text-xs font-semibold text-brand-800">
                                        <x-icon name="sparkles" size="h-4 w-4" />
                                        AI weekly review
                                    </p>
                                    <p class="mt-2 min-h-[7.5rem] text-[12px] leading-relaxed text-gray-700">
                                        <span x-text="shown"></span><span class="mk-blink ml-px inline-block h-3.5 w-[2px] translate-y-0.5 bg-brand-600"></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Floating notification cards --}}
                <div class="mk-float absolute -left-20 top-44 hidden w-64 rounded-surface border border-gray-200 bg-white p-3 text-left shadow-e4 xl:block">
                    <div class="flex items-start gap-2.5">
                        <span class="flex h-8 w-8 flex-none items-center justify-center rounded-control bg-brand-600 text-white"><x-icon name="receipt" size="h-4 w-4" /></span>
                        <div>
                            <p class="text-[12px] font-semibold text-gray-950">Invoice read · 12 lines</p>
                            <p class="mt-0.5 text-[11px] leading-snug text-gray-600">Chicken thigh <span class="font-semibold text-warning-700">+6.7%</span> since last delivery</p>
                        </div>
                    </div>
                </div>
                <div class="mk-float-2 absolute -right-16 bottom-10 hidden w-60 rounded-surface border border-gray-200 bg-white p-3 text-left shadow-e4 xl:block">
                    <div class="flex items-start gap-2.5">
                        <span class="flex h-8 w-8 flex-none items-center justify-center rounded-control bg-success-600 text-white"><x-icon name="printer" size="h-4 w-4" /></span>
                        <div>
                            <p class="text-[12px] font-semibold text-gray-950">24 labels printed</p>
                            <p class="mt-0.5 text-[11px] leading-snug text-gray-600">Morning prep · use-by worked out</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="h-16 lg:h-24"></div>
    </section>

    {{-- ── 1b. Price ticker ─────────────────────────────────────────────
         Real ingredient prices, moving. The one thing on this page that is
         evidence rather than claim. Hidden when there is nothing to show.
    --}}
    @if ($tickerItems->isNotEmpty())
        {{-- top-16 is the header's height; z-19 sits one below the header so
             its mobile sheet opens over this strip. --}}
        <section class="sticky top-16 z-[19] bg-navy-950 py-3.5" aria-label="Recent ingredient prices">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <span class="hidden shrink-0 items-center gap-2 sm:flex">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-success-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-success-500"></span>
                        </span>
                        <span class="mk-mono text-[11px] font-medium uppercase tracking-widest text-gray-400">{{ $t('ticker.label') }}</span>
                    </span>

                    <div class="mask-edges min-w-0 flex-1 overflow-hidden">
                        <ul class="flex w-max animate-marquee items-center gap-8">
                            @foreach ([false, true] as $isDuplicate)
                                @foreach ($tickerItems as $item)
                                    <li class="flex items-center gap-2 whitespace-nowrap"
                                        @if ($isDuplicate) aria-hidden="true" @endif>
                                        <span class="text-sm font-medium text-white">{{ $item['item'] }}</span>
                                        <span class="text-sm tabular-nums text-gray-300">
                                            RM {{ number_format($item['price'], 2) }}@if ($item['unit'])<span class="text-gray-400">/{{ $item['unit'] }}</span>@endif
                                        </span>
                                        @if ($item['direction'] === 'up')
                                            <span class="text-xs font-semibold tabular-nums text-danger-400">&#9650; {{ number_format(abs($item['change']), 1) }}%</span>
                                        @elseif ($item['direction'] === 'down')
                                            <span class="text-xs font-semibold tabular-nums text-success-400">&#9660; {{ number_format(abs($item['change']), 1) }}%</span>
                                        @else
                                            <span class="text-xs font-semibold text-gray-400">&mdash;</span>
                                        @endif
                                    </li>
                                @endforeach
                            @endforeach
                        </ul>
                    </div>
                </div>
                <p class="mt-1.5 text-center text-[11px] text-gray-400 sm:text-left">
                    {{ $t('ticker.note') }}
                    &middot;
                    <a href="{{ route('tools.recipe-cost') }}" class="text-gray-200 underline hover:text-white">{{ $t('ticker.link') }}</a>
                </p>
            </div>
        </section>
    @endif

    {{-- ── 2. Who it is for ───────────────────────────────────────────── --}}
    <section class="border-y border-gray-200 bg-gray-50/70 py-8" aria-label="Business types served">
        <p class="mk-mono text-center text-[11px] font-medium uppercase tracking-[0.18em] text-gray-500">
            {{ $t('types.heading') }}
        </p>
        @php
            $types = [
                ['fire', $t('types.restaurants')], ['sun', $t('types.cafes')], ['truck', $t('types.cloud')], ['users', $t('types.catering')],
                ['cube', $t('types.bakeries')], ['moon', $t('types.bars')], ['building', $t('types.food_courts')], ['home', $t('types.hotels')],
                ['clipboard', $t('types.central')], ['tag', $t('types.franchises')],
            ];
        @endphp
        <div class="mask-edges mt-5 overflow-hidden">
            <ul class="flex w-max animate-marquee items-center gap-12">
                @foreach ([false, true] as $isDuplicate)
                    @foreach ($types as [$icon, $type])
                        <li class="flex items-center gap-2 whitespace-nowrap text-lg font-semibold text-gray-400"
                            @if ($isDuplicate) aria-hidden="true" @endif>
                            <x-icon :name="$icon" size="h-5 w-5" />
                            <span class="mk-display">{{ $type }}</span>
                        </li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    </section>

    {{-- ── 3. Facts that count up ─────────────────────────────────────────
         True by inspection, never measured outcomes: the old "12% average
         cost reduction" band went because nobody could substantiate it.
    --}}
    @php
        $facts = [
            ['n' => count($modules), 'pre' => '', 'post' => '', 'label' => $t('facts.areas')],
            ['n' => $trialDays, 'pre' => '', 'post' => $t('facts.days'), 'label' => $t('facts.trial')],
            ['n' => 30, 'pre' => '', 'post' => '', 'label' => $t('facts.scans')],
            ['n' => 0, 'pre' => trim($book->symbol()), 'post' => '', 'label' => $t('facts.free')],
        ];
    @endphp
    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <dl class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4" x-data="{ on: false }" x-intersect.once="on = true">
            @foreach ($facts as $i => $f)
                <div data-reveal-index="{{ $i }}" class="reveal border-l-2 border-brand-600 pl-5">
                    <dt class="sr-only">{{ $f['label'] }}</dt>
                    <dd>
                        <p class="mk-stat">{{ $f['pre'] }}<span x-effect="on && mkCount($el, {{ $f['n'] }})">{{ $f['n'] }}</span><span class="ml-1 text-2xl text-brand-600 sm:text-3xl">{{ $f['post'] }}</span></p>
                        <p class="mt-2 max-w-[16rem] text-sm leading-relaxed text-gray-600">{{ $f['label'] }}</p>
                    </dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- ── 4. Live recipe costing ─────────────────────────────────────────
         The calculator moment. The arithmetic is the product's own: cost per
         serving from line costs, food cost % against the selling price.
    --}}
    <section id="tour" class="scroll-mt-28 border-t border-gray-200 bg-white py-20 lg:py-28">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="grid items-end gap-8 lg:grid-cols-2">
                <div>
                    <p class="mk-kicker">{{ $t('cost.kicker') }}</p>
                    <h2 class="display-2 mt-5 text-gray-950">{{ $t('cost.title') }}<br><span class="mk-accent">{{ $t('cost.title_accent') }}</span></h2>
                </div>
                <p class="text-lg leading-relaxed text-gray-600 lg:pb-2">
                    {{ $t('cost.lead') }}
                </p>
            </div>

            <div class="mt-12 rounded-panel border border-gray-200 bg-gray-50/60 p-4 shadow-e2 sm:p-8"
                 x-data="{
                    price: 10.50, min: 8, max: 16, sell: 15.90, target: 28, plates: 1500, base: 10.50,
                    lines: [
                        { name: 'Chicken thigh', qty: '180 g', live: true },
                        { name: 'Rice', qty: '150 g', cost: 0.48 },
                        { name: 'Sambal tumis', qty: '30 g', cost: 0.35 },
                        { name: 'Tempeh & tauhu', qty: '2 pcs', cost: 0.60 },
                        { name: 'Ulam & cucumber', qty: '40 g', cost: 0.25 },
                        { name: 'Takeaway box', qty: '1 pc', cost: 0.45, pack: true },
                    ],
                    lineCost(l) { return l.live ? this.price * 0.18 : l.cost },
                    get cost() { return this.lines.reduce((a, l) => a + this.lineCost(l), 0) },
                    get pct() { return this.cost / this.sell * 100 },
                    get over() { return this.pct > this.target },
                    get monthly() { return (this.price - this.base) * 0.18 * this.plates },
                    get hold() { return this.cost / (this.target / 100) },
                    get fill() { return ((this.price - this.min) / (this.max - this.min) * 100) + '%' },
                    rm(v) { return 'RM ' + v.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
                 }">
                <div class="grid gap-6 lg:grid-cols-5">
                    {{-- Recipe card --}}
                    <div class="rounded-surface border border-gray-200 bg-white p-5 shadow-e1 lg:col-span-3">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-base font-semibold text-gray-950">Nasi Ayam Penyet</p>
                                <p class="text-xs text-gray-500">{{ $t('cost.selling', [':price' => 'RM 15.90', ':target' => 28]) }}</p>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 transition-colors"
                                  :class="over ? 'bg-danger-50 text-danger-700 ring-danger-200' : 'bg-success-50 text-success-700 ring-success-200'"
                                  x-text="over ? @js($t('cost.over')) : @js($t('cost.within'))">{{ $t('cost.within') }}</span>
                        </div>

                        <table class="mt-4 w-full text-sm">
                            <thead>
                                <tr class="text-left text-[11px] uppercase tracking-wide text-gray-500">
                                    <th class="pb-2 font-medium">{{ $t('cost.col_ingredient') }}</th>
                                    <th class="pb-2 font-medium">{{ $t('cost.col_qty') }}</th>
                                    <th class="pb-2 text-right font-medium">{{ $t('cost.col_cost') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="l in lines" :key="l.name">
                                    <tr :class="l.live && 'bg-brand-50/70'">
                                        <td class="py-2 pl-1 text-gray-800">
                                            <span x-text="l.name"></span>
                                            <span x-show="l.live" class="ml-1 rounded bg-brand-600 px-1.5 py-0.5 text-[10px] font-semibold text-white">{{ $t('cost.live') }}</span>
                                            <span x-show="l.pack" class="ml-1 text-[11px] text-gray-500">{{ $t('cost.packaging') }}</span>
                                        </td>
                                        <td class="py-2 text-gray-600" x-text="l.qty"></td>
                                        <td class="py-2 pr-1 text-right font-medium tabular-nums text-gray-900" x-text="rm(lineCost(l))"></td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot>
                                <tr class="border-t-2 border-gray-200">
                                    <td class="pt-3 font-semibold text-gray-950" colspan="2">{{ $t('cost.serving') }}</td>
                                    <td class="pt-3 text-right text-base font-bold tabular-nums text-gray-950" x-text="rm(cost)"></td>
                                </tr>
                            </tfoot>
                        </table>

                        <div class="mt-6">
                            <div class="flex items-baseline justify-between">
                                <label for="mk-chicken" class="text-sm font-medium text-gray-700">{{ $t('cost.slider') }}</label>
                                <span class="mk-display text-2xl font-semibold tabular-nums text-gray-950" x-text="rm(price)">RM 10.50</span>
                            </div>
                            <input id="mk-chicken" type="range" class="mk-range mt-3" step="0.10"
                                   :min="min" :max="max" x-model.number="price" :style="`--mk-fill:${fill}`">
                            <div class="mk-mono mt-2 flex justify-between text-[11px] text-gray-500">
                                <span>RM 8</span><span>RM 10</span><span>RM 12</span><span>RM 14</span><span>RM 16</span>
                            </div>
                        </div>
                    </div>

                    {{-- Outcome --}}
                    <div class="flex flex-col gap-4 lg:col-span-2">
                        <div class="rounded-surface border bg-white p-5 shadow-e1 transition-colors"
                             :class="over ? 'border-danger-200' : 'border-success-200'">
                            <p class="text-sm font-medium text-gray-600">{{ $t('cost.food_cost') }}</p>
                            <p class="mk-display mt-1 text-5xl font-semibold tabular-nums tracking-tight"
                               :class="over ? 'text-danger-600' : 'text-gray-950'">
                                <span x-text="pct.toFixed(1)">25.3</span>%
                            </p>
                            <div class="mt-4 h-2 overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full transition-all duration-300"
                                     :class="over ? 'bg-danger-500' : 'bg-success-500'"
                                     :style="`width:${Math.min(100, pct / 40 * 100)}%`"></div>
                            </div>
                            <p class="mt-2 text-xs text-gray-500">{{ $t('cost.scale', [':target' => 28]) }}</p>
                        </div>

                        <div class="flex-1 rounded-surface bg-navy-950 p-5 text-white shadow-e3">
                            <p class="text-sm text-gray-300">{{ $t('cost.impact') }}</p>
                            <p class="mk-display mt-2 text-4xl font-semibold tabular-nums tracking-tight"
                               :class="monthly > 0 ? 'text-danger-300' : 'text-success-300'">
                                <span x-text="(monthly > 0 ? '−' : '+') + rm(Math.abs(monthly))">+RM 0.00</span>
                            </p>
                            <p class="mt-1 text-sm text-gray-300">{{ $t('cost.impact_after') }}</p>
                            <p class="mt-5 border-t border-white/10 pt-4 text-sm text-gray-300" x-show="over">
                                {{ $t('cost.hold', [':target' => 28]) }}
                                <span class="font-semibold text-white" x-text="rm(hold)"></span>.
                            </p>
                            <p class="mt-5 border-t border-white/10 pt-4 text-sm text-gray-300" x-show="! over">
                                <span class="font-semibold text-white" x-text="rm(sell * target / 100 - cost)"></span>
                                {{ $t('cost.headroom', [':target' => 28]) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <p class="mt-4 text-center text-xs text-gray-500">{{ $t('cost.note') }}</p>
        </div>
    </section>

    {{-- ── 5. AI invoice capture ──────────────────────────────────────────
         AiInvoiceExtractionService: supplier invoices and DOs from a photo or
         PDF, staged for review, matched to ingredients, price history on
         approval.
    --}}
    <section class="border-y border-gray-200 bg-gray-50/70 py-20 lg:py-28">
        <div class="mx-auto grid max-w-6xl items-center gap-14 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
            <div>
                <p class="mk-kicker">{{ $t('ai.kicker') }}</p>
                <h2 class="display-2 mt-5 text-gray-950">{{ $t('ai.title') }}<br><span class="mk-accent">{{ $t('ai.title_accent') }}</span></h2>
                <p class="mt-6 text-lg leading-relaxed text-gray-600">
                    {{ $t('ai.lead') }}
                </p>

                <div class="mt-8 grid gap-3">
                    @foreach ([
                        ['receipt', $t('ai.1_title'), $t('ai.1_body')],
                        ['check', $t('ai.2_title'), $t('ai.2_body')],
                        ['trending-up', $t('ai.3_title'), $t('ai.3_body')],
                    ] as $i => [$icon, $title, $body])
                        <div data-reveal-index="{{ $i }}" class="reveal mk-card flex gap-4 p-4">
                            <span class="mk-icon-tile"><x-icon :name="$icon" size="h-5 w-5" /></span>
                            <div>
                                <p class="text-sm font-semibold text-gray-950">{{ $title }}</p>
                                <p class="mt-1 text-sm text-gray-600">{{ $body }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Scan mock --}}
            <div class="relative" aria-hidden="true"
                 x-data="{
                    step: 0,
                    rows: [
                        ['Chicken thigh, boneless', '20 kg', '11.20', '+6.7%', 'up'],
                        ['Cooking oil 17 kg tin', '2 tin', '98.00', '', ''],
                        ['Eggs grade A, tray 30', '10 tray', '13.50', '', ''],
                        ['Garlic, peeled', '5 kg', '9.30', '−3.1%', 'down'],
                        ['Coriander leaf', '1 kg', '12.00', 'New', 'new'],
                    ],
                    run() { setInterval(() => { this.step = this.step >= this.rows.length + 4 ? 0 : this.step + 1 }, 750) }
                 }"
                 x-intersect.once="run()">
                <div class="grid gap-4 sm:grid-cols-5">
                    {{-- The paper --}}
                    <div class="relative overflow-hidden rounded-surface border border-gray-200 bg-white p-4 shadow-e3 sm:col-span-2 sm:-rotate-2">
                        <div class="mk-scan absolute inset-x-0 h-10 bg-gradient-to-b from-transparent via-brand-400/30 to-transparent">
                            <div class="absolute inset-x-0 top-1/2 h-px bg-brand-500"></div>
                        </div>
                        <p class="text-[11px] font-bold text-gray-900">KEDAI BORONG SEGAR</p>
                        <p class="text-[9px] text-gray-500">Invoice INV-20931 · 28/09</p>
                        <div class="mt-3 space-y-2">
                            @foreach ([80, 64, 72, 56, 68, 44, 60] as $w)
                                <div class="flex items-center gap-2">
                                    <span class="h-1.5 rounded-full bg-gray-200" style="width: {{ $w }}%"></span>
                                    <span class="ml-auto h-1.5 w-6 rounded-full bg-gray-300"></span>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4 flex justify-end">
                            <span class="h-2 w-12 rounded-full bg-gray-400"></span>
                        </div>
                    </div>

                    {{-- The extracted lines --}}
                    <div class="rounded-surface border border-gray-200 bg-white shadow-e3 sm:col-span-3">
                        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                            <p class="flex items-center gap-1.5 text-xs font-semibold text-gray-900">
                                <x-icon name="sparkles" size="h-4 w-4" class="text-brand-600" />
                                Review queue
                            </p>
                            <span class="text-[10px] font-medium text-gray-500" x-text="Math.min(step, rows.length) + ' / ' + rows.length + ' lines'"></span>
                        </div>
                        <ul class="divide-y divide-gray-100">
                            <template x-for="(r, i) in rows" :key="i">
                                <li class="flex items-center gap-3 px-4 py-2.5 transition-all duration-500"
                                    :class="step > i ? 'opacity-100 translate-x-0' : 'opacity-0 translate-x-3'">
                                    <span class="flex h-5 w-5 flex-none items-center justify-center rounded-full bg-success-100 text-success-700">
                                        <x-icon name="check" size="h-3 w-3" stroke="3" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-[12px] font-medium text-gray-900" x-text="r[0]"></p>
                                        <p class="text-[10px] text-gray-500"><span x-text="r[1]"></span> · RM <span x-text="r[2]"></span></p>
                                    </div>
                                    <span x-show="r[4]" class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                          :class="{ 'bg-warning-50 text-warning-700': r[4] === 'up', 'bg-success-50 text-success-700': r[4] === 'down', 'bg-brand-50 text-brand-700': r[4] === 'new' }"
                                          x-text="r[3]"></span>
                                </li>
                            </template>
                        </ul>
                        <div class="border-t border-gray-100 px-4 py-3">
                            <span class="btn-primary btn-sm w-full transition-opacity duration-300"
                                  :class="step > rows.length ? 'opacity-100' : 'opacity-40'">Approve &amp; update costs</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── 6. Purchasing flow ─────────────────────────────────────────────
         The dark moment mid-page. PR → approval → PO → DO → GRN → invoice,
         with the three-way match at the end, all real screens.
    --}}
    <section class="relative overflow-hidden bg-navy-950 py-20 text-white lg:py-28">
        <div aria-hidden="true" class="mk-grid-bg-dark pointer-events-none absolute inset-0"></div>
        <div aria-hidden="true" class="pointer-events-none absolute left-1/2 top-1/3 h-96 w-[48rem] -translate-x-1/2 rounded-full bg-brand-600/20 blur-3xl"></div>

        <div class="relative mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-3xl text-center">
                <p class="mk-kicker mk-kicker-dark">{{ $t('buy.kicker') }}</p>
                <h2 class="display-2 mt-5 text-white">{{ $t('buy.title') }}<br><span class="mk-accent mk-accent-dark">{{ $t('buy.title_accent') }}</span></h2>
                <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-gray-300">
                    {{ $t('buy.lead') }}
                </p>
            </div>

            @php
                $flow = [
                    ['clipboard', $t('buy.step_1'), $t('buy.step_1_sub')],
                    ['shield', $t('buy.step_2'), $t('buy.step_2_sub')],
                    ['cart', $t('buy.step_3'), $t('buy.step_3_sub')],
                    ['truck', $t('buy.step_4'), $t('buy.step_4_sub')],
                    ['inbox', $t('buy.step_5'), $t('buy.step_5_sub')],
                    ['receipt', $t('buy.step_6'), $t('buy.step_6_sub')],
                ];
            @endphp
            <div class="mt-14 rounded-panel border border-white/10 bg-white/[0.03] p-6 shadow-e4 backdrop-blur-sm sm:p-10" aria-hidden="true"
                 x-data="{ step: 0, run() { setInterval(() => this.step = (this.step + 1) % {{ count($flow) + 2 }}, 1100) } }"
                 x-intersect.once="run()">
                <div class="relative">
                    {{-- Rail --}}
                    <div class="absolute left-[8%] right-[8%] top-6 hidden h-0.5 bg-white/10 sm:block">
                        <div class="h-full bg-gradient-to-r from-brand-400 to-brand-300 transition-all duration-700 ease-out"
                             :style="`width:${Math.min(step, {{ count($flow) - 1 }}) / {{ count($flow) - 1 }} * 100}%`"></div>
                    </div>
                    <ol class="relative grid grid-cols-3 gap-y-8 sm:grid-cols-6">
                        @foreach ($flow as $i => [$icon, $title, $sub])
                            <li class="flex flex-col items-center text-center">
                                <span class="flex h-12 w-12 items-center justify-center rounded-full border-2 transition-all duration-500"
                                      :class="step >= {{ $i }} ? 'border-brand-400 bg-brand-600 text-white shadow-[0_0_24px_rgba(69,139,249,.55)]' : 'border-white/15 bg-navy-900 text-gray-400'">
                                    <x-icon :name="$icon" size="h-5 w-5" />
                                </span>
                                <span class="mt-3 text-sm font-semibold transition-colors" :class="step >= {{ $i }} ? 'text-white' : 'text-gray-400'">{{ $title }}</span>
                                <span class="mt-0.5 text-[11px] text-gray-400">{{ $sub }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>

                <div class="mt-10 flex flex-wrap items-center justify-center gap-3 border-t border-white/10 pt-8">
                    <span class="mk-mono text-[11px] uppercase tracking-[0.18em] text-gray-400">{{ $t('buy.match') }}</span>
                    @foreach ([$t('buy.doc_1'), $t('buy.doc_2'), $t('buy.doc_3')] as $j => $doc)
                        <span class="flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition-all duration-500"
                              :class="step >= {{ count($flow) - 1 + ($j > 0 ? 1 : 0) }} ? 'border-success-400/50 bg-success-500/15 text-success-300' : 'border-white/10 text-gray-400'">
                            <x-icon name="check" size="h-3.5 w-3.5" stroke="2.6" />
                            {{ $doc }}
                        </span>
                    @endforeach
                </div>
            </div>

            <div class="mt-8 grid gap-4 md:grid-cols-3">
                @foreach ([
                    ['bolt', $t('buy.1_title'), $t('buy.1_body')],
                    ['truck', $t('buy.2_title'), $t('buy.2_body')],
                    ['building', $t('buy.3_title'), $t('buy.3_body')],
                ] as $i => [$icon, $title, $body])
                    <div data-reveal-index="{{ $i }}" class="reveal rounded-surface border border-white/10 bg-white/[0.04] p-6 transition-colors hover:border-brand-400/40 hover:bg-white/[0.07]">
                        <span class="flex h-10 w-10 items-center justify-center rounded-control bg-brand-600/20 text-brand-300"><x-icon :name="$icon" size="h-5 w-5" /></span>
                        <p class="mt-4 text-base font-semibold text-white">{{ $title }}</p>
                        <p class="mt-1.5 text-sm leading-relaxed text-gray-300">{{ $body }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── 7. The weekly review ───────────────────────────────────────────
         WIP review (slide deck / PDF) and AiAnalyticsService.
    --}}
    <section class="py-20 lg:py-28">
        <div class="mx-auto grid max-w-6xl items-center gap-14 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
            {{-- Slide mock --}}
            <div class="relative order-last lg:order-first" aria-hidden="true"
                 x-data="{ slide: 0, run() { setInterval(() => this.slide = (this.slide + 1) % 3, 3200) } }"
                 x-intersect.once="run()">
                <div class="mk-window">
                    <div class="mk-window-bar">
                        <i></i><i></i><i></i>
                        <span class="ml-3 text-[11px] font-medium text-gray-500">WIP review · week 39</span>
                        <span class="ml-auto flex gap-1">
                            @foreach ([0, 1, 2] as $d)
                                <span class="h-1.5 rounded-full transition-all duration-300" :class="slide === {{ $d }} ? 'w-5 bg-brand-600' : 'w-1.5 bg-gray-300'"></span>
                            @endforeach
                        </span>
                    </div>
                    <div class="relative h-72 bg-white p-6">
                        {{-- Slide 1: sales --}}
                        <div x-show="slide === 0" x-transition.opacity.duration.500ms class="absolute inset-6">
                            <p class="mk-mono text-[10px] uppercase tracking-[0.18em] text-brand-700">Sales performance</p>
                            <p class="mk-display mt-2 text-3xl font-semibold text-gray-950">RM 46,210 <span class="text-base font-semibold text-success-600">+6.2%</span></p>
                            <div class="mt-6 flex h-32 items-end gap-3">
                                @foreach ([55, 62, 48, 71, 90, 100, 84] as $k => $h)
                                    <div class="flex flex-1 flex-col items-center gap-1.5">
                                        <div class="w-full rounded-t-md {{ $k === 5 ? 'bg-brand-600' : 'bg-brand-200' }}" style="height: {{ $h * 1.1 }}px"></div>
                                        <span class="text-[10px] text-gray-500">{{ ['M', 'T', 'W', 'T', 'F', 'S', 'S'][$k] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        {{-- Slide 2: cost of goods --}}
                        <div x-show="slide === 1" x-cloak x-transition.opacity.duration.500ms class="absolute inset-6">
                            <p class="mk-mono text-[10px] uppercase tracking-[0.18em] text-brand-700">Cost of goods</p>
                            <div class="mt-4 space-y-4">
                                @foreach ([['Kitchen', 29.4, 31], ['Beverage', 22.1, 24], ['Consumable', 3.8, 3.5]] as [$dept, $v, $goal])
                                    <div>
                                        <div class="flex justify-between text-xs"><span class="font-medium text-gray-900">{{ $dept }}</span><span class="tabular-nums {{ $v > $goal ? 'text-danger-600' : 'text-gray-600' }}">{{ $v }}% <span class="text-gray-400">/ {{ $goal }}%</span></span></div>
                                        <div class="mt-1.5 h-2 rounded-full bg-gray-100"><div class="h-full rounded-full {{ $v > $goal ? 'bg-danger-500' : 'bg-brand-600' }}" style="width: {{ $v / 40 * 100 }}%"></div></div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        {{-- Slide 3: AI --}}
                        <div x-show="slide === 2" x-cloak x-transition.opacity.duration.500ms class="absolute inset-6">
                            <p class="mk-mono flex items-center gap-1.5 text-[10px] uppercase tracking-[0.18em] text-brand-700"><x-icon name="sparkles" size="h-3.5 w-3.5" /> What to do next week</p>
                            <ol class="mt-4 space-y-3 text-sm text-gray-700">
                                <li class="flex gap-3"><span class="mk-display font-semibold text-brand-600">01</span> Thursday dinner is 18% under the weekday average. Try a set menu.</li>
                                <li class="flex gap-3"><span class="mk-display font-semibold text-brand-600">02</span> Consumables are over target at 3.8%. Check takeaway packaging.</li>
                                <li class="flex gap-3"><span class="mk-display font-semibold text-brand-600">03</span> Overtime in the kitchen doubled. Roster one more on Saturday AM.</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <p class="mk-kicker">{{ $t('review.kicker') }}</p>
                <h2 class="display-2 mt-5 text-gray-950">{{ $t('review.title') }} <span class="mk-accent">{{ $t('review.title_accent') }}</span> {{ $t('review.title_end') }}</h2>
                <p class="mt-6 text-lg leading-relaxed text-gray-600">
                    {{ $t('review.lead') }}
                </p>
                <ul class="mt-8 space-y-3">
                    @foreach ([
                        $t('review.point_1'),
                        $t('review.point_2'),
                        $t('review.point_3'),
                    ] as $line)
                        <li class="flex items-start gap-3 text-[15px] text-gray-700">
                            <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-600 text-white"><x-icon name="check" size="h-3 w-3" stroke="3" /></span>
                            {{ $line }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- ── 8. On the kitchen floor ────────────────────────────────────────
         Four staff-facing apps, as tabs that advance on their own until
         someone picks one.
    --}}
    @php
        $floor = [
            ...array_map(fn ($k, $icon) => [
                'key' => $k, 'icon' => $icon, 'tab' => $t("floor.{$k}_tab"), 'title' => $t("floor.{$k}_title"),
                'body' => $t("floor.{$k}_body"), 'addon' => $t("floor.{$k}_addon"),
            ], ['labels', 'clock', 'audits', 'learn'], ['printer', 'clock', 'clipboard', 'academic']),
        ];
    @endphp
    <section class="border-t border-gray-200 bg-gray-50/70 py-20 lg:py-28"
             x-data="{
                tabs: @js(array_column($floor, 'key')), tab: 'labels', auto: true,
                pick(k) { this.tab = k; this.auto = false },
                run() { setInterval(() => { if (this.auto) { const i = this.tabs.indexOf(this.tab); this.tab = this.tabs[(i + 1) % this.tabs.length] } }, 6000) },
             }"
             x-intersect.once="run()">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-3xl text-center">
                <p class="mk-kicker">{{ $t('floor.kicker') }}</p>
                <h2 class="display-2 mt-5 text-gray-950">{{ $t('floor.title') }}<br>{{ $t('floor.title_and') }} <span class="mk-accent">{{ $t('floor.title_accent') }}</span>.</h2>
                <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-gray-600">
                    {{ $t('floor.lead') }}
                </p>
            </div>

            <div class="mx-auto mt-10 flex max-w-2xl flex-wrap justify-center gap-1 rounded-full border border-gray-200 bg-white p-1 shadow-e1" role="tablist" aria-label="Staff apps">
                @foreach ($floor as $f)
                    <button type="button" role="tab" @click="pick('{{ $f['key'] }}')"
                            :aria-selected="tab === '{{ $f['key'] }}'"
                            class="flex min-h-[44px] items-center gap-2 rounded-full px-4 text-sm font-semibold transition-colors"
                            :class="tab === '{{ $f['key'] }}' ? 'bg-brand-600 text-white shadow-btn' : 'text-gray-600 hover:text-gray-950'">
                        <x-icon :name="$f['icon']" size="h-4 w-4" />
                        {{ $f['tab'] }}
                    </button>
                @endforeach
            </div>

            <div class="mt-10 grid items-center gap-10 lg:grid-cols-2">
                {{-- Copy --}}
                <div class="relative min-h-[15rem]">
                    @foreach ($floor as $f)
                        <div x-show="tab === '{{ $f['key'] }}'" @if (! $loop->first) x-cloak @endif
                             x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-3"
                             role="tabpanel">
                            <span class="mk-icon-tile h-12 w-12"><x-icon :name="$f['icon']" size="h-6 w-6" /></span>
                            <h3 class="display-3 mt-5 text-gray-950">{{ $f['title'] }}</h3>
                            <p class="mt-4 max-w-lg text-lg leading-relaxed text-gray-600">{{ $f['body'] }}</p>
                            <p class="mt-5 inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-gray-600 ring-1 ring-gray-200">
                                <x-icon name="tag" size="h-3.5 w-3.5" class="text-brand-600" />
                                {{ $f['addon'] }}
                                @if ($f['key'] !== 'clock') · {{ $t('floor.included') }} @endif
                            </p>
                        </div>
                    @endforeach
                </div>

                {{-- Phone --}}
                <div class="flex justify-center" aria-hidden="true">
                    <div class="relative w-[280px] rounded-[2.5rem] border-[10px] border-gray-900 bg-white shadow-e4">
                        <div class="absolute left-1/2 top-0 z-10 h-5 w-24 -translate-x-1/2 rounded-b-2xl bg-gray-900"></div>
                        <div class="relative h-[500px] overflow-hidden rounded-[1.8rem] bg-gray-50">

                            {{-- Labels --}}
                            <div x-show="tab === 'labels'" x-transition.opacity.duration.400ms class="absolute inset-0 p-4 pt-8">
                                <p class="text-xs font-semibold text-gray-900">Morning prep</p>
                                <div class="mt-3 space-y-2">
                                    @foreach (['Sambal tumis', 'Marinated chicken', 'Peeled garlic'] as $k => $item)
                                        <div class="flex items-center justify-between rounded-control bg-white px-3 py-2.5 text-[12px] shadow-e1 ring-1 ring-gray-100 {{ $k === 0 ? 'ring-2 ring-brand-500' : '' }}">
                                            <span class="font-medium text-gray-900">{{ $item }}</span>
                                            <span class="text-gray-500">{{ [3, 1, 5][$k] }} d</span>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="mt-5 rounded-t-md bg-gray-800 px-3 py-2 text-[10px] font-medium text-gray-300">DL-888D · printing</div>
                                <div class="overflow-hidden">
                                    <div class="mk-print mx-2 rounded-b-md border border-t-0 border-dashed border-gray-300 bg-white p-3 shadow-e2">
                                        <p class="mk-display text-lg font-bold text-gray-950">SAMBAL TUMIS</p>
                                        <div class="mt-2 grid grid-cols-2 gap-1 text-[10px]">
                                            <span class="text-gray-500">Prepared</span><span class="font-semibold text-gray-900">28 Sep 09:12</span>
                                            <span class="text-gray-500">Use by</span><span class="font-semibold text-danger-700">01 Oct 09:12</span>
                                            <span class="text-gray-500">By</span><span class="font-semibold text-gray-900">Aina</span>
                                        </div>
                                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100"><div class="h-full w-full rounded-full bg-success-500"></div></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Clock-in --}}
                            <div x-show="tab === 'clock'" x-cloak x-transition.opacity.duration.400ms class="absolute inset-0 flex flex-col items-center p-4 pt-10 text-center"
                                 x-data="{
                                    cells: [], left: 30,
                                    fresh() { this.cells = Array.from({ length: 121 }, () => Math.random() > 0.5) },
                                    init() { this.fresh(); setInterval(() => { this.left--; if (this.left <= 0) { this.left = 30; this.fresh() } }, 180) }
                                 }">
                                <p class="text-xs font-semibold text-gray-900">Bangsar kiosk</p>
                                <p class="text-[11px] text-gray-500">Scan with your phone to clock in</p>
                                <div class="relative mt-6 rounded-surface bg-white p-3 shadow-e2 ring-1 ring-gray-200">
                                    <div class="grid grid-cols-11 gap-[2px]">
                                        <template x-for="(c, i) in cells" :key="i">
                                            <span class="h-3 w-3 rounded-[2px] transition-colors duration-300" :class="c ? 'bg-gray-900' : 'bg-white'"></span>
                                        </template>
                                    </div>
                                    @foreach (['left-2 top-2', 'right-2 top-2', 'left-2 bottom-2'] as $pos)
                                        <span class="absolute {{ $pos }} h-8 w-8 rounded-md border-[5px] border-gray-900 bg-white"></span>
                                    @endforeach
                                </div>
                                <div class="mt-6 flex items-center gap-3">
                                    <svg class="h-10 w-10 -rotate-90" viewBox="0 0 36 36">
                                        <circle cx="18" cy="18" r="15" fill="none" stroke="#e5e7eb" stroke-width="3"/>
                                        <circle cx="18" cy="18" r="15" fill="none" stroke="#0962ef" stroke-width="3" stroke-linecap="round"
                                                stroke-dasharray="94.2" :stroke-dashoffset="94.2 - (left / 30) * 94.2" class="transition-all duration-200"/>
                                    </svg>
                                    <p class="text-left text-[11px] leading-snug text-gray-600">New code in <span class="font-semibold tabular-nums text-gray-900" x-text="left + 's'"></span><br>Rotates on its own</p>
                                </div>
                                <div class="mk-pop mt-6 w-full rounded-control bg-success-50 px-3 py-2.5 text-left text-[11px] text-success-800 ring-1 ring-success-200">
                                    <span class="font-semibold">Faiz</span> clocked in · 08:57
                                </div>
                            </div>

                            {{-- Audits --}}
                            <div x-show="tab === 'audits'" x-cloak x-transition.opacity.duration.400ms class="absolute inset-0 p-4 pt-8"
                                 x-data="{ score: 0, init() { this.$watch('tab', v => { if (v === 'audits') this.go() }); } , go() { this.score = 0; const t = setInterval(() => { this.score += 3; if (this.score >= 93) { this.score = 93; clearInterval(t) } }, 30) } }">
                                <p class="text-xs font-semibold text-gray-900">ROSE audit · Bangsar</p>
                                <div class="mt-4 flex items-center gap-4">
                                    <div class="relative h-24 w-24 flex-none rounded-full" :style="`background: conic-gradient(#0962ef ${score * 3.6}deg, #e5e7eb 0)`">
                                        <div class="absolute inset-2 flex flex-col items-center justify-center rounded-full bg-gray-50">
                                            <span class="mk-display text-2xl font-bold tabular-nums text-gray-950" x-text="score + '%'"></span>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="rounded-full bg-warning-50 px-2.5 py-1 text-[11px] font-semibold text-warning-700 ring-1 ring-warning-200">Conditional pass</span>
                                        <p class="mt-2 text-[11px] text-gray-600">1 critical item failed<br>Re-audit due 12 Oct</p>
                                    </div>
                                </div>
                                <p class="mt-6 text-[11px] font-semibold uppercase tracking-wide text-gray-500">Corrective actions</p>
                                <div class="mt-2 space-y-2">
                                    @foreach ([['Chiller at 7°C, service it', 'Overdue', 'text-danger-700'], ['Hand-wash sign missing', 'Awaiting verification', 'text-warning-700'], ['Label on opened sauce', 'Done', 'text-success-700']] as [$what, $state, $tone])
                                        <div class="rounded-control bg-white px-3 py-2.5 shadow-e1 ring-1 ring-gray-100">
                                            <p class="text-[12px] font-medium text-gray-900">{{ $what }}</p>
                                            <p class="mt-0.5 text-[10px] font-semibold {{ $tone }}">{{ $state }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Learn --}}
                            <div x-show="tab === 'learn'" x-cloak x-transition.opacity.duration.400ms class="absolute inset-0 p-4 pt-8">
                                <p class="text-xs font-semibold text-gray-900">Quiz · Nasi Ayam Penyet SOP</p>
                                <p class="mt-3 text-[13px] font-medium leading-snug text-gray-900">How much sambal goes on a dine-in plate?</p>
                                <div class="mt-3 space-y-2">
                                    @foreach (['20 g', '30 g', '50 g'] as $k => $opt)
                                        <div class="flex items-center justify-between rounded-control px-3 py-2.5 text-[12px] ring-1 {{ $k === 1 ? 'bg-success-50 font-semibold text-success-800 ring-success-300' : 'bg-white text-gray-700 ring-gray-200' }}">
                                            {{ $opt }}
                                            @if ($k === 1)<x-icon name="check" size="h-4 w-4" stroke="2.6" />@endif
                                        </div>
                                    @endforeach
                                </div>
                                <p class="mt-6 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-gray-500"><x-icon name="trophy" size="h-3.5 w-3.5" /> Leaderboard</p>
                                <div class="mt-2 space-y-1.5">
                                    @foreach ([['Hana', 1240], ['Faiz', 1180], ['Aina', 960]] as $k => [$who, $pts])
                                        <div class="flex items-center gap-2 rounded-control bg-white px-3 py-2 text-[12px] shadow-e1 ring-1 ring-gray-100">
                                            <span class="mk-display w-4 font-bold text-brand-600">{{ $k + 1 }}</span>
                                            <span class="flex-1 font-medium text-gray-900">{{ $who }}</span>
                                            <span class="tabular-nums text-gray-500">{{ number_format($pts) }} pts</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── 9. Every module ────────────────────────────────────────────────
         A summary of the features page, in the same order and under the same
         titles. When one list changes the other has to move with it.
    --}}
    <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
        <div class="grid items-end gap-6 lg:grid-cols-2">
            <div>
                <p class="mk-kicker">{{ $t('modules.kicker') }}</p>
                <h2 class="display-2 mt-5 text-gray-950">{{ $t('modules.title') }} <span class="mk-accent">{{ $t('modules.title_accent') }}</span>.</h2>
            </div>
            <p class="text-lg leading-relaxed text-gray-600 lg:pb-2">
                {{ $t('modules.lead') }}
            </p>
        </div>


        <div class="mt-12 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-6">
            @foreach ($modules as $i => $m)
                @php $dark = in_array($m['tone'], ['brand', 'navy'], true); @endphp
                <article data-reveal-index="{{ $i % 6 }}"
                         @class([
                             'reveal group relative flex flex-col overflow-hidden rounded-surface border shadow-e1 transition-[box-shadow,transform] duration-300 hover:-translate-y-1 hover:shadow-e3',
                             $m['span'],
                             'border-brand-700 bg-brand-600' => $m['tone'] === 'brand',
                             'border-navy-800 bg-navy-900' => $m['tone'] === 'navy',
                             'border-gray-200 bg-white' => ! $dark,
                         ])>
                    @if ($m['tone'] === 'photo')
                        <div class="overflow-hidden">
                            <picture>
                                <source srcset="{{ asset($m['photo'] . '.webp') }}" type="image/webp">
                                <img src="{{ asset($m['photo'] . '.jpg') }}" alt="{{ $m['alt'] }}" width="1200" height="560" loading="lazy" decoding="async"
                                     class="aspect-[15/7] w-full object-cover transition-transform duration-700 group-hover:scale-105">
                            </picture>
                        </div>
                    @endif
                    <div class="flex flex-1 flex-col p-6">
                        <div class="flex items-center justify-between gap-3">
                            <span @class([
                                'flex h-10 w-10 items-center justify-center rounded-control',
                                'bg-white/15 text-white' => $dark,
                                'bg-brand-50 text-brand-700' => ! $dark,
                            ])>
                                <x-icon :name="$m['icon']" size="h-5 w-5" />
                            </span>
                            <span @class([
                                'mk-mono rounded-full px-2 py-0.5 text-[10px] font-medium uppercase tracking-wider',
                                'bg-white/15 text-white' => $dark,
                                'bg-gray-100 text-gray-600' => ! $dark,
                            ])>{{ $m['tag'] }}</span>
                        </div>
                        <h3 @class(['mt-4 text-base font-semibold tracking-tight', 'text-white' => $dark, 'text-gray-950' => ! $dark])>{{ $m['title'] }}</h3>
                        <p @class(['mt-2 text-sm leading-relaxed', 'text-brand-50' => $m['tone'] === 'brand', 'text-gray-300' => $m['tone'] === 'navy', 'text-gray-600' => ! $dark])>{{ $m['desc'] }}</p>

                        @if ($dark)
                            {{-- A few rows of the thing itself, so a coloured card is not just a colour. --}}
                            <ul class="mt-auto space-y-2 pt-6" aria-hidden="true">
                                @foreach ($m['tone'] === 'brand'
                                    ? [['Chicken thigh, boneless', 'Matched'], ['Cooking oil 17 kg', 'Matched'], ['Coriander leaf', 'Review']]
                                    : [['Sambal tumis', 'Use by 01 Oct'], ['Marinated chicken', 'Use by 29 Sep'], ['Peeled garlic', 'Use by 03 Oct']] as $k => [$row, $state])
                                    <li class="relative flex items-center justify-between overflow-hidden rounded-control bg-white/10 px-3 py-2 text-xs text-white ring-1 ring-white/10">
                                        <span class="font-medium">{{ $row }}</span>
                                        <span class="text-white/70">{{ $state }}</span>
                                        @if ($k === 0)<span class="mk-shimmer pointer-events-none absolute inset-0"></span>@endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-10 text-center">
            <a href="{{ route('features') }}" class="btn-secondary group">
                {{ $t('modules.cta') }}
                <x-icon name="arrow-right" size="h-4 w-4" class="transition-transform group-hover:translate-x-0.5" />
            </a>
        </div>
    </section>

    {{-- ── 10. Getting started ────────────────────────────────────────── --}}
    <section class="border-y border-gray-200 bg-gray-50/70 py-20 lg:py-28">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <p class="mk-kicker">{{ $t('steps.kicker') }}</p>
                <h2 class="display-2 mt-5 text-gray-950">{{ $t('steps.title') }} <span class="mk-accent">{{ $t('steps.title_accent') }}</span>.</h2>
                <p class="mx-auto mt-5 max-w-xl text-lg text-gray-600">{{ $t('steps.lead') }}</p>
            </div>

            @php
                $steps = [
                    [$t('steps.1_title'), $t('steps.1_body'), 'users'],
                    [$t('steps.2_title'), $t('steps.2_body'), 'ingredient'],
                    [$t('steps.3_title'), $t('steps.3_body'), 'chart'],
                ];
            @endphp
            <ol class="relative mt-14 grid gap-6 md:grid-cols-3">
                <span aria-hidden="true" class="absolute left-[16%] right-[16%] top-7 hidden border-t-2 border-dashed border-brand-200 md:block"></span>
                @foreach ($steps as $i => [$title, $desc, $icon])
                    <li data-reveal-index="{{ $i }}" class="reveal relative text-center">
                        <span class="relative mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-brand-600 text-white shadow-btn ring-8 ring-gray-50">
                            <x-icon :name="$icon" size="h-6 w-6" />
                            <span class="mk-mono absolute -right-1 -top-1 flex h-6 w-6 items-center justify-center rounded-full bg-navy-950 text-[11px] font-medium text-white">{{ $i + 1 }}</span>
                        </span>
                        <h3 class="mt-5 text-lg font-semibold tracking-tight text-gray-950">{{ $title }}</h3>
                        <p class="mx-auto mt-2 max-w-xs text-sm leading-relaxed text-gray-600">{{ $desc }}</p>
                    </li>
                @endforeach
            </ol>
            <div class="mt-12 text-center">
                <a href="{{ route('saas.register') }}" class="btn-primary btn-lg">{{ $t('cta.trial') }}</a>
            </div>
        </div>
    </section>

    {{-- ── 11. Testimonials ─────────────────────────────────────────────
         See the CLAIMS note at the top: these need real attribution and
         permission, or the section should go. Do not add more like them.
    --}}
    <section class="py-20 lg:py-28">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <p class="mk-kicker">{{ $t('quotes.kicker') }}</p>
            <h2 class="display-2 mt-5 max-w-2xl text-gray-950">{{ $t('quotes.title') }} <span class="mk-accent">{{ $t('quotes.title_accent') }}</span>.</h2>
            @php
                $testimonials = [
                    ['quote' => $t('quotes.1'), 'name' => 'Ahmad R.', 'role' => $t('quotes.1_role'), 'place' => 'Kuala Lumpur'],
                    ['quote' => $t('quotes.2'), 'name' => 'Sarah L.', 'role' => $t('quotes.2_role'), 'place' => 'Penang'],
                    ['quote' => $t('quotes.3'), 'name' => 'David T.', 'role' => $t('quotes.3_role'), 'place' => 'Johor Bahru'],
                ];
            @endphp
            <div class="mt-12 grid gap-5 md:grid-cols-3">
                @foreach ($testimonials as $i => $q)
                    <figure data-reveal-index="{{ $i }}" class="reveal mk-card flex flex-col">
                        <span class="mk-display text-5xl leading-none text-brand-200" aria-hidden="true">&ldquo;</span>
                        <blockquote class="mt-2 flex-1 text-[15px] leading-relaxed text-gray-800">{{ $q['quote'] }}</blockquote>
                        <figcaption class="mt-6 flex items-center gap-3 border-t border-gray-100 pt-4">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white" aria-hidden="true">{{ mb_substr($q['name'], 0, 1) }}</span>
                            <span>
                                <span class="block text-sm font-semibold text-gray-950">{{ $q['name'] }}</span>
                                <span class="block text-xs text-gray-600">{{ $q['role'] }}, {{ $q['place'] }}</span>
                            </span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── 12. Pricing teaser ─────────────────────────────────────────────
         From the plans table, the same rows checkout charges.
    --}}
    @if ($plans->isNotEmpty())
        <section class="relative overflow-hidden border-t border-gray-200 bg-gray-50/70 py-20 lg:py-28">
            <div aria-hidden="true" class="mk-grid-bg pointer-events-none absolute inset-0"></div>
            <div class="relative mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="mk-kicker">{{ $t('plans.kicker') }}</p>
                    <h2 class="display-2 mt-5 text-gray-950">{{ $t('plans.title') }}<br><span class="mk-accent">{{ $t('plans.title_accent') }}</span> {{ $t('plans.title_end') }}</h2>
                    <p class="mx-auto mt-5 max-w-xl text-lg text-gray-600">{{ $t('plans.lead') }}</p>
                </div>

                <div class="mt-12 grid items-stretch gap-5 md:grid-cols-3">
                    @foreach ($plans as $i => $plan)
                        @php
                            $isFull = $plan->slug === 'full';
                            $isFree = (float) $plan->price_monthly === 0.0;
                            $price  = $isFree ? 0.0 : (float) ($book->suite((string) $plan->slug, (float) $plan->price_monthly) ?? $plan->price_monthly);
                            // A country page describes the plans in its language; English reads the plans table.
                            $desc   = $landing && isset($copy["plans.{$plan->slug}_desc"]) ? $t("plans.{$plan->slug}_desc") : $plan->description;
                        @endphp
                        <div data-reveal-index="{{ $i }}"
                             @class([
                                 'reveal relative flex flex-col rounded-panel p-7 transition-transform duration-300 hover:-translate-y-1',
                                 'bg-navy-950 text-white shadow-e4 ring-1 ring-navy-800' => $isFull,
                                 'border border-gray-200 bg-white shadow-e1' => ! $isFull,
                             ])>
                            @if ($isFull)
                                <span class="mk-mono absolute -top-3 left-7 rounded-full bg-brand-600 px-3 py-1 text-[10px] font-medium uppercase tracking-wider text-white shadow-btn">{{ $t('plans.everything') }}</span>
                            @endif
                            <p @class(['text-lg font-semibold', 'text-white' => $isFull, 'text-gray-950' => ! $isFull])>{{ $plan->name }}</p>
                            <p @class(['mt-1 min-h-[2.5rem] text-sm', 'text-gray-300' => $isFull, 'text-gray-600' => ! $isFull])>{{ $desc }}</p>
                            <p class="mt-5 flex items-baseline gap-1.5">
                                <span @class(['mk-display text-5xl font-semibold tracking-tight tabular-nums', 'text-white' => $isFull, 'text-gray-950' => ! $isFull])>{{ $book->format($price) }}</span>
                                <span @class(['text-sm', 'text-gray-300' => $isFull, 'text-gray-600' => ! $isFull])>{{ $isFree ? $t('plans.forever') : $t('plans.per_outlet') }}</span>
                            </p>
                            <div class="flex-1"></div>
                            <a href="{{ route('saas.register') }}" @class(['mt-7 w-full', 'btn-primary' => $isFull, 'btn-secondary' => ! $isFull])>
                                {{ $isFree ? $t('plans.start_free') : $t('plans.try') }}
                            </a>
                        </div>
                    @endforeach
                </div>
                <p class="mt-8 text-center text-sm text-gray-600">
                    {{ $t('plans.addons') }}
                    <a href="{{ route('pricing') }}" class="font-semibold text-brand-700 hover:text-brand-800">{{ $t('plans.work_out') }} &rarr;</a>
                </p>
                @unless ($book->isMyr())
                    <p class="mx-auto mt-3 max-w-xl text-center text-xs text-gray-500">{{ $t('plans.fx_note', [':currency' => $book->currency]) }}</p>
                @endunless
            </div>
        </section>
    @endif

    {{-- ── 13. FAQ ──────────────────────────────────────────────────────── --}}
    <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
        <div class="grid gap-12 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <p class="mk-kicker">{{ $t('faq.kicker') }}</p>
                <h2 class="display-2 mt-5 text-gray-950">{{ $t('faq.title') }} <span class="mk-accent">{{ $t('faq.title_accent') }}</span>.</h2>
                <p class="mt-5 text-gray-600">{{ $t('faq.lead') }}</p>
                <a href="{{ route('help.index') }}" class="btn-secondary mt-6">{{ $t('faq.help') }}</a>
            </div>
            @php
                $faqs = array_map(fn ($n) => [$t("faq.{$n}_q"), $t("faq.{$n}_a")], range(1, 7));
            @endphp
            <div class="border-t border-gray-200 lg:col-span-8">
                @foreach ($faqs as [$q, $a])
                    <details class="mk-faq group">
                        <summary>{{ $q }}<span class="mk-faq-plus" aria-hidden="true"></span></summary>
                        <p class="-mt-1 max-w-prose pb-5 text-[15px] leading-relaxed text-gray-600">{{ $a }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── 14. Close ──────────────────────────────────────────────────────
         Directly above the dark footer, so the page ends on one dark block.
    --}}
    <section class="relative overflow-hidden bg-navy-950">
        <div aria-hidden="true" class="mk-grid-bg-dark pointer-events-none absolute inset-0"></div>
        <div aria-hidden="true" class="pointer-events-none absolute left-1/2 top-0 h-80 w-[40rem] -translate-x-1/2 rounded-full bg-brand-600/25 blur-3xl"></div>
        <div class="relative mx-auto max-w-4xl px-4 py-24 text-center sm:px-6 lg:px-8 lg:py-32">
            <h2 class="display-1 text-white">{{ $t('close.title') }}<br><span class="mk-accent mk-accent-dark">{{ $t('close.title_accent') }}</span>.</h2>
            <p class="mx-auto mt-6 max-w-xl text-lg leading-relaxed text-gray-300">
                {{ $t('close.lead') }}
            </p>
            <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('saas.register') }}" class="btn-primary btn-lg group">
                    {{ $t('cta.trial') }}
                    <x-icon name="arrow-right" size="h-4 w-4" class="transition-transform group-hover:translate-x-0.5" />
                </a>
                <a href="{{ route('pricing') }}" class="btn-on-dark btn-lg">{{ $t('cta.pricing') }}</a>
            </div>
            <p class="mt-6 text-sm text-gray-400">{{ $t('close.note') }}</p>
        </div>
    </section>
</div>
