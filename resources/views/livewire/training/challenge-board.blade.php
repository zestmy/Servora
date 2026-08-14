<div wire:poll.10s>
    <x-training.flash />

    <x-page-header :title="$session->name ?: ($session->quiz?->title ?? 'Challenge')"
                   eyebrow="Learning &amp; Development"
                   :subtitle="'Staff take this on their own phones. ' . $session->windowLabel() . '.'">
        <x-slot:actions>
            <a href="{{ route('training.live') }}" class="btn-ghost">Back</a>
            @can('training.host')
                @unless ($session->hasClosed())
                    <button type="button" wire:click="close"
                            data-confirm-delete="Close this challenge now? Anyone part-way through loses their run."
                            class="btn-secondary">Close it</button>
                @endunless
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-3 mb-4">
        <div class="stat">
            <p class="stat-label">Finished</p>
            <p class="stat-value">{{ $finished->count() }}</p>
        </div>
        <div class="stat">
            <p class="stat-label">Passed</p>
            <p class="stat-value">{{ $finished->where('passed', true)->count() }}</p>
        </div>
        <div class="stat">
            <p class="stat-label">Average</p>
            <p class="stat-value">
                {{ $finished->isEmpty() ? '—' : round($finished->avg('percent')) . '%' }}
            </p>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="px-5 pt-5">
            <h2 class="text-sm font-semibold text-gray-900">Standings</h2>
            <p class="help mb-3">
                {{ $session->quiz?->title }} · pass at {{ $session->quiz?->pass_mark }}%
                @if ($session->outlet) · {{ $session->outlet->name }} @else · all outlets @endif
            </p>
        </div>
        <div class="table-scroll">
            <table class="table-surface min-w-full">
                <thead>
                    <tr class="text-left">
                        <th class="px-4 py-3 w-14">#</th>
                        <th class="px-4 py-3">Staff</th>
                        <th class="px-4 py-3">Outlet</th>
                        <th class="px-4 py-3">Score</th>
                        <th class="px-4 py-3">Result</th>
                        <th class="px-4 py-3">Taken</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($standings as $row)
                        <tr wire:key="standing-{{ $row['rank'] }}-{{ $row['name'] }}">
                            <td class="px-4 py-3 tabular-nums font-semibold text-gray-700">{{ $row['rank'] }}</td>
                            <td class="px-4 py-3">
                                <span class="flex items-center gap-2 min-w-0">
                                    <x-staff-avatar :name="$row['name']" size="h-8 w-8 text-[11px]"
                                                    :employee="auth()->user()->can('hr.view') ? ($row['employee'] ?? null) : null"
                                                    :photo="$row['photo'] ?? null"
                                                    photoRoute="hr.employees.photo" />
                                    <span class="truncate font-medium text-gray-900">{{ $row['name'] }}</span>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $row['outlet'] ?? '—' }}</td>
                            <td class="px-4 py-3 tabular-nums text-gray-900">{{ number_format($row['score']) }}</td>
                            <td class="px-4 py-3">
                                @if (! $row['finished'])
                                    <span class="badge-neutral">Part-way through</span>
                                @elseif ($row['passed'])
                                    <span class="badge-success">Passed {{ (float) $row['percent'] }}%</span>
                                @else
                                    <span class="badge-warning">{{ (float) $row['percent'] }}%</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $row['when']?->format('j M, H:i') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <x-icon name="trophy" size="h-8 w-8" class="text-gray-500" />
                                    <p class="empty-title">Nobody has taken it yet</p>
                                    <p class="empty-body">
                                        It shows at the top of the Staff Portal for everybody it is
                                        open to. This board fills in as they finish.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
