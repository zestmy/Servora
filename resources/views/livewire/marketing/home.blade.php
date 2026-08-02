{{--
    Servora marketing home.

    ── IMAGES ───────────────────────────────────────────────────────────────
    All photography is now real and self-hosted from public/images/marketing,
    served as WebP with a JPEG fallback. The picsum.photos placeholders are
    gone — nothing here depends on a third-party image host any more.

    A module card takes a photo by setting `'tone' => 'photo'` and
    `'photo' => 'images/marketing/<name>'`, with `<name>.webp` and
    `<name>.jpg` sitting in that directory. Card art is 1200x560 (15:7).

    ── CLAIMS ───────────────────────────────────────────────────────────────
    The three testimonials are carried over verbatim from the previous
    version. They are unattributed to real, verifiable customers. Either
    replace them with real quotes and permission, or remove the section.
    Malaysian advertising rules treat invented testimonials as deceptive.
--}}
<div>

    {{-- ── 1. Hero ─────────────────────────────────────────────────────────
         Asymmetric split. Four text elements exactly: eyebrow, headline,
         subtext, CTA pair. No tagline under the buttons, no trust strip
         inside the hero — the business-type strip lives in its own section
         directly below.
    --}}
    <section class="relative overflow-hidden bg-white">
        {{-- Soft brand wash, well below the content. Not an AI mesh-gradient
             blob: one tint, one direction, no glow. --}}
        <div aria-hidden="true"
             class="pointer-events-none absolute inset-x-0 top-0 h-[520px]
                    bg-gradient-to-b from-brand-50/70 via-white to-white"></div>

        <div class="relative mx-auto max-w-6xl px-4 pb-16 pt-16 sm:px-6 lg:px-8 lg:pb-24 lg:pt-24">
            <div class="grid items-center gap-12 lg:grid-cols-12 lg:gap-16">

                <div class="lg:col-span-7">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-700">
                        AI-powered restaurant operations
                    </p>

                    <h1 class="display-1 mt-4 text-gray-950">
                        Know your food cost<br class="hidden sm:block">
                        before month end.
                    </h1>

                    <p class="body-lg mt-6">
                        AI reads your supplier invoices and reviews your numbers weekly, so costing,
                        purchasing and stock stay current without the spreadsheet.
                    </p>

                    <div class="mt-9 flex flex-wrap items-center gap-3">
                        <a href="{{ route('saas.register') }}" class="btn-primary btn-lg">
                            Start {{ $trialDays }}-day free trial
                        </a>
                        <a href="{{ route('features') }}" class="btn-secondary btn-lg">
                            See features
                        </a>
                    </div>
                </div>

                {{-- Real photography. Cropped 4:5 from a 3:2 original, framed right
                     so the chef and the tablet carry the frame. WebP first, JPEG
                     fallback; this is the LCP element, hence fetchpriority. --}}
                <div class="lg:col-span-5">
                    <div class="relative overflow-hidden rounded-panel border border-gray-200 shadow-e4">
                        <picture>
                            <source srcset="{{ asset('images/marketing/hero-kitchen.webp') }}" type="image/webp">
                            <img src="{{ asset('images/marketing/hero-kitchen.jpg') }}"
                                 alt="A head chef reviewing figures on a tablet during service"
                                 width="853" height="1066" fetchpriority="high" decoding="async"
                                 class="aspect-[4/5] w-full object-cover">
                        </picture>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── 2. Who it is for ────────────────────────────────────────────────
         The trust strip, in its own section under the hero. Marquee on
         narrow screens so the full list is reachable without a wrapped,
         ragged block; static from md up where it simply fits.
    --}}
    <section class="border-y border-gray-200 bg-white py-8" aria-label="Business types served">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            {{-- Deliberately NOT an eyebrow. The hero directly above already
                 uses one, and stacking a second wide-tracked caps label here
                 is what gives every generated page the same rhythm. --}}
            <h2 class="text-center text-sm font-medium text-gray-500">
                Built for every kind of F&amp;B operation
            </h2>

            @php
                $types = ['Restaurants', 'Cafes', 'Cloud Kitchens', 'Catering', 'Bakeries', 'Bars &amp; Pubs', 'Food Courts', 'Hotels'];
            @endphp

            <div class="mask-edges mt-6 overflow-hidden md:mask-none">
                <ul class="flex w-max animate-marquee items-center gap-10 md:w-full md:animate-none md:justify-center md:gap-x-8 md:gap-y-3 md:flex-wrap">
                    {{-- Duplicated once so the marquee loop has no visible seam.
                         The copy is aria-hidden so it is not read out twice. --}}
                    @foreach ([false, true] as $isDuplicate)
                        @foreach ($types as $type)
                            <li class="whitespace-nowrap text-sm font-medium text-gray-700 {{ $isDuplicate ? 'md:hidden' : '' }}"
                                @if ($isDuplicate) aria-hidden="true" @endif>
                                {!! $type !!}
                            </li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- ── 3. The problem ──────────────────────────────────────────────────
         Full-width tinted band, single column, centred. Different layout
         family from everything around it and no eyebrow.
    --}}
    <section class="bg-brand-50/60 py-20 lg:py-28">
        <div class="mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
            <h2 class="display-2 text-gray-950">
                Most kitchens find out the margin was wrong once the month is already closed.
            </h2>
            <p class="mx-auto mt-6 max-w-prose text-lg leading-relaxed text-gray-700">
                Supplier prices move weekly. Recipes change. Portions drift. By the time the
                numbers are reconciled in a spreadsheet, the month you could have fixed is gone.
                Servora keeps the cost of every dish current as the inputs change.
            </p>
        </div>
    </section>

    {{-- ── 4. AI ───────────────────────────────────────────────────────────
         Both capabilities below ship today:
           AiInvoiceExtractionService — vision extraction from invoice images
           AiAnalyticsService         — periodic review and recommended actions
         Do not extend this section with capabilities that do not exist yet.

         No eyebrow here on purpose: the hero already used one and the
         headline states the topic without a label.
    --}}
    <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
        <div class="grid gap-12 lg:grid-cols-12 lg:gap-16">

            <div class="lg:col-span-5">
                <h2 class="display-2 text-gray-950">
                    The admin nobody has time for, done for you
                </h2>
                <p class="body-base mt-5">
                    Two jobs that quietly eat a manager's week are handled automatically:
                    typing in supplier invoices, and actually reading the numbers afterwards.
                </p>
                <a href="{{ route('features') }}" class="btn-secondary mt-7">
                    See features
                </a>
            </div>

            @php
                $aiCapabilities = [
                    ['icon'  => 'ingredient',
                     'title' => 'Photograph an invoice, get the line items',
                     'body'  => 'Snap the delivery invoice on your phone. Supplier, items, quantities and prices are read off the page and matched to your ingredients. When a price has moved since last time, you are told before it reaches your costings.'],
                    ['icon'  => 'sparkles',
                     'title' => 'A written review of your week',
                     'body'  => 'Revenue against last week, which outlet moved and why, the best and worst trading days, and two or three specific things worth doing next week. In plain sentences, not another chart to interpret.'],
                ];
            @endphp

            <div class="grid gap-4 lg:col-span-7">
                @foreach ($aiCapabilities as $i => $cap)
                    <article data-reveal-index="{{ $i }}" class="reveal card flex gap-5 p-6 sm:p-7">
                        <span class="flex h-11 w-11 flex-none items-center justify-center rounded-control bg-brand-50 text-brand-700">
                            <x-icon :name="$cap['icon']" size="h-5 w-5" />
                        </span>
                        <div>
                            <h3 class="text-base font-semibold tracking-tight text-gray-950">{{ $cap['title'] }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ $cap['body'] }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── 5. Modules ──────────────────────────────────────────────────────
         Bento with real rhythm: nine features, nine cells, mixed spans, and
         genuine visual variation rather than nine identical white boxes.
    --}}
    <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
        <div class="max-w-2xl">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-700">
                One platform
            </p>
            <h2 class="display-2 mt-4 text-gray-950">
                Every part of the operation, on the same set of numbers
            </h2>
        </div>

        @php
            // span = lg column span out of 6. Rows: 3+3 / 2+2+2 / 3+3 / 3+3.
            // Nine items, nine cells, no filler tile.
            $modules = [
                ['icon' => 'clipboard',  'title' => 'Recipe costing',       'span' => 'lg:col-span-3', 'tone' => 'photo',
                 'photo' => 'images/marketing/recipe-costing', 'alt' => 'A recipe and its quantities written out by hand on a notepad',
                 'desc' => 'Build a recipe once and watch its cost, yield and food-cost percentage update as ingredient prices move.'],

                ['icon' => 'ingredient', 'title' => 'Ingredient management', 'span' => 'lg:col-span-3', 'tone' => 'brand',
                 'desc' => 'UOM conversions, pack sizes, yield percentages and full cost history against every supplier you buy from.'],

                ['icon' => 'cart',       'title' => 'Purchasing and GRN',    'span' => 'lg:col-span-2', 'tone' => 'plain',
                 'desc' => 'Purchase order through delivery order to goods received, with approvals and PDF documents at each step.'],

                ['icon' => 'database',   'title' => 'Inventory control',     'span' => 'lg:col-span-2', 'tone' => 'plain',
                 'desc' => 'Stock takes, wastage, staff meals, par levels and transfers between outlets.'],

                ['icon' => 'currency',   'title' => 'Sales tracking',        'span' => 'lg:col-span-2', 'tone' => 'plain',
                 'desc' => 'Daily sales by meal period, Z-report capture and CSV import from your POS.'],

                ['icon' => 'chart',      'title' => 'Reports and P&L',       'span' => 'lg:col-span-3', 'tone' => 'photo',
                 'photo' => 'images/marketing/reports-pnl', 'alt' => 'An income statement showing revenue, cost of goods and gross profit',
                 'desc' => 'Monthly cost summaries, COGS, labour cost and exports your accountant will accept without rework.'],

                ['icon' => 'academic',   'title' => 'Staff training',        'span' => 'lg:col-span-3', 'tone' => 'plain',
                 'desc' => 'SOPs with step-by-step method, plating photos and training video, opened by QR code on any phone.'],

                ['icon' => 'sparkles',   'title' => 'AI analytics',          'span' => 'lg:col-span-3', 'tone' => 'plain',
                 'desc' => 'Weekly and monthly reviews written for you: what moved, what caused it, and what to do next.'],

                ['icon' => 'building',   'title' => 'Multi-outlet',          'span' => 'lg:col-span-3', 'tone' => 'plain',
                 'desc' => 'Shared ingredients and recipes across sites, with data scoped per outlet and quick switching.'],
            ];
        @endphp

        <div class="mt-12 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-6">
            @foreach ($modules as $i => $m)
                <article data-reveal-index="{{ $i }}"
                         class="reveal card card-hover group flex flex-col overflow-hidden {{ $m['span'] }}
                                {{ $m['tone'] === 'brand' ? 'bg-brand-600 border-brand-700' : '' }}">

                    @if ($m['tone'] === 'photo')
                        {{-- WebP with a JPEG fallback. --}}
                        <picture>
                            <source srcset="{{ asset($m['photo'] . '.webp') }}" type="image/webp">
                            <img src="{{ asset($m['photo'] . '.jpg') }}"
                                 alt="{{ $m['alt'] }}" width="1200" height="560" loading="lazy" decoding="async"
                                 class="aspect-[15/7] w-full object-cover">
                        </picture>
                    @endif

                    <div class="flex flex-1 flex-col p-6">
                        <span @class([
                            'flex h-10 w-10 items-center justify-center rounded-control',
                            'bg-white/15 text-white' => $m['tone'] === 'brand',
                            'bg-brand-50 text-brand-700' => $m['tone'] !== 'brand',
                        ])>
                            <x-icon :name="$m['icon']" size="h-5 w-5" />
                        </span>

                        <h3 @class([
                            'mt-4 text-base font-semibold tracking-tight',
                            'text-white' => $m['tone'] === 'brand',
                            'text-gray-950' => $m['tone'] !== 'brand',
                        ])>{{ $m['title'] }}</h3>

                        <p @class([
                            'mt-2 text-sm leading-relaxed',
                            'text-brand-50' => $m['tone'] === 'brand',
                            'text-gray-600' => $m['tone'] !== 'brand',
                        ])>{{ $m['desc'] }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    {{-- ── 6. Getting started ──────────────────────────────────────────────
         Connected vertical list, not three numbered cards. The verb is the
         label; there is no "Step 1 / Stage 2" scaffolding.
    --}}
    <section class="border-y border-gray-200 bg-gray-50 py-20 lg:py-28">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-12 lg:grid-cols-12 lg:gap-16">

                <div class="lg:col-span-4">
                    <h2 class="display-2 text-gray-950">Live in an afternoon</h2>
                    <p class="body-base mt-5">
                        No implementation project, no consultant. Most operators are costing
                        their first recipes the same day they sign up.
                    </p>
                    <a href="{{ route('saas.register') }}" class="btn-primary mt-7">
                        Start {{ $trialDays }}-day free trial
                    </a>
                </div>

                @php
                    $steps = [
                        ['title' => 'Create your account',
                         'desc'  => 'Company name and email. No card, no sales call.'],
                        ['title' => 'Load ingredients and recipes',
                         'desc'  => 'Import a supplier price list, or add items as you go. Costs calculate the moment an ingredient has a price.'],
                        ['title' => 'Work the month normally',
                         'desc'  => 'Raise POs, receive deliveries, record sales and stock takes. The reports build themselves from what you already do.'],
                    ];
                @endphp

                <ol class="relative lg:col-span-8">
                    {{-- Connector runs behind the markers rather than between
                         cards, so it never breaks when a step wraps. --}}
                    <span aria-hidden="true"
                          class="absolute left-[15px] top-3 bottom-3 w-px bg-gray-300"></span>

                    @foreach ($steps as $i => $step)
                        <li data-reveal-index="{{ $i }}" class="reveal relative flex gap-5 pb-10 last:pb-0">
                            <span class="relative z-sticky mt-0.5 flex h-8 w-8 flex-none items-center justify-center
                                         rounded-full bg-brand-600 text-white shadow-btn">
                                <x-icon name="check" size="h-4 w-4" stroke="2.4" />
                            </span>
                            <div class="pt-1">
                                <h3 class="text-base font-semibold tracking-tight text-gray-950">{{ $step['title'] }}</h3>
                                <p class="mt-1.5 max-w-prose text-sm leading-relaxed text-gray-600">{{ $step['desc'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>

    {{-- ── 7. What you get ─────────────────────────────────────────────────
         Replaces the previous stats band. The old figures (500+ recipes,
         12% average cost reduction, 10x faster reporting) were not measured
         and could not be substantiated, so they are gone. These four are
         statements about the product that are true by inspection.
    --}}
    <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:px-8 lg:py-24">
        @php
            $facts = [
                ['icon' => 'device',  'title' => 'Works on any device',
                 'body' => 'Browser based. Office desktop, kitchen tablet or a phone on the pass.'],
                ['icon' => 'building','title' => 'Multi-outlet from day one',
                 'body' => 'Run several sites off shared recipes without duplicating the data.'],
                ['icon' => 'printer', 'title' => 'HACCP label printing',
                 'body' => 'Prep and expiry labels straight from your recipes, with a print log.'],
                ['icon' => 'clock',   'title' => "Free for {$trialDays} days",
                 'body' => 'Full product, no card required, cancel from inside the app.'],
            ];
        @endphp

        <dl class="grid gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($facts as $i => $fact)
                <div data-reveal-index="{{ $i }}" class="reveal">
                    <dt class="flex items-center gap-3 text-base font-semibold tracking-tight text-gray-950">
                        <x-icon :name="$fact['icon']" size="h-5 w-5" class="flex-none text-brand-600" />
                        {{ $fact['title'] }}
                    </dt>
                    <dd class="mt-2.5 text-sm leading-relaxed text-gray-600">{{ $fact['body'] }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- ── 8. Testimonials ─────────────────────────────────────────────────
         See the note at the top of this file: these quotes need real
         attribution and permission, or the section should be removed.
    --}}
    <section class="border-t border-gray-200 bg-gray-50 py-20 lg:py-28">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            {{-- No eyebrow: the headline already says what the section is. --}}
            <h2 class="display-2 max-w-2xl text-gray-950">
                What operators say after the first quarter
            </h2>

            @php
                $testimonials = [
                    ['quote' => 'Servora helped us cut food costs by 12% in 3 months. The recipe costing alone is worth it.',
                     'name'  => 'Ahmad R.', 'role' => 'Restaurant Owner', 'place' => 'Kuala Lumpur'],
                    ['quote' => 'Finally, a system that understands F&B operations. The PO to GRN flow saved us hours every week.',
                     'name'  => 'Sarah L.', 'role' => 'Operations Manager', 'place' => 'Penang'],
                    ['quote' => 'The LMS module transformed our staff training. New hires get up to speed in half the time.',
                     'name'  => 'David T.', 'role' => 'F&B Group Director', 'place' => 'Johor Bahru'],
                ];
            @endphp

            <div class="mt-12 grid gap-6 md:grid-cols-3">
                @foreach ($testimonials as $i => $t)
                    <figure data-reveal-index="{{ $i }}" class="reveal card flex flex-col p-6">
                        <blockquote class="flex-1 text-[15px] leading-relaxed text-gray-800">
                            &ldquo;{{ $t['quote'] }}&rdquo;
                        </blockquote>
                        <figcaption class="mt-6 border-t border-gray-100 pt-4">
                            <p class="text-sm font-semibold text-gray-950">{{ $t['name'] }}</p>
                            <p class="mt-0.5 text-xs text-gray-600">{{ $t['role'] }}, {{ $t['place'] }}</p>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── 9. Close ────────────────────────────────────────────────────────
         The one dark moment on the page, sitting directly above the dark
         footer so the page ends on a single continuous block rather than
         flipping theme mid-scroll.
    --}}
    <section class="bg-gray-950">
        <div class="mx-auto max-w-4xl px-4 py-20 text-center sm:px-6 lg:px-8 lg:py-28">
            <h2 class="display-2 text-white">
                Start with one recipe. See where the margin actually goes.
            </h2>
            <p class="mx-auto mt-5 max-w-prose text-lg leading-relaxed text-gray-300">
                Set up takes a few minutes and the trial is the full product.
            </p>
            <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('saas.register') }}" class="btn-primary btn-lg">
                    Start {{ $trialDays }}-day free trial
                </a>
                <a href="{{ route('pricing') }}" class="btn-on-dark btn-lg">
                    View pricing
                </a>
            </div>
        </div>
    </section>
</div>
