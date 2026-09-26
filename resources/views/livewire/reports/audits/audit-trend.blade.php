<div>
    {{-- Loaded here, as the COGS report does: the layouts carry no script stack. --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

    <x-page-header title="Audit Score Trend" eyebrow="Reports · Audits"
                   subtitle="Submitted audits only. Is each outlet improving, and where do the points keep going?">
        <x-slot:actions>
            <a href="{{ route('reports.hub') }}" wire:navigate class="btn-secondary">Reports</a>
            <a href="{{ route('audits.index') }}" wire:navigate class="btn-secondary">Audits</a>
            <button wire:click="exportCsv" class="btn-secondary">
                <x-icon name="download" size="h-4 w-4" /><span class="hidden sm:inline">CSV</span>
            </button>
        </x-slot:actions>
    </x-page-header>

    <div class="toolbar mb-4">
        <div class="w-full">
            <x-quick-ranges :options="$quickRangeOptions" :current="$quickRange" />
        </div>
        <div class="flex w-full flex-col flex-wrap gap-3 sm:flex-row sm:items-center">
            <input type="date" wire:model.live="dateFrom" class="input sm:w-40" />
            <input type="date" wire:model.live="dateTo" class="input sm:w-40" />
            @if ($outlets->isNotEmpty())
                <select wire:model.live="outletFilter" class="input sm:w-48">
                    <option value="">All outlets</option>
                    @foreach ($outlets as $o)
                        <option value="{{ $o->id }}">{{ $o->name }}</option>
                    @endforeach
                </select>
            @endif
            @if ($templates->count() > 1)
                <select wire:model.live="templateFilter" class="input sm:w-48">
                    <option value="">All forms</option>
                    @foreach ($templates as $t)
                        <option value="{{ $t->id }}">{{ $t->code ?: $t->name }}</option>
                    @endforeach
                </select>
            @endif
        </div>
    </div>

    @if ($audits->isEmpty())
        <div class="card p-10">
            <div class="empty-state">
                <p class="empty-title">No submitted audits in this range</p>
                <p class="empty-body">Widen the dates, or conduct and submit an audit first.</p>
            </div>
        </div>
    @else
        <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="card p-4"><p class="stat-label">Audits</p><p class="stat-value">{{ $audits->count() }}</p><p class="stat-meta">{{ $byOutlet->count() }} outlet{{ $byOutlet->count() === 1 ? '' : 's' }}</p></div>
            <div class="card p-4"><p class="stat-label">Average score</p><p class="stat-value">{{ $average !== null ? number_format($average, 1) . '%' : '—' }}</p></div>
            <div class="card p-4">
                <p class="stat-label">Pass rate</p>
                <p class="stat-value {{ $audits->count() && $outcomeCounts['pass'] / $audits->count() >= 0.8 ? 'text-success-700' : '' }}">
                    {{ $audits->count() ? round($outcomeCounts['pass'] / $audits->count() * 100) . '%' : '—' }}
                </p>
                <p class="stat-meta">{{ $outcomeCounts['pass'] }} pass · {{ $outcomeCounts['conditional'] }} conditional · {{ $outcomeCounts['fail'] }} fail</p>
            </div>
            <div class="card p-4">
                <p class="stat-label">Failed</p>
                <p class="stat-value {{ $outcomeCounts['fail'] ? 'text-danger-700' : 'text-success-700' }}">{{ $outcomeCounts['fail'] }}</p>
                <p class="stat-meta">audits below the conditional bar</p>
            </div>
        </div>

        {{-- Score over time --}}
        <div class="card mb-4 p-4">
            <x-card-title>Score by audit</x-card-title>
            <div class="relative mt-3 h-72"
                 wire:key="trend-{{ md5(json_encode($chart)) }}"
                 x-data="{
                    init() {
                        if (typeof Chart === 'undefined') { setTimeout(() => this.init(), 150); return; }
                        const old = Chart.getChart(this.$refs.c); if (old) old.destroy();
                        const d = @js($chart);
                        const palette = ['#0f766e', '#b45309', '#1d4ed8', '#be123c', '#4d7c0f', '#7e22ce', '#0369a1', '#c2410c'];
                        new Chart(this.$refs.c, {
                            type: 'line',
                            data: { datasets: d.datasets.map((s, i) => ({ ...s, borderColor: palette[i % palette.length], backgroundColor: palette[i % palette.length], tension: 0.25, pointRadius: 4 })) },
                            options: {
                                responsive: true, maintainAspectRatio: false,
                                parsing: { xAxisKey: 'x', yAxisKey: 'y' },
                                scales: {
                                    x: { type: 'category', labels: [...new Set(d.datasets.flatMap(s => s.data.map(p => p.x)))].sort() },
                                    y: { min: 0, max: 100, ticks: { callback: v => v + '%' } },
                                },
                                plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: i => i.dataset.label + ': ' + Number(i.parsed.y).toFixed(1) + '%' } } },
                            },
                        });
                    },
                 }">
                <canvas x-ref="c"></canvas>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            {{-- Per outlet --}}
            <div class="card overflow-hidden">
                <div class="border-b border-gray-100 px-4 py-3"><x-card-title>By outlet</x-card-title></div>
                <div class="overflow-x-auto">
                    <table class="table-surface min-w-[680px]">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left">Outlet</th>
                                <th class="px-4 py-2 text-right w-16">Audits</th>
                                <th class="px-4 py-2 text-right w-24">Latest</th>
                                <th class="px-4 py-2 text-left w-32">Outcome</th>
                                <th class="px-4 py-2 text-right w-20">Change</th>
                                <th class="px-4 py-2 text-right w-20">Avg</th>
                                <th class="px-4 py-2 text-right w-24">Trend</th>
                                <th class="px-4 py-2 text-right w-20">Open NC</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($byOutlet as $o)
                                @php $band = \App\Services\Audits\AuditScoreService::band($o['latest']); @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2">
                                        <a href="{{ route('audits.show', $o['latestId']) }}" wire:navigate class="font-medium text-gray-900 hover:text-brand-700">{{ $o['outlet'] }}</a>
                                        <span class="block text-xs text-gray-600">{{ $o['latestOn']->format('d M Y') }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ $o['count'] }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums font-semibold {{ ['good' => 'text-success-700', 'fair' => 'text-warning-700', 'poor' => 'text-danger-700', 'none' => 'text-gray-500'][$band] }}">{{ number_format($o['latest'], 1) }}%</td>
                                    <td class="px-4 py-2">
                                        @if ($o['latestOutcome'])
                                            <span @class([
                                                'badge-success' => $o['latestOutcome'] === 'pass',
                                                'badge-warning' => $o['latestOutcome'] === 'conditional',
                                                'badge-danger'  => $o['latestOutcome'] === 'fail',
                                            ])>{{ $o['latestOutcomeLabel'] }}</span>
                                            <span class="block text-[11px] text-gray-600">{{ $o['passes'] }}/{{ $o['count'] }} passed</span>
                                        @else
                                            <span class="text-gray-500">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right tabular-nums {{ $o['delta'] === null ? 'text-gray-500' : ($o['delta'] > 0 ? 'text-success-700' : ($o['delta'] < 0 ? 'text-danger-700' : 'text-gray-600')) }}">
                                        {{ $o['delta'] === null ? '—' : ($o['delta'] > 0 ? '+' : '') . number_format($o['delta'], 1) }}
                                    </td>
                                    <td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ number_format($o['average'], 1) }}%</td>
                                    <td class="px-4 py-2 text-right"><x-sparkline :values="$o['series']" /></td>
                                    <td class="px-4 py-2 text-right tabular-nums {{ $o['openFindings'] ? 'font-medium text-danger-700' : 'text-gray-600' }}">{{ $o['openFindings'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Per section --}}
            <div class="card overflow-hidden">
                <div class="border-b border-gray-100 px-4 py-3"><x-card-title>By section</x-card-title></div>
                <ul class="divide-y divide-gray-100">
                    @foreach ($sections as $s)
                        <li class="flex items-center gap-3 px-4 py-2.5">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm text-gray-900">{{ $s['name'] }}</p>
                                <p class="text-xs text-gray-600">{{ $s['penalty'] ? 'Penalty section' : 'Average across ' . $s['audits'] . ' audit' . ($s['audits'] === 1 ? '' : 's') }} · {{ $s['lost'] }} pts lost in total</p>
                            </div>
                            @if ($s['penalty'])
                                <span class="tabular-nums text-sm font-semibold {{ $s['lost'] ? 'text-danger-700' : 'text-success-700' }}">−{{ $s['lost'] }}</span>
                            @else
                                <div class="w-28">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="tabular-nums font-semibold {{ ['good' => 'text-success-700', 'fair' => 'text-warning-700', 'poor' => 'text-danger-700', 'none' => 'text-gray-500'][\App\Services\Audits\AuditScoreService::band($s['average'])] }}">{{ number_format($s['average'], 1) }}%</span>
                                    </div>
                                    <span class="progress mt-1"><span class="progress-fill" style="width: {{ max(2, (int) $s['average']) }}%"></span></span>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Most failed items --}}
        <div class="card mt-4 overflow-hidden">
            <div class="border-b border-gray-100 px-4 py-3"><x-card-title>Most failed items</x-card-title></div>
            @if ($mostFailed->isEmpty())
                <p class="help px-4 py-6">No non-conformances in this range.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table-surface min-w-[600px]">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left">Item</th>
                                <th class="px-4 py-2 text-left w-48">Section</th>
                                <th class="px-4 py-2 text-right w-24">Failed</th>
                                <th class="px-4 py-2 text-right w-24">Pts lost</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($mostFailed as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 text-gray-900">@if ($row['major'])<span class="badge-danger mr-1.5">Major</span>@endif{{ $row['label'] }}</td>
                                    <td class="px-4 py-2 text-gray-600">{{ $row['section'] }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums font-medium text-gray-900">{{ $row['times'] }}×</td>
                                    <td class="px-4 py-2 text-right tabular-nums text-danger-700">−{{ $row['lost'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>
