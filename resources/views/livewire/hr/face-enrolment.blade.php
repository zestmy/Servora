<div>
    {{-- The face module is not part of the main bundle. Pulled in only on
         this screen, the one manager-facing place that needs a camera. --}}
    @vite('resources/js/clock.js')

    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="text-xs text-gray-600">HR / Face Enrolment</p>
            <h2 class="text-lg font-semibold text-gray-700 mt-1">Face Enrolment</h2>
        </div>
        <a href="{{ route('hr.clock-ins') }}" wire:navigate class="btn-secondary">Back to Clock-Ins</a>
    </div>

    <div class="panel p-4 mb-4 text-xs text-gray-700">
        <p class="font-medium text-gray-900">Do this with the person standing next to you.</p>
        <p class="mt-1">
            What is stored is a set of numbers describing the face, not the photograph —
            the numbers cannot be turned back into a picture. The reference photo is kept
            only so you can see who you enrolled, and deleting it does not stop the
            matching working. Take {{ $minCaptures }} or more captures, moving the head
            slightly between each: one head-on shot stops working the week somebody grows
            a beard.
        </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Who --}}
        <div class="panel overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100">
                <input type="text" wire:model.live.debounce.400ms="search"
                       placeholder="Search staff"
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>
            <div class="divide-y divide-gray-100 max-h-[28rem] overflow-y-auto">
                @forelse ($employees as $employee)
                    @php $count = (int) ($counts[$employee->id] ?? 0); @endphp
                    <button type="button" wire:click="select({{ $employee->id }})"
                            wire:key="emp-{{ $employee->id }}"
                            class="w-full flex items-center justify-between gap-3 px-4 py-2.5 text-left hover:bg-gray-50
                                   {{ $selected?->id === $employee->id ? 'bg-brand-50' : '' }}">
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-gray-900 truncate">{{ $employee->name }}</span>
                            <span class="block text-[11px] text-gray-500 truncate">{{ $employee->outlet?->name }}</span>
                        </span>
                        {{-- The count is the whole point of this list: it says
                             at a glance who still cannot use the clock. --}}
                        <span class="shrink-0 text-[11px] font-medium px-2 py-0.5 rounded-full
                            {{ $count >= $minCaptures ? 'bg-success-100 text-success-700'
                               : ($count > 0 ? 'bg-warning-100 text-warning-700' : 'bg-gray-100 text-gray-500') }}">
                            {{ $count ?: 'none' }}
                        </span>
                    </button>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-gray-600">No staff match that.</p>
                @endforelse
            </div>
        </div>

        {{-- Capture.

             The camera panel is rendered whether or not somebody is selected,
             and never re-keyed. Tearing it down and rebuilding it per employee
             would mean a fresh getUserMedia — and a fresh permission prompt on
             some browsers — every time the manager moved to the next person in
             a queue of thirty. It boots once, and the button is what changes. --}}
        <div class="lg:col-span-2">
            <div class="panel p-5">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">
                            {{ $selected?->name ?? 'Nobody selected' }}
                        </h3>
                        <p class="text-xs text-gray-600">
                            @if ($selected)
                                {{ $captures->count() }} of {{ $maxCaptures }} captures
                                @if ($captures->count() < $minCaptures)
                                    · at least {{ $minCaptures }} recommended
                                @endif
                            @else
                                Pick somebody from the list to enrol.
                            @endif
                        </p>
                    </div>
                    @if ($selected)
                        <button wire:click="clearSelection" class="text-xs font-medium text-gray-600 hover:underline">Done</button>
                    @endif
                </div>

                @if ($errorMessage)
                    <div class="mb-3 px-4 py-3 bg-danger-50 border border-danger-200 text-danger-700 text-sm rounded-lg">
                        {{ $errorMessage }}
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        {{-- wire:ignore: saving a capture re-renders this
                             component, and a morph that touches the <video>
                             drops its srcObject — the preview would die on the
                             first capture, which is exactly when the manager
                             needs the next one. --}}
                        <div wire:ignore class="rounded-lg overflow-hidden bg-gray-900 relative" style="aspect-ratio: 4 / 3;">
                            <video id="enrol-video" class="w-full h-full object-cover" style="transform: scaleX(-1);"
                                   playsinline muted autoplay></video>
                            <canvas id="enrol-canvas" class="hidden"></canvas>
                            {{-- Tappable, and labelled before any script runs —
                                 see the clock screen for why both matter. --}}
                            <button type="button" id="enrol-overlay"
                                    class="absolute inset-0 grid place-items-center bg-gray-900/80 px-6 text-center w-full">
                                <span id="enrol-overlay-message" class="text-sm text-gray-200">Tap to start camera</span>
                            </button>
                        </div>

                        <p id="enrol-status" class="mt-2 text-center text-xs text-gray-600 min-h-[1rem]" aria-live="polite"></p>

                        {{-- Enabled state comes from the server, not from JS:
                             this button is re-rendered on every save, and a
                             flag set in JS would be wiped by the morph. --}}
                        <button type="button" id="enrol-capture" @disabled(! $selected)
                                class="mt-2 w-full min-h-[3rem] rounded-lg bg-brand-700 text-white text-sm font-semibold
                                       disabled:opacity-50 disabled:cursor-not-allowed active:bg-brand-800">
                            {{ $selected ? 'Capture' : 'Pick somebody first' }}
                        </button>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-600 mb-2">Enrolled</p>

                        @if (! $selected)
                            <p class="text-sm text-gray-500">—</p>
                        @elseif ($captures->isEmpty())
                            <p class="text-sm text-gray-500">Nothing enrolled yet.</p>
                        @else
                            <div class="grid grid-cols-3 gap-2">
                                @foreach ($captures as $capture)
                                    <div wire:key="cap-{{ $capture->id }}" class="relative group">
                                        @if ($capture->photo_path)
                                            <img src="{{ route('hr.face-enrolment.photo', $capture) }}"
                                                 alt="Enrolment capture"
                                                 class="w-full aspect-square object-cover rounded-lg border border-gray-200">
                                        @else
                                            <div class="w-full aspect-square rounded-lg border border-dashed border-gray-300 grid place-items-center text-[10px] text-gray-400 text-center px-1">
                                                no photo
                                            </div>
                                        @endif
                                        <button wire:click="deleteCapture({{ $capture->id }})"
                                                wire:confirm="Delete this capture?"
                                                aria-label="Delete capture"
                                                class="absolute -top-1.5 -right-1.5 w-6 h-6 rounded-full bg-white border border-gray-300
                                                       text-gray-600 text-sm leading-none shadow-sm hover:bg-danger-50 hover:text-danger-700">
                                            &times;
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@script
<script>
    /*
     * Enrolment capture. Same camera helper as the staff app so a face
     * enrolled here is measured exactly the way it will be measured at the
     * door — a different pipeline on either side would produce descriptors
     * that never quite match.
     */
    const video   = document.getElementById('enrol-video');
    const canvas  = document.getElementById('enrol-canvas');
    const overlay = document.getElementById('enrol-overlay');
    const overlayMessage = document.getElementById('enrol-overlay-message');

    // Everything inside wire:ignore keeps its identity across renders and can
    // be held in a variable. The status line and the button are re-rendered,
    // so they are looked up fresh each time they are touched.
    const setStatus = (text) => {
        const el = document.getElementById('enrol-status');
        if (el) el.textContent = text || '';
    };

    let camera = null;
    let modelsReady = false;
    let capturing = false;
    let starting = false;

    const setOverlay = (text) => { if (overlayMessage) overlayMessage.textContent = text; };

    async function boot() {
        if (starting || camera?.stream || ! video) return;

        starting = true;
        setOverlay('Starting camera…');

        if (! window.ServoraClock) {
            setOverlay('The camera could not be set up. Reload the page.');
            starting = false;
            return;
        }

        camera ??= new window.ServoraClock.ClockCamera({ video, canvas, onStatus: setStatus });

        try {
            await camera.start();
            overlay.classList.add('hidden');
        } catch (e) {
            setOverlay(e?.name === 'NotAllowedError'
                ? 'Camera blocked. Allow camera for this site, then tap here.'
                : 'Could not start the camera. Tap to try again.');
            starting = false;
            return;
        }

        setStatus('Loading the face model…');

        try {
            await window.ServoraClock.loadModels();
            modelsReady = true;
            setStatus('');
        } catch (e) {
            setStatus('Face model could not load. Check the connection and reload.');
        }

        starting = false;
    }

    // Retrying from a real tap is the call browsers reliably prompt for.
    overlay?.addEventListener('click', boot);

    // Delegated: the button element is replaced on every render, so a handler
    // bound to the original node would stop firing after the first capture.
    document.addEventListener('click', async (event) => {
        if (! event.target.closest('#enrol-capture')) return;

        if (! modelsReady) {
            setStatus('Face model still loading — a moment.');
            return;
        }

        if (capturing) return;
        capturing = true;
        setStatus('Hold still…');

        try {
            const face = await camera.capture();

            if (! face) {
                setStatus('No face found. Move closer and try again.');
                return;
            }

            await $wire.enrol({ descriptor: face.descriptor, photo: face.selfie });
            setStatus('Saved. Turn the head slightly and take another.');
        } catch (e) {
            setStatus('Capture failed. Try again.');
        } finally {
            capturing = false;
        }
    });

    document.addEventListener('livewire:navigating', () => camera?.stop(), { once: true });

    boot();
</script>
@endscript
