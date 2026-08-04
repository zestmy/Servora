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
                    {{-- A real link, not a wire:click. Picking a name has to work
                         on a device where Livewire's JavaScript never started,
                         which is exactly how this failed: taps did nothing and
                         the list looked identical to a slow network. --}}
                    <a href="{{ route('hr.face-enrolment', ['employee' => $employee->id]) }}" wire:navigate
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
                    </a>
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
                        <a href="{{ route('hr.face-enrolment') }}" wire:navigate
                           class="text-xs font-medium text-gray-600 hover:underline">Done</a>
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

                        {{-- Hidden until something has plainly gone wrong. "app
                             NOT STARTED" is the difference between a camera
                             fault and Livewire never booting, and from a
                             screenshot the two are identical. --}}
                        <p id="enrol-diagnostics" class="hidden mt-1 text-center text-[11px] font-mono text-gray-400"></p>

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
     * The camera and the model live in resources/js/clock.js, which self-boots
     * as soon as the module evaluates. It used to be started from here, and
     * that was a race: @script runs when Livewire initialises, the Vite tag is
     * a deferred module, and neither order is guaranteed — so this block could
     * reach for window.ServoraClock and find nothing there yet.
     *
     * This exists for one reason: $wire is only available here.
     *
     * Delegated, because the button is replaced on every render — a handler
     * bound to the original node would stop firing after the first capture.
     */
    document.addEventListener('click', (event) => {
        if (! event.target.closest('#enrol-capture')) return;

        window.ServoraClock?.performEnrolCapture($wire);
    });
</script>
@endscript
