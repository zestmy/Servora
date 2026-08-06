{{--
    Staff clock-in app shell.

    Same bones as the labels staff shell — fixed app frame, bottom tabs,
    safe-area handling — because staff move between the two on the same
    phone and a second set of conventions to learn would be a cost with no
    benefit. Two tabs only: the thing you came to do, and proof you did it.
--}}
@php
    $brandCompany = app()->bound('currentCompany') ? app('currentCompany') : null;
    $brandName    = $brandCompany?->brand_name ?? $brandCompany?->name ?? 'Clock In';
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Matches the header fill exactly, so there is no seam between the OS
         status bar and the header on Android. --}}
    <meta name="theme-color" content="#0d5f61">
    {{-- Where clock.js fetches the recognition weights from. A meta tag
         rather than a hard-coded path: the app mounts on a subdomain in
         production and a sub-path locally, and the JS should not have to
         know which. --}}
    <meta name="face-models-url" content="{{ asset('face-models') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Clock In">
    <link rel="manifest" href="{{ route('clock.staff.manifest') }}">
    <link rel="apple-touch-icon" href="{{ asset('clock-app/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('clock-app/icon-192.png') }}">
    <title>{{ $title ?? 'Clock In' }} | {{ $brandName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/clock.js'])
    @livewireStyles
    <style>
        html, body { overscroll-behavior-y: contain; }

        .safe-top {
            padding-top: calc(env(safe-area-inset-top, 0px) + 0.75rem);
            padding-bottom: 0.75rem;
        }
        .safe-top-plain { padding-top: calc(env(safe-area-inset-top, 0px) + 0.75rem); }
        .safe-bottom { padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 0.5rem); }
        .safe-x {
            padding-left: env(safe-area-inset-left, 0px);
            padding-right: env(safe-area-inset-right, 0px);
        }

        /* Pinned to all four edges, scrolling in the middle. Anchoring to
           inset:0 asks the browser where its edges are rather than computing
           a height from a viewport unit and hoping it agrees — see the
           labels shell for the two attempts that failed on real phones. */
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

        /* Mirroring is applied by clock.js, per camera, not here. A blanket
           scaleX(-1) would flip the REAR camera too — showing the room, and
           any writing in it, back to front — and it would fight the inline
           transform the module sets when the camera is switched. */
    </style>
</head>
<body class="h-full bg-gray-50 antialiased overflow-hidden">

<div class="app-shell bg-gray-50">

    @isset($staff)
        @php
            $initials = collect(preg_split('/\s+/', trim($staff->name)))
                ->filter()->take(2)
                ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
                ->implode('');
        @endphp

        <header class="shrink-0 z-20 bg-brand-700 text-white px-3 safe-top safe-x flex items-center gap-2">
            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                <x-brand-mark :company="$brandCompany" surface="dark"
                              size="h-6" width="max-w-[64px]" :alt="$brandName" />
                <div class="min-w-0">
                    {{-- The outlet leads: it is what the geofence is measured
                         against and what the punch is recorded to. --}}
                    <p class="text-sm font-semibold leading-tight truncate">{{ $outletName ?: $brandName }}</p>
                    <p class="text-[11px] leading-tight text-brand-100 truncate">{{ $staff->name }}</p>
                </div>
            </div>

            {{-- A real form post. This control sits in the LAYOUT, outside any
                 Livewire component's root element, where a wire:click is never
                 bound and the button is silently inert — which is exactly how
                 it shipped and how it was reported. A form also keeps working
                 when the app's JavaScript has failed, which is the moment
                 somebody most needs to get out. --}}
            <form method="POST" action="{{ route('clock.staff.logout') }}" class="shrink-0">
                @csrf
                <button type="submit"
                        aria-label="Signed in as {{ $staff->name }} — sign out"
                        class="flex min-h-[2.75rem] items-center gap-2 rounded-full bg-white/15 pl-1 pr-3 active:bg-white/25">
                    <span aria-hidden="true"
                          class="grid h-8 w-8 place-items-center rounded-full bg-white/25 text-[11px] font-bold tracking-wide">
                        {{ $initials }}
                    </span>
                    <span class="text-xs font-medium">Sign out</span>
                </button>
            </form>
        </header>
    @endisset

    <main class="app-scroll px-3 pb-3 safe-x {{ isset($staff) ? 'pt-3' : 'safe-top-plain' }}">
        {{ $slot }}
    </main>

    @isset($staff)
        @php
            $tabs = [
                ['route' => 'clock.staff.punch',   'label' => 'Clock',
                 'icon'  => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['route' => 'clock.staff.history', 'label' => 'Punches',
                 'icon'  => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
                ['route' => 'clock.staff.leave',   'label' => 'Leave',
                 'icon'  => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                ['route' => 'clock.staff.time-off', 'label' => 'Time off',
                 'icon'  => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            ];
        @endphp
        {{-- The active tab is marked three ways — a rule above it, a heavier
             stroke, and colour — because colour alone is the one signal a
             glare-washed screen in a doorway will not carry, and it is also
             WCAG 1.4.1. --}}
        <nav class="shrink-0 z-20 bg-white border-t border-gray-200 safe-bottom safe-x">
            {{-- Literal class strings, not "grid-cols-{{ n }}": Tailwind scans
                 templates as plain text and never sees an interpolated name,
                 so the built stylesheet would not contain the column count. --}}
            @php
                $tabCols = match (count($tabs)) {
                    1 => 'grid-cols-1', 2 => 'grid-cols-2', 3 => 'grid-cols-3',
                    4 => 'grid-cols-4', 5 => 'grid-cols-5', default => 'grid-cols-4',
                };
            @endphp
            <div class="grid {{ $tabCols }}">
                @foreach ($tabs as $tab)
                    @php $active = request()->routeIs($tab['route']); @endphp
                    <a href="{{ route($tab['route']) }}" wire:navigate
                       @if ($active) aria-current="page" @endif
                       class="relative flex min-h-[3.25rem] flex-col items-center justify-center gap-0.5 py-2
                              {{ $active ? 'text-brand-700' : 'text-gray-500' }} active:bg-gray-50">
                        @if ($active)
                            <span aria-hidden="true" class="absolute inset-x-8 top-0 h-0.5 rounded-full bg-brand-700"></span>
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

    @include('partials.pwa-install', [
        'appName'    => 'Clock In',
        'storageKey' => 'clock-install-dismissed',
    ])
</div>

@livewireScripts
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker
                .register(@js(route('clock.staff.sw')), { scope: @js(route('clock.staff.punch', absolute: false)) })
                // Registration failing must never stop somebody clocking in.
                .catch(() => {});
        });
    }

</script>
</body>
</html>
