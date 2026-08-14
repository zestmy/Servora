{{--
    One question, on a phone, with wet hands.

    Every option is a full-width 44px+ target — the staff apps in this product
    are used gloved and that rule is not optional there. The countdown is a CSS
    animation rather than a JS timer: it is a picture of the clock, and the
    clock itself is on the server (see App\Livewire\Lms\QuizPlay).
--}}
<div class="p-4"
     x-data="{ expired: false }"
     wire:key="q-{{ $question->id }}">

    <div class="mb-4 flex items-center justify-between gap-3">
        <p class="text-sm font-medium text-gray-600">
            Question {{ $index + 1 }} of {{ $total }}
        </p>
        <p class="text-sm text-gray-600">
            {{ $quiz->title }}
        </p>
    </div>

    {{-- Progress through the quiz --}}
    <div class="mb-5 h-1.5 w-full overflow-hidden rounded-full bg-gray-200">
        <div class="h-full rounded-full bg-brand-600 transition-all duration-500"
             style="width: {{ round(($index) / max(1, $total) * 100) }}%"></div>
    </div>

    {{-- The countdown.

         Only drawn while the question is open. On timeout it calls the server
         once — @js guards against it firing twice if Alpine re-inits. --}}
    @unless ($showFeedback)
        <div class="mb-5"
             x-init="setTimeout(() => { if (! expired) { expired = true; $wire.timeout(); } }, {{ $seconds * 1000 }})">
            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200">
                {{-- The duration is the question's own limit, so it goes in a
                     style attribute — a Tailwind class cannot carry data. --}}
                <div class="h-full w-full origin-left rounded-full bg-warning-500 motion-safe:animate-countdown"
                     style="animation-duration: {{ $seconds }}s"></div>
            </div>
            <p class="mt-1 text-right text-xs text-gray-600">{{ $seconds }} seconds</p>
        </div>
    @endunless

    <div class="card p-6 mb-4">
        <p class="text-lg font-semibold leading-snug text-gray-900">{{ $question->prompt }}</p>
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
        <div class="card p-5 mt-4">
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
                        @endif
                    </p>
                    @if ($lastExplanation)
                        <p class="mt-1 text-sm text-gray-700">{{ $lastExplanation }}</p>
                    @endif
                </div>
            </div>

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
