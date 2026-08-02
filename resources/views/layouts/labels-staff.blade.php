{{--
    Staff label app shell — built for a phone or a kitchen tablet held in
    one hand, not a desktop.

    Bottom navigation rather than a sidebar: thumbs reach the bottom of a
    screen, and a chef is usually holding the device one-handed with the
    other hand occupied. Targets are deliberately large and spaced — this is
    used with wet or gloved fingers.
--}}
@php
    // Same resolution the LMS layout uses, so a company that has branded its
    // training portal is branded here too without setting anything up twice.
    $brandCompany = app()->bound('currentCompany') ? app('currentCompany') : null;
    $brandName    = $brandCompany?->brand_name ?? $brandCompany?->name ?? 'Labels';
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Matches the header fill exactly. brand-600 left a visible seam between
         the OS status bar and the header on Android. --}}
    <meta name="theme-color" content="#0d5f61">
    {{-- Full-screen when saved to a home screen, which is how this is meant
         to be used day to day. --}}
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Labels">
    <link rel="manifest" href="{{ route('labels.staff.manifest') }}">
    {{-- iOS ignores the manifest's icons and uses this one. --}}
    <link rel="apple-touch-icon" href="{{ asset('labels-app/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('labels-app/icon-192.png') }}">
    <title>{{ $title ?? 'Labels' }} | {{ $brandName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        /* Stop the pull-to-refresh bounce turning a mis-swipe into a page
           reload mid-print. */
        html, body { overscroll-behavior-y: contain; }

        /*
         * Safe areas. viewport-fit=cover plus a translucent status bar means
         * the page starts at y=0 and runs UNDER the clock and battery — and
         * Android 15 draws PWAs edge-to-edge regardless. The coloured header
         * should fill that strip, but its CONTENT has to sit below it.
         */
        .safe-top {
            padding-top: calc(env(safe-area-inset-top, 0px) + 0.75rem);
            padding-bottom: 0.75rem;
        }

        /* Screens with no header (the PIN screen) need the inset themselves. */
        .safe-top-plain { padding-top: calc(env(safe-area-inset-top, 0px) + 0.75rem); }

        /* Respect the home-indicator area on phones. */
        .safe-bottom { padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 0.5rem); }

        /* Notches in landscape. Cheap insurance even though we lock portrait. */
        .safe-x {
            padding-left: env(safe-area-inset-left, 0px);
            padding-right: env(safe-area-inset-right, 0px);
        }

        /*
         * App shell: pinned to all four edges, scrolling in the middle.
         *
         * Deliberately NOT sized with a viewport unit. Both previous
         * attempts here failed on a real phone — first a `fixed bottom-0`
         * nav that sat at different heights depending on whether the page
         * scrolled, then `100dvh`, which still left a strip of background
         * below the bar. Anchoring to inset:0 asks the browser where its
         * edges are instead of computing a height and hoping it agrees.
         *
         * max-width plus auto margins keeps it centred on a tablet, since
         * inset:0 sets left and right to 0.
         */
        .app-shell {
            position: fixed;
            inset: 0;
            margin: 0 auto;
            max-width: 42rem;
            display: flex;
            flex-direction: column;
        }

        .app-scroll {
            flex: 1 1 auto;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
    </style>
</head>
{{-- overflow-hidden on the body: the shell scrolls, the page never does. --}}
<body class="h-full bg-gray-50 antialiased overflow-hidden">

{{-- Width and centring live in .app-shell so they can't fight the fixed
     positioning. --}}
<div class="app-shell bg-gray-50">

    @isset($staff)
        {{-- brand-700 rather than 600: white sits at 7.43:1 on it and the
             secondary line still clears AA at brand-100 (6.42:1), which it
             did not on the old indigo-200-on-indigo-600 pairing. --}}
        <header class="shrink-0 z-20 bg-brand-700 text-white px-4 safe-top safe-x flex items-center gap-3 justify-between">
            <div class="flex items-center gap-2.5 min-w-0">
                {{-- The white pill used to be unconditional here. It is the
                     right answer for dark artwork on this header and the
                     wrong one for a light logo, which reads fine bare. --}}
                <x-brand-mark :company="$brandCompany" surface="dark"
                              size="h-6" width="max-w-[64px]" :alt="$brandName" />
                <div class="min-w-0">
                    <p class="text-[11px] uppercase tracking-wider text-brand-100 truncate">{{ $outletName ?? $brandName }}</p>
                    <p class="text-sm font-semibold truncate">{{ $title ?? 'Labels' }}</p>
                </div>
            </div>
            {{-- White-alpha rather than a brand shade: this pill has to hold
                 up if the header fill is ever re-tinted per company. --}}
            <a href="{{ route('labels.staff.pin') }}" wire:navigate
               class="flex min-h-[2.75rem] items-center gap-2 pl-3 pr-2 rounded-full bg-white/15 active:bg-white/25">
                <span class="text-xs font-medium truncate max-w-[8rem]">{{ $staff->name }}</span>
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </a>
        </header>
    @endisset

    {{-- Without a header there is nothing to hold the status bar off the
         content, so the inset lands here instead. No bottom padding for the
         nav any more: the nav is in the flow below, not floating over this. --}}
    <main class="app-scroll px-3 pb-3 safe-x {{ isset($staff) ? 'pt-3' : 'safe-top-plain' }}">
        {{ $slot }}
    </main>

    @isset($staff)
        @php
            $tabs = [
                ['route' => 'labels.staff.print',    'label' => 'Print',
                 'icon'  => 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z'],
                ['route' => 'labels.staff.sets',     'label' => 'Sets',
                 'icon'  => 'M4 6h16M4 10h16M4 14h16M4 18h16'],
                ['route' => 'labels.staff.expiring', 'label' => 'Expiring',
                 'icon'  => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['route' => 'labels.staff.log',      'label' => 'Log',
                 'icon'  => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
            ];
        @endphp
        {{-- The active tab is marked three ways — a rule above it, a heavier
             stroke, and colour — because colour alone is the one signal a
             third of kitchen staff on a glare-washed screen will not catch,
             and it is also WCAG 1.4.1. Inactive moves off gray-400, which
             was 2.54:1 on this surface. --}}
        <nav class="shrink-0 z-20 bg-white border-t border-gray-200 safe-bottom safe-x">
            <div class="grid grid-cols-4">
                @foreach ($tabs as $tab)
                    @php $active = request()->routeIs($tab['route']) || request()->routeIs($tab['route'] . '.*'); @endphp
                    <a href="{{ route($tab['route']) }}" wire:navigate
                       @if ($active) aria-current="page" @endif
                       class="relative flex min-h-[3.25rem] flex-col items-center justify-center gap-0.5 py-2
                              {{ $active ? 'text-brand-700' : 'text-gray-500' }} active:bg-gray-50">
                        @if ($active)
                            <span aria-hidden="true" class="absolute inset-x-5 top-0 h-0.5 rounded-full bg-brand-700"></span>
                        @endif
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="{{ $active ? '2.2' : '1.8' }}">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $tab['icon'] }}"/>
                        </svg>
                        <span class="text-[11px] {{ $active ? 'font-semibold' : 'font-medium' }}">{{ $tab['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </nav>
    @endisset

    {{-- Install banner, in the flow directly above the nav rather than
         floating over it. Chrome fires beforeinstallprompt only when the app
         is installable, so this stays hidden otherwise — and once installed
         it never fires again. --}}
    <div id="pwa-install" class="hidden shrink-0 px-3 pb-3 safe-x">
        <div class="flex items-center gap-3 bg-gray-900 text-white rounded-xl px-4 py-3 shadow-lg">
            <span class="flex-1 text-sm">Add Labels to your home screen</span>
            <button id="pwa-install-go" class="px-3 py-1.5 bg-white text-gray-900 text-xs font-semibold rounded-lg">Add</button>
            <button id="pwa-install-no" class="text-gray-400 text-lg leading-none px-1" aria-label="Dismiss">&times;</button>
        </div>
    </div>
</div>

{{-- The print target. Same one-document-one-print-call rule as the desktop
     app: separate jobs race under kiosk printing. --}}
<iframe id="label-print-frame" class="hidden" aria-hidden="true" tabindex="-1"></iframe>

@livewireScripts
<script>
    // ---- PWA -------------------------------------------------------------
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker
                .register(@js(route('labels.staff.sw')), { scope: @js(route('labels.staff.print', absolute: false)) })
                .catch(() => { /* Registration failing must never break printing. */ });
        });
    }

    let deferredPrompt = null;
    const installBar = document.getElementById('pwa-install');

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;

        if (installBar && localStorage.getItem('labels-install-dismissed') !== '1') {
            installBar.classList.remove('hidden');
        }
    });

    document.getElementById('pwa-install-go')?.addEventListener('click', async () => {
        installBar?.classList.add('hidden');
        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt = null;
        }
    });

    document.getElementById('pwa-install-no')?.addEventListener('click', () => {
        installBar?.classList.add('hidden');
        localStorage.setItem('labels-install-dismissed', '1');
    });

    // ---- Printing --------------------------------------------------------
    window.addEventListener('label-print', (event) => {
        const html  = event.detail.document;
        const frame = document.getElementById('label-print-frame');

        if (! html || ! frame) {
            return;
        }

        // onload before srcdoc — srcdoc can resolve immediately for a small
        // document and the handler would otherwise never fire.
        frame.onload = () => {
            frame.contentWindow.focus();
            frame.contentWindow.print();
        };

        frame.srcdoc = html;
    });
</script>
</body>
</html>
