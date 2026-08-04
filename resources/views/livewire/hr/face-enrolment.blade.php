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
        <div class="panel p-4 space-y-4">

            {{-- Outlet first.

                 The name list used to span every outlet a manager could see
                 and stop at fifty, so at a multi-branch company the person
                 standing in front of them could simply be absent from it,
                 with nothing on screen explaining why. Narrowing first makes
                 the second list short enough to be complete.

                 Its own form: choosing an outlet must DROP the chosen
                 employee, and it does that by not carrying one. --}}
            @if ($outlets->count() > 1)
                <form method="GET" action="{{ route('hr.face-enrolment') }}">
                    <label for="enrol-outlet" class="block text-sm font-semibold text-gray-900 mb-1.5">
                        Which outlet?
                    </label>

                    <div class="flex gap-2">
                        <select id="enrol-outlet" name="outlet"
                                onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()"
                                class="flex-1 min-h-[3rem] rounded-lg border-gray-300 text-sm">
                            <option value="">Choose an outlet…</option>
                            @foreach ($outlets as $outlet)
                                <option value="{{ $outlet->id }}" @selected($outletId === $outlet->id)>
                                    {{ $outlet->name }}
                                </option>
                            @endforeach
                        </select>

                        <button type="submit" class="btn-secondary shrink-0">Select</button>
                    </div>
                </form>
            @endif

            {{-- Then who.

                 A GET form rather than a wire:click or an onchange-only
                 select: it works with no JavaScript, and the Select button
                 means a browser that ignores onchange is still usable. --}}
            <form method="GET" action="{{ route('hr.face-enrolment') }}">
                {{-- Keeps the outlet across the hop, so picking a name does
                     not silently reset the list it came from. --}}
                <input type="hidden" name="outlet" value="{{ $outletId }}">

                <label for="enrol-employee" class="block text-sm font-semibold text-gray-900 mb-1.5">
                    Who are you enrolling?
                </label>

                <div class="flex gap-2">
                    <select id="enrol-employee" name="employee"
                            @disabled(! $outletId)
                            onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()"
                            class="flex-1 min-h-[3rem] rounded-lg border-gray-300 text-sm disabled:bg-gray-100 disabled:text-gray-500">
                        <option value="">
                            {{ $outletId ? 'Choose somebody…' : 'Choose an outlet first' }}
                        </option>
                        @foreach ($employees as $employee)
                            @php $count = (int) ($counts[$employee->id] ?? 0); @endphp
                            {{-- The capture count rides in the label: it is the
                                 question this screen exists to answer, and a
                                 select has nowhere else to put it. --}}
                            <option value="{{ $employee->id }}" @selected($selected?->id === $employee->id)>
                                {{ $employee->name }}
                                ({{ $count ?: 'no' }} {{ Str::plural('face', $count ?: 0) }})
                            </option>
                        @endforeach
                    </select>

                    <button type="submit" class="btn-secondary shrink-0" @disabled(! $outletId)>Select</button>
                </div>

                @if ($outletId && $employees->isEmpty())
                    <p class="mt-2 text-sm text-gray-600">No active staff at this outlet.</p>
                @elseif ($outlets->isEmpty())
                    <p class="mt-2 text-sm text-gray-600">No outlets you can see.</p>
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

                            {{-- Framing guide plus the scan ring. The dashed
                                 oval says where the face goes; the ring around
                                 it fills as the scan walks its five poses, so
                                 progress is read where the eyes already are
                                 rather than somewhere below the video. --}}
                            <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                                <svg viewBox="0 0 200 260" class="h-[86%] w-auto" aria-hidden="true">
                                    <ellipse id="enrol-guide-oval" cx="100" cy="130" rx="86" ry="118"
                                             fill="none" stroke="rgba(255,255,255,0.55)"
                                             stroke-width="3" stroke-dasharray="7 6"
                                             style="transition: stroke .2s;"></ellipse>
                                    {{-- Wound from the top: rotate -90 puts the
                                         dash origin at 12 o'clock. --}}
                                    <ellipse id="enrol-guide-ring" cx="100" cy="130" rx="86" ry="118"
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
                        <div id="enrol-progress" class="mt-2" data-needed="{{ $needed }}" data-have="{{ $captures->count() }}">
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
                        {{-- The scan runs itself when somebody is selected and
                             is short of faces. This is for re-running it, and
                             for stopping one mid-way. --}}
                        <button type="button" data-clock-scan @disabled(! $selected)
                                class="mt-2 w-full min-h-[2.75rem] rounded-lg border border-brand-600 text-sm font-semibold
                                       text-brand-700 disabled:opacity-50 active:bg-brand-50">
                            Start / stop guided scan
                        </button>

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
                                    {{-- An "odd one out" is a capture sitting further from this
                                         person's other pictures than a punch is allowed to sit
                                         from any of them. It can never be the one that matches,
                                         so at best it is dead weight — and at worst it is a bad
                                         frame dragging the enrolment around.

                                         The number is shown rather than just a warning colour:
                                         0.62 next to a row of 0.28s tells you which to delete
                                         without having to trust a badge. --}}
                                    @php
                                        $odd = $capture->nearest !== null && $capture->nearest > 0.5;
                                    @endphp
                                    <div class="relative">
                                        @if ($capture->photo_path)
                                            <img src="{{ route('hr.face-enrolment.photo', $capture) }}"
                                                 alt="Enrolment capture"
                                                 class="w-full aspect-square object-cover rounded-lg border-2 {{ $odd ? 'border-danger-400' : 'border-gray-200' }}">
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

                                        @if ($capture->nearest !== null)
                                            <span title="Distance to this person's closest other capture. Lower is better."
                                                  class="absolute bottom-1 left-1 rounded px-1 text-[10px] font-mono leading-tight
                                                         {{ $odd ? 'bg-danger-600 text-white' : 'bg-black/55 text-white' }}">
                                                {{ number_format($capture->nearest, 2) }}
                                            </span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            @php $odds = $captures->filter(fn ($c) => $c->nearest !== null && $c->nearest > 0.5); @endphp

                            @if ($odds->isNotEmpty())
                                <p class="mt-2 text-xs text-danger-700">
                                    {{ $odds->count() }} {{ Str::plural('capture', $odds->count()) }}
                                    {{ $odds->count() === 1 ? 'sits' : 'sit' }} further from the others than a
                                    clock-in is allowed to. Look at {{ $odds->count() === 1 ? 'it' : 'them' }} —
                                    if the picture is a bad angle, badly lit, or not a face at all, delete it and
                                    take another. It cannot help a match and it may be hurting one.
                                </p>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
