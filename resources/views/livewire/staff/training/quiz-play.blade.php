{{--
    One question, on a phone, with wet hands.

    Every option is a full-width 44px+ target — the staff apps in this product
    are used gloved and that rule is not optional there. The countdown is a CSS
    animation rather than a JS timer: it is a picture of the clock, and the
    clock itself is on the server (see App\Livewire\Lms\QuizPlay).
--}}
{{--
    NO wire:key ON THIS ROOT ELEMENT. It had one, keyed on the question, meant
    to force a clean slate between questions — and a key on a component's root
    is the one place Livewire says never to put it. The root is what Livewire
    morphs the response into; keying it makes the morph replace the element
    Livewire is tracking, and the rebuilt DOM comes back with nothing bound to
    it. The symptom is precisely what was reported: the options render, look
    right, and do nothing at all when tapped.

    The per-question keys that were actually wanted are on the option buttons
    below, which is where they belong.
--}}
<div class="p-4" x-data="{ expired: false }">

    {{-- First child, same as on the start screen — see the note there. The
         morph between the two must not tear the music player down. --}}
    <x-training.quiz-fx :music="$quiz->musicEmbedUrl()" />

    <div class="mb-4 flex items-center justify-between gap-3">
        <p class="text-sm font-medium text-gray-600">
            Question {{ $index + 1 }} of {{ $total }}
        </p>
        @if ($quiz->isMultilingual())
            <p class="text-xs text-gray-600">{{ \App\Models\TrainingQuiz::LANGUAGES[$language] ?? '' }}</p>
        @endif
    </div>

    {{-- Progress through the quiz --}}
    <div class="mb-5 h-1.5 w-full overflow-hidden rounded-full bg-gray-200">
        <div class="h-full rounded-full bg-brand-600 transition-all duration-500"
             style="width: {{ round(($index) / max(1, $total) * 100) }}%"></div>
    </div>

    {{-- The countdown, and it is meant to be the loudest thing on the screen.

         A bar alone is a fact; a number ticking down is a pulse, and the whole
         reason this format works on a floor at 3pm is that it feels like a
         game. It goes red and beats in the last five seconds.

         THE NUMBER IS STILL NOT THE CLOCK. The seconds that decide points are
         the difference between two SERVER stamps — see the component. This is
         a picture of the clock, drawn locally so it stays smooth on a phone
         that is polling nothing.

         Its own x-data, so that each question gets a fresh count: the block is
         removed on feedback and re-added for the next question, and state
         hoisted to the root would come back holding the previous question's
         remaining seconds. The interval checks it is still in the document
         rather than being cleaned up by hand — a morph can remove this element
         without anything getting the chance to run teardown. --}}
    @unless ($showFeedback)
        <div class="mb-5" wire:key="timer-{{ $question->id }}"
             x-data="{ left: {{ $seconds }}, fired: false }"
             x-init="const tick = setInterval(() => {
                         if (! $el.isConnected) { clearInterval(tick); return; }
                         left = Math.max(0, left - 1);
                         if (left === 0 && ! fired && ! expired) {
                             fired = expired = true;
                             clearInterval(tick);
                             $wire.timeout();
                         }
                     }, 1000)">
            <div class="flex items-center gap-4">
                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full border-4 transition-colors"
                     :class="left <= 5
                         ? 'border-danger-500 text-danger-600 motion-safe:animate-urgent'
                         : 'border-brand-500 text-brand-700'">
                    <span class="text-2xl font-bold tabular-nums" x-text="left">{{ $seconds }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    {{-- The duration is the question's own limit, so it goes in
                         a style attribute — a Tailwind class cannot carry data. --}}
                    <div class="h-3 w-full overflow-hidden rounded-full bg-gray-200">
                        <div class="h-full w-full origin-left rounded-full motion-safe:animate-countdown transition-colors"
                             :class="left <= 5 ? 'bg-danger-500' : 'bg-warning-500'"
                             style="animation-duration: {{ $seconds }}s"></div>
                    </div>
                    <p class="mt-1.5 text-xs font-medium text-gray-600">
                        Answer fast — points fall as the clock does.
                    </p>
                </div>
            </div>
        </div>
    @endunless

    <div class="card p-6 mb-4">
        {{-- $prompt, not $question->prompt: the same question in whichever
             language this run is being read in. The answer key is shared, so
             the options below are index-for-index with the original. --}}
        <p class="text-lg font-semibold leading-snug text-gray-900">{{ $prompt }}</p>
        @if ($question->isMultiSelect())
            <p class="help mt-2">Choose all that apply, then press Answer.</p>
        @endif
    </div>

    <div class="space-y-2">
        @foreach ($order as $position)
            @php
                $option    = $options[$position];
                $isChosen  = in_array($position, array_map('intval', $chosen), true);
                $isRight   = $showFeedback && in_array($position, $lastCorrectIndexes, true);
                $isWrongly = $showFeedback && $isChosen && ! $isRight;
            @endphp
            <button type="button" wire:key="opt-{{ $question->id }}-{{ $position }}"
                    wire:click="choose({{ $position }}, {{ $question->isMultiSelect() ? 'true' : 'false' }})"
                    @disabled($showFeedback)
                    class="flex w-full items-center gap-3 rounded-surface border px-4 py-4 text-left text-base
                           transition min-h-[3.25rem]
                           {{ $isRight ? 'border-success-400 bg-success-50 text-success-900'
                              : ($isWrongly ? 'border-danger-400 bg-danger-50 text-danger-900'
                              : ($isChosen ? 'border-brand-500 bg-brand-50 text-brand-900'
                              : 'border-gray-200 bg-white text-gray-900 hover:border-brand-300 hover:bg-brand-50/40')) }}">
                <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border
                             {{ $isChosen || $isRight ? 'border-transparent bg-current/10' : 'border-gray-300' }}">
                    @if ($isRight)
                        <x-icon name="check" size="h-4 w-4" class="text-success-700" />
                    @elseif ($isWrongly)
                        <span class="text-sm font-bold text-danger-700" aria-hidden="true">&times;</span>
                    @elseif ($isChosen)
                        <span class="h-2.5 w-2.5 rounded-full bg-brand-600" aria-hidden="true"></span>
                    @endif
                </span>
                <span class="min-w-0 flex-1">{{ $option }}</span>
            </button>
        @endforeach
    </div>

    @if ($showFeedback)
        {{-- Keyed on the question so the entrance animation actually plays
             again: without a key the morph reuses the element, and a CSS
             animation on an element that was never removed does not restart. --}}
        <div wire:key="feedback-{{ $question->id }}"
             class="card p-5 mt-4 relative overflow-visible
                    {{ $lastCorrect ? 'border-success-200 motion-safe:animate-pop-in'
                                    : 'border-danger-200 motion-safe:animate-shake' }}">

            {{-- What the answer was worth, floating off the top. A penalty is
                 shown as the negative it was; the running total it feeds never
                 goes below zero, which is why nobody sees a minus score. --}}
            @if ($lastPoints !== 0)
                <span aria-hidden="true"
                      class="pointer-events-none absolute -top-2 right-4 text-xl font-bold tabular-nums
                             motion-safe:animate-score-float
                             {{ $lastPoints > 0 ? 'text-success-600' : 'text-danger-600' }}">
                    {{ $lastPoints > 0 ? '+' : '−' }}{{ number_format(abs($lastPoints)) }}
                </span>
            @endif

            <div class="flex items-start gap-3">
                @if ($lastCorrect)
                    <x-icon name="check" size="h-6 w-6" class="flex-shrink-0 text-success-600" />
                @else
                    <x-icon name="alert" size="h-6 w-6" class="flex-shrink-0 text-warning-600" />
                @endif
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-gray-900">
                        {{ $lastCorrect ? 'Correct' : 'Not this time' }}
                        @if ($lastPoints > 0)
                            <span class="ml-1 text-brand-700">+{{ number_format($lastPoints) }}</span>
                        @elseif ($lastPoints < 0)
                            <span class="ml-1 text-danger-700">−{{ number_format(abs($lastPoints)) }}</span>
                        @endif
                    </p>
                    @if ($lastExplanation)
                        <p class="mt-1 text-sm text-gray-700">{{ $lastExplanation }}</p>
                    @endif
                </div>
            </div>

            {{-- ── The board, between questions ──

                 A score that only appears at the end is a mark. A POSITION
                 THAT MOVES is a race, and the person two places above you is
                 the reason the next question gets read properly rather than
                 skimmed. This is the whole difference between a quiz somebody
                 finishes and a quiz somebody plays twice.

                 Only drawn when there is somebody to be ahead of: "1st of 1"
                 is not a standing, it is an empty room, and dressing it up as
                 a leaderboard is the kind of thing that makes a floor stop
                 believing the rest of the screen. --}}
            @if ($standing && $standing['of'] > 1)
                @php
                    $climbed = $standingBefore !== null && $standing['rank'] < $standingBefore;
                    $slipped = $standingBefore !== null && $standing['rank'] > $standingBefore;
                @endphp
                <div wire:key="standing-{{ $index }}"
                     class="mt-4 rounded-surface border px-4 py-3
                            {{ $climbed ? 'border-success-200 bg-success-50 motion-safe:animate-pop-in'
                               : ($slipped ? 'border-warning-200 bg-warning-50' : 'border-gray-200 bg-gray-50') }}">
                    <div class="flex items-center justify-between gap-3">
                        <span class="flex items-baseline gap-2 min-w-0">
                            <span class="text-2xl font-bold tabular-nums
                                         {{ $climbed ? 'text-success-700' : 'text-gray-900' }}">
                                {{ $standing['rank'] }}<span class="text-sm font-semibold">{{ [1 => 'st', 2 => 'nd', 3 => 'rd'][$standing['rank']] ?? 'th' }}</span>
                            </span>
                            <span class="truncate text-sm text-gray-600">of {{ $standing['of'] }}</span>
                        </span>

                        @if ($climbed)
                            <span class="shrink-0 text-sm font-semibold text-success-700">
                                &#9650; up {{ $standingBefore - $standing['rank'] }}
                            </span>
                        @elseif ($slipped)
                            <span class="shrink-0 text-sm font-semibold text-warning-700">
                                &#9660; down {{ $standing['rank'] - $standingBefore }}
                            </span>
                        @endif
                    </div>

                    @if ($standing['ahead'])
                        <p class="help mt-1">
                            {{ number_format($standing['gap']) }} behind
                            {{ Str::before($standing['ahead'], ' ') }} — catch them on the next one.
                        </p>
                    @elseif ($standing['rank'] === 1)
                        <p class="help mt-1">Top of the board. Hold it.</p>
                    @endif
                </div>
            @endif

            <button type="button" wire:click="nextQuestion" class="btn-primary w-full mt-4">
                {{ $index + 1 >= $total ? 'See my result' : 'Next question' }}
            </button>
        </div>
    @else
        <button type="button" wire:click="submit" class="btn-primary w-full mt-4"
                @disabled(empty($chosen))>
            Answer
        </button>
        @if (empty($chosen))
            <p class="help mt-2 text-center">Pick an option first.</p>
        @endif
    @endif
</div>
