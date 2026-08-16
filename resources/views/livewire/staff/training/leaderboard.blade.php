{{--
    The board, and the first screen the staff app opens on.

    Built to be read at arm's length in a corridor: the reader's own row is
    pinned at the top so they never have to hunt for themselves, and it is
    marked three ways — background, weight and an explicit "you" — because
    colour alone is the signal a glare-washed screen will not carry.
--}}
<div class="p-4 space-y-4">

    <div>
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Learning</p>
        <h1 class="text-2xl font-bold text-gray-900 mt-0.5">Leaderboard</h1>
    </div>

    {{-- Your own standing, before the list — with your own face, the same
         shared avatar the rows below already wear. Photo over initials, so
         no photo on file or a 404 degrades to the coloured disc. --}}
    <div class="rounded-surface bg-brand-600 p-4 text-white">
        <div class="flex items-center justify-between gap-3">
            <x-staff-avatar :name="$employee->name" :employee="$employee->id"
                            :photo="$employee->photo_path" size="h-11 w-11 text-sm" />
            <div class="min-w-0 flex-1">
                <p class="text-xs text-brand-100">
                    {{ $scope === 'outlet' ? ($employee->outlet?->name ?? 'Your branch') : 'Whole company' }}
                    · {{ \App\Services\Training\LeaderboardService::PERIODS[$period] ?? $period }}
                </p>
                <p class="mt-0.5 truncate text-lg font-semibold">{{ $employee->name }}</p>
            </div>
            <div class="shrink-0 text-right">
                @if ($position)
                    <p class="text-3xl font-bold tabular-nums leading-none">#{{ $position['rank'] }}</p>
                    <p class="text-xs text-brand-100">of {{ $position['of'] }}</p>
                @else
                    <p class="text-sm text-brand-100">No score yet</p>
                @endif
            </div>
        </div>

        @if ($me)
            <p class="mt-2 text-sm text-brand-50">
                {{ number_format($me['score']) }} points · {{ $me['accuracy'] }}% accuracy
                · {{ $me['quizzes'] }} {{ Str::plural('quiz', $me['quizzes']) }}
            </p>
        @else
            <p class="mt-2 text-sm text-brand-50">
                Take a quiz and you are on the board.
            </p>
        @endif
    </div>

    {{-- Filters. Branch first, because that is the board worth competing on. --}}
    <div class="flex flex-wrap gap-2">
        <div class="seg" role="tablist">
            <button type="button" role="tab" wire:click="$set('scope', 'outlet')"
                    aria-selected="{{ $scope === 'outlet' ? 'true' : 'false' }}"
                    class="seg-item {{ $scope === 'outlet' ? 'seg-item-on' : '' }}">My branch</button>
            <button type="button" role="tab" wire:click="$set('scope', 'company')"
                    aria-selected="{{ $scope === 'company' ? 'true' : 'false' }}"
                    class="seg-item {{ $scope === 'company' ? 'seg-item-on' : '' }}">Everyone</button>
        </div>

        <label class="sr-only" for="board-period">Period</label>
        <select id="board-period" wire:model.live="period" class="input w-auto text-sm">
            @foreach (\App\Services\Training\LeaderboardService::PERIODS as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    {{-- One quiz, or all of them. "Who is best overall" and "who did best on
         the allergen paper" are different questions, and only the second is any
         use when somebody is following up one piece of training. Its own row
         because the titles are long and a third control on the line above would
         squeeze all three.

         SHOWN WHENEVER THERE IS A QUIZ AT ALL, including when there is only
         one. It was hidden below two on the reasoning that a one-option filter
         is redundant — which is true and beside the point: this company has one
         live quiz, so the control never appeared, and a feature that cannot be
         seen has not been delivered. A redundant dropdown costs a line; an
         invisible one costs a conversation. --}}
    @if ($quizzes->isNotEmpty())
        <div>
            <label class="sr-only" for="board-quiz">Quiz</label>
            <select id="board-quiz" wire:model.live="quizId" class="input text-sm">
                <option value="">Every quiz</option>
                @foreach ($quizzes as $quiz)
                    <option value="{{ $quiz->id }}">{{ $quiz->title }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if ($board->isEmpty())
        <div class="empty-state">
            <x-icon name="trophy" size="h-8 w-8" class="text-gray-500" />
            <p class="empty-title">Nothing on the board yet</p>
            <p class="empty-body">
                Be the first — anything you pass this {{ $period === 'all' ? 'year' : $period }} shows up here.
            </p>
            <a href="{{ route('clock.staff.learn') }}" class="btn-primary mt-2">Start learning</a>
        </div>
    @else
        <div class="card divide-y divide-gray-100">
            @foreach ($board as $row)
                @php $isMe = $row['employee_id'] === $employee->id; @endphp
                <div wire:key="row-{{ $row['employee_id'] }}"
                     class="flex items-center gap-3 px-4 py-3 {{ $isMe ? 'bg-brand-50' : '' }}">
                    <span class="w-7 shrink-0 text-right">
                        @if ($row['rank'] <= 3)
                            <x-icon name="trophy" size="h-5 w-5"
                                    class="{{ $row['rank'] === 1 ? 'text-warning-500' : 'text-gray-400' }}" />
                        @else
                            <span class="tabular-nums text-sm font-semibold text-gray-500">{{ $row['rank'] }}</span>
                        @endif
                    </span>

                    <x-staff-avatar :name="$row['name']" :employee="$row['employee_id'] ?? null" :photo="$row['photo'] ?? null" />

                    <span class="min-w-0 flex-1">
                        <span class="block truncate {{ $isMe ? 'font-bold text-brand-900' : 'font-medium text-gray-900' }}">
                            {{ $row['name'] }}
                            @if ($isMe)
                                <span class="ml-1 text-xs font-semibold uppercase tracking-wide text-brand-700">you</span>
                            @endif
                        </span>
                        <span class="block truncate text-xs text-gray-600">
                            {{ $row['outlet'] ?? 'No branch' }} · {{ $row['accuracy'] }}% accuracy
                        </span>
                    </span>

                    <span class="shrink-0 text-right">
                        <span class="block tabular-nums font-semibold text-gray-900">{{ number_format($row['score']) }}</span>
                        <span class="block text-xs text-gray-600">points</span>
                    </span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ── Not started yet ──

         The board answers "who is winning". On a floor of fifty-odd people the
         more useful question is "who has not begun", and it cannot be read off
         a list of the people who have — a manager wanting to chase three names
         should not have to diff a roster against a leaderboard.

         IT IS A NUDGE LIST, NOT A WALL OF SHAME, and the copy is what keeps it
         one. It carries no score, no rank and no history — only "not yet" —
         and a person leaves it the moment they finish anything. Collapsed
         behind a summary line so it is something you open rather than
         something that greets everybody who looks at the board. --}}
    @if ($notStarted->isNotEmpty())
        <details class="card p-4">
            <summary class="cursor-pointer list-none">
                <span class="flex items-center justify-between gap-3">
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-gray-900">
                            {{ $notStarted->count() }} {{ Str::plural('person', $notStarted->count()) }}
                            {{ $notStarted->count() === 1 ? 'has' : 'have' }} not started
                        </span>
                        <span class="help">
                            {{ $scope === 'outlet' ? 'At your branch' : 'Across the company' }} ·
                            {{ Str::lower(\App\Services\Training\LeaderboardService::PERIODS[$period] ?? $period) }}
                        </span>
                    </span>
                    <x-icon name="chevron-down" size="h-5 w-5" class="shrink-0 text-gray-400" />
                </span>
            </summary>

            <ul class="mt-3 space-y-2 border-t border-gray-100 pt-3">
                @foreach ($notStarted as $person)
                    <li wire:key="not-started-{{ $person->id }}" class="flex items-center gap-2">
                        <x-staff-avatar :name="$person->name" :employee="$person->id"
                                        :photo="$person->photo_path" size="h-8 w-8 text-[11px]" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm text-gray-900">{{ $person->name }}</span>
                            @if ($person->outlet)
                                <span class="block truncate text-xs text-gray-600">{{ $person->outlet->name }}</span>
                            @endif
                        </span>
                        @if ($person->id === $employee->id)
                            <a href="{{ route('clock.staff.learn') }}" wire:navigate
                               class="btn-primary btn-sm shrink-0">Start</a>
                        @endif
                    </li>
                @endforeach
            </ul>

            <p class="help mt-3">
                Nudge them — a quiz takes about three minutes.
            </p>
        </details>
    @endif

    <a href="{{ route('clock.staff.learn') }}"
       class="btn-secondary w-full justify-center">
        <x-icon name="academic" size="h-4 w-4" class="mr-1" /> Go to training
    </a>
</div>
