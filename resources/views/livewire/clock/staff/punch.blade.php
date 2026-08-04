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
                {{ $lastEvent->type === ClockEvent::TYPE_IN ? 'Clocked in' : 'Clocked out' }}
                at {{ $lastEvent->happened_at->format('g:i A') }}
            </p>

            @if ($lastEvent->minutes_late > 0)
                <p class="mt-1 text-sm text-amber-900">
                    {{ $lastEvent->minutes_late }} {{ Str::plural('minute', $lastEvent->minutes_late) }} late.
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

        <div id="clock-camera-overlay"
             class="absolute inset-0 grid place-items-center bg-gray-900/80 text-center px-6">
            <p id="clock-camera-message" class="text-sm text-gray-200">Starting camera…</p>
        </div>
    </div>

    <p id="clock-status" class="text-center text-sm text-gray-600 min-h-[1.25rem] mb-3" aria-live="polite"></p>

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

    {{-- ── Today ───────────────────────────────────────────────────────── --}}
    @if ($punches->isNotEmpty())
        <div class="mt-4 rounded-xl border border-gray-200 bg-white divide-y divide-gray-100">
            @foreach ($punches as $punch)
                <div class="flex items-center justify-between px-4 py-2.5">
                    <span class="text-sm text-gray-700">
                        {{ $punch->type === ClockEvent::TYPE_IN ? 'In' : 'Out' }}
                    </span>
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
     * Drives the camera and the punch.
     *
     * Deliberately plain and linear: this runs once, in a doorway, on a
     * phone that may be three years old, and the person using it is late.
     * Every failure path ends with the button usable again — being unable
     * to press it at all is worse than a punch that gets flagged.
     */
    const video   = document.getElementById('clock-video');
    const canvas  = document.getElementById('clock-canvas');
    const overlay = document.getElementById('clock-camera-overlay');
    const overlayMessage = document.getElementById('clock-camera-message');

    const api = window.ServoraClock;

    // Everything inside wire:ignore keeps its identity across renders and can
    // be held in a variable. The status line is re-rendered, so it is looked
    // up fresh each time it is written to.
    const setStatus = (text) => {
        const el = document.getElementById('clock-status');
        if (el) el.textContent = text || '';
    };

    let camera = null;
    let busy = false;
    let booted = false;
    /** Whether the face step can run at all — a denied camera or missing
     *  model files turn it off, and the server decides what that costs. */
    let faceAvailable = false;

    async function boot() {
        if (! api || ! navigator.mediaDevices?.getUserMedia) {
            overlayMessage.textContent = 'This browser cannot use the camera. Ask your manager to record this shift.';
            booted = true;
            return;
        }

        camera = new api.ClockCamera({ video, canvas, onStatus: setStatus });

        try {
            await camera.start();
            overlay.classList.add('hidden');
        } catch (e) {
            overlayMessage.textContent = 'Camera blocked. Allow camera for this site in your browser settings, then reload.';
            // Still punchable: if the company does not require a face the
            // punch is perfectly valid, and if it does the server refuses it
            // in words the employee can act on.
            booted = true;
            return;
        }

        setStatus('Getting the face check ready…');

        try {
            await api.loadModels();
            faceAvailable = true;
            setStatus('');
        } catch (e) {
            // The weights are ~6.5MB; a bad connection is the usual cause.
            setStatus('Face check unavailable — your punch will be sent for review.');
        }

        booted = true;
    }

    async function punch() {
        if (busy) return;

        if (! booted) {
            setStatus('Just getting the camera ready…');
            return;
        }

        busy = true;

        try {
            // The position request goes out first and is awaited last: the
            // GPS fix is the slowest part and there is no reason for it to
            // queue behind the camera work.
            const positionPromise = api.currentPosition();

            let face = null;

            if (faceAvailable) {
                setStatus('Blink once');
                await camera.waitForBlink();

                setStatus('Hold still…');
                face = await camera.capture();

                if (! face) {
                    setStatus('Could not see your face. Try again in better light.');
                    return;
                }
            }

            setStatus('Checking where you are…');
            const position = await positionPromise;

            setStatus('Recording…');

            await $wire.submit({
                latitude:   position?.latitude ?? null,
                longitude:  position?.longitude ?? null,
                accuracy:   position?.accuracy ?? null,
                descriptor: face?.descriptor ?? null,
                // A still is kept even when the descriptor could not be
                // computed — a manager reviewing a flagged punch would much
                // rather have a photo than a shrug.
                selfie:     face?.selfie ?? (camera?.stream ? camera.still() : null),
                device:     navigator.userAgentData?.platform ?? null,
            });

            setStatus('');
        } catch (e) {
            setStatus('Something went wrong. Try again.');
        } finally {
            busy = false;
        }
    }

    // Delegated: the button is replaced on every render, so a handler bound
    // to the original node would stop firing after the first punch.
    document.addEventListener('click', (event) => {
        if (event.target.closest('#clock-action')) punch();
    });

    // Free the camera when the screen is left, so the indicator light goes
    // out and the battery stops paying for a preview nobody is looking at.
    document.addEventListener('livewire:navigating', () => camera?.stop(), { once: true });

    boot();
</script>
@endscript
