@php
    use App\Models\ClockEvent;

    $isIn      = $nextType === ClockEvent::TYPE_IN;
    $actionLabel = $isIn ? 'Clock In' : 'Clock Out';

    // Everything the shift card needs, worked out once so the markup below
    // stays readable.
    $now        = now();
    $shiftStart = $shift['start'] ?? null;
    $shiftEnd   = $shift['end'] ?? null;
    $lateBy     = ($isIn && $shiftStart && $now->gt($shiftStart))
        ? (int) floor($shiftStart->diffInSeconds($now, false) / 60)
        : 0;
    $chargeable = max(0, $lateBy - (int) $settings->grace_minutes);
@endphp

{{-- No wire:key here, and that is load-bearing.

     It used to be wire:key="punch-{{ '{{' }} $nextType }}". Clocking in flips
     nextType from "in" to "out", so the key CHANGED — and a changed key on a
     component's root element makes Livewire discard the element and rebuild
     it rather than morph it. That took the wire:ignore camera subtree with
     it: a fresh <video> with no stream, an overlay reset to its server text,
     and a click listener still bound to the button that had just been thrown
     away. Which is exactly "Tap to start camera does nothing" the moment you
     try to start a break. --}}
<div>

    {{-- ── The shift you are on ─────────────────────────────────────────
         First thing on the screen because it is the thing that decides
         whether pressing the button costs money. --}}
    @if ($shift)
        <div class="rounded-xl border {{ $lateBy > 0 ? 'border-amber-300 bg-amber-50' : 'border-gray-200 bg-white' }} px-4 py-3 mb-3">
            <p class="text-[11px] uppercase tracking-wide text-gray-500">
                {{ $shiftStart->isToday() ? 'Today' : $shiftStart->format('D j M') }}
                @if ($shift['entry']->station)
                    · {{ $shift['entry']->station->name }}
                @endif
            </p>
            <p class="text-lg font-semibold text-gray-900 leading-tight">
                {{ $shiftStart->format('g:iA') }} – {{ $shiftEnd->format('g:iA') }}
            </p>

            @if ($isIn && $lateBy > 0)
                <p class="mt-1 text-sm font-medium text-amber-800">
                    You are {{ $lateBy }} {{ Str::plural('minute', $lateBy) }} past your start time.
                    @if ($chargeable > 0 && $settings->chargesForLateness())
                        {{-- Said out loud BEFORE the button is pressed. A
                             deduction someone only discovers afterwards is
                             the kind of surprise that makes people distrust
                             the whole system. --}}
                        Clocking in now deducts about
                        RM {{ number_format(min(
                            $chargeable * (float) $settings->late_rate_per_minute,
                            $settings->late_cap_per_shift !== null
                                ? (float) $settings->late_cap_per_shift
                                : PHP_FLOAT_MAX
                        ), 2) }}
                        from your service charge.
                    @elseif ($chargeable === 0)
                        That is still within the {{ $settings->grace_minutes }}-minute grace.
                    @endif
                </p>
            @elseif ($isIn)
                <p class="mt-1 text-sm text-gray-600">
                    Starts {{ $shiftStart->diffForHumans(['parts' => 1]) }}. You are on time.
                </p>
            @else
                <p class="mt-1 text-sm text-gray-600">Ends {{ $shiftEnd->diffForHumans(['parts' => 1]) }}.</p>
            @endif
        </div>
    @else
        <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 mb-3">
            <p class="text-sm font-medium text-gray-800">No shift on the roster right now</p>
            <p class="mt-1 text-sm text-gray-600">
                You can still clock {{ $isIn ? 'in' : 'out' }} — it will be sent to your manager to confirm.
            </p>
        </div>
    @endif

    {{-- ── Result of the punch just made ───────────────────────────────── --}}
    @if ($lastEvent)
        @php
            $ok = $lastEvent->status === ClockEvent::STATUS_VERIFIED;
        @endphp
        <div class="rounded-xl border px-4 py-3 mb-3 {{ $ok ? 'border-teal-300 bg-teal-50' : 'border-amber-300 bg-amber-50' }}"
             role="status">
            <p class="text-base font-semibold {{ $ok ? 'text-teal-900' : 'text-amber-900' }}">
                {{ $lastEvent->typeLabel() }} at {{ $lastEvent->happened_at->format('g:i A') }}
            </p>

            @if ($lastEvent->minutes_late > 0)
                <p class="mt-1 text-sm text-amber-900">
                    @if ($lastEvent->type === ClockEvent::TYPE_BREAK_END)
                        {{ $lastEvent->minutes_late }} {{ Str::plural('minute', $lastEvent->minutes_late) }} over your break allowance.
                    @else
                        {{ $lastEvent->minutes_late }} {{ Str::plural('minute', $lastEvent->minutes_late) }} late.
                    @endif
                    @if ($lastEvent->latenessWaived() && (float) $lastEvent->penalty_amount > 0)
                        RM {{ number_format((float) $lastEvent->penalty_amount, 2) }} was waived — nothing deducted.
                    @elseif ((float) $lastEvent->penalty_amount > 0)
                        RM {{ number_format((float) $lastEvent->penalty_amount, 2) }} deducted from your service charge.
                    @else
                        Within grace — nothing deducted.
                    @endif
                </p>
            @endif

            {{-- The reviewable reasons only, which also fixes what this block
                 claimed: it appeared for ANY flag, so a punch whose only note
                 was "Late" — already settled, already deducted, nothing for
                 anybody to decide — told the employee their manager would be
                 reviewing it. --}}
            @if ($lastEvent->reviewFlagLabels())
                <ul class="mt-2 space-y-0.5 text-sm text-amber-900">
                    @foreach ($lastEvent->reviewFlagLabels() as $label)
                        <li>• {{ $label }}</li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs text-amber-800">Your manager will review this.</p>
            @endif
        </div>
    @endif

    @if ($errorMessage)
        <div class="rounded-xl border border-danger-300 bg-danger-50 px-4 py-3 mb-3" role="alert">
            <p class="text-sm font-medium text-danger-800">{{ $errorMessage }}</p>
        </div>
    @endif

    @if ($kioskOnly)
        {{-- ── This outlet clocks in on its kiosk ─────────────────────────

             Everything below — the camera, the buttons, the off-site note —
             is absent rather than disabled, and that is deliberate on two
             counts.

             clock.js boots off the presence of #clock-video, so leaving the
             element out is what actually stops the camera. A disabled button
             over a live preview would keep the lens on all shift for a punch
             that cannot be made, and the indicator light would say so.

             And a control that is visible but refuses teaches people to press
             it anyway. If the answer is "use the tablet", the screen should
             say that and nothing else.

             Anyone who genuinely needs their phone — an area manager, a
             driver, somebody working an offsite event — is marked "always
             allowed" on their employee record and never sees this. --}}
        <div class="rounded-xl border border-brand-200 bg-brand-50 px-5 py-6 text-center mb-3">
            <div class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-full bg-brand-100 text-brand-700">
                <x-icon name="device" class="h-6 w-6" />
            </div>

            <p class="text-base font-semibold text-brand-900">
                {{ $kiosk ? 'Clock in on the ' . $kiosk->name : 'Clock in on the kiosk' }}
            </p>
            <p class="mt-1.5 text-sm text-brand-800">
                {{ $outlet?->name }} clocks in and out on its own tablet, so there is nothing
                to press here.
            </p>
            <p class="mt-3 text-xs text-brand-700">
                Just look at the camera on the tablet — no PIN needed. If it cannot recognise you,
                it will offer you one.
            </p>
        </div>

        {{-- Leave, time off, the roster and your own punch history all still
             work from this phone; only the clock itself has moved. Said out
             loud because an app that has stopped doing the thing it is named
             after looks broken otherwise. --}}
        <p class="mb-4 text-center text-xs text-gray-600">
            Leave, time off and your roster all still work from here.
        </p>
    @else

    @if ($kioskDown)
        {{-- The kiosk is the way in here, but it is not answering — so the
             phone is, and saying why saves somebody a walk to a dead tablet.
             The punch is still recorded and carries kiosk_down, which is a
             record rather than a fault and stays out of the review queue. --}}
        <div class="rounded-xl border border-warning-300 bg-warning-50 px-4 py-3 mb-3">
            <p class="text-sm font-medium text-warning-900">The outlet kiosk is not responding.</p>
            <p class="mt-0.5 text-xs text-warning-800">
                Clock in here instead — this one counts, and your manager will see the tablet is down.
            </p>
        </div>
    @endif

    {{-- ── Camera ────────────────────────────────────────────────────────
         wire:ignore is load-bearing, not tidiness. Every punch re-renders
         this component, and a morph that touches the <video> drops its
         srcObject — the preview would go black the moment somebody clocked
         in, on the one screen where a dead camera is unrecoverable without
         a reload. The overlay inside is driven by JS alone for the same
         reason. --}}
    <div wire:ignore class="rounded-xl overflow-hidden bg-gray-900 relative mb-3" style="aspect-ratio: 4 / 3;">
        {{-- No mirror class here: JS applies it, and only to the front
             camera, so a rear-facing kiosk is not shown back to front. --}}
        <video id="clock-video" class="w-full h-full object-cover"
               playsinline muted autoplay></video>
        <canvas id="clock-canvas" class="hidden"></canvas>

        {{-- Framing guide plus the scan ring. The dashed
             oval says where the face goes; the ring around
             it fills as the scan walks its five poses, so
             progress is read where the eyes already are
             rather than somewhere below the video. --}}
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
            <svg viewBox="0 0 200 260" class="h-[86%] w-auto" aria-hidden="true">
                <ellipse id="clock-guide-oval" cx="100" cy="130" rx="86" ry="118"
                         fill="none" stroke="rgba(255,255,255,0.55)"
                         stroke-width="3" stroke-dasharray="7 6"
                         style="transition: stroke .2s;"></ellipse>
                {{-- Wound from the top: rotate -90 puts the
                     dash origin at 12 o'clock. --}}
                <ellipse id="clock-guide-ring" cx="100" cy="130" rx="86" ry="118"
                         fill="none" stroke="#34d399" stroke-width="5"
                         stroke-linecap="round" transform="rotate(-90 100 130)"
                         style="opacity: 0; transition: opacity .2s, stroke-dashoffset .12s linear;"></ellipse>
            </svg>
        </div>

        <button type="button" data-clock-flip
                aria-label="Switch between the front and back camera"
                class="absolute bottom-2 right-2 rounded-full bg-gray-900/70 px-3 py-1.5 text-[11px] font-medium text-white active:bg-gray-900">
            Flip camera
        </button>

        {{-- A button, not a div, and it says "Tap to start" before any script
             touches it. Two reasons. On iOS a getUserMedia call made from a
             real tap is the one that reliably prompts, so every failure here
             has to be retryable by touching it. And because the script
             overwrites this text as its first action, text that still reads
             "Tap to start camera" means the script never ran at all — which
             is otherwise indistinguishable from a camera that hung. --}}
        <button type="button" id="clock-camera-overlay"
                class="absolute inset-0 grid place-items-center bg-gray-900/80 text-center px-6 w-full">
            <span id="clock-camera-message" class="text-sm text-gray-200">Tap to start camera</span>
        </button>
    </div>

    {{-- Live framing advice, kept apart from the status line because it
         changes several times a second and would stamp on messages the
         person actually needs to read. --}}
    <p id="clock-guide-hint" class="text-center text-sm font-medium text-gray-700 min-h-[1.25rem]"></p>

    <p id="clock-status" class="text-center text-sm text-gray-600 min-h-[1.25rem] mb-3" aria-live="polite"></p>

    {{-- Hidden until something has plainly gone wrong, then it is one line a
         staff member can read down the phone to a manager. "It's frozen" is
         not something anyone can act on; "app NOT STARTED" is. --}}
    <p id="clock-diagnostics" class="hidden text-center text-[11px] font-mono text-gray-400 mb-3"></p>

    {{-- Off-site note. Always available rather than only appearing after a
         refusal: somebody sent to another branch already knows they are
         away, and making them fail once first is pointless friction.

         Absent entirely for somebody allowed to clock in from anywhere. The
         box exists to justify an unexpected distance, and for them the
         distance is the job — asking every day for a reason that was settled
         once on their employee record is the friction this is meant to
         avoid. --}}
    @unless ($canClockAnywhere)
        <details class="mb-3 rounded-xl border border-gray-200 bg-white px-4 py-3" @if ($errorMessage) open @endif>
            <summary class="text-sm font-medium text-gray-700 cursor-pointer">Not at the outlet?</summary>
            <textarea wire:model="reason" rows="2" maxlength="1000"
                      class="mt-2 w-full rounded-lg border-gray-300 text-sm"
                      placeholder="e.g. covering at the Bangsar branch today"></textarea>
        </details>
    @endunless

    {{-- Never server-disabled. This button is re-rendered after every punch,
         so any disabled state JS had set would come back from the morph and
         strand somebody at the door with a dead button. Readiness is guarded
         in the handler instead, where it can say what it is waiting for. --}}
    <button type="button" id="clock-action"
            class="w-full min-h-[3.75rem] rounded-xl text-white text-lg font-semibold shadow-sm
                   disabled:opacity-50 disabled:cursor-not-allowed
                   {{ $isIn ? 'bg-brand-700 active:bg-brand-800' : 'bg-gray-800 active:bg-gray-900' }}">
        {{ $actionLabel }}
    </button>

    {{-- Breaks only exist between clocking in and clocking out, so the button
         is simply absent otherwise rather than present and refusing. --}}
    @if ($breakType)
        <button type="button" id="clock-break"
                class="mt-2 w-full min-h-[3.25rem] rounded-xl text-base font-semibold border
                       {{ $onBreak
                           ? 'border-warning-300 bg-warning-50 text-warning-800 active:bg-warning-100'
                           : 'border-gray-300 bg-white text-gray-800 active:bg-gray-50' }}">
            {{ $onBreak ? 'End Break' : 'Start Break' }}
        </button>

        @if ($onBreak)
            <p class="mt-2 text-center text-xs text-gray-600">
                On break since {{ $this->lastPunch()?->happened_at?->format('g:i A') }}.
                @if ($breakAllowance > 0)
                    {{ $breakAllowance }} minutes allowed this shift{{ $breakTaken > 0 ? ", {$breakTaken} already taken" : '' }}.
                @endif
            </p>
        @elseif ($breakTaken > 0)
            <p class="mt-2 text-center text-xs {{ $breakTaken > $breakAllowance ? 'text-danger-700 font-medium' : 'text-gray-600' }}">
                {{ $breakTaken }} of {{ $breakAllowance }} break minutes used this shift.
            </p>
        @endif
    @endif

    @endif {{-- /kioskOnly --}}

    {{-- ── Today ───────────────────────────────────────────────────────── --}}
    @if ($punches->isNotEmpty())
        <div class="mt-4 rounded-xl border border-gray-200 bg-white divide-y divide-gray-100">
            @foreach ($punches as $punch)
                <div class="flex items-center justify-between px-4 py-2.5">
                    <span class="text-sm text-gray-700">{{ $punch->typeLabel() }}</span>
                    <span class="text-sm font-medium text-gray-900 tabular-nums">
                        {{ $punch->happened_at->format('g:i A') }}
                        @if ($punch->needsReview())
                            <span class="ml-1 text-[11px] font-normal text-amber-700">under review</span>
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
    @endif

    @if ($outlet && ! $outlet->latitude)
        <p class="mt-3 text-xs text-gray-500 text-center">
            {{ $outlet->name }} has no location set, so we cannot check you are on site. Your manager can set it in HR settings.
        </p>
    @endif

    {{-- ── What just happened ───────────────────────────────────────────────

         The outcome used to be a line of text in a tinted box above the
         camera. At the moment it appears, the person is looking at the
         button they just pressed, near the bottom of a scrolled page — so
         the one thing they came to find out was off screen, and the honest
         answer to "did it work?" was to scroll up and read carefully.

         So it takes the whole screen instead. At a doorway, at shift change,
         held at arm's length, the question is binary and it should be
         answerable at a glance and from a distance.

         Last in the component root on purpose: it is a sibling of the
         wire:ignore camera subtree, never a wrapper, so a morph that adds or
         removes it cannot touch the <video>.

         Three outcomes, three colours, and the distinction is deliberate:

           Recorded and clean       teal    dismisses itself
           Recorded but flagged     amber   waits to be read
           Refused                  red     waits to be read

         Only the first goes away on its own. Somebody who has just lost RM3
         of service charge, or whose punch is going to a manager, is being
         told something they need to have actually read — and a notice that
         vanishes while you are still working out what it says is a notice
         that did not tell you. --}}
    @if ($showResult && ($lastEvent || $errorMessage))
        @php
            $refused  = (bool) $errorMessage;
            $flagged  = ! $refused && $lastEvent->status !== ClockEvent::STATUS_VERIFIED;
            $clean    = ! $refused && ! $flagged;

            $skin = $refused
                ? ['bg' => 'bg-danger-600',  'ink' => 'text-danger-50',  'btn' => 'text-danger-700']
                : ($flagged
                    ? ['bg' => 'bg-amber-500', 'ink' => 'text-amber-50',  'btn' => 'text-amber-700']
                    : ['bg' => 'bg-teal-600',  'ink' => 'text-teal-50',   'btn' => 'text-teal-700']);

            $headline = $refused
                ? 'NOT RECORDED'
                : Str::upper($lastEvent->typeLabel() === 'Clock in' ? 'Clocked in'
                    : ($lastEvent->typeLabel() === 'Clock out' ? 'Clocked out'
                    : ($lastEvent->typeLabel() === 'Break start' ? 'Break started' : 'Break ended')));
        @endphp

        {{-- data-punch-result names this element for tests and for anyone
             debugging from a screenshot: the inline error box below also
             carries role="alert", and "the first alert on the page" is not
             this one. The inline copy stays deliberately — it is the record
             that survives dismissing the notice, and the off-site details
             block opens itself against it. --}}
        <div role="alert" aria-live="assertive" data-punch-result="{{ $refused ? 'refused' : ($flagged ? 'flagged' : 'clean') }}"
             wire:key="result-{{ $lastEvent?->id ?? 'refused' }}"
             x-data="{
                 dismiss() { $wire.dismissResult(); },
                 init() {
                     {{-- Only a clean punch times out. See above. --}}
                     @if ($clean)
                         setTimeout(() => this.dismiss(), 4500);
                     @endif
                 },
             }"
             class="fixed inset-0 z-50 flex flex-col items-center justify-center px-6 text-center {{ $skin['bg'] }}">

            {{-- A tick or a cross, big enough to read across a kitchen. --}}
            <svg class="w-24 h-24 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                @if ($refused)
                    <circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/>
                @else
                    <circle cx="12" cy="12" r="10"/><path d="M8 12.5l2.5 2.5L16 9"/>
                @endif
            </svg>

            <p class="mt-5 text-3xl font-bold tracking-wide text-white">{{ $headline }}</p>

            @unless ($refused)
                <p class="mt-1 text-5xl font-bold tabular-nums text-white">
                    {{ $lastEvent->happened_at->format('g:i') }}
                    <span class="text-2xl align-top">{{ $lastEvent->happened_at->format('A') }}</span>
                </p>
            @endunless

            <p class="mt-3 text-base font-medium {{ $skin['ink'] }}">{{ $staff->name }}</p>

            @if ($refused)
                <p class="mt-3 max-w-sm text-sm {{ $skin['ink'] }}">{{ $errorMessage }}</p>
            @else
                @if ($lastEvent->minutes_late > 0)
                    {{-- The money gets its own line and its own size.

                         Written as one small sentence, the amount deducted was
                         the faintest thing on a screen whose entire job is to
                         tell somebody what just happened to their pay. It is
                         the fact most worth carrying away, so it is set like
                         one — and the minutes stay small above it, because
                         "seven" explains the charge but is not the charge. --}}
                    <p class="mt-4 max-w-sm text-sm {{ $skin['ink'] }}">
                        @if ($lastEvent->type === ClockEvent::TYPE_BREAK_END)
                            {{ $lastEvent->minutes_late }} {{ Str::plural('minute', $lastEvent->minutes_late) }} over your break allowance
                        @else
                            {{ $lastEvent->minutes_late }} {{ Str::plural('minute', $lastEvent->minutes_late) }} late
                        @endif
                    </p>

                    @if ($lastEvent->latenessWaived() && (float) $lastEvent->penalty_amount > 0)
                        {{-- Struck through rather than hidden. The charge was
                             real and somebody chose not to collect it, and a
                             card that simply stopped mentioning it would take
                             the credit for a decision away from whoever made
                             it — and leave the employee unsure what happened. --}}
                        <p class="mt-1 text-3xl font-bold tabular-nums text-white/60 line-through">
                            RM {{ number_format((float) $lastEvent->penalty_amount, 2) }}
                        </p>
                        <p class="text-xs {{ $skin['ink'] }}">waived — nothing off your service charge</p>
                    @elseif ((float) $lastEvent->penalty_amount > 0)
                        <p class="mt-1 text-3xl font-bold tabular-nums text-white">
                            RM {{ number_format((float) $lastEvent->penalty_amount, 2) }}
                        </p>
                        <p class="text-xs {{ $skin['ink'] }}">off your service charge</p>
                    @else
                        <p class="mt-1 text-sm font-medium text-white">Within grace — nothing deducted.</p>
                    @endif
                @endif

                @if ($lastEvent->reviewFlagLabels())
                    <ul class="mt-3 space-y-0.5 text-sm {{ $skin['ink'] }}">
                        @foreach ($lastEvent->reviewFlagLabels() as $label)
                            <li>• {{ $label }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-sm font-medium text-white">Your manager will review this.</p>
                @endif
            @endif

            {{-- Always present, even on the notice that times out: four and a
                 half seconds is a long time to stand in a doorway once you
                 already know the answer. --}}
            <button type="button" wire:click="dismissResult"
                    class="mt-8 min-h-[3rem] w-full max-w-xs rounded-xl bg-white px-6 text-base font-semibold {{ $skin['btn'] }} active:bg-gray-100">
                Done
            </button>
        </div>
    @endif
</div>

@script
<script>
    /*
     * Almost everything lives in resources/js/clock.js, which is a plain
     * module and runs whether or not Livewire boots — so a Livewire failure
     * can no longer take the camera preview down with it.
     *
     * This block exists for one reason: $wire is only available here.
     */
    document.addEventListener('click', (event) => {
        const shift = event.target.closest('#clock-action');
        const brk   = event.target.closest('#clock-break');

        if (! shift && ! brk) return;

        // The page only says WHICH button was pressed. What that means —
        // clock in, clock out, start break, end break — is decided server
        // side from what is already on record.
        window.ServoraClock?.performPunch($wire, brk ? 'break' : 'shift');
    });
</script>
@endscript
