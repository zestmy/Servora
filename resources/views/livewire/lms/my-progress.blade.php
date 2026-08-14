<div class="max-w-3xl">
    <div class="mb-6">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-600">Training</p>
        <h1 class="text-2xl font-bold text-gray-900 mt-1">My progress</h1>
    </div>

    <div class="card p-6 mb-4">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="stat">
                <span class="stat-label">Average</span>
                <span class="stat-value">{{ $card['average'] }}%</span>
            </div>
            <div class="stat">
                <span class="stat-label">Passed</span>
                <span class="stat-value">{{ $card['passed'] }}<span class="text-base font-normal text-gray-600">/{{ $card['attempts'] }}</span></span>
            </div>
            <div class="stat">
                <span class="stat-label">Points</span>
                <span class="stat-value">{{ number_format($card['points']) }}</span>
            </div>
            <div class="stat">
                <span class="stat-label">Rank</span>
                <span class="stat-value">
                    {{ $position ? '#' . $position['rank'] : '—' }}
                    @if ($position)
                        <span class="text-base font-normal text-gray-600">of {{ $position['of'] }}</span>
                    @endif
                </span>
            </div>
        </div>
    </div>

    {{-- Phrased as what to look at next, not as a deficiency. It is the same
         data the manager sees, from the same service — see ReportCardService. --}}
    @if ($card['weak_topics']->isNotEmpty())
        <div class="card p-5 mb-4">
            <h2 class="text-sm font-semibold text-gray-900 mb-1">Worth another look</h2>
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
        <div class="card p-5 mb-4 border-l-4 border-l-warning-400">
            <h2 class="text-sm font-semibold text-gray-900 mb-2">Still to do</h2>
            <ul class="space-y-2">
                @foreach ($card['outstanding'] as $item)
                    <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span class="text-gray-900">{{ $item['title'] }}</span>
                        <span class="text-xs {{ $item['overdue'] ? 'text-danger-700 font-semibold' : 'text-gray-600' }}">
                            {{ $item['due_on']?->format('j M') ?? 'No date' }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($card['certificates']->isNotEmpty())
        <div class="card p-5 mb-4">
            <h2 class="text-sm font-semibold text-gray-900 mb-2">My certificates</h2>
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
                            <a href="{{ route('training.certificates.pdf', $certificate->id) }}"
                               class="text-xs text-brand-600 hover:underline flex-shrink-0">Download</a>
                        @endunless
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ── Leaderboard ── --}}
    <div class="card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 p-5 pb-3">
            <h2 class="text-sm font-semibold text-gray-900">Leaderboard</h2>
            <select wire:model.live="period" class="input w-auto text-sm" aria-label="Period">
                @foreach (\App\Services\Training\LeaderboardService::PERIODS as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        @if ($board->isEmpty())
            <p class="px-5 pb-5 text-sm text-gray-600">Nothing on the board for this period yet.</p>
        @else
            <ol class="divide-y divide-gray-100">
                @foreach ($board as $row)
                    <li wire:key="board-{{ $row['trainee_id'] }}"
                        class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm
                               {{ $row['trainee_id'] === $trainee->id ? 'bg-brand-50/60 font-semibold text-brand-900' : 'text-gray-800' }}">
                        <span class="flex items-center gap-3 min-w-0">
                            <span class="w-5 text-right tabular-nums text-gray-600">{{ $row['rank'] }}</span>
                            @if ($row['rank'] <= 3)
                                <x-icon name="trophy" size="h-4 w-4"
                                        class="{{ $row['rank'] === 1 ? 'text-warning-600' : 'text-gray-500' }}" />
                            @endif
                            <span class="truncate">{{ $row['name'] }}</span>
                        </span>
                        <span class="tabular-nums">{{ number_format($row['score']) }}</span>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</div>
