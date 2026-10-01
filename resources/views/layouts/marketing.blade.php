<!DOCTYPE html>
@php
    // A country landing page (App\Models\LandingPage) passes its language
    // and its copy; the nav and footer labels read from it, English otherwise.
    $landing ??= null;
    $copy ??= null;
    $nav = fn (string $key, string $english) => $copy[$key] ?? $english;
@endphp
<html lang="{{ $landing?->locale ?? str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
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
    <meta property="og:image" content="{{ brand_asset('images/servora-logo-black.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="{{ url()->current() }}">
    @if (request()->routeIs('marketing.home', 'marketing.landing'))
        {{-- The home page and its translations point at each other, so a
             search engine shows each country its own language. --}}
        <link rel="alternate" hreflang="en" href="{{ route('marketing.home', ['lang' => 'en']) }}">
        <link rel="alternate" hreflang="x-default" href="{{ route('marketing.home') }}">
        @foreach (\App\Models\LandingPage::published()->get(['slug', 'locale']) as $alt)
            <link rel="alternate" hreflang="{{ $alt->locale }}" href="{{ route('marketing.landing', $alt->slug) }}">
        @endforeach
    @endif

    <link rel="icon" type="image/png" href="{{ brand_asset('favicon.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800|space-grotesk:500,600,700|jetbrains-mono:400,500&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>[x-cloak] { display: none !important; }</style>

    {{-- Count a number up when it scrolls into view. Inline and classic
         (not in the Vite module) because Livewire boots Alpine as soon as its
         own script tag is parsed, before deferred modules have run. --}}
    <script>
        window.mkCount = function (el, to, decimals = 0, ms = 1400) {
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const fmt = (v) => v.toLocaleString('en-MY', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
            if (reduce) { el.textContent = fmt(to); return; }
            const start = performance.now();
            const tick = (now) => {
                const t = Math.min(1, (now - start) / ms);
                el.textContent = fmt(to * (1 - Math.pow(1 - t, 3)));
                if (t < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        };
    </script>
</head>
<body class="mk bg-white text-gray-900 antialiased">

    {{-- Keyboard users land here first and can jump the nav. --}}
    <a href="#main"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-toast
              focus:inline-flex focus:items-center focus:rounded-control focus:bg-brand-600
              focus:px-4 focus:py-2.5 focus:text-sm focus:font-semibold focus:text-white focus:shadow-btn">
        Skip to content
    </a>

    @include('partials.marketing.header')

    {{-- Pages that end on a dark band pass flush, so it meets the footer.
         Everything else keeps air above it. --}}
    <main id="main" @class(["relative isolate", "pb-24" => ! ($flush ?? false)])>
        {{-- The grid the home page hero sits on, behind every other page's
             heading too. Pages that paint their own hero cover it. --}}
        @unless ($flush ?? false)
            <div aria-hidden="true" class="mk-grid-bg pointer-events-none absolute inset-x-0 top-0 -z-10 h-[480px]"></div>
        @endunless
        {{ $slot }}
    </main>

    @include('partials.marketing.footer')

    @livewireScripts

{{-- The delete confirmation gate. Inert until something on the page
     carries data-confirm-delete. See components/confirm-delete.blade.php. --}}
<x-confirm-delete />

</body>
</html>
