{{--
    The beat before the clock starts.

    THREE JOBS, and it would be worth the tap for any one of them. It is where
    the language is chosen, and that has to happen before question one rather
    than be discovered at it. It is a "ready?" before a timed thing begins —
    the clock starts the instant a question is on screen, and dropping somebody
    straight into that from a course page is an ambush rather than a game. And
    the Start button is the first tap INSIDE this page, which is the only moment
    a browser will let the backing music begin: autoplay is refused outside a
    user gesture, and the tap that navigated here belonged to the last document.

    Nothing has been created at this point. Backing out costs nothing, which
    matters most on a one-shot challenge where a mis-tap used to burn the run.
--}}
<div class="p-4" x-data="{ chosen: @js($language) }"
     {{-- Fired by the tap-to-play card the moment the track is running: on an
          iPhone that tap is the only way music can start, so it doubles as
          Start — one tap, both things. begin() guards itself against firing
          twice. --}}
     @music-started.window="$wire.begin()">

    {{-- FIRST CHILD, and it stays the first child of the question screen too.
         Everything it renders is fixed or teleported, so the position costs the
         layout nothing — but it is what lets Livewire morph the component from
         this screen to the next without tearing the music player down, which
         would stop the track at the exact moment the quiz begins. --}}
    <x-training.quiz-fx :music="$quiz->musicEmbedUrl()" :music-file="$quiz->musicFileUrl()" :offer-tap="true" />

    @if (session()->has('error'))
        <div class="alert-danger mb-4">
            <x-icon name="alert" size="h-5 w-5" class="flex-shrink-0" />
            <p>{{ session('error') }}</p>
        </div>
    @endif

    <div class="card overflow-hidden">
        <div class="bg-brand-600 px-5 py-5 text-white">
            @if ($challenge)
                <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-brand-100">
                    <x-icon name="trophy" size="h-4 w-4" /> Challenge · {{ $challenge->windowLabel() }}
                </p>
            @elseif ($quiz->course)
                <p class="text-xs font-semibold uppercase tracking-wider text-brand-100">
                    {{ $quiz->course->title }}
                </p>
            @endif
            <h1 class="mt-1 text-2xl font-bold leading-tight">{{ $quiz->title }}</h1>
        </div>

        <div class="p-5">
            <div class="grid grid-cols-3 gap-2 text-center">
                <div class="rounded-surface bg-gray-50 px-2 py-3">
                    <p class="text-xl font-bold tabular-nums text-gray-900">{{ $quiz->questions_count }}</p>
                    <p class="text-xs text-gray-600">questions</p>
                </div>
                <div class="rounded-surface bg-gray-50 px-2 py-3">
                    <p class="text-xl font-bold tabular-nums text-gray-900">{{ $quiz->default_seconds }}s</p>
                    <p class="text-xs text-gray-600">each</p>
                </div>
                <div class="rounded-surface bg-gray-50 px-2 py-3">
                    <p class="text-xl font-bold tabular-nums text-gray-900">{{ $quiz->pass_mark }}%</p>
                    <p class="text-xs text-gray-600">to pass</p>
                </div>
            </div>

            {{-- ── The language ──

                 One quiz, several languages. The score counts once whichever
                 was read — the questions and the answer key are the same, only
                 the words differ. Drawn as full-width buttons rather than a
                 dropdown: this is the last thing somebody decides before a
                 timed run, on a phone, and a native select is a two-tap
                 control that hides its options until opened. --}}
            @if (count($languages) > 1)
                <div class="mt-5">
                    <p class="label">Read it in</p>
                    <div class="mt-1.5 space-y-2">
                        @foreach ($languages as $code => $label)
                            <button type="button" wire:key="lang-{{ $code }}"
                                    @click="chosen = @js($code); $wire.set('language', @js($code))"
                                    class="flex w-full items-center gap-3 rounded-surface border px-4 py-3.5 text-left
                                           text-base transition min-h-[3.25rem]"
                                    :class="chosen === @js($code)
                                        ? 'border-brand-500 bg-brand-50 text-brand-900 font-medium'
                                        : 'border-gray-200 bg-white text-gray-900'">
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border"
                                      :class="chosen === @js($code) ? 'border-brand-600' : 'border-gray-300'">
                                    <span class="h-2.5 w-2.5 rounded-full bg-brand-600"
                                          x-show="chosen === @js($code)" style="display:none"></span>
                                </span>
                                <span class="min-w-0 flex-1">{{ $label }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            <ul class="mt-5 space-y-1.5 text-sm text-gray-700">
                <li class="flex items-start gap-2">
                    <x-icon name="clock" size="h-4 w-4" class="mt-0.5 shrink-0 text-brand-600" />
                    <span>The faster you answer, the more it scores.</span>
                </li>
                @if ($quiz->wrong_penalty_percent > 0)
                    <li class="flex items-start gap-2">
                        <x-icon name="alert" size="h-4 w-4" class="mt-0.5 shrink-0 text-warning-600" />
                        <span>A wrong answer costs points on this one. Running out of time does not.</span>
                    </li>
                @endif
                @if ($best)
                    <li class="flex items-start gap-2">
                        <x-icon name="trophy" size="h-4 w-4" class="mt-0.5 shrink-0 text-warning-600" />
                        <span>Your best so far is {{ (float) $best->percent }}%. Beat it.</span>
                    </li>
                @endif
                @if ($challenge)
                    <li class="flex items-start gap-2">
                        <x-icon name="info" size="h-4 w-4" class="mt-0.5 shrink-0 text-info-600" />
                        <span>One run only — this one counts on the challenge board.</span>
                    </li>
                @endif
            </ul>

            {{-- THE TAP THAT STARTS THE MUSIC. `startMusic` is dispatched to
                 the window and quiz-fx picks it up synchronously, inside this
                 gesture — the only moment a browser will begin playback. The
                 Livewire call goes out in the same handler. --}}
            <button type="button"
                    @click="$dispatch('start-music')"
                    wire:click="begin"
                    class="btn-primary mt-6 w-full justify-center py-4 text-base">
                <span wire:loading.remove wire:target="begin">Start</span>
                <span wire:loading wire:target="begin">Starting…</span>
            </button>

            <a href="{{ route('clock.staff.learn') }}" wire:navigate
               class="btn-ghost mt-2 w-full justify-center">Not now</a>
        </div>
    </div>
</div>
