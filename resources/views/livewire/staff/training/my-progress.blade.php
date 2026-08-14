{{--
    An employee's own record.

    No leaderboard here — that is its own tab now, and repeating it would give
    the same numbers two homes that could drift. This page answers the other
    question: how am I doing, and what should I look at next.
--}}
<div class="p-4 space-y-4">

    <div>
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Learning</p>
        <h1 class="text-2xl font-bold text-gray-900 mt-0.5">My progress</h1>
        <p class="text-sm text-gray-600 mt-0.5">{{ $employee->name }}</p>
    </div>

    <div class="card p-4">
        <div class="grid grid-cols-2 gap-4">
            <div class="stat">
                <span class="stat-label">Average</span>
                <span class="stat-value">{{ $card['average'] }}%</span>
            </div>
            <div class="stat">
                <span class="stat-label">Passed</span>
                <span class="stat-value">
                    {{ $card['passed'] }}<span class="text-base font-normal text-gray-600">/{{ $card['attempts'] }}</span>
                </span>
            </div>
            <div class="stat">
                <span class="stat-label">Points</span>
                <span class="stat-value">{{ number_format($card['points']) }}</span>
            </div>
            <div class="stat">
                <span class="stat-label">Rank this month</span>
                <span class="stat-value">
                    {{ $card['position'] ? '#' . $card['position']['rank'] : '—' }}
                </span>
            </div>
        </div>

        @if ($card['last_activity'])
            <p class="help mt-3">Last activity {{ $card['last_activity']->diffForHumans() }}.</p>
        @endif
    </div>

    {{-- The most useful block on the page: what to look at next, phrased as
         that rather than as a deficiency. Same data the manager sees. --}}
    @if ($card['weak_topics']->isNotEmpty())
        <div class="card p-4">
            <h2 class="text-sm font-semibold text-gray-900">Worth another look</h2>
            <p class="help mb-3">These came up more than once and did not stick yet.</p>
            <div class="space-y-3">
                @foreach ($card['weak_topics'] as $topic)
                    <div wire:key="weak-{{ $topic['topic'] }}">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="font-medium text-gray-900">{{ $topic['topic'] }}</span>
                            <span class="tabular-nums text-gray-600">{{ $topic['correct'] }}/{{ $topic['answered'] }}</span>
                        </div>
                        <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-warning-500"
                                 style="width: {{ max(2, (float) $topic['accuracy']) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($card['outstanding']->isNotEmpty())
        <div class="card border-l-4 border-l-warning-400 p-4">
            <h2 class="text-sm font-semibold text-gray-900 mb-2">Still to do</h2>
            <ul class="space-y-2">
                @foreach ($card['outstanding'] as $item)
                    <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span class="text-gray-900">{{ $item['title'] }}</span>
                        <span class="text-xs {{ $item['overdue'] ? 'font-semibold text-danger-700' : 'text-gray-600' }}">
                            {{ $item['due_on']?->format('j M') ?? 'No date' }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($card['certificates']->isNotEmpty())
        <div class="card p-4">
            <div class="mb-2 flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-gray-900">My certificates</h2>
                <a href="{{ route('clock.staff.learn.certificates') }}" wire:navigate
                   class="text-xs font-medium text-brand-600 hover:underline">See all</a>
            </div>
            <ul class="space-y-2">
                @foreach ($card['certificates'] as $certificate)
                    <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span class="min-w-0">
                            <span class="block truncate text-gray-900">{{ $certificate->title }}</span>
                            <span class="block text-xs text-gray-600">
                                {{ $certificate->issued_at?->format('j M Y') }}
                                @if ($certificate->expires_on)
                                    · {{ $certificate->isExpired() ? 'expired' : 'valid until' }}
                                    {{ $certificate->expires_on->format('j M Y') }}
                                @endif
                            </span>
                        </span>
                        @unless ($certificate->isRevoked())
                            <a href="{{ route('clock.staff.certificate', $certificate->id) }}"
                               class="shrink-0 text-xs text-brand-600 hover:underline">Download</a>
                        @endunless
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($card['recent']->isNotEmpty())
        <div class="card p-4">
            <h2 class="text-sm font-semibold text-gray-900 mb-2">Recent attempts</h2>
            <ul class="divide-y divide-gray-100">
                @foreach ($card['recent'] as $attempt)
                    <li class="flex items-center justify-between gap-3 py-2 text-sm">
                        <span class="min-w-0">
                            <span class="block truncate text-gray-900">{{ $attempt->quiz?->title ?? '—' }}</span>
                            <span class="block text-xs text-gray-600">
                                {{ $attempt->completed_at?->format('j M, H:i') }}
                                @if ($attempt->mode === 'live') · live @endif
                            </span>
                        </span>
                        <span class="{{ $attempt->passed ? 'badge-success' : 'badge-warning' }} shrink-0">
                            {{ (float) $attempt->percent }}%
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex gap-2">
        <a href="{{ route('clock.staff.learn') }}" wire:navigate class="btn-secondary flex-1 justify-center">Training</a>
        <a href="{{ route('clock.staff.leaderboard') }}" wire:navigate class="btn-primary flex-1 justify-center">Board</a>
    </div>
</div>
