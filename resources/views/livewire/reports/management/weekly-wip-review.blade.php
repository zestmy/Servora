@php
    $money = fn ($v) => $v === null ? '—' : 'RM ' . number_format((float) $v, 2);
    $pct   = fn ($v) => $v === null ? '—' : number_format((float) $v, 1) . '%';

    // A change is coloured by whether it is good news, not by its sign: sales
    // up is green, wastage up is red.
    $delta = function ($change, bool $upIsGood, bool $points = false): array {
        if ($change === null) {
            return ['—', 'text-gray-500'];
        }
        if (abs($change) < 0.05) {
            return [$points ? '0.0 pts' : '0.0%', 'text-gray-600'];
        }
        $good = $upIsGood ? $change > 0 : $change < 0;

        return [
            ($change > 0 ? '▲ ' : '▼ ') . number_format(abs($change), 1) . ($points ? ' pts' : '%'),
            $good ? 'text-success-700' : 'text-danger-600',
        ];
    };

    $slides = ['Week at a glance', 'Sales vs purchases', 'By department', 'Wastage', 'Staff meals', 'By outlet'];
    $cw = $report['current'];
    $pw = $report['previous'];
@endphp

{{--
    One page, two ways to read it. Scrolling, it is an ordinary report. "Present"
    turns the same sections into a full-screen deck: one section per slide,
    arrow keys / Page Up-Down / space to move, Esc to leave. Fullscreen puts the
    deck in the browser's top layer, which also escapes the sidebar's transform.
    Charts are resized on every slide change — Chart.js measures a hidden
    canvas as zero wide.
--}}
<div x-data="{
        presenting: false,
        current: 0,
        total: {{ count($slides) }},
        start() {
            this.current = 0;
            this.presenting = true;
            if (this.$refs.deck.requestFullscreen) { this.$refs.deck.requestFullscreen().catch(() => {}); }
            this.refresh();
        },
        stop() {
            this.presenting = false;
            if (document.fullscreenElement) { document.exitFullscreen().catch(() => {}); }
            this.refresh();
        },
        go(i) {
            this.current = Math.max(0, Math.min(this.total - 1, i));
            this.refresh();
        },
        refresh() {
            this.$nextTick(() => { if (window.Chart) { Object.values(Chart.instances).forEach(c => c.resize()); } });
        },
        key(e) {
            if (! this.presenting) return;
            if (['ArrowRight', 'PageDown', ' '].includes(e.key)) { e.preventDefault(); this.go(this.current + 1); }
            if (['ArrowLeft', 'PageUp'].includes(e.key)) { e.preventDefault(); this.go(this.current - 1); }
            if (e.key === 'Escape') { this.stop(); }
        },
     }"
     x-on:keydown.window="key($event)"
     x-on:fullscreenchange.document="if (! document.fullscreenElement && presenting) { presenting = false; refresh(); }">

    @once
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    @endonce

    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
        <div class="min-w-0">
            <p class="page-eyebrow">Reports / Management</p>
            <h1 class="page-title mt-1">Weekly WIP Review</h1>
            <p class="text-xs text-gray-600 mt-1">
                {{ $cw['range'] }} vs {{ $pw['range'] }} · {{ $scopeLabel }}
            </p>
        </div>

        <div class="page-actions">
            <div class="flex items-center gap-1">
                <button type="button" wire:click="previousWeek" class="btn-secondary" title="Previous week">‹</button>
                <input type="date" wire:model.live="week" class="input w-auto text-sm" aria-label="Week to review" />
                <button type="button" wire:click="nextWeek" class="btn-secondary" title="Next week" @disabled($isLatestWeek)>›</button>
            </div>
            <select wire:model.live="weeks" class="input w-auto text-sm" aria-label="Weeks in trend">
                @foreach ($weekOptions as $n)
                    <option value="{{ $n }}">{{ $n }}-week trend</option>
                @endforeach
            </select>
            @if ($outlets->isNotEmpty())
                <select wire:model.live="outletFilter" class="input w-auto text-sm" aria-label="Outlet">
                    <option value="">All outlets</option>
                    @foreach ($outlets as $o)
                        <option value="{{ $o->id }}">{{ $o->name }}</option>
                    @endforeach
                </select>
            @endif
            <button type="button" x-on:click="start()" class="btn-primary">Present</button>
        </div>
    </div>

    <div x-ref="deck" wire:loading.class="opacity-60"
         :class="presenting ? 'fixed inset-0 z-[100] bg-gray-50 flex flex-col p-6 sm:p-10 overflow-auto' : ''">

        {{-- ── 1. Week at a glance ─────────────────────────────────────── --}}
        <section class="card p-5 mb-6" x-show="! presenting || current === 0" :class="presenting && 'flex-1 !mb-0'">
            @include('livewire.reports.management.partials.wip-slide-head', ['n' => 1, 'title' => $slides[0]])

            @if (! $report['has_data'])
                <div class="empty-state py-10 text-center text-sm text-gray-600">
                    No sales, purchases, wastage or staff meals recorded for these weeks.
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach ($report['kpis'] as $k)
                        @php
                            $isPct = $k['format'] === 'pct';
                            [$dText, $dClass] = $delta($k['change'], $k['up_is_good'], $isPct);
                        @endphp
                        <div class="stat rounded-surface border border-gray-100 p-4" wire:key="kpi-{{ $k['key'] }}">
                            <p class="stat-label">{{ $k['label'] }}</p>
                            <p class="stat-value tabular-nums" :class="presenting && 'text-3xl'">
                                {{ $isPct ? $pct($k['current']) : $money($k['current']) }}
                            </p>
                            <p class="text-xs mt-1">
                                <span class="font-semibold {{ $dClass }}">{{ $dText }}</span>
                                <span class="text-gray-600">vs {{ $isPct ? $pct($k['previous']) : $money($k['previous']) }} last week</span>
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ── 2. Sales vs purchases trend ─────────────────────────────── --}}
        <section class="card p-5 mb-6" x-show="! presenting || current === 1" :class="presenting && 'flex-1 !mb-0'">
            @include('livewire.reports.management.partials.wip-slide-head', ['n' => 2, 'title' => $slides[1], 'hint' => 'click a week to review it'])

            <div class="relative h-72" :class="presenting && '!h-[50vh]'"
                 wire:key="wip-trend-{{ md5(json_encode($report['charts']['trend'])) }}"
                 x-data="{
                    init() {
                        const old = Chart.getChart(this.$refs.c); if (old) { old.destroy(); }
                        const d = @js($report['charts']['trend']);
                        const rm = v => 'RM ' + Number(v).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        new Chart(this.$refs.c, {
                            data: {
                                labels: d.labels,
                                datasets: [
                                    { type: 'bar', label: 'Sales', data: d.sales, backgroundColor: d.colors.sales, borderRadius: 3, yAxisID: 'y', order: 2 },
                                    { type: 'bar', label: 'Purchases', data: d.purchases, backgroundColor: d.colors.purchases, borderRadius: 3, yAxisID: 'y', order: 2 },
                                    { type: 'line', label: 'Purchase cost %', data: d.cost_pct, borderColor: d.colors.line, backgroundColor: d.colors.line, yAxisID: 'pct', tension: 0.3, spanGaps: true, order: 1 },
                                ],
                            },
                            options: {
                                responsive: true, maintainAspectRatio: false,
                                interaction: { mode: 'index', intersect: false },
                                onClick: (evt, els) => { if (els.length) { this.$wire.reviewWeek(d.starts[els[0].index]); } },
                                onHover: (evt, els) => { evt.native.target.style.cursor = els.length ? 'pointer' : 'default'; },
                                plugins: {
                                    legend: { position: 'bottom' },
                                    tooltip: { callbacks: { label: i => i.dataset.yAxisID === 'pct'
                                        ? i.dataset.label + ': ' + (i.parsed.y === null ? '—' : i.parsed.y.toFixed(1) + '%')
                                        : i.dataset.label + ': ' + rm(i.parsed.y) } },
                                },
                                scales: {
                                    y: { beginAtZero: true, ticks: { callback: v => 'RM ' + Number(v).toLocaleString() } },
                                    pct: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { callback: v => v + '%' } },
                                },
                            },
                        });
                    },
                 }">
                <canvas x-ref="c"></canvas>
            </div>

            <div class="overflow-x-auto mt-5">
                <table class="table-surface min-w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left">Week</th>
                            @foreach ($report['weeks'] as $w)
                                <th class="px-3 py-2 text-right whitespace-nowrap {{ $loop->last ? 'text-brand-700' : '' }}">{{ $w['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ([
                            ['Sales', 'sales', 'money'], ['Purchases', 'purchases', 'money'], ['Purchase cost %', 'cost_pct', 'pct'],
                            ['Wastage', 'wastage', 'money'], ['Staff meals', 'staff_meal', 'money'],
                        ] as [$label, $key, $format])
                            <tr>
                                <td class="px-3 py-2 font-medium text-gray-800 whitespace-nowrap">{{ $label }}</td>
                                @foreach ($report['totals'][$key] as $v)
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap {{ $loop->last ? 'font-semibold text-gray-900' : 'text-gray-700' }}">
                                        {{ $format === 'pct' ? $pct($v) : number_format((float) $v, 2) }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- ── 3. By department ────────────────────────────────────────── --}}
        <section class="card p-5 mb-6" x-show="! presenting || current === 2" :class="presenting && 'flex-1 !mb-0'">
            @include('livewire.reports.management.partials.wip-slide-head', ['n' => 3, 'title' => $slides[2], 'hint' => 'this week, sales against purchases'])

            @if ($report['departments'] === [])
                <p class="py-8 text-center text-sm text-gray-600">No department figures for these two weeks.</p>
            @else
                <div class="relative" style="height: {{ max(180, count($report['departments']) * 56 + 40) }}px"
                     :class="presenting && '!h-[40vh]'"
                     wire:key="wip-dept-{{ md5(json_encode($report['charts']['departments'])) }}"
                     x-data="{
                        init() {
                            const old = Chart.getChart(this.$refs.c); if (old) { old.destroy(); }
                            const d = @js($report['charts']['departments']);
                            const rm = v => 'RM ' + Number(v).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            new Chart(this.$refs.c, {
                                type: 'bar',
                                data: {
                                    labels: d.labels,
                                    datasets: [
                                        { label: 'Sales', data: d.sales, backgroundColor: d.colors.sales, borderRadius: 3 },
                                        { label: 'Purchases', data: d.purchases, backgroundColor: d.colors.purchases, borderRadius: 3 },
                                    ],
                                },
                                options: {
                                    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                                    plugins: {
                                        legend: { position: 'bottom' },
                                        tooltip: { callbacks: {
                                            label: i => i.dataset.label + ': ' + rm(i.parsed.x),
                                            afterBody: items => {
                                                const p = d.cost_pct[items[0].dataIndex];
                                                return p === null ? 'Purchase cost %: —' : 'Purchase cost %: ' + p.toFixed(1) + '%';
                                            },
                                        } },
                                    },
                                    scales: { x: { beginAtZero: true, ticks: { callback: v => 'RM ' + Number(v).toLocaleString() } }, y: { grid: { display: false } } },
                                },
                            });
                        },
                     }">
                    <canvas x-ref="c"></canvas>
                </div>

                <div class="overflow-x-auto mt-5">
                    <table class="table-surface min-w-full text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left">Department</th>
                                <th class="px-3 py-2 text-right">Sales</th>
                                <th class="px-3 py-2 text-right">vs last wk</th>
                                <th class="px-3 py-2 text-right">Purchases</th>
                                <th class="px-3 py-2 text-right">vs last wk</th>
                                <th class="px-3 py-2 text-right">Cost %</th>
                                <th class="px-3 py-2 text-right">vs last wk</th>
                                <th class="px-3 py-2 text-right">Wastage</th>
                                <th class="px-3 py-2 text-right">vs last wk</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report['departments'] as $d)
                                @php
                                    $s = $delta($d['sales']['change'], true);
                                    $p = $delta($d['purchases']['change'], false);
                                    $c = $delta($d['cost_pct']['change'], false, true);
                                    $w = $delta($d['wastage']['change'], false);
                                @endphp
                                <tr wire:key="dept-{{ $loop->index }}-{{ $d['name'] }}">
                                    <td class="px-3 py-2 font-medium text-gray-800 whitespace-nowrap">{{ $d['name'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($d['sales']['current'], 2) }}</td>
                                    <td class="px-3 py-2 text-right text-xs font-semibold whitespace-nowrap {{ $s[1] }}">{{ $s[0] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($d['purchases']['current'], 2) }}</td>
                                    <td class="px-3 py-2 text-right text-xs font-semibold whitespace-nowrap {{ $p[1] }}">{{ $p[0] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ $pct($d['cost_pct']['current']) }}</td>
                                    <td class="px-3 py-2 text-right text-xs font-semibold whitespace-nowrap {{ $c[1] }}">{{ $c[0] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($d['wastage']['current'], 2) }}</td>
                                    <td class="px-3 py-2 text-right text-xs font-semibold whitespace-nowrap {{ $w[1] }}">{{ $w[0] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-[11px] text-gray-500">
                    Sales reach a department through its sales category (Settings &gt; Departments). Sales no department claims, and
                    purchases or wastage keyed without a department, are shown as Unassigned.
                </p>
            @endif
        </section>

        {{-- ── 4. Wastage ──────────────────────────────────────────────── --}}
        <section class="card p-5 mb-6" x-show="! presenting || current === 3" :class="presenting && 'flex-1 !mb-0'">
            @include('livewire.reports.management.partials.wip-slide-head', ['n' => 4, 'title' => $slides[3], 'hint' => 'cost, and as a share of sales'])

            @include('livewire.reports.management.partials.wip-cost-trend', [
                'chart' => $report['charts']['wastage'], 'key' => 'wastage', 'label' => 'Wastage',
            ])

            @php $wasteRows = array_values(array_filter($report['departments'], fn ($d) => $d['wastage']['current'] > 0 || $d['wastage']['previous'] > 0)); @endphp
            @if ($wasteRows !== [])
                <div class="overflow-x-auto mt-5">
                    <table class="table-surface min-w-full text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left">Department</th>
                                <th class="px-3 py-2 text-right">This week</th>
                                <th class="px-3 py-2 text-right">Last week</th>
                                <th class="px-3 py-2 text-right">Change</th>
                                <th class="px-3 py-2 text-right">% of dept sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($wasteRows as $d)
                                @php $w = $delta($d['wastage']['change'], false); @endphp
                                <tr wire:key="waste-{{ $loop->index }}-{{ $d['name'] }}">
                                    <td class="px-3 py-2 font-medium text-gray-800">{{ $d['name'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format($d['wastage']['current'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-600">{{ number_format($d['wastage']['previous'], 2) }}</td>
                                    <td class="px-3 py-2 text-right text-xs font-semibold {{ $w[1] }}">{{ $w[0] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $pct($d['wastage_pct']['current']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- ── 5. Staff meals ──────────────────────────────────────────── --}}
        <section class="card p-5 mb-6" x-show="! presenting || current === 4" :class="presenting && 'flex-1 !mb-0'">
            @include('livewire.reports.management.partials.wip-slide-head', ['n' => 5, 'title' => $slides[4], 'hint' => 'cost, and as a share of sales'])

            @include('livewire.reports.management.partials.wip-cost-trend', [
                'chart' => $report['charts']['staff_meal'], 'key' => 'staff-meal', 'label' => 'Staff meals',
            ])

            @php $mealRows = array_values(array_filter($report['outlets'], fn ($o) => $o['staff_meal']['current'] > 0 || $o['staff_meal']['previous'] > 0)); @endphp
            @if ($mealRows !== [])
                <div class="overflow-x-auto mt-5">
                    <table class="table-surface min-w-full text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left">Outlet</th>
                                <th class="px-3 py-2 text-right">This week</th>
                                <th class="px-3 py-2 text-right">Last week</th>
                                <th class="px-3 py-2 text-right">Change</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($mealRows as $o)
                                @php $m = $delta($o['staff_meal']['change'], false); @endphp
                                <tr wire:key="meal-{{ $loop->index }}-{{ $o['name'] }}">
                                    <td class="px-3 py-2 font-medium text-gray-800">{{ $o['name'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format($o['staff_meal']['current'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-600">{{ number_format($o['staff_meal']['previous'], 2) }}</td>
                                    <td class="px-3 py-2 text-right text-xs font-semibold {{ $m[1] }}">{{ $m[0] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- ── 6. By outlet ────────────────────────────────────────────── --}}
        <section class="card p-5 mb-6" x-show="! presenting || current === 5" :class="presenting && 'flex-1 !mb-0'">
            @include('livewire.reports.management.partials.wip-slide-head', ['n' => 6, 'title' => $slides[5]])

            @if ($report['outlets'] === [])
                <p class="py-8 text-center text-sm text-gray-600">No outlet figures for these two weeks.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table-surface min-w-full text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left">Outlet</th>
                                <th class="px-3 py-2 text-right">Sales</th>
                                <th class="px-3 py-2 text-right">vs last wk</th>
                                <th class="px-3 py-2 text-right">Purchases</th>
                                <th class="px-3 py-2 text-right">Cost %</th>
                                <th class="px-3 py-2 text-right">vs last wk</th>
                                <th class="px-3 py-2 text-right">Wastage</th>
                                <th class="px-3 py-2 text-right">Staff meals</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report['outlets'] as $o)
                                @php
                                    $s = $delta($o['sales']['change'], true);
                                    $c = $delta($o['cost_pct']['change'], false, true);
                                @endphp
                                <tr wire:key="outlet-{{ $loop->index }}-{{ $o['name'] }}">
                                    <td class="px-3 py-2 font-medium text-gray-800 whitespace-nowrap">{{ $o['name'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($o['sales']['current'], 2) }}</td>
                                    <td class="px-3 py-2 text-right text-xs font-semibold whitespace-nowrap {{ $s[1] }}">{{ $s[0] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($o['purchases']['current'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ $pct($o['cost_pct']['current']) }}</td>
                                    <td class="px-3 py-2 text-right text-xs font-semibold whitespace-nowrap {{ $c[1] }}">{{ $c[0] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($o['wastage']['current'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($o['staff_meal']['current'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- Presenter bar --}}
        <div x-show="presenting" x-cloak class="mt-4 flex items-center justify-between gap-3 text-sm text-gray-600">
            <span>{{ $cw['range'] }} · {{ $scopeLabel }}</span>
            <div class="flex items-center gap-2">
                <button type="button" x-on:click="go(current - 1)" class="btn-secondary" :disabled="current === 0">‹ Prev</button>
                <span class="tabular-nums w-14 text-center" x-text="(current + 1) + ' / ' + total"></span>
                <button type="button" x-on:click="go(current + 1)" class="btn-secondary" :disabled="current === total - 1">Next ›</button>
                <button type="button" x-on:click="stop()" class="btn-ghost">Exit</button>
            </div>
        </div>
    </div>
</div>
