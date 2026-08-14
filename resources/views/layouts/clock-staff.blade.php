{{--
    Staff Portal shell.

    Same bones as the labels staff shell — fixed app frame, bottom tabs,
    safe-area handling — because staff move between the two on the same
    phone and a second set of conventions to learn would be a cost with no
    benefit.

    Named the Staff PORTAL rather than anything about clocking: it now holds
    punches, leave and time off, and an app called "Clock In" is the wrong
    place to go looking for annual leave.
--}}
@php
    $brandCompany = app()->bound('currentCompany') ? app('currentCompany') : null;
    $brandName    = $brandCompany?->brand_name ?? $brandCompany?->name ?? 'Staff Portal';
    /*
     * The Staff Portal's OWN icon, not the company's logo.
     *
     * It used to be the tenant's brand mark, on the reasoning that staff pick
     * this off a home screen full of others and their own brand is the fastest
     * thing to find. What that missed is what a brand logo IS: artwork drawn
     * for a letterhead or a shopfront, usually wide, usually transparent, and
     * with nothing behind it. Dropped onto a home screen it gets letterboxed
     * into a white tile, and on a dark wallpaper a transparent one loses its
     * own strokes. This is drawn as an app icon — square, its own background,
     * legible at 48px — which is a different job from a logo.
     */
    $appIcon      = asset('clock-app/staff-portal.png');
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
    <meta name="apple-mobile-web-app-title" content="Staff Portal">
    <link rel="manifest" href="{{ route('clock.staff.manifest') }}">
    <link rel="apple-touch-icon" href="{{ $appIcon }}">
    <link rel="icon" type="image/png" href="{{ $appIcon }}">
    <title>{{ $title ?? 'Staff Portal' }} | {{ $brandName }}</title>
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

            {{-- Your own name is the way to the things about you. A link rather
                 than a seventh tab: the bar is full at six and a seventh would
                 drop it to four columns. --}}
            <a href="{{ route('clock.staff.account') }}" wire:navigate
               aria-label="Account settings"
               class="ml-auto mr-2 grid min-h-[2.75rem] min-w-[2.75rem] place-items-center rounded-full active:bg-white/20">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </a>

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
            /*
             * FOUR TABS, down from the eight this briefly grew to.
             *
             * Adding Board and Learn to the existing six pushed the bar to two
             * rows, and a two-row bottom bar on a phone is a bar that has
             * stopped being a bar. The home screen absorbed the difference:
             * Punches, Roster, Leave, Time off and Payslips are all one tap
             * from it in the links grid, and they are things somebody goes
             * LOOKING for rather than switches between mid-shift. The five that
             * remain are the ones a shift actually moves between.
             *
             * Home is first and Clock second: the thumb that reached for the
             * leftmost tab at 8:59am now finds home, whose primary button is
             * Clock in — so that path is still one tap, and every other path
             * got shorter.
             */
            $tabs = [
                ['route' => 'clock.staff.home',    'label' => 'Home',
                 'icon'  => 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75'],
                ['route' => 'clock.staff.punch',   'label' => 'Clock',
                 'icon'  => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                // Trophy and mortarboard: the two learning tabs have to be
                // told apart at a glance in a bar that is scanned, not read.
                ['route' => 'clock.staff.learn',   'label' => 'Learn',
                 'icon'  => 'M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342'],
                ['route' => 'clock.staff.leaderboard', 'label' => 'Board',
                 'icon'  => 'M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 01-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35m0 0a6.772 6.772 0 01-3.044 0'],
                // Punches, Roster, Leave, Time off and Payslips used to sit
                // here. They are all one tap from the home screen's links grid
                // now, and none of them is something a shift switches BETWEEN
                // — you go looking for a payslip, you do not flick to it.
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
            {{-- Eight tabs is more than a bottom bar wants, and the default
                 below is what catches it: past six the grid drops to four
                 columns and WRAPS TO TWO ROWS rather than squeezing eight
                 45px cells onto a 360px phone, where the labels would be
                 unreadable and the targets below the 44px floor. Two rows
                 costs vertical space, which is the lesser harm — but this bar
                 is now carrying more than it should, and the next thing added
                 to it should replace something rather than join it. --}}
            @php
                $tabCols = match (count($tabs)) {
                    1 => 'grid-cols-1', 2 => 'grid-cols-2', 3 => 'grid-cols-3',
                    4 => 'grid-cols-4', 5 => 'grid-cols-5', 6 => 'grid-cols-6',
                    default => 'grid-cols-4',
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
            {{-- The APP's base, not the punch screen.

                 It registered with /staff/clock, which contradicted the
                 Service-Worker-Allowed header the worker is served with and
                 left Home, Punches, Leave, Payslips and Learning outside the
                 worker's control — uncontrolled, and offline for nobody. See
                 ClockAppController::scope(), whose comment says exactly this
                 about the value that was being passed. --}}
            navigator.serviceWorker
                .register(@js(route('clock.staff.sw')), {
                    scope: @js(rtrim(route('clock.staff.home', absolute: false), '/') . '/'),
                })
                // Registration failing must never stop somebody clocking in.
                .catch(() => {});
        });
    }

</script>

{{-- The delete confirmation gate. Inert until something on the page
     carries data-confirm-delete. See components/confirm-delete.blade.php. --}}
<x-confirm-delete />

</body>
</html>
