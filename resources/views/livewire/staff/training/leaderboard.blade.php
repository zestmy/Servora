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

    {{-- Your own standing, before the list. --}}
    <div class="rounded-surface bg-brand-600 p-4 text-white">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
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

                    <x-staff-avatar :name="$row['name']" />

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

    <a href="{{ route('clock.staff.learn') }}"
       class="btn-secondary w-full justify-center">
        <x-icon name="academic" size="h-4 w-4" class="mr-1" /> Go to training
    </a>
</div>
