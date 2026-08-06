{{--
    The kiosk screen.

    A tablet in a stand on a counter, read from about half a metre away by
    somebody carrying a tray. Everything here follows from that:

      Type is large and the contrast is high. This is a glare-washed screen in
      a kitchen doorway, not a phone held at reading distance.

      Nothing is smaller than 44px, and the buttons that matter are far bigger.
      Wet hands, gloved hands, and somebody in a hurry.

      The camera preview is present but subordinate. People need to see that it
      is looking at them — a black rectangle reads as broken — but the thing
      they are here to read is their own name and one button.

    No Livewire. The screen stays open for a fourteen-hour shift on a device
    nobody is watching, and a component that quietly stops re-rendering is a
    kiosk that looks alive and records nothing. Everything is plain JSON to
    endpoints that authenticate on the device token.
--}}
@php
    $brandName = $company?->brand_name ?? $company?->name ?? 'Servora';
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b1220">
    {{-- Where the recognition weights come from. A meta tag rather than a
         hard-coded path: the app mounts on a subdomain in production and a
         sub-path locally, and the JS should not have to know which. --}}
    <meta name="face-models-url" content="{{ asset('face-models') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Clock Kiosk">
    <link rel="manifest" href="{{ route('clock.kiosk.manifest') }}">
    <title>Clock Kiosk | {{ $outlet?->name ?? $brandName }}</title>
    @vite(['resources/css/app.css', 'resources/js/kiosk.js'])
    <style>
        html, body { overscroll-behavior: none; }

        /* Pinned to all four edges rather than computed from a viewport unit —
           the same reason the staff shell does it. A tablet in a stand with a
           browser chrome bar is exactly where vh guesses wrong. */
        .kiosk-shell { position: fixed; inset: 0; display: flex; flex-direction: column; }

        /* Nothing on this screen is selectable or long-pressable. A kiosk that
           pops a copy/paste bubble under a wet thumb is a kiosk somebody has
           to come and fix. */
        .kiosk-shell, .kiosk-shell * {
            -webkit-user-select: none; user-select: none;
            -webkit-touch-callout: none;
            -webkit-tap-highlight-color: transparent;
        }

        /* The search box is the one exception — it has to accept text. */
        .kiosk-shell input { -webkit-user-select: text; user-select: text; }

        /* A recorded-but-flagged punch. Amber, not red: the punch WORKED and
           the person is clocked in. Red would read as failure and have them
           standing there tapping again. */
        .kiosk-result-flagged { background-color: #78350f !important; }
    </style>
</head>
<body class="h-full bg-gray-900 text-white antialiased overflow-hidden">

<div id="kiosk-root" class="kiosk-shell"
     data-identify="{{ route('clock.kiosk.identify') }}"
     data-punch="{{ route('clock.kiosk.punch') }}"
     data-ping="{{ route('clock.kiosk.ping') }}"
     {{-- A copy of the device token, handed to the page deliberately so its
          scripts can send it as a header. The cookie itself stays httpOnly and
          unreadable, and the JSON endpoints accept ONLY the header — which is
          what makes them immune to CSRF and therefore safe to exempt from it. --}}
     data-token="{{ $kioskToken }}">

    <header class="shrink-0 flex items-center justify-between gap-3 px-5 py-3 bg-gray-950/60">
        <div class="flex items-center gap-3 min-w-0">
            <x-brand-mark :company="$company" surface="dark" size="h-6"
                          width="max-w-[90px]" :alt="$brandName" />
            <p class="text-sm font-semibold truncate">{{ $outlet?->name }}</p>
        </div>
        {{-- gray-400 reads at 6.99:1 on this surface, where it is the correct
             muted step. The same class on a white card would fail AA. --}}
        <p class="text-xs text-gray-400 truncate">{{ $device->name }}</p>
    </header>

    <main class="flex-1 min-h-0 grid gap-4 p-4 lg:grid-cols-[minmax(0,22rem)_1fr]">

        {{-- ── Camera ────────────────────────────────────────────────── --}}
        <section class="relative overflow-hidden rounded-panel bg-black min-h-[10rem]">
            <video id="kiosk-video" autoplay playsinline muted
                   class="h-full w-full object-cover"></video>
            <canvas id="kiosk-canvas" class="hidden"></canvas>

            {{-- The whole overlay is the retry target. A tap is the gesture
                 iOS reliably prompts for, so every camera failure recovers
                 through the thing covering the screen. --}}
            <button type="button" id="kiosk-camera-overlay"
                    class="absolute inset-0 grid place-items-center bg-gray-900/90 px-6 text-center">
                <span class="text-sm text-gray-300">Starting camera…</span>
            </button>

            <p id="kiosk-status"
               class="hidden absolute inset-x-0 bottom-0 bg-gray-950/85 px-3 py-2 text-center text-xs text-warning-200"></p>
        </section>

        {{-- ── State panels ──────────────────────────────────────────── --}}
        <section class="min-h-0 overflow-hidden">

            {{-- Idle --}}
            <div id="kiosk-state-idle" class="h-full flex flex-col items-center justify-center gap-6 text-center px-4">
                <div>
                    <p class="text-3xl font-semibold leading-snug" id="kiosk-hint">Look at the camera to clock in</p>
                    <p class="mt-3 text-base text-gray-400">Stand about an arm's length away.</p>
                </div>

                {{-- The fallback is visible, not hidden behind a failure. The
                     people who need it most — a new hire, somebody in a
                     hairnet — should not have to fail twice to discover it. --}}
                <button type="button" data-kiosk-pin-open
                        class="min-h-[3.5rem] rounded-control border border-gray-600 px-8 text-lg
                               font-semibold text-gray-200 hover:bg-gray-800 active:bg-gray-800">
                    Use my PIN instead
                </button>
            </div>

            {{-- Confirm --}}
            <div id="kiosk-state-confirm" class="hidden h-full flex flex-col justify-center gap-5 px-2">
                <div class="text-center">
                    <p class="text-xl text-gray-400">Hi</p>
                    <p class="text-5xl font-bold leading-tight" id="kiosk-name"></p>
                    <p class="mt-1 text-base text-gray-400" id="kiosk-full-name"></p>
                </div>

                <button type="button" id="kiosk-primary"
                        class="min-h-[6rem] w-full rounded-panel bg-brand-600 text-4xl font-bold
                               text-white shadow-btn hover:bg-brand-700 active:bg-brand-700">
                    <span id="kiosk-primary-label">Clock IN</span>
                </button>

                <div class="grid grid-cols-2 gap-3">
                    <button type="button" id="kiosk-break"
                            class="hidden min-h-[3.5rem] rounded-control border border-gray-600
                                   text-lg font-semibold text-gray-200 hover:bg-gray-800 active:bg-gray-800">
                        <span id="kiosk-break-label">Start break</span>
                    </button>
                    <button type="button" data-kiosk-cancel
                            class="col-start-2 min-h-[3.5rem] rounded-control border border-gray-700
                                   text-lg font-semibold text-gray-400 hover:bg-gray-800 active:bg-gray-800">
                        Not me
                    </button>
                </div>
            </div>

            {{-- PIN fallback --}}
            <div id="kiosk-state-pin" class="hidden h-full flex flex-col min-h-0">
                <p id="kiosk-pin-hint" class="shrink-0 pb-2 text-center text-base text-warning-200"></p>

                {{-- Step one: find your name --}}
                <div id="kiosk-pin-picker" class="flex-1 min-h-0 flex flex-col">
                    <input id="kiosk-pin-search" type="search" inputmode="search"
                           autocomplete="off" spellcheck="false" placeholder="Type your name to search"
                           class="shrink-0 w-full rounded-control border border-gray-600 bg-gray-800
                                  px-4 py-3 text-lg text-white placeholder:text-gray-500
                                  focus:border-brand-500 focus:ring-2 focus:ring-brand-500/40">

                    <div id="kiosk-pin-list" class="mt-3 flex-1 min-h-0 overflow-y-auto flex flex-wrap gap-2 content-start">
                        @forelse ($employees as $person)
                            <button type="button"
                                    data-employee="{{ $person->id }}"
                                    data-name="{{ $person->name }}"
                                    class="min-h-[3.5rem] flex-auto basis-[calc(50%-0.25rem)] rounded-control
                                           border border-gray-700 bg-gray-800 px-4 text-lg font-semibold
                                           text-gray-100 ring-brand-500 hover:bg-gray-700 active:bg-gray-700">
                                {{ $person->name }}
                            </button>
                        @empty
                            <p class="text-base text-gray-400">
                                Nobody at this outlet has a PIN yet. Ask a manager to set one in HR &rsaquo; Employees.
                            </p>
                        @endforelse
                    </div>

                    <button type="button" data-kiosk-pin-cancel
                            class="shrink-0 mt-3 min-h-[3.25rem] rounded-control border border-gray-700
                                   text-base font-semibold text-gray-400 hover:bg-gray-800">
                        Back
                    </button>
                </div>

                {{-- Step two: key the PIN --}}
                <div id="kiosk-pin-keypad" class="hidden flex-1 min-h-0 flex flex-col items-center justify-center gap-4">
                    <div class="text-center">
                        <p class="text-2xl font-bold" id="kiosk-pin-name"></p>
                        <p class="mt-2 text-3xl tracking-[0.5em] text-brand-300" id="kiosk-pin-dots">····</p>
                    </div>

                    <div class="grid grid-cols-3 gap-2 w-full max-w-xs">
                        @foreach ([1,2,3,4,5,6,7,8,9] as $digit)
                            <button type="button" data-kiosk-digit="{{ $digit }}"
                                    class="min-h-[3.5rem] rounded-control bg-gray-800 text-2xl font-semibold
                                           text-white hover:bg-gray-700 active:bg-gray-700">{{ $digit }}</button>
                        @endforeach

                        <button type="button" data-kiosk-back
                                class="min-h-[3.5rem] rounded-control bg-gray-800 text-xl font-semibold
                                       text-gray-300 hover:bg-gray-700 active:bg-gray-700">&larr;</button>
                        <button type="button" data-kiosk-digit="0"
                                class="min-h-[3.5rem] rounded-control bg-gray-800 text-2xl font-semibold
                                       text-white hover:bg-gray-700 active:bg-gray-700">0</button>
                        <button type="button" data-kiosk-pin-submit
                                class="min-h-[3.5rem] rounded-control bg-brand-600 text-lg font-bold
                                       text-white shadow-btn hover:bg-brand-700 active:bg-brand-700">OK</button>
                    </div>

                    <button type="button" data-kiosk-pin-cancel
                            class="min-h-[3rem] px-6 text-base font-semibold text-gray-400 hover:text-gray-200">
                        Not you?
                    </button>
                </div>
            </div>

            {{-- Result --}}
            <div id="kiosk-state-result" class="hidden h-full flex items-center justify-center px-2">
                <div id="kiosk-result-card"
                     class="w-full rounded-panel bg-success-700 px-6 py-10 text-center shadow-e4">
                    <p class="text-2xl font-medium text-white/80" id="kiosk-result-headline">Clock in</p>
                    <p class="mt-2 text-5xl font-bold leading-tight text-white" id="kiosk-result-name"></p>
                    <p class="mt-3 text-3xl font-semibold text-white/90" id="kiosk-result-at"></p>
                    <p class="mt-4 hidden text-lg text-white/85" id="kiosk-result-note"></p>
                </div>
            </div>

        </section>
    </main>
</div>

<script>
    /*
     * The service worker is registered for the ~6.5MB of face weights alone —
     * it is cache-first on those and network-only on everything else, so
     * nothing about a punch can ever come from a cache. A cold download of
     * that size over a mall's wifi at 8:58am is the difference between a
     * two-second clock-in and a thirty-second one.
     */
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker
                .register(@js(route('clock.staff.sw')), { scope: @js(route('clock.kiosk.screen', absolute: false)) })
                // Registration failing must never stop the kiosk working.
                .catch(() => {});
        });
    }
</script>
</body>
</html>
