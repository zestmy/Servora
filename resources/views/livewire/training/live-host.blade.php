{{--
    The host console — the screen the room looks at.

    Polls once a second. That interval is what makes the lobby fill in front of
    everybody and the answer counter climb while a question is live, and the
    counter is how a host decides whether to reveal now or wait for the two
    people still reading. See App\Models\TrainingSession for why this is polling
    and not websockets.
--}}
<div wire:poll.1s="tick">
    <x-training.flash />

    <x-page-header :title="$session->name ?: $session->quiz?->title" eyebrow="Learning & Development / Live"
                   :subtitle="\App\Models\TrainingSession::STATUSES[$session->status] ?? $session->status">
        <x-slot:actions>
            <a href="{{ route('training.live') }}" class="btn-ghost">All sessions</a>
            @can('training.host')
                @if ($session->status !== 'ended')
                    <button wire:click="end" data-confirm-delete="End the session? Everyone's score is kept."
                            class="btn-secondary">End session</button>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($session->status === 'lobby')
        {{-- ── Lobby: the PIN is the only thing that matters here ── --}}
        <div class="card p-8 text-center mb-4">
            <p class="page-eyebrow">Join at {{ url('/lms/live') }}</p>
            <p class="display-1 tabular-nums tracking-[0.2em] mt-2">{{ $session->pin }}</p>
            <p class="text-sm text-gray-600 mt-3">
                {{ $players->count() }} {{ Str::plural('player', $players->count()) }} in the room
                · {{ $session->questionCount() }} questions
            </p>

            @can('training.host')
                <button wire:click="start" class="btn-primary mt-5">
                    <x-icon name="play" size="h-4 w-4" class="mr-1" /> Start
                </button>
            @endcan
        </div>

        <div class="card p-5">
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Who has joined</h2>
            @if ($players->isEmpty())
                <p class="text-sm text-gray-600">Nobody yet. The PIN is on screen above.</p>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach ($players as $player)
                        <span wire:key="lobby-{{ $player->id }}" class="badge-brand">
                            {{ $player->nickname }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>

    @elseif ($session->status === 'ended')
        {{-- ── Podium ── --}}
        <div class="card p-6 mb-4">
            <h2 class="text-sm font-semibold text-gray-900 mb-4">Final scores</h2>

            @if ($players->isEmpty())
                <p class="text-sm text-gray-600">Nobody played this round.</p>
            @else
                <ol class="space-y-2">
                    @foreach ($players as $i => $player)
                        <li wire:key="final-{{ $player->id }}"
                            class="list-row flex items-center justify-between gap-3 {{ $i === 0 ? 'bg-brand-50/60' : '' }}">
                            <span class="flex items-center gap-3 min-w-0">
                                <span class="w-6 text-right tabular-nums text-sm font-semibold text-gray-600">{{ $i + 1 }}</span>
                                @if ($i === 0)
                                    <x-icon name="trophy" size="h-5 w-5" class="text-warning-600 flex-shrink-0" />
                                @endif
                                <span class="truncate font-medium text-gray-900">{{ $player->nickname }}</span>
                            </span>
                            <span class="flex items-center gap-4 text-sm">
                                <span class="text-gray-600">
                                    {{ $player->correct_count }}/{{ $session->questionCount() }} right
                                </span>
                                <span class="tabular-nums font-semibold text-gray-900">
                                    {{ number_format($player->score) }}
                                </span>
                            </span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>

    @else
        {{-- ── A question is live, or its answer is showing ── --}}
        <div class="card p-6 mb-4">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <p class="page-eyebrow">
                    Question {{ $session->current_index + 1 }} of {{ $session->questionCount() }}
                </p>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-600">
                        {{ $answered }} of {{ $players->count() }} answered
                    </span>
                    @if ($session->status === 'question')
                        <span class="badge-brand tabular-nums">{{ $remaining }}s</span>
                    @endif
                </div>
            </div>

            @if ($question)
                @if ($question->image_path)
                    <img src="{{ Storage::disk('public')->url($question->image_path) }}"
                         class="mb-4 mx-auto max-h-72 w-auto rounded-surface" alt="">
                @endif
                <p class="display-3 text-gray-900">{{ $question->prompt }}</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-5">
                    @foreach ($question->optionList() as $index => $option)
                        @php
                            $isRight = in_array($index, array_map('intval', (array) $question->correct), true);
                            $revealing = $session->status !== 'question';
                        @endphp
                        <div wire:key="opt-{{ $question->id }}-{{ $index }}"
                             class="rounded-surface border p-4 text-sm
                                    {{ $revealing && $isRight ? 'border-success-300 bg-success-50 text-success-900'
                                       : ($revealing ? 'border-gray-200 bg-gray-50 text-gray-600' : 'border-gray-200 text-gray-900') }}">
                            <div class="flex items-start justify-between gap-3">
                                <span class="font-medium">{{ $option }}</span>
                                @if ($revealing)
                                    <span class="flex items-center gap-2 flex-shrink-0">
                                        <span class="tabular-nums text-xs">{{ $tally[$index] ?? 0 }}</span>
                                        @if ($isRight)
                                            <x-icon name="check" size="h-4 w-4" class="text-success-700" />
                                        @endif
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($session->status !== 'question' && $question->explanation)
                    <div class="alert-info mt-4">
                        <x-icon name="info" size="h-5 w-5" class="flex-shrink-0" />
                        <p>{{ $question->explanation }}</p>
                    </div>
                @endif
            @else
                <p class="text-sm text-gray-600">This session has no question at that position.</p>
            @endif

            @can('training.host')
                <div class="flex flex-wrap items-center gap-2 mt-5">
                    @if ($session->status === 'question')
                        <button wire:click="reveal" class="btn-secondary">Show the answer</button>
                    @else
                        <button wire:click="next" class="btn-primary">
                            {{ $session->isLastQuestion() ? 'Finish and show the podium' : 'Next question' }}
                        </button>
                    @endif

                    <label class="flex items-center gap-2 ml-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model.live="autoReveal" class="rounded-control border-gray-300">
                        Reveal automatically when the clock runs out
                    </label>
                </div>
            @endcan
        </div>

        {{-- Running scores, so the room can see it move. --}}
        <div class="card p-5">
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Scores</h2>
            <ol class="space-y-1">
                @foreach ($players->take(10) as $i => $player)
                    <li wire:key="score-{{ $player->id }}"
                        class="flex items-center justify-between gap-3 py-1 text-sm">
                        <span class="flex items-center gap-3 min-w-0">
                            <span class="w-5 text-right tabular-nums text-gray-600">{{ $i + 1 }}</span>
                            <span class="truncate text-gray-900">{{ $player->nickname }}</span>
                            @if ($player->streak >= 3)
                                <span class="badge-warning inline-flex items-center gap-1">
                                    <x-icon name="bolt" size="h-3 w-3" /> {{ $player->streak }}
                                </span>
                            @endif
                        </span>
                        <span class="tabular-nums font-medium text-gray-900">{{ number_format($player->score) }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif
</div>
