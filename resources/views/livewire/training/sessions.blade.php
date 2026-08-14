<div>
    <x-training.flash />

    <x-page-header title="Live sessions" eyebrow="Learning & Development"
                   subtitle="Run a quiz in the room, or leave one open for the week and watch the board." />

    @can('training.host')
        <div class="card p-5 mb-4">
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Start a session</h2>

            {{-- Two genuinely different things behind one form, because they
                 start from the same three answers: which quiz, which outlet,
                 what to call it. The only difference is whether everybody is
                 in the room now or has until Friday. --}}
            <div class="seg mb-4 max-w-md" role="tablist">
                <button type="button" wire:click="$set('kind', 'room')" role="tab"
                        @class(['seg-item', 'seg-item-on' => $kind === 'room'])
                        aria-selected="{{ $kind === 'room' ? 'true' : 'false' }}">
                    Live room
                </button>
                <button type="button" wire:click="$set('kind', 'challenge')" role="tab"
                        @class(['seg-item', 'seg-item-on' => $kind === 'challenge'])
                        aria-selected="{{ $kind === 'challenge' ? 'true' : 'false' }}">
                    Scheduled challenge
                </button>
            </div>

            <p class="help mb-3">
                @if ($kind === 'room')
                    Everybody plays at once from their phones with a PIN. You drive the questions.
                @else
                    Staff take it in their own time before it closes, once each. Nobody hosts it —
                    you just watch the board.
                @endif
            </p>

            @if ($quizzes->isEmpty())
                <div class="alert-info">
                    <x-icon name="info" size="h-5 w-5" class="flex-shrink-0" />
                    <p>
                        No published quiz has any questions yet. Publish a quiz with questions in it and it
                        will appear here.
                    </p>
                </div>
            @else
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[220px]">
                        <label class="label" for="host-quiz">Quiz</label>
                        <select id="host-quiz" wire:model="quizId" class="input">
                            <option value="">Choose a quiz…</option>
                            @foreach ($quizzes as $quiz)
                                <option value="{{ $quiz->id }}">
                                    {{ $quiz->title }} ({{ $quiz->questions_count }} questions)
                                </option>
                            @endforeach
                        </select>
                        @error('quizId') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="host-outlet">Outlet</label>
                        <select id="host-outlet" wire:model="outletId" class="input">
                            <option value="">Any</option>
                            @foreach ($outlets as $outlet)
                                <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                            @endforeach
                        </select>
                        @error('outletId') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="host-name">Name it (optional)</label>
                        <input id="host-name" type="text" wire:model="sessionName" class="input"
                               placeholder="{{ $kind === 'room' ? 'Tuesday briefing' : 'Allergen week' }}">
                    </div>

                    @if ($kind === 'challenge')
                        <div>
                            <label class="label" for="host-opens">Opens</label>
                            <input id="host-opens" type="date" wire:model="opensAt" class="input">
                            <p class="help">Leave blank to open it now.</p>
                            @error('opensAt') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="host-closes">Closes</label>
                            <input id="host-closes" type="date" wire:model="closesAt" class="input">
                            <p class="help">End of that day.</p>
                            @error('closesAt') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <button type="button" wire:click="host" class="btn-primary">
                        @if ($kind === 'room')
                            <x-icon name="play" size="h-4 w-4" class="mr-1" /> Open the room
                        @else
                            <x-icon name="calendar-days" size="h-4 w-4" class="mr-1" /> Schedule it
                        @endif
                    </button>
                </div>
            @endif
        </div>
    @endcan

    @if ($live->isNotEmpty())
        <div class="card p-5 mb-4">
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Rooms open now</h2>
            <div class="space-y-2">
                @foreach ($live as $session)
                    <div wire:key="live-{{ $session->id }}"
                         class="list-row flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900">
                                {{ $session->name ?: $session->quiz?->title }}
                                <span class="badge-success ml-1">{{ \App\Models\TrainingSession::STATUSES[$session->status] ?? $session->status }}</span>
                            </p>
                            <p class="text-xs text-gray-600">
                                PIN <span class="font-mono font-semibold tracking-widest">{{ $session->pin }}</span>
                                · {{ $session->players_count }} joined
                                @if ($session->outlet) · {{ $session->outlet->name }} @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            @can('training.host')
                                <a href="{{ route('training.live.host', $session->id) }}" class="btn-primary btn-sm">Open console</a>
                                <button wire:click="endStale({{ $session->id }})"
                                        data-confirm-delete="End this session now? Scores are kept."
                                        class="btn-ghost btn-sm">End</button>
                            @endcan
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($challenges->isNotEmpty())
        <div class="card p-5 mb-4">
            <h2 class="text-sm font-semibold text-gray-900 mb-1">Challenges</h2>
            <p class="help mb-3">Open in the Staff Portal. Nobody hosts these — the board fills itself in.</p>
            <div class="space-y-2">
                @foreach ($challenges as $challenge)
                    <div wire:key="challenge-{{ $challenge->id }}"
                         class="list-row flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900">
                                {{ $challenge->name ?: $challenge->quiz?->title }}
                                <span class="{{ $challenge->isOpen() ? 'badge-success' : 'badge-neutral' }} ml-1">
                                    {{ $challenge->windowLabel() }}
                                </span>
                            </p>
                            <p class="text-xs text-gray-600">
                                {{ $challenge->quiz?->title }}
                                · {{ $challenge->taken_count }} taken it
                                @if ($challenge->outlet) · {{ $challenge->outlet->name }} @else · all outlets @endif
                            </p>
                        </div>
                        <a href="{{ route('training.challenge', $challenge->id) }}" class="btn-secondary btn-sm">
                            Watch the board
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card overflow-hidden">
        <div class="px-5 pt-5">
            <h2 class="text-sm font-semibold text-gray-900">Past sessions</h2>
            <p class="help mb-3">What was run, where, and who turned up.</p>
        </div>
        <div class="table-scroll">
            <table class="table-surface min-w-full">
                <thead>
                    <tr class="text-left">
                        <th class="px-4 py-3">Session</th>
                        <th class="px-4 py-3">Outlet</th>
                        <th class="px-4 py-3">Host</th>
                        <th class="px-4 py-3">Players</th>
                        <th class="px-4 py-3">Ended</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($past as $session)
                        <tr wire:key="past-{{ $session->id }}">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ $session->name ?: $session->quiz?->title }}</p>
                                <p class="text-xs text-gray-600">{{ $session->quiz?->title }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $session->outlet?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $session->host?->name ?? '—' }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ $session->players_count }}</td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $session->ended_at?->format('j M Y, H:i') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <x-icon name="trophy" size="h-8 w-8" class="text-gray-500" />
                                    <p class="empty-title">No sessions yet</p>
                                    <p class="empty-body">
                                        A live round takes about five minutes and works well at the start of a shift.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $past->links() }}</div>
</div>
