{{--
    Staff label app shell — built for a phone or a kitchen tablet held in
    one hand, not a desktop.

    Bottom navigation rather than a sidebar: thumbs reach the bottom of a
    screen, and a chef is usually holding the device one-handed with the
    other hand occupied. Targets are deliberately large and spaced — this is
    used with wet or gloved fingers.
--}}
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#4f46e5">
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
    <title>{{ $title ?? 'Labels' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        /* Stop the pull-to-refresh bounce turning a mis-swipe into a page
           reload mid-print. */
        html, body { overscroll-behavior-y: contain; }
        /* Respect the home-indicator area on phones. */
        .safe-bottom { padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 0.5rem); }
    </style>
</head>
<body class="h-full bg-gray-50 antialiased">

<div class="min-h-full flex flex-col max-w-2xl mx-auto bg-gray-50">

    @isset($staff)
        <header class="sticky top-0 z-20 bg-indigo-600 text-white px-4 py-3 flex items-center justify-between">
            <div class="min-w-0">
                <p class="text-[11px] uppercase tracking-wider text-indigo-200">{{ $outletName ?? '' }}</p>
                <p class="text-sm font-semibold truncate">{{ $title ?? 'Labels' }}</p>
            </div>
            <a href="{{ route('labels.staff.pin') }}" wire:navigate
               class="flex items-center gap-2 pl-3 pr-2 py-1.5 rounded-full bg-indigo-500/60 active:bg-indigo-500">
                <span class="text-xs font-medium truncate max-w-[8rem]">{{ $staff->name }}</span>
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </a>
        </header>
    @endisset

    <main class="flex-1 px-3 py-3 {{ isset($staff) ? 'pb-24' : '' }}">
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
        <nav class="fixed bottom-0 inset-x-0 z-20 max-w-2xl mx-auto bg-white border-t border-gray-200 safe-bottom">
            <div class="grid grid-cols-4">
                @foreach ($tabs as $tab)
                    @php $active = request()->routeIs($tab['route']) || request()->routeIs($tab['route'] . '.*'); @endphp
                    <a href="{{ route($tab['route']) }}" wire:navigate
                       class="flex flex-col items-center gap-0.5 py-2.5 {{ $active ? 'text-indigo-600' : 'text-gray-400' }} active:bg-gray-50">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="{{ $active ? '2.2' : '1.8' }}">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $tab['icon'] }}"/>
                        </svg>
                        <span class="text-[11px] {{ $active ? 'font-semibold' : '' }}">{{ $tab['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </nav>
    @endisset
</div>

{{-- The print target. Same one-document-one-print-call rule as the desktop
     app: separate jobs race under kiosk printing. --}}
<iframe id="label-print-frame" class="hidden" aria-hidden="true" tabindex="-1"></iframe>

{{-- Install banner. Chrome fires beforeinstallprompt only when the app is
     installable, so this stays hidden otherwise — and once installed it
     never fires again. --}}
<div id="pwa-install" class="hidden fixed bottom-20 inset-x-0 z-30 max-w-2xl mx-auto px-3">
    <div class="flex items-center gap-3 bg-gray-900 text-white rounded-xl px-4 py-3 shadow-lg">
        <span class="flex-1 text-sm">Add Labels to your home screen</span>
        <button id="pwa-install-go" class="px-3 py-1.5 bg-white text-gray-900 text-xs font-semibold rounded-lg">Add</button>
        <button id="pwa-install-no" class="text-gray-400 text-lg leading-none px-1" aria-label="Dismiss">&times;</button>
    </div>
</div>

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
