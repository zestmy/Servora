<div>
    <x-page-header title="Report cards" eyebrow="Learning & Development"
                   subtitle="Who is up to date, who is getting better, and what they do not know yet." />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- ── Roster ── --}}
        <div class="space-y-3">
            <div class="toolbar">
                <div class="flex flex-wrap items-end gap-3 w-full">
                    <div class="flex-1 min-w-[140px]">
                        <label class="label" for="rc-search">Search</label>
                        <input id="rc-search" type="search" wire:model.live.debounce.300ms="search"
                               class="input" placeholder="Name or email">
                    </div>
                    <div>
                        <label class="label" for="rc-outlet">Outlet</label>
                        <select id="rc-outlet" wire:model.live="outletId" class="input">
                            <option value="">All</option>
                            @foreach ($outlets as $outlet)
                                <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="max-h-[36rem] overflow-y-auto divide-y divide-gray-100">
                    @forelse ($roster as $row)
                        <button type="button" wire:key="roster-{{ $row['trainee']->id }}"
                                wire:click="select({{ $row['trainee']->id }})"
                                class="w-full text-left px-4 py-3 transition-colors
                                       {{ (int) $selectedId === $row['trainee']->id ? 'bg-brand-50/60' : 'hover:bg-gray-50' }}">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900 truncate">{{ $row['trainee']->name }}</p>
                                    <p class="text-xs text-gray-600 truncate">
                                        {{ $row['trainee']->outlet?->name ?? 'No outlet' }}
                                        · {{ $row['attempts'] }} {{ Str::plural('attempt', $row['attempts']) }}
                                    </p>
                                </div>
                                <span class="text-sm tabular-nums font-semibold flex-shrink-0
                                             {{ $row['average'] === null ? 'text-gray-500'
                                                : ($row['average'] >= 70 ? 'text-success-700' : 'text-warning-700') }}">
                                    {{ $row['average'] === null ? '—' : $row['average'] . '%' }}
                                </span>
                            </div>
                        </button>
                    @empty
                        <div class="empty-state border-0 bg-transparent">
                            <p class="empty-title">No trainees</p>
                            <p class="empty-body">
                                Staff appear here once they register on the training portal and are approved.
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ── One person ── --}}
        <div class="lg:col-span-2">
            @if (! $card)
                <div class="empty-state h-full">
                    <x-icon name="users" size="h-8 w-8" class="text-gray-500" />
                    <p class="empty-title">Pick somebody</p>
                    <p class="empty-body">
                        Their scores, their weak topics, and what they still owe.
                    </p>
                </div>
            @else
                @can('training.reports')
                    <div class="space-y-4">
                        <div class="card p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 class="text-base font-semibold text-gray-900">{{ $card['trainee']->name }}</h2>
                                    <p class="text-sm text-gray-600">
                                        {{ $card['trainee']->email }}
                                        @if ($card['trainee']->outlet) · {{ $card['trainee']->outlet->name }} @endif
                                    </p>
                                </div>
                                <button wire:click="clearSelection" class="btn-ghost btn-sm">Close</button>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4">
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
                                    <span class="stat-label">Rank this month</span>
                                    <span class="stat-value">
                                        {{ $card['position'] ? '#' . $card['position']['rank'] : '—' }}
                                        @if ($card['position'])
                                            <span class="text-base font-normal text-gray-600">of {{ $card['position']['of'] }}</span>
                                        @endif
                                    </span>
                                </div>
                            </div>

                            @if ($card['last_activity'])
                                <p class="help mt-3">Last activity {{ $card['last_activity']->diffForHumans() }}.</p>
                            @endif
                        </div>

                        {{-- The most useful block on the page — what to teach next. --}}
                        <div class="card p-5">
                            <h3 class="text-sm font-semibold text-gray-900 mb-1">What to work on</h3>
                            @if ($card['weak_topics']->isEmpty())
                                <p class="text-sm text-gray-600">
                                    Nothing is standing out — every topic with enough answers is above
                                    {{ (int) (\App\Services\Training\ReportCardService::WEAK_THRESHOLD * 100) }}%.
                                </p>
                            @else
                                <div class="space-y-2 mt-2">
                                    @foreach ($card['weak_topics'] as $topic)
                                        <div wire:key="weak-{{ $topic['topic'] }}">
                                            <div class="flex items-center justify-between gap-3 text-sm">
                                                <span class="font-medium text-gray-900">{{ $topic['topic'] }}</span>
                                                <span class="tabular-nums text-warning-700">
                                                    {{ $topic['correct'] }}/{{ $topic['answered'] }} · {{ $topic['accuracy'] }}%
                                                </span>
                                            </div>
                                            <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                                                <div class="h-full rounded-full bg-warning-500"
                                                     style="width: {{ max(2, (float) $topic['accuracy']) }}%"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="card p-5">
                                <h3 class="text-sm font-semibold text-gray-900 mb-2">Still outstanding</h3>
                                @if ($card['outstanding']->isEmpty())
                                    <p class="text-sm text-gray-600">Nothing outstanding.</p>
                                @else
                                    <ul class="space-y-2">
                                        @foreach ($card['outstanding'] as $item)
                                            <li class="flex items-start justify-between gap-3 text-sm">
                                                <span class="text-gray-900">{{ $item['title'] }}</span>
                                                <span class="flex-shrink-0 text-xs {{ $item['overdue'] ? 'text-danger-700 font-medium' : 'text-gray-600' }}">
                                                    {{ $item['due_on']?->format('j M') ?? 'No date' }}
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>

                            <div class="card p-5">
                                <h3 class="text-sm font-semibold text-gray-900 mb-2">Certificates</h3>
                                @if ($card['certificates']->isEmpty())
                                    <p class="text-sm text-gray-600">None issued.</p>
                                @else
                                    <ul class="space-y-2">
                                        @foreach ($card['certificates'] as $certificate)
                                            <li class="flex items-start justify-between gap-3 text-sm">
                                                <span class="min-w-0">
                                                    <span class="block truncate text-gray-900">{{ $certificate->title }}</span>
                                                    <span class="block text-xs text-gray-600 font-mono">{{ $certificate->serial }}</span>
                                                </span>
                                                <a href="{{ route('training.certificates.pdf', $certificate->id) }}"
                                                   class="flex-shrink-0 text-xs text-brand-600 hover:underline">PDF</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>

                        <div class="card overflow-hidden">
                            <div class="px-5 pt-5">
                                <h3 class="text-sm font-semibold text-gray-900">Recent attempts</h3>
                            </div>
                            <div class="table-scroll">
                                <table class="table-surface min-w-full">
                                    <thead>
                                        <tr class="text-left">
                                            <th class="px-4 py-2">Quiz</th>
                                            <th class="px-4 py-2">Mode</th>
                                            <th class="px-4 py-2">Score</th>
                                            <th class="px-4 py-2">Result</th>
                                            <th class="px-4 py-2">When</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($card['recent'] as $attempt)
                                            <tr wire:key="attempt-{{ $attempt->id }}">
                                                <td class="px-4 py-2 text-gray-900">{{ $attempt->quiz?->title ?? '—' }}</td>
                                                <td class="px-4 py-2 text-gray-700">{{ $attempt->mode === 'live' ? 'Live' : 'On their own' }}</td>
                                                <td class="px-4 py-2 tabular-nums text-gray-700">{{ number_format($attempt->score) }}</td>
                                                <td class="px-4 py-2">
                                                    <span class="{{ $attempt->passed ? 'badge-success' : 'badge-warning' }}">
                                                        {{ (float) $attempt->percent }}%
                                                    </span>
                                                </td>
                                                <td class="px-4 py-2 text-gray-700">{{ $attempt->completed_at?->format('j M, H:i') }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-gray-600">No attempts yet.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="alert-warning">
                        <x-icon name="warning" size="h-5 w-5" class="flex-shrink-0" />
                        <p>
                            Individual results are performance data about a named person. You need the
                            "Staff report cards" permission to open one.
                        </p>
                    </div>
                @endcan
            @endif
        </div>
    </div>
</div>
