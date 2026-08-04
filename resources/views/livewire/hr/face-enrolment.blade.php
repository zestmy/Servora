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

    <div class="space-y-4">

        {{-- Who.

             A dropdown, not a scrolling list. The list pushed the camera and
             the capture button so far down the page that enrolling somebody
             meant scrolling past everybody else to reach the shutter — on the
             one screen where the manager and the employee are both standing
             there waiting.

             A GET form rather than a wire:click or an onchange-only select:
             it works with no JavaScript, and the Select button means a
             browser that ignores onchange is still usable. --}}
        <div class="panel p-4">
            <form method="GET" action="{{ route('hr.face-enrolment') }}">
                <label for="enrol-employee" class="block text-sm font-semibold text-gray-900 mb-1.5">
                    Who are you enrolling?
                </label>

                <div class="flex gap-2">
                    <select id="enrol-employee" name="employee"
                            onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()"
                            class="flex-1 min-h-[3rem] rounded-lg border-gray-300 text-sm">
                        <option value="">Choose somebody…</option>
                        @foreach ($employees as $employee)
                            @php $count = (int) ($counts[$employee->id] ?? 0); @endphp
                            {{-- The capture count rides in the label: it is the
                                 question this screen exists to answer, and a
                                 select has nowhere else to put it. --}}
                            <option value="{{ $employee->id }}" @selected($selected?->id === $employee->id)>
                                {{ $employee->name }}
                                @if ($employee->outlet?->name) — {{ $employee->outlet->name }} @endif
                                ({{ $count ?: 'no' }} {{ Str::plural('face', $count ?: 0) }})
                            </option>
                        @endforeach
                    </select>

                    <button type="submit" class="btn-secondary shrink-0">Select</button>
                </div>

                @if ($employees->isEmpty())
                    <p class="mt-2 text-sm text-gray-600">No staff at the outlets you can see.</p>
                @endif
            </form>
        </div>

        {{-- Capture.

             The camera panel is rendered whether or not somebody is selected,
             and never re-keyed. Tearing it down and rebuilding it per employee
             would mean a fresh getUserMedia — and a fresh permission prompt on
             some browsers — every time the manager moved to the next person in
             a queue of thirty. It boots once, and the button is what changes. --}}
        <div>
            <div class="panel p-5" id="enrol-form"
                 data-employee="{{ $selected?->id }}"
                 data-endpoint="{{ route('hr.face-enrolment.capture') }}">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">
                            {{ $selected?->name ?? 'Nobody selected' }}
                        </h3>
                        <p class="text-xs text-gray-600">
                            @if ($selected)
                                <span id="enrol-count">{{ $captures->count() }}</span> of {{ $maxCaptures }} captures
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

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        {{-- wire:ignore: saving a capture re-renders this
                             component, and a morph that touches the <video>
                             drops its srcObject — the preview would die on the
                             first capture, which is exactly when the manager
                             needs the next one. --}}
                        <div wire:ignore class="rounded-lg overflow-hidden bg-gray-900 relative" style="aspect-ratio: 4 / 3;">
                            {{-- The mirror is applied by JS, not here: only the
                                 front camera should be flipped. --}}
                            <video id="enrol-video" class="w-full h-full object-cover"
                                   playsinline muted autoplay></video>
                            <canvas id="enrol-canvas" class="hidden"></canvas>

                            {{-- Framing guide. pointer-events-none so it never
                                 swallows the tap that retries the camera. --}}
                            <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                                <div id="enrol-guide-oval"
                                     class="rounded-[50%] border-[3px] border-dashed transition-colors duration-200"
                                     style="width: 56%; aspect-ratio: 3 / 4; border-color: rgba(255,255,255,0.55);"></div>
                            </div>

                            <button type="button" data-clock-flip
                                    aria-label="Switch between the front and back camera"
                                    class="absolute bottom-2 right-2 rounded-full bg-gray-900/70 px-3 py-1.5 text-[11px] font-medium text-white active:bg-gray-900">
                                Flip camera
                            </button>
                            {{-- Tappable, and labelled before any script runs —
                                 see the clock screen for why both matter. --}}
                            <button type="button" id="enrol-overlay"
                                    class="absolute inset-0 grid place-items-center bg-gray-900/80 px-6 text-center w-full">
                                <span id="enrol-overlay-message" class="text-sm text-gray-200">Tap to start camera</span>
                            </button>
                        </div>

                        {{-- Live framing advice. Separate from the status line
                             because it changes several times a second and would
                             otherwise stamp on messages the person needs to read. --}}
                        <p id="enrol-guide-hint" class="mt-2 text-center text-xs font-medium text-gray-700 min-h-[1rem]"></p>

                        <p id="enrol-status" class="mt-1 text-center text-xs text-gray-600 min-h-[1rem]" aria-live="polite"></p>

                        @php $needed = $minCaptures; @endphp
                        {{-- Progress, so "how many more?" is answerable without
                             counting thumbnails. The first three slots are the
                             ones that matter; the rest are headroom. --}}
                        <div id="enrol-progress" class="mt-2" data-needed="{{ $needed }}">
                            <div class="flex items-center gap-1" role="img"
                                 aria-label="{{ $captures->count() }} of {{ $maxCaptures }} captures taken">
                                @for ($i = 0; $i < $maxCaptures; $i++)
                                    <span data-slot class="h-1.5 flex-1 rounded-full transition-colors duration-200"
                                          style="background-color: {{ $i < $captures->count()
                                              ? ($i < $needed ? '#0d5f61' : '#6ee7b7')
                                              : '#e5e7eb' }};"></span>
                                @endfor
                            </div>
                            <p data-progress-label class="mt-1 text-center text-[11px] text-gray-600">
                                {{ $captures->count() >= $needed
                                    ? $captures->count() . ' on file — enough to clock in with'
                                    : $captures->count() . ' of ' . $needed . ' needed' }}
                            </p>
                        </div>

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
                        @else
                            <p id="enrol-empty" class="text-sm text-gray-500 {{ $captures->isEmpty() ? '' : 'hidden' }}">
                                Nothing enrolled yet.
                            </p>

                            {{-- Appended to by clock.js after each capture, so
                                 the camera is not torn down between shots. --}}
                            <div id="enrol-captures" class="grid grid-cols-3 gap-2">
                                @foreach ($captures as $capture)
                                    <div class="relative">
                                        @if ($capture->photo_path)
                                            <img src="{{ route('hr.face-enrolment.photo', $capture) }}"
                                                 alt="Enrolment capture"
                                                 class="w-full aspect-square object-cover rounded-lg border border-gray-200">
                                        @else
                                            <div class="w-full aspect-square rounded-lg border border-dashed border-gray-300 grid place-items-center text-[10px] text-gray-400 text-center px-1">
                                                no photo
                                            </div>
                                        @endif
                                        {{-- A form post: removing a bad capture
                                             must work with no JavaScript too. --}}
                                        <form method="POST"
                                              action="{{ route('hr.face-enrolment.delete', $capture) }}"
                                              onsubmit="return confirm('Delete this capture?')"
                                              class="absolute -top-1.5 -right-1.5">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" aria-label="Delete capture"
                                                    class="w-6 h-6 rounded-full bg-white border border-gray-300
                                                           text-gray-600 text-sm leading-none shadow-sm hover:bg-danger-50 hover:text-danger-700">
                                                &times;
                                            </button>
                                        </form>
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
