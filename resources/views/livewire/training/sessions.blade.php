<div>
    <x-training.flash />

    <x-page-header title="Live sessions" eyebrow="Learning & Development"
                   subtitle="Run a quiz in the room. Staff join from their phones with a PIN — no account needed." />

    @can('training.host')
        <div class="card p-5 mb-4">
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Start a session</h2>

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
                               placeholder="Tuesday briefing">
                    </div>
                    <button type="button" wire:click="host" class="btn-primary">
                        <x-icon name="play" size="h-4 w-4" class="mr-1" /> Open the room
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
