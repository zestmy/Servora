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

<div wire:key="punch-{{ $nextType }}">

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
                    @if ((float) $lastEvent->penalty_amount > 0)
                        RM {{ number_format((float) $lastEvent->penalty_amount, 2) }} deducted from your service charge.
                    @else
                        Within grace — nothing deducted.
                    @endif
                </p>
            @endif

            @if ($lastEvent->flagLabels())
                <ul class="mt-2 space-y-0.5 text-sm text-amber-900">
                    @foreach ($lastEvent->flagLabels() as $label)
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

    {{-- ── Camera ────────────────────────────────────────────────────────
         wire:ignore is load-bearing, not tidiness. Every punch re-renders
         this component, and a morph that touches the <video> drops its
         srcObject — the preview would go black the moment somebody clocked
         in, on the one screen where a dead camera is unrecoverable without
         a reload. The overlay inside is driven by JS alone for the same
         reason. --}}
    <div wire:ignore class="rounded-xl overflow-hidden bg-gray-900 relative mb-3" style="aspect-ratio: 4 / 3;">
        <video id="clock-video" class="clock-video w-full h-full object-cover"
               playsinline muted autoplay></video>
        <canvas id="clock-canvas" class="hidden"></canvas>

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

    <p id="clock-status" class="text-center text-sm text-gray-600 min-h-[1.25rem] mb-3" aria-live="polite"></p>

    {{-- Hidden until something has plainly gone wrong, then it is one line a
         staff member can read down the phone to a manager. "It's frozen" is
         not something anyone can act on; "app NOT STARTED" is. --}}
    <p id="clock-diagnostics" class="hidden text-center text-[11px] font-mono text-gray-400 mb-3"></p>

    {{-- Off-site note. Always available rather than only appearing after a
         refusal: somebody sent to another branch already knows they are
         away, and making them fail once first is pointless friction. --}}
    <details class="mb-3 rounded-xl border border-gray-200 bg-white px-4 py-3" @if ($errorMessage) open @endif>
        <summary class="text-sm font-medium text-gray-700 cursor-pointer">Not at the outlet?</summary>
        <textarea wire:model="reason" rows="2" maxlength="1000"
                  class="mt-2 w-full rounded-lg border-gray-300 text-sm"
                  placeholder="e.g. covering at the Bangsar branch today"></textarea>
    </details>

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
