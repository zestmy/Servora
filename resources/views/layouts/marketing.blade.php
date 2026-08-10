<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? $title . ' | Servora' : 'Servora' }}</title>
    <meta name="description" content="{{ $description ?? 'Servora is AI-powered restaurant operations software for F&B: AI reads your supplier invoices and reviews your numbers weekly, alongside recipe costing, purchasing, inventory and staff training.' }}">

    {{-- Social cards. Previously absent, so every shared link rendered bare. --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Servora">
    <meta property="og:title" content="{{ $title ?? 'Servora' }}">
    <meta property="og:description" content="{{ $description ?? 'AI-powered restaurant operations: costing, purchasing, inventory and training in one place.' }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('images/servora-logo-black.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="{{ url()->current() }}">

    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-white text-gray-900 antialiased">

    {{-- Keyboard users land here first and can jump the nav. --}}
    <a href="#main"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-toast
              focus:inline-flex focus:items-center focus:rounded-control focus:bg-brand-600
              focus:px-4 focus:py-2.5 focus:text-sm focus:font-semibold focus:text-white focus:shadow-btn">
        Skip to content
    </a>

    @php
        $headerPages = \App\Models\Page::inHeader()->get();
        $footerPages = \App\Models\Page::inFooter()->get();

        // Nav labels are deliberately unchanged. Muscle memory and analytics
        // both key off them, so the one-line fix is the breakpoint and the
        // type scale, not the wording.
        $navLinks = [
            ['route' => 'features',         'label' => 'Features'],
            ['route' => 'pricing',          'label' => 'Pricing'],
            ['route' => 'marketplace',      'label' => 'Marketplace'],
            // Third, not last: it is the only item here somebody can use
            // without deciding anything first, and the nav is read left to
            // right until something looks free.
            ['route' => 'tools.index',      'label' => 'Free Tools'],
            ['route' => 'for-suppliers',    'label' => 'For Suppliers'],
            ['route' => 'referral.program', 'label' => 'Refer & Earn'],
        ];
    @endphp

    {{-- ── Nav ──────────────────────────────────────────────────────────────
         Sticky, 64px, single line from lg up. Below lg it is a sheet: the
         full set plus any CMS header pages never fit a phone width, and the
         old 192px dropdown truncated them.
    --}}
    <header class="sticky top-0 z-sticky border-b border-gray-200/70 bg-white/85 backdrop-blur-md"
            x-data="{ open: false }">
        <nav class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-6 px-4 sm:px-6 lg:px-8"
             aria-label="Primary">

            <a href="{{ route('marketing.home') }}" class="flex-shrink-0" aria-label="Servora home">
                <img src="{{ asset('images/servora-logo-black.png') }}" alt="Servora" class="h-8 w-auto">
            </a>

            <div class="hidden items-center gap-7 lg:flex">
                @foreach ($navLinks as $link)
                    @php $isActive = request()->routeIs($link['route']); @endphp
                    <a href="{{ route($link['route']) }}"
                       @class([
                           'text-[13px] font-medium transition-colors',
                           'text-brand-700' => $isActive,
                           'text-gray-600 hover:text-gray-900' => ! $isActive,
                       ])
                       @if ($isActive) aria-current="page" @endif>
                        {{ $link['label'] }}
                    </a>
                @endforeach

                @foreach ($headerPages as $hp)
                    <a href="{{ $hp->url() }}" target="{{ $hp->linkTarget() }}"
                       class="text-[13px] font-medium text-gray-600 transition-colors hover:text-gray-900">
                        {{ $hp->title }}
                    </a>
                @endforeach
            </div>

            <div class="hidden items-center gap-3 lg:flex">
                <a href="{{ route('login') }}"
                   class="text-[13px] font-medium text-gray-600 transition-colors hover:text-gray-900">
                    Log In
                </a>
                <a href="{{ route('saas.register') }}" class="btn-primary btn-sm">
                    Start Free Trial
                </a>
            </div>

            {{-- Mobile trigger --}}
            <button type="button" @click="open = ! open"
                    class="btn-ghost btn-icon lg:hidden"
                    :aria-expanded="open ? 'true' : 'false'"
                    aria-controls="mobile-nav"
                    aria-label="Toggle navigation">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path x-show="! open" stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16"/>
                    <path x-show="open" x-cloak stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/>
                </svg>
            </button>
        </nav>

        {{-- Mobile sheet --}}
        <div id="mobile-nav" x-show="open" x-cloak @click.away="open = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="border-t border-gray-200 bg-white lg:hidden">
            <div class="mx-auto max-w-6xl px-4 py-4 sm:px-6">
                <div class="stack">
                    @foreach ($navLinks as $link)
                        <a href="{{ route($link['route']) }}"
                           class="block py-3 text-sm font-medium text-gray-700 hover:text-brand-700">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                    @foreach ($headerPages as $hp)
                        <a href="{{ $hp->url() }}" target="{{ $hp->linkTarget() }}"
                           class="block py-3 text-sm font-medium text-gray-700 hover:text-brand-700">
                            {{ $hp->title }}
                        </a>
                    @endforeach
                    <a href="{{ route('login') }}"
                       class="block py-3 text-sm font-medium text-gray-700 hover:text-brand-700">
                        Log In
                    </a>
                </div>
                <a href="{{ route('saas.register') }}" class="btn-primary mt-4 w-full">
                    Start Free Trial
                </a>
            </div>
        </div>
    </header>

    <main id="main">
        {{ $slot }}
    </main>

    {{-- ── Footer ───────────────────────────────────────────────────────── --}}
    <footer class="mt-24 bg-gray-950 text-gray-400">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8">

            @php
                /* The static columns as data.

                   The Product column had quietly become a dumping ground —
                   nine links, four of them tools — while Company held one and
                   Legal two. Columns of 9/1/2 do not read as columns; they read
                   as a list that ran out of room. Grouping by what a visitor is
                   trying to DO puts four or five in each. */
                $footerColumns = [
                    'Product' => [
                        ['route' => 'features',      'label' => 'Features'],
                        ['route' => 'pricing',       'label' => 'Pricing'],
                        ['route' => 'marketplace',   'label' => 'Marketplace'],
                        ['route' => 'for-suppliers', 'label' => 'For Suppliers'],
                    ],
                    'Free tools' => [
                        ['route' => 'tools.recipe-cost', 'label' => 'Recipe Cost Calculator'],
                        ['route' => 'tools.food-cost',   'label' => 'Food Cost Calculator'],
                        ['route' => 'tools.menu-matrix', 'label' => 'Menu Engineering Matrix'],
                        ['route' => 'tools.salary',      'label' => 'Salary Calculator'],
                        ['route' => 'tools.index',       'label' => 'All free tools', 'muted' => true],
                    ],
                ];

                /* One list per column, and `other` is the exact complement of
                   the two, so a CMS page can never fall out of the footer by
                   being given a slug nobody anticipated. */
                $companySlugs = ['about', 'about-us', 'contact', 'contact-us', 'careers'];
                $legalSlugs   = ['privacy-policy', 'privacy', 'terms', 'terms-of-use', 'terms-of-service', 'refund-policy'];

                $companyPages = $footerPages->filter(fn ($p) => in_array($p->slug, $companySlugs));
                $legalPages   = $footerPages->filter(fn ($p) => in_array($p->slug, $legalSlugs));
                $otherPages   = $footerPages->reject(fn ($p) => in_array($p->slug, array_merge($companySlugs, $legalSlugs)));
            @endphp

            {{-- Four equal link columns rather than three uneven ones. Brand
                 sits above them until there is room to put it alongside: two
                 by two on a tablet (three columns would strand the fourth on
                 a row of its own), all five in a line from lg. --}}
            <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-[1.6fr_repeat(4,1fr)]">

                <div class="max-w-xs md:col-span-2 lg:col-span-1">
                    <img src="{{ asset('images/servora-logo-white.png') }}" alt="Servora" class="h-8 w-auto">
                    <p class="mt-4 text-sm leading-relaxed">
                        Costing, purchasing, inventory and training for F&amp;B operators who need to know
                        their numbers before month end.
                    </p>
                    <a href="{{ route('saas.register') }}"
                       class="mt-5 inline-flex items-center gap-1.5 text-sm font-medium text-brand-300 transition-colors hover:text-brand-200">
                        Start your free trial
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                </div>

                @foreach ($footerColumns as $heading => $links)
                    <div>
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-white">{{ $heading }}</h2>
                        <ul class="mt-4 space-y-2.5 text-sm">
                            @foreach ($links as $link)
                                <li>
                                    <a href="{{ route($link['route']) }}"
                                       class="transition-colors hover:text-white {{ ($link['muted'] ?? false) ? 'text-gray-500' : '' }}">
                                        {{ $link['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach

                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-white">Company</h2>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        @foreach ($companyPages as $cp)
                            <li><a href="{{ $cp->url() }}" target="{{ $cp->linkTarget() }}" class="transition-colors hover:text-white">{{ $cp->title }}</a></li>
                        @endforeach

                        {{-- A referral programme is something you join, not a
                             product you buy — it reads oddly under Product. --}}
                        <li><a href="{{ route('referral.program') }}" class="transition-colors hover:text-white">Refer &amp; Earn</a></li>
                        <li><a href="{{ route('login') }}" class="transition-colors hover:text-white">Log in</a></li>
                    </ul>
                </div>

                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-white">Legal</h2>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        @forelse ($legalPages as $lp)
                            <li><a href="{{ $lp->url() }}" target="{{ $lp->linkTarget() }}" class="transition-colors hover:text-white">{{ $lp->title }}</a></li>
                        @empty
                            {{-- Placeholders, so the column is never empty on a
                                 fresh install and it is obvious what belongs
                                 here once the pages exist. --}}
                            <li><span class="text-gray-600">Privacy Policy</span></li>
                            <li><span class="text-gray-600">Terms of Service</span></li>
                        @endforelse

                        @foreach ($otherPages as $op)
                            <li><a href="{{ $op->url() }}" target="{{ $op->linkTarget() }}" class="transition-colors hover:text-white">{{ $op->title }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="mt-12 flex flex-col gap-3 border-t border-white/10 pt-6 text-sm sm:flex-row sm:items-center sm:justify-between">
                <p>{!! \App\Models\AppSetting::get('footer_copyright', '&copy; ' . date('Y') . ' Servora. All rights reserved.') !!}</p>
                <p class="text-gray-500">Made for F&amp;B operators in Malaysia.</p>
            </div>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
