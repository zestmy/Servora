{{--
    The kiosk screen.

    A tablet in a stand on a counter, read from about half a metre away by
    somebody carrying a tray. Everything here follows from that:

      Type is large and the contrast is high. This is a glare-washed screen in
      a kitchen doorway, not a phone held at reading distance.

      Nothing is smaller than 44px, and the buttons that matter are far bigger.
      Wet hands, gloved hands, and somebody in a hurry.

      The camera is the screen. It used to be a 22rem panel beside the content,
      which on a tablet in landscape — the way every one of these is actually
      mounted — left a third of the display showing a small video of somebody's
      chest and the rest showing a sentence. Full-bleed makes the tablet behave
      like the mirror people already treat it as: you walk up, you see yourself
      framed, you know without being told where to stand. Everything the screen
      has to SAY is overlaid along the bottom, within reach and out of the way
      of your own face.

    Landscape is the primary orientation and portrait is the fallback, which is
    the reverse of the rest of this product. A counter kiosk sits in a landscape
    stand; the only portrait tablets are the ones somebody is holding.

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
    {{-- iOS reads these rather than the manifest's icons, and without them
         "Add to Home Screen" on an iPad uses a screenshot of the page — which
         for this screen is a photograph of whoever was standing in front of
         the camera when somebody installed it. --}}
    <link rel="apple-touch-icon" href="{{ asset('clock-app/staff-portal.png') }}">
    <link rel="icon" type="image/png" href="{{ asset('clock-app/staff-portal.png') }}">
    <title>Clock Kiosk | {{ $outlet?->name ?? $brandName }}</title>
    @vite(['resources/css/app.css', 'resources/js/kiosk.js'])
    <style>
        html, body { overscroll-behavior: none; }

        /* Pinned to all four edges rather than computed from a viewport unit —
           the same reason the staff shell does it. A tablet in a stand with a
           browser chrome bar is exactly where vh guesses wrong. */
        .kiosk-shell { position: fixed; inset: 0; overflow: hidden; background: #000; }

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

        /* ── The camera, filling the screen ──────────────────────────────
           object-fit: cover, so a 4:3 sensor on a 16:10 tablet crops instead
           of letterboxing into two black bars.

           MIRRORED, and only here: the transform is on the preview element,
           while face-api reads the <video> buffer and ClockCamera draws the
           selfie onto its own canvas — neither of which a CSS transform
           touches. So recognition and the stored photograph are unaffected,
           and the person gets the mirror they expect. An unmirrored full-screen
           self-view makes people lean the wrong way to centre themselves. */
        .kiosk-video {
            position: absolute; inset: 0;
            width: 100%; height: 100%;
            object-fit: cover;
            transform: scaleX(-1);
        }

        /* Scrims, not panels. Text over a moving camera picture needs a floor
           under it or it becomes unreadable the moment somebody in a white
           chef's jacket walks past. */
        .kiosk-scrim-top {
            position: absolute; inset: 0 0 auto 0; height: 8rem;
            background: linear-gradient(to bottom, rgba(3, 7, 18, 0.88), rgba(3, 7, 18, 0));
            pointer-events: none;
        }
        .kiosk-scrim-bottom {
            position: absolute; left: 0; right: 0; bottom: 0; top: 38%;
            background: linear-gradient(to top,
                rgba(3, 7, 18, 0.96) 42%, rgba(3, 7, 18, 0.72) 68%, rgba(3, 7, 18, 0));
            pointer-events: none;
        }

        .kiosk-header {
            position: absolute; inset: 0 0 auto 0; z-index: 3;
            padding-top: env(safe-area-inset-top, 0px);
        }

        /* ── The stage ───────────────────────────────────────────────────
           Everything the screen says, anchored to the bottom edge. Given a
           definite top rather than a max-height, so the panels inside can be
           flex columns with their own scrollers and actually resolve. */
        /*
           NO TRANSITION ON `top`, and this is a correctness fix rather than a
           taste one.

           It animated 44% → 0 when a form took the screen, and that transition
           never completed: `top` was left sitting at the 44% start value while
           every other declaration in the same rule applied. The consequence was
           not a missing animation — the PIN and enrolment panels never got the
           full height they are laid out for, so their content overflowed the
           bottom of the screen and the LAST thing in each panel fell off it.
           The last thing in the PIN panel is the button that gets you out of
           the PIN panel.

           Percentage-to-length transitions on a positioned offset are fragile
           in exactly this way, and the 180ms bought nothing worth a screen you
           cannot leave. The stage snapping between modes is right anyway: this
           is a mode change on a counter tablet, not a reveal.
        */
        .kiosk-stage {
            position: absolute; left: 0; right: 0; bottom: 0; top: 44%;
            z-index: 2;
            display: flex; flex-direction: column; min-height: 0;
            padding: 0 clamp(1rem, 3vw, 2.5rem) calc(0.75rem + env(safe-area-inset-bottom, 0px));
        }

        /* A portrait tablet is somebody holding it, and holding it puts the
           bottom of the screen nearer the thumb — so the band can be deeper. */
        @media (orientation: portrait) {
            .kiosk-stage        { top: 52%; }
            .kiosk-scrim-bottom { top: 46%; }
        }

        /* Modes that are a FORM rather than a message take the whole screen.
           A keypad or a staff list squeezed into a bottom third is a kiosk
           somebody mis-taps, and none of these three states is one where
           watching yourself on camera is doing any work. */
        .kiosk-shell[data-mode="pin"]       .kiosk-stage,
        .kiosk-shell[data-mode="enrol"]     .kiosk-stage,
        .kiosk-shell[data-mode="enrolcode"] .kiosk-stage { top: 0; padding-top: 4.25rem; }

        .kiosk-shell[data-mode="pin"]       .kiosk-scrim-bottom,
        .kiosk-shell[data-mode="enrol"]     .kiosk-scrim-bottom,
        .kiosk-shell[data-mode="enrolcode"] .kiosk-scrim-bottom {
            top: 0; background: rgba(3, 7, 18, 0.93);
        }

        /* …except the enrolment SCAN, which is the one screen in the building
           where seeing the camera is the whole job — a manager holding the
           tablet up to somebody's face and walking five poses. Enrolment gets
           the full screen for its staff list and hands it straight back here. */
        .kiosk-shell[data-mode="enrol"][data-enrolstep="scan"] .kiosk-stage {
            top: 44%; padding-top: 0;
        }
        .kiosk-shell[data-mode="enrol"][data-enrolstep="scan"] .kiosk-scrim-bottom {
            top: 38%;
            background: linear-gradient(to top,
                rgba(3, 7, 18, 0.96) 42%, rgba(3, 7, 18, 0.72) 68%, rgba(3, 7, 18, 0));
        }
        @media (orientation: portrait) {
            .kiosk-shell[data-mode="enrol"][data-enrolstep="scan"] .kiosk-stage       { top: 52%; }
            .kiosk-shell[data-mode="enrol"][data-enrolstep="scan"] .kiosk-scrim-bottom { top: 46%; }
        }

        /* ── Scan reticle ────────────────────────────────────────────────
           Centred in whatever the camera has left above the stage, so it moves
           with the stage rather than colliding with it. */
        .kiosk-reticle {
            position: absolute; left: 0; right: 0; top: 0; bottom: 56%;
            display: grid; place-items: center;
            pointer-events: none; z-index: 1;
            transition: opacity 200ms ease;
        }
        @media (orientation: portrait) { .kiosk-reticle { bottom: 48%; } }

        .kiosk-shell[data-mode="pin"]       .kiosk-reticle,
        .kiosk-shell[data-mode="enrolcode"] .kiosk-reticle,
        .kiosk-shell[data-mode="result"]    .kiosk-reticle,
        .kiosk-shell[data-scan="off"]       .kiosk-reticle { opacity: 0; }

        /* The enrolment list is a list; its scan is a scan. */
        .kiosk-shell[data-mode="enrol"] .kiosk-reticle { opacity: 0; }
        .kiosk-shell[data-mode="enrol"][data-enrolstep="scan"] .kiosk-reticle { opacity: 1; }

        .kiosk-ring {
            width: min(42vh, 20rem); height: min(42vh, 20rem);
            max-width: 60vw; max-height: 60vw;
            overflow: visible;
        }

        /* Both circles share a geometry so one dash length serves both. r=52
           on a 120 box → 2πr = 326.73. */
        .kiosk-ring circle {
            fill: none;
            stroke-linecap: round;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
        .kiosk-ring-track { stroke: rgba(255, 255, 255, 0.18); stroke-width: 2.5; }
        .kiosk-ring-value {
            stroke: #14b8a6; stroke-width: 4;
            stroke-dasharray: 326.73;
            /* --kiosk-scan is written by kiosk.js, 0 → 1. */
            stroke-dashoffset: calc(326.73 * (1 - var(--kiosk-scan, 0)));
            transition: stroke-dashoffset 120ms linear, stroke 180ms ease;
            filter: drop-shadow(0 0 6px rgba(20, 184, 166, 0.55));
        }
        .kiosk-bracket {
            fill: none; stroke: rgba(255, 255, 255, 0.55);
            stroke-width: 2.5; stroke-linecap: round;
            transition: stroke 180ms ease;
        }

        /* Waiting: the brackets breathe so the screen never looks frozen, and
           the ring sits at zero rather than hidden — an empty track tells you
           there is something to fill. */
        .kiosk-shell[data-scan="idle"] .kiosk-bracket { animation: kiosk-breathe 2.8s ease-in-out infinite; }

        /* Thinking: the server has the descriptor and nothing local can say how
           far along it is, so the ring becomes a chase rather than a lie. */
        .kiosk-shell[data-scan="working"] .kiosk-ring-value {
            stroke-dasharray: 82 245;
            stroke-dashoffset: 0;
            transition: none;
            animation: kiosk-chase 1.05s linear infinite;
        }

        .kiosk-shell[data-scan="ok"]   .kiosk-ring-value { stroke: #22c55e; filter: drop-shadow(0 0 8px rgba(34, 197, 94, 0.6)); }
        .kiosk-shell[data-scan="ok"]   .kiosk-bracket    { stroke: rgba(34, 197, 94, 0.85); }
        .kiosk-shell[data-scan="fail"] .kiosk-ring-value { stroke: #f59e0b; filter: none; }
        .kiosk-shell[data-scan="fail"] .kiosk-bracket    { stroke: rgba(245, 158, 11, 0.85); }

        @keyframes kiosk-breathe {
            0%, 100% { opacity: 0.45; }
            50%      { opacity: 1; }
        }
        @keyframes kiosk-chase {
            to { transform: rotate(270deg); }
        }

        /* The house rule, and it matters more here than anywhere: this screen
           animates continuously in front of people all day. */
        @media (prefers-reduced-motion: reduce) {
            .kiosk-bracket, .kiosk-ring-value { animation: none !important; }
            .kiosk-reticle { transition: none; }
        }

        /* The armed action.

           Filled rather than merely outlined-brighter, because this is a claim
           about what the NEXT face to appear will be recorded as, and somebody
           three people back in a queue has to be able to see which one is lit
           from where they are standing. The ring under it goes brand-coloured
           to match, so the two halves of the screen agree. */
        .kiosk-arm-on {
            background-color: #0d9488 !important;
            border-color: #0d9488 !important;
            color: #fff !important;
            box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.35);
        }

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
     {{-- Whether there is a fallback to fall back TO. The server refuses a PIN
          punch outright when this is off — see KioskController::fromPin() —
          so this only stops the screen offering a door that is already shut. --}}
     data-allow-pin="{{ $allowPin ? '1' : '0' }}"
     data-enrol-start="{{ route('clock.kiosk.enrol.start') }}"
     data-enrol-stop="{{ route('clock.kiosk.enrol.stop') }}"
     data-enrol-capture="{{ route('clock.kiosk.enrol.capture') }}"
     {{-- Whether a manager already opened the window before this page loaded,
          so a reload mid-session does not drop back to the punch screen. --}}
     data-enrolling="{{ $device->enrolmentOpen() ? '1' : '0' }}"
     {{-- A copy of the device token, handed to the page deliberately so its
          scripts can send it as a header. The cookie itself stays httpOnly and
          unreadable, and the JSON endpoints accept ONLY the header — which is
          what makes them immune to CSRF and therefore safe to exempt from it. --}}
     data-token="{{ $kioskToken }}"
     {{-- Both written by kiosk.js. `mode` lets the CSS give a form the whole
          screen and a message only the bottom of it; `scan` drives the ring. --}}
     data-mode="idle"
     data-scan="idle"
     data-enrolstep="list">

    {{-- ── The camera, underneath everything ─────────────────────────── --}}
    <video id="kiosk-video" autoplay playsinline muted class="kiosk-video"></video>
    <canvas id="kiosk-canvas" class="hidden"></canvas>

    <div class="kiosk-scrim-top"></div>
    <div class="kiosk-scrim-bottom"></div>

    {{-- ── Scan reticle ──────────────────────────────────────────────────
         Where to stand, and how far along the recognition is. The brackets
         answer the first question without a sentence — people centre their
         face in a frame on sight — and the ring answers the second, which
         previously had no answer at all: the old screen went from "Look at the
         camera" straight to a name, and the second and a half in between was
         indistinguishable from a kiosk that had not noticed you. --}}
    <div class="kiosk-reticle" aria-hidden="true">
        <svg class="kiosk-ring" viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg">
            {{-- Corner brackets: a face-sized frame, drawn as four elbows
                 rather than a closed box so it reads as a viewfinder and not
                 as a border somebody forgot to style. --}}
            <path class="kiosk-bracket" d="M28 44 V32 A4 4 0 0 1 32 28 H44" />
            <path class="kiosk-bracket" d="M76 28 H88 A4 4 0 0 1 92 32 V44" />
            <path class="kiosk-bracket" d="M92 76 V88 A4 4 0 0 1 88 92 H76" />
            <path class="kiosk-bracket" d="M44 92 H32 A4 4 0 0 1 28 88 V76" />

            <circle class="kiosk-ring-track" cx="60" cy="60" r="52" />
            <circle class="kiosk-ring-value" cx="60" cy="60" r="52" />
        </svg>
    </div>

    {{-- The whole screen is the retry target. A tap is the gesture iOS
         reliably prompts for, so every camera failure recovers through the
         thing covering the screen. --}}
    <button type="button" id="kiosk-camera-overlay"
            class="absolute inset-0 z-40 grid place-items-center bg-gray-950/95 px-6 text-center">
        <span class="text-base text-gray-300">Starting camera…</span>
    </button>

    <header class="kiosk-header flex items-center justify-between gap-3 px-5 py-3">
        <div class="flex items-center gap-3 min-w-0">
            <x-brand-mark :company="$company" surface="dark" size="h-6"
                          width="max-w-[90px]" :alt="$brandName" />
            <p class="text-sm font-semibold truncate drop-shadow">{{ $outlet?->name }}</p>
        </div>
        <div class="flex items-center gap-3 shrink-0">
            {{-- gray-400 reads at 6.99:1 on this surface, where it is the
                 correct muted step. On a white card it would fail AA. --}}
            <p class="text-xs text-gray-400 truncate">{{ $device->name }}</p>
            {{-- Deliberately plain and unlabelled-looking. It leads to a code
                 prompt, not to enrolment, so it is not worth making prominent
                 on a screen staff use forty times a day. --}}
            <button type="button" data-kiosk-enrol-open
                    class="min-h-[2.75rem] rounded-control border border-white/25 bg-gray-950/40 px-3 text-xs
                           font-medium text-gray-300 hover:bg-gray-800 active:bg-gray-800">
                Enrol faces
            </button>
        </div>
    </header>

    <main class="kiosk-stage">

        {{-- Camera trouble, and the one message that outranks whatever panel
             is up: a kiosk with no camera can still take PINs, and saying so
             is the difference between a queue and a phone call. --}}
        <p id="kiosk-status"
           class="hidden shrink-0 mb-2 rounded-control bg-gray-950/85 px-4 py-2 text-center
                  text-sm text-warning-200"></p>

        {{-- ── State panels ──────────────────────────────────────────── --}}
        <section class="min-h-0 flex-1 overflow-hidden">

            {{-- Idle.

                 The four punches are on screen BEFORE anybody is recognised,
                 and that is the whole shape of a shift change. Twelve people
                 arriving at once all want the same thing; making each of them
                 wait to be named, read a card and then tap it turns a queue
                 into twelve conversations with a tablet.

                 Tapping one ARMS it: the next face recognised is recorded as
                 that punch and the screen goes straight to the confirmation.
                 One tap and a look, per person, and the tap can happen while
                 the person in front is still being scanned.

                 It disarms after every punch and after twenty seconds of
                 nothing, which is what keeps the old protection intact — a
                 kiosk in a doorway sees the same people walk past all day, and
                 an armed screen left armed would clock in whoever passed it
                 next. Unarmed, a recognised face still gets the confirm card
                 and still has to be told what to do, exactly as before. --}}
            <div id="kiosk-state-idle"
                 class="h-full flex flex-col justify-end gap-3 pb-1 text-center
                        landscape:flex-row landscape:items-end landscape:text-left">
                <div class="landscape:flex-1 landscape:min-w-0">
                    <p class="text-3xl font-semibold leading-snug drop-shadow-lg" id="kiosk-hint">Look at the camera to clock in</p>
                    <p class="mt-2 text-base text-gray-300 drop-shadow" id="kiosk-subhint">
                        Tap what you are doing, then look at the camera.
                    </p>

                    {{-- The fallback, and it starts HIDDEN.

                         It used to be a permanent button on this screen,
                         offered as an equal choice, and that was a mistake: a
                         PIN is familiar and instant where a camera takes a
                         second, so side by side the PIN wins every time — and a
                         kiosk whose staff all clock in by PIN has bought
                         nothing at all.

                         kiosk.js reveals it only after a recognition has
                         actually failed, and hides it again a few seconds
                         later. So it is there for the person who needs it, at
                         the moment they need it, and is not an option anybody
                         browses to. --}}
                    @if ($allowPin)
                        <button type="button" data-kiosk-pin-open id="kiosk-pin-offer"
                                class="hidden mt-3 min-h-[3.25rem] rounded-control border border-white/30
                                       bg-gray-950/50 px-6 text-base font-semibold text-gray-100
                                       hover:bg-gray-800 active:bg-gray-800">
                            Use my PIN instead
                        </button>
                    @endif
                </div>

                <div class="landscape:w-[26rem] landscape:shrink-0">
                    <div class="grid grid-cols-2 gap-2.5">
                        @foreach ([
                            ['in',          'Clock IN'],
                            ['out',         'Clock OUT'],
                            ['break_start', 'Start break'],
                            ['break_end',   'End break'],
                        ] as [$type, $label])
                            {{-- All four always enabled here, because this
                                 screen does not know who is standing at it —
                                 whether a break can be ended is a fact about a
                                 person, and nobody has been named yet. The
                                 server refuses the ones that do not apply, by
                                 name: "You have no break running to end." --}}
                            <button type="button" data-kiosk-arm="{{ $type }}"
                                    class="kiosk-arm min-h-[4rem] rounded-panel border border-white/25 bg-gray-950/50
                                           px-2 text-xl font-semibold text-gray-100
                                           hover:bg-gray-800 active:bg-gray-800">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Confirm.

                 Name on the left, the button on the right, side by side in
                 landscape and stacked in portrait. The name has to stay
                 readable while the thumb is already moving towards the button,
                 which a vertical stack in a bottom band cannot do — it pushes
                 the name up behind the person's own chin. --}}
            <div id="kiosk-state-confirm"
                 class="hidden h-full flex flex-col justify-end gap-3 pb-1
                        landscape:flex-row landscape:items-end">
                {{-- Sized for a full Malay name over two lines rather than one
                     first name at 48px. "NURUL MUHAMMAD FARHAN BIN JAILUDIN" is
                     the ordinary case here, and truncating it to fit defeats
                     the only thing this card is for — letting somebody check
                     the kiosk has the right one of the four MOHDs on shift. --}}
                <div class="min-w-0 text-center landscape:flex-1 landscape:text-left">
                    <p class="text-base text-gray-300">Hi</p>
                    <p class="line-clamp-2 text-3xl font-bold leading-tight drop-shadow-lg landscape:text-4xl"
                       id="kiosk-name"></p>
                </div>

                {{-- The four punches, named.

                     This was one big button whose label the server chose from
                     the last day of punches, and when that guess was wrong
                     there was nothing to do about it: somebody arriving for a
                     shift was offered "Clock OUT" because of a punch from the
                     night before, pressed the only button there was, and the
                     record then said they had clocked out of a shift they had
                     not started. The screen had decided something only the
                     person standing at it could know.

                     So all four are on screen and the person says which. The
                     suggested one is filled and sits first, so the ordinary
                     case is still one tap on a large target; the others are
                     outlined but the same size, because an override that is
                     harder to hit than the wrong answer is not an override.
                     kiosk.js paints them from the options the server sent and
                     disables the two that have nothing to attach to. --}}
                <div class="flex flex-col gap-2.5 landscape:w-[26rem] landscape:shrink-0">
                    <div id="kiosk-actions" class="grid grid-cols-2 gap-2.5"></div>

                    <button type="button" data-kiosk-cancel
                            class="min-h-[3.25rem] rounded-control border border-white/20 bg-gray-950/50
                                   text-base font-semibold text-gray-300 hover:bg-gray-800 active:bg-gray-800">
                        Not me
                    </button>
                </div>
            </div>

            {{-- PIN fallback. Absent from the markup entirely when the company
                 has switched it off — an empty panel and a staff list nobody
                 can use are not worth shipping to a screen on a counter. --}}
            @if ($allowPin)
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
                                    {{-- Two across in portrait, three in
                                         landscape: the same 44px-plus target
                                         either way, and a roster of thirty
                                         that does not need scrolling on a
                                         counter tablet. --}}
                                    class="min-h-[3.5rem] flex-auto basis-[calc(50%-0.25rem)]
                                           landscape:basis-[calc(33.333%-0.34rem)] rounded-control
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

                    {{-- Says where it goes, not which direction it is. "Back"
                         on the first step of a two-step flow is ambiguous —
                         back to what? — and this is the control somebody
                         reaches for when they have opened the PIN list by
                         mistake and want the camera again. --}}
                    <button type="button" data-kiosk-pin-exit
                            class="shrink-0 mt-3 min-h-[3.5rem] rounded-control border border-white/25
                                   bg-gray-950/50 text-base font-semibold text-gray-100
                                   hover:bg-gray-800 active:bg-gray-800">
                        Cancel — back to the camera
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

                    {{-- TWO exits, and they are different exits.

                         There was one — "Not you?", set as bare grey text with
                         no border and no fill, at the bottom of a dark screen.
                         It did not look like a control, and it only stepped
                         back to the staff list, so the way out of the PIN
                         screen was two low-contrast discoveries in a row.
                         Somebody who opened this by accident, or keyed a wrong
                         PIN and gave up, had no visible way back to the camera
                         at all.

                         So: one to change who this is, one to leave. Both look
                         like buttons, both are 44px-plus, and the second says
                         where it goes. --}}
                    <div class="grid w-full max-w-xs grid-cols-2 gap-2">
                        <button type="button" data-kiosk-pin-cancel
                                class="min-h-[3.25rem] rounded-control border border-white/25 bg-gray-950/50
                                       text-sm font-semibold text-gray-100 hover:bg-gray-800 active:bg-gray-800">
                            Not you?
                        </button>
                        <button type="button" data-kiosk-pin-exit
                                class="min-h-[3.25rem] rounded-control border border-white/25 bg-gray-950/50
                                       text-sm font-semibold text-gray-100 hover:bg-gray-800 active:bg-gray-800">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
            @endif

            {{-- ── Enrolment: the code ────────────────────────────────── --}}
            <div id="kiosk-state-enrolcode" class="hidden h-full flex flex-col items-center justify-center gap-4 px-4 text-center">
                <div>
                    <p class="text-2xl font-semibold">Enrolment code</p>
                    <p class="mt-2 text-sm text-gray-400">
                        A manager issues this from HR &rsaquo; Clock kiosks &rsaquo; Enrol faces.
                    </p>
                </div>

                <p id="kiosk-enrol-dots" class="text-4xl font-mono tracking-[0.4em] text-brand-300">······</p>
                <p id="kiosk-enrol-error" class="min-h-[1.25rem] text-sm text-danger-300"></p>

                <div class="grid grid-cols-3 gap-2 w-full max-w-xs">
                    @foreach (['A','B','C','D','E','F','G','H','J'] as $ch)
                        <button type="button" data-kiosk-enrol-key="{{ $ch }}"
                                class="min-h-[3rem] rounded-control bg-gray-800 text-lg font-semibold text-white hover:bg-gray-700">{{ $ch }}</button>
                    @endforeach
                </div>

                {{-- A full keyboard rather than the 9 shortcuts above: the code
                     alphabet is 31 characters, so the grid is a convenience and
                     this is the thing that actually works. --}}
                <input id="kiosk-enrol-input" type="text" inputmode="latin" autocomplete="off"
                       autocapitalize="characters" spellcheck="false" maxlength="12"
                       placeholder="Type the code"
                       class="w-full max-w-xs rounded-control border border-gray-600 bg-gray-800 px-4 py-3
                              text-center text-2xl font-semibold uppercase tracking-[0.3em] text-white
                              placeholder:text-sm placeholder:tracking-normal placeholder:text-gray-500">

                <div class="grid grid-cols-2 gap-3 w-full max-w-xs">
                    <button type="button" data-kiosk-enrol-cancel
                            class="min-h-[3.25rem] rounded-control border border-gray-700 text-base font-semibold text-gray-400 hover:bg-gray-800">
                        Cancel
                    </button>
                    <button type="button" data-kiosk-enrol-submit
                            class="min-h-[3.25rem] rounded-control bg-brand-600 text-base font-bold text-white shadow-btn hover:bg-brand-700">
                        Start
                    </button>
                </div>
            </div>

            {{-- ── Enrolment: pick a person, then scan ────────────────── --}}
            <div id="kiosk-state-enrol" class="hidden h-full flex flex-col min-h-0">
                <div class="shrink-0 flex items-center justify-between gap-2 pb-2">
                    <p class="text-sm font-semibold">
                        Enrolling <span class="text-gray-400" id="kiosk-enrol-left"></span>
                    </p>
                    <button type="button" data-kiosk-enrol-finish
                            class="min-h-[2.75rem] rounded-control border border-gray-600 px-4 text-sm font-semibold text-gray-200 hover:bg-gray-800">
                        Finish
                    </button>
                </div>

                {{-- Who to enrol. The capture count is on every row because
                     "have I done this person yet" is the question a manager
                     working through a roster asks constantly. --}}
                <div id="kiosk-enrol-picker" class="flex-1 min-h-0 flex flex-col">
                    <input id="kiosk-enrol-search" type="search" inputmode="search" autocomplete="off"
                           placeholder="Type a name to search"
                           class="shrink-0 w-full rounded-control border border-gray-600 bg-gray-800 px-4 py-3
                                  text-lg text-white placeholder:text-gray-500">
                    <div id="kiosk-enrol-list" class="mt-3 flex-1 min-h-0 overflow-y-auto flex flex-wrap gap-2 content-start"></div>
                </div>

                {{-- The scan itself. Reuses the same five-pose ceremony as the
                     HR enrolment screen, so a face captured here is measured
                     exactly the way one captured there is. --}}
                {{-- Laid out like the confirm card, and for the same reason:
                     the pose instruction has to stay readable while the manager
                     is reaching for the button, and the person being enrolled
                     needs the camera above it unobstructed. --}}
                <div id="kiosk-enrol-scan"
                     class="hidden flex-1 min-h-0 flex flex-col justify-end gap-3 pb-1
                            landscape:flex-row landscape:items-end">
                    <div class="min-w-0 text-center landscape:flex-1 landscape:text-left">
                        <p class="truncate text-3xl font-bold drop-shadow-lg" id="kiosk-enrol-who"></p>
                        <p class="mt-1 text-xl text-brand-300 min-h-[1.75rem] drop-shadow" id="kiosk-enrol-pose"></p>
                        <p class="text-base text-gray-300 min-h-[1.5rem] drop-shadow" id="kiosk-enrol-progress"></p>
                    </div>

                    <div class="grid grid-cols-2 gap-3 w-full landscape:w-[22rem] landscape:shrink-0">
                        <button type="button" data-kiosk-enrol-back
                                class="min-h-[3.5rem] rounded-control border border-white/20 bg-gray-950/50 text-base font-semibold text-gray-300 hover:bg-gray-800">
                            Back to list
                        </button>
                        <button type="button" data-kiosk-enrol-scan
                                class="min-h-[3.5rem] rounded-control bg-brand-600 text-base font-bold text-white shadow-btn hover:bg-brand-700">
                            Start scan
                        </button>
                    </div>
                </div>
            </div>

            {{-- Result.

                 A solid card rather than another overlay. This is the one
                 moment the screen is making a claim about what it just
                 recorded, and it should not have somebody's face moving behind
                 it while they read the time off it. --}}
            <div id="kiosk-state-result" class="hidden h-full flex items-end justify-center pb-1">
                <div id="kiosk-result-card"
                     class="w-full rounded-panel bg-success-700 px-6 py-5 shadow-e4">
                    <div class="flex flex-col items-center gap-1 text-center
                                landscape:flex-row landscape:items-baseline landscape:justify-between
                                landscape:gap-6 landscape:text-left">
                        <div class="min-w-0 landscape:flex-1">
                            <p class="text-xl font-medium text-white/80" id="kiosk-result-headline">Clock in</p>
                            {{-- Same as the confirm card: the full name, wrapped
                                 rather than cut. This is the record of what was
                                 just written down. --}}
                            <p class="line-clamp-2 text-3xl font-bold leading-tight text-white landscape:text-4xl"
                               id="kiosk-result-name"></p>
                        </div>
                        <p class="text-4xl font-semibold text-white/90 landscape:shrink-0" id="kiosk-result-at"></p>
                    </div>
                    <p class="mt-3 hidden text-center text-lg text-white/85" id="kiosk-result-note"></p>
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
