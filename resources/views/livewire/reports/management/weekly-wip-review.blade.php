@php
    $money = fn ($v) => $v === null ? '—' : 'RM ' . number_format((float) $v, 2);
    $pct   = fn ($v) => $v === null ? '—' : number_format((float) $v, 1) . '%';
    $hrs   = fn ($v) => $v === null ? '—' : number_format((float) $v, 1) . ' h';
    $fmt   = fn ($v, string $format) => match ($format) { 'pct' => $pct($v), 'hours' => $hrs($v), default => $money($v) };

    // A change is coloured by whether it is good news, not by its sign: sales
    // up is green, wastage up is red. $upIsGood null is neither (transfers).
    $delta = function ($change, ?bool $upIsGood, bool $points = false): array {
        if ($change === null) {
            return ['—', 'text-gray-500'];
        }
        if (abs($change) < 0.05) {
            return [$points ? '0.0 pts' : '0.0%', 'text-gray-600'];
        }
        $text = ($change > 0 ? '▲ ' : '▼ ') . number_format(abs($change), 1) . ($points ? ' pts' : '%');

        if ($upIsGood === null) {
            return [$text, 'text-gray-700'];
        }
        $good = $upIsGood ? $change > 0 : $change < 0;

        return [$text, $good ? 'text-success-700' : 'text-danger-600'];
    };

    $monthly = $report['granularity'] === 'month';
    $unit    = $monthly ? 'month' : 'week';
    $vsLast  = $monthly ? 'vs last mth' : 'vs last wk';
    $canPay  = $report['can_view_pay'];
    $labour  = $report['labour'];

    // The deck is built from whichever sections this view has, so slide
    // numbers and the presenter's count follow the mode and the viewer.
    $slides = [
        'glance'      => $monthly ? 'Month at a glance' : 'Week at a glance',
        'trend'       => 'Sales vs purchases',
        'departments' => 'By department',
        'wastage'     => 'Wastage',
        'staff_meals' => 'Staff meals',
        'transfers'   => 'Stock transfers',
        'overtime'    => 'Overtime claims',
    ];
    if ($labour !== null) {
        $slides['labour'] = 'Labour cost';
    }
    $slides['outlets'] = 'By outlet';
    $idx = array_flip(array_keys($slides));

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
     x-effect="total = {{ count($slides) }}; if (current > total - 1) { current = total - 1; }"
     x-on:keydown.window="key($event)"
     x-on:fullscreenchange.document="if (! document.fullscreenElement && presenting) { presenting = false; refresh(); }">

    @once
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    @endonce

    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
        <div class="min-w-0">
            <p class="page-eyebrow">Reports / Management</p>
            <h1 class="page-title mt-1">{{ $monthly ? 'Monthly' : 'Weekly' }} WIP Review</h1>
            <p class="text-xs text-gray-600 mt-1">
                {{ $cw['range'] }} vs {{ $pw['range'] }} · {{ $scopeLabel }}
            </p>
        </div>

        <div class="page-actions">
            <div class="seg" role="group" aria-label="Review by">
                <button type="button" wire:click="$set('mode', 'week')" class="seg-item {{ $monthly ? '' : 'seg-item-on' }}">Weekly</button>
                <button type="button" wire:click="$set('mode', 'month')" class="seg-item {{ $monthly ? 'seg-item-on' : '' }}">Monthly</button>
            </div>

            <div class="flex items-center gap-1">
                <button type="button" wire:click="previousPeriod" class="btn-secondary" title="Previous {{ $unit }}">‹</button>
                @if ($monthly)
                    <input type="month" wire:model.live="month" class="input w-auto text-sm" aria-label="Month to review" />
                @else
                    <input type="date" wire:model.live="week" class="input w-auto text-sm" aria-label="Week to review" />
                @endif
                <button type="button" wire:click="nextPeriod" class="btn-secondary" title="Next {{ $unit }}" @disabled($isLatest)>›</button>
            </div>

            @if ($monthly)
                <select wire:model.live="months" class="input w-auto text-sm" aria-label="Months in trend">
                    @foreach ($monthOptions as $n)
                        <option value="{{ $n }}">{{ $n }}-month trend</option>
                    @endforeach
                </select>
            @else
                <select wire:model.live="weeks" class="input w-auto text-sm" aria-label="Weeks in trend">
                    @foreach ($weekOptions as $n)
                        <option value="{{ $n }}">{{ $n }}-week trend</option>
                    @endforeach
                </select>
            @endif

            @if ($outlets->isNotEmpty())
                <select wire:model.live="outletFilter" class="input w-auto text-sm" aria-label="Outlet">
                    <option value="">All outlets</option>
                    @foreach ($outlets as $o)
                        <option value="{{ $o->id }}">{{ $o->name }}</option>
                    @endforeach
                </select>
            @endif

            @if ($monthly && $canPay)
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 min-h-[44px]">
                    <input type="checkbox" wire:model.live="includeDraftPayroll"
                           class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                    Include draft payroll
                </label>
            @endif

            <button type="button" x-on:click="start()" class="btn-primary">Present</button>
        </div>
    </div>

    <div x-ref="deck" wire:loading.class="opacity-60"
         :class="presenting ? 'fixed inset-0 z-[100] bg-gray-50 flex flex-col p-6 sm:p-10 overflow-auto' : ''">

        {{-- ── At a glance ─────────────────────────────────────────────── --}}
        <section class="card p-5 mb-6" x-show="! presenting || current === {{ $idx['glance'] }}" :class="presenting && 'flex-1 !mb-0'">
            @include('livewire.reports.management.partials.wip-slide-head', ['n' => $idx['glance'] + 1, 'title' => $slides['glance']])

            @if (! $report['has_data'])
                <div class="empty-state py-10 text-center text-sm text-gray-600">
                    Nothing recorded for these {{ $unit }}s — no sales, purchases, wastage, staff meals, transfers, overtime or payroll.
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach ($report['kpis'] as $k)
                        @php
                            [$dText, $dClass] = $delta($k['change'], $k['up_is_good'], $k['format'] === 'pct');
                        @endphp
                        <div class="stat rounded-surface border border-gray-100 p-4" wire:key="kpi-{{ $k['key'] }}">
                            <p class="stat-label">{{ $k['label'] }}</p>
                            <p class="stat-value tabular-nums" :class="presenting && 'text-3xl'">{{ $fmt($k['current'], $k['format']) }}</p>
                            <p class="text-xs mt-1">
                                <span class="font-semibold {{ $dClass }}">{{ $dText }}</span>
                                <span class="text-gray-600">vs {{ $fmt($k['previous'], $k['format']) }} last {{ $unit }}</span>
                            </p>
                        </div>
                    @endforeach
                </div>
                @unless ($canPay)
                    <p class="mt-3 text-[11px] text-gray-500">Overtime cost and labour cost are shown only to users who can see pay.</p>
                @endunless
            @endif
        </section>

        {{-- ── Sales vs purchases trend ────────────────────────────────── --}}
        <section class="card p-5 mb-6" x-show="! presenting || current === {{ $idx['trend'] }}" :class="presenting && 'flex-1 !mb-0'">
            @include('livewire.reports.management.partials.wip-slide-head', ['n' => $idx['trend'] + 1, 'title' => $slides['trend'], 'hint' => 'click a ' . $unit . ' to review it'])

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
                                onClick: (evt, els) => { if (els.length) { this.$wire.reviewPeriod(d.starts[els[0].index]); } },
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

            @php
                $trendRows = [
                    ['Sales', 'sales', 'money'], ['Purchases', 'purchases', 'money'], ['Purchase cost %', 'cost_pct', 'pct'],
                    ['Wastage', 'wastage', 'money'], ['Staff meals', 'staff_meal', 'money'],
                    ['Stock transfers', 'transfers', 'money'], ['Overtime hours', 'ot_hours', 'hours'],
                ];
                if ($canPay) {
                    $trendRows[] = ['Overtime cost', 'ot_cost', 'money'];
                }
                if ($labour !== null) {
                    $trendRows[] = ['Labour cost', 'labour_cost', 'money'];
                    $trendRows[] = ['Labour cost %', 'labour_pct', 'pct'];
                }
            @endphp
            <div class="overflow-x-auto mt-5">
                <table class="table-surface min-w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left">{{ ucfirst($unit) }}</th>
                            @foreach ($report['periods'] as $p)
                                <th class="px-3 py-2 text-right whitespace-nowrap {{ $loop->last ? 'text-brand-700' : '' }}">{{ $p['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($trendRows as [$label, $key, $format])
                            <tr>
                                <td class="px-3 py-2 font-medium text-gray-800 whitespace-nowrap">{{ $label }}</td>
                                @foreach ($report['totals'][$key] as $v)
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap {{ $loop->last ? 'font-semibold text-gray-900' : 'text-gray-700' }}">
                                        @if ($format === 'pct') {{ $pct($v) }} @elseif ($format === 'hours') {{ number_format((float) $v, 1) }} @else {{ number_format((float) $v, 2) }} @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- ── By department ───────────────────────────────────────────── --}}
        <section class="card p-5 mb-6" x-show="! presenting || current === {{ $idx['departments'] }}" :class="presenting && 'flex-1 !mb-0'">
            @include('livewire.reports.management.partials.wip-slide-head', ['n' => $idx['departments'] + 1, 'title' => $slides['departments'], 'hint' => 'this ' . $unit . ', sales against purchases'])

            @if ($report['departments'] === [])
                <p class="py-8 text-center text-sm text-gray-600">No department figures for these two {{ $unit }}s.</p>
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
                                <th class="px-3 py-2 text-right">{{ $vsLast }}</th>
                                <th class="px-3 py-2 text-right">Purchases</th>
                                <th class="px-3 py-2 text-right">{{ $vsLast }}</th>
                                <th class="px-3 py-2 text-right">Cost %</th>
                                <th class="px-3 py-2 text-right">{{ $vsLast }}</th>
                                <th class="px-3 py-2 text-right">Wastage</th>
                                <th class="px-3 py-2 text-right">{{ $vsLast }}</th>
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

        {{-- ── Wastage ─────────────────────────────────────────────────── --}}
        <section class="card p-5 mb-6" x-show="! presenting || current === {{ $idx['wastage'] }}" :class="presenting && 'flex-1 !mb-0'">
            @include('livewire.reports.management.partials.wip-slide-head', ['n' => $idx['wastage'] + 1, 'title' => $slides['wastage'], 'hint' => 'cost, and as a share of sales'])

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
                                <th class="px-3 py-2 text-right">This {{ $unit }}</th>
                                <th class="px-3 py-2 text-right">Last {{ $unit }}</th>
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

        {{-- ── Staff meals ─────────────────────────────────────────────── --}}
        <section class="card p-5 mb-6" x-show="! presenting || current === {{ $idx['staff_meals'] }}" :class="presenting && 'flex-1 !mb-0'">
            @include('livewire.reports.management.partials.wip-slide-head', ['n' => $idx['staff_meals'] + 1, 'title' => $slides['staff_meals'], 'hint' => 'cost, and as a share of sales'])

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
                                <th class="px-3 py-2 text-right">This {{ $unit }}</th>
                                <th class="px-3 py-2 text-right">Last {{ $unit }}</th>
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

        {{-- ── Stock transfers ─────────────────────────────────────────── --}}
        <section class="card p-5 mb-6" x-show="! presenting || current === {{ $idx['transfers'] }}" :class="presenting && 'flex-1 !mb-0'">
            @include('livewire.reports.management.partials.wip-slide-head', ['n' => $idx['transfers'] + 1, 'title' => $slides['transfers'], 'hint' => 'in transit and received, at line cost'])

            @include('livewire.reports.management.partials.wip-cost-trend', [
                'chart' => $report['charts']['transfers'], 'key' => 'transfers', 'label' => 'Transfers',
            ])

            @php
                $transferRows = array_values(array_filter($report['outlets'], fn ($o) =>
                    $o['transfers_out']['current'] > 0 || $o['transfers_in']['current'] > 0
                    || $o['transfers_out']['previous'] > 0 || $o['transfers_in']['previous'] > 0));
            @endphp
            @if ($transferRows !== [])
                <div class="overflow-x-auto mt-5">
                    <table class="table-surface min-w-full text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left">Outlet</th>
                                <th class="px-3 py-2 text-right">Sent</th>
                                <th class="px-3 py-2 text-right">{{ $vsLast }}</th>
                                <th class="px-3 py-2 text-right">Received</th>
                                <th class="px-3 py-2 text-right">{{ $vsLast }}</th>
                                <th class="px-3 py-2 text-right">Net in / (out)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transferRows as $o)
                                @php
                                    $out = $delta($o['transfers_out']['change'], null);
                                    $in  = $delta($o['transfers_in']['change'], null);
                                    $net = $o['transfers_in']['current'] - $o['transfers_out']['current'];
                                @endphp
                                <tr wire:key="transfer-{{ $loop->index }}-{{ $o['name'] }}">
                                    <td class="px-3 py-2 font-medium text-gray-800 whitespace-nowrap">{{ $o['name'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($o['transfers_out']['current'], 2) }}</td>
                                    <td class="px-3 py-2 text-right text-xs font-semibold whitespace-nowrap {{ $out[1] }}">{{ $out[0] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($o['transfers_in']['current'], 2) }}</td>
                                    <td class="px-3 py-2 text-right text-xs font-semibold whitespace-nowrap {{ $in[1] }}">{{ $in[0] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap font-semibold">
                                        {{ $net < 0 ? '(' . number_format(abs($net), 2) . ')' : number_format($net, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-[11px] text-gray-500">
                    Drafts and cancelled transfers are left out. Across the whole company a transfer nets to zero — it moves cost
                    between outlets rather than adding to it.
                </p>
            @endif
        </section>

        {{-- ── Overtime claims ─────────────────────────────────────────── --}}
        <section class="card p-5 mb-6" x-show="! presenting || current === {{ $idx['overtime'] }}" :class="presenting && 'flex-1 !mb-0'">
            @include('livewire.reports.management.partials.wip-slide-head', [
                'n' => $idx['overtime'] + 1, 'title' => $slides['overtime'],
                'hint' => $canPay ? 'approved claims — cost at each person\'s hourly rate' : 'approved claims, in hours',
            ])

            @include('livewire.reports.management.partials.wip-cost-trend', [
                'chart' => $report['charts']['overtime'], 'key' => 'overtime', 'label' => $canPay ? 'Overtime cost' : 'Overtime hours',
            ])

            <div class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-xs text-gray-600">
                <span>{{ $hrs($report['overtime']['pending_hours']['current']) }} still awaiting approval this {{ $unit }} — not counted above.</span>
                @if ($canPay && $report['overtime']['unpriced'] > 0)
                    <span class="text-warning-700">{{ $report['overtime']['unpriced'] }} approved claim(s) could not be costed: no salary on record.</span>
                @endif
                @if ($canPay)
                    <span>Hours settled as time off count as hours but cost nothing.</span>
                @else
                    <span>Overtime cost is shown only to users who can see pay.</span>
                @endif
            </div>

            @php
                $otRows = array_values(array_filter($report['outlets'], fn ($o) =>
                    $o['ot_hours']['current'] > 0 || $o['ot_hours']['previous'] > 0
                    || ($canPay && ($o['ot_cost']['current'] > 0 || $o['ot_cost']['previous'] > 0))));
            @endphp
            @if ($otRows !== [])
                <div class="overflow-x-auto mt-5">
                    <table class="table-surface min-w-full text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left">Outlet</th>
                                <th class="px-3 py-2 text-right">Hours</th>
                                <th class="px-3 py-2 text-right">{{ $vsLast }}</th>
                                @if ($canPay)
                                    <th class="px-3 py-2 text-right">Cost</th>
                                    <th class="px-3 py-2 text-right">{{ $vsLast }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($otRows as $o)
                                @php
                                    $h = $delta($o['ot_hours']['change'], false);
                                    $c = $canPay ? $delta($o['ot_cost']['change'], false) : null;
                                @endphp
                                <tr wire:key="ot-{{ $loop->index }}-{{ $o['name'] }}">
                                    <td class="px-3 py-2 font-medium text-gray-800 whitespace-nowrap">{{ $o['name'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($o['ot_hours']['current'], 1) }}</td>
                                    <td class="px-3 py-2 text-right text-xs font-semibold whitespace-nowrap {{ $h[1] }}">{{ $h[0] }}</td>
                                    @if ($canPay)
                                        <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($o['ot_cost']['current'], 2) }}</td>
                                        <td class="px-3 py-2 text-right text-xs font-semibold whitespace-nowrap {{ $c[1] }}">{{ $c[0] }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- ── Labour cost (monthly, pay viewers only) ─────────────────── --}}
        @if ($labour !== null)
            <section class="card p-5 mb-6" x-show="! presenting || current === {{ $idx['labour'] }}" :class="presenting && 'flex-1 !mb-0'">
                @include('livewire.reports.management.partials.wip-slide-head', [
                    'n' => $idx['labour'] + 1, 'title' => $slides['labour'],
                    'hint' => $labour['include_drafts'] ? 'from payroll, including draft runs' : 'from approved and paid payroll',
                ])

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <div>
                        @include('livewire.reports.management.partials.wip-cost-trend', [
                            'chart' => $report['charts']['labour'], 'key' => 'labour', 'label' => 'Labour cost',
                        ])
                    </div>

                    <div class="overflow-x-auto">
                        @php $labourPct = collect($report['kpis'])->firstWhere('key', 'labour_pct'); @endphp
                        <table class="table-surface min-w-full text-sm">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left">{{ $cw['range'] }}</th>
                                    <th class="px-3 py-2 text-right">This month</th>
                                    <th class="px-3 py-2 text-right">Last month</th>
                                    <th class="px-3 py-2 text-right">Change</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($labour['rows'] as $r)
                                    @php $lc = $delta($r['change'], false); @endphp
                                    <tr wire:key="labour-{{ $r['key'] }}" class="{{ $r['key'] === 'employer_cost' ? 'font-semibold bg-gray-50' : '' }}">
                                        <td class="px-3 py-2 text-gray-800">{{ $r['label'] }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($r['current'], 2) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap text-gray-600">{{ number_format($r['previous'], 2) }}</td>
                                        <td class="px-3 py-2 text-right text-xs font-semibold whitespace-nowrap {{ $lc[1] }}">{{ $lc[0] }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td class="px-3 py-2 text-gray-800">Headcount paid</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $labour['headcount']['current'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-600">{{ $labour['headcount']['previous'] }}</td>
                                    <td class="px-3 py-2"></td>
                                </tr>
                                @if ($labourPct)
                                    @php $lp = $delta($labourPct['change'], false, true); @endphp
                                    <tr>
                                        <td class="px-3 py-2 text-gray-800">Labour cost % of sales</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $pct($labourPct['current']) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums text-gray-600">{{ $pct($labourPct['previous']) }}</td>
                                        <td class="px-3 py-2 text-right text-xs font-semibold {{ $lp[1] }}">{{ $lp[0] }}</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>

                @php $labourRows = array_values(array_filter($report['outlets'], fn ($o) => $o['labour_cost']['current'] > 0 || $o['labour_cost']['previous'] > 0)); @endphp
                @if ($labourRows !== [])
                    <div class="overflow-x-auto mt-5">
                        <table class="table-surface min-w-full text-sm">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left">Outlet</th>
                                    <th class="px-3 py-2 text-right">Labour cost</th>
                                    <th class="px-3 py-2 text-right">{{ $vsLast }}</th>
                                    <th class="px-3 py-2 text-right">Sales</th>
                                    <th class="px-3 py-2 text-right">Labour %</th>
                                    <th class="px-3 py-2 text-right">{{ $vsLast }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($labourRows as $o)
                                    @php
                                        $lc = $delta($o['labour_cost']['change'], false);
                                        $lp = $delta($o['labour_pct']['change'], false, true);
                                    @endphp
                                    <tr wire:key="labour-outlet-{{ $loop->index }}-{{ $o['name'] }}">
                                        <td class="px-3 py-2 font-medium text-gray-800 whitespace-nowrap">{{ $o['name'] }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($o['labour_cost']['current'], 2) }}</td>
                                        <td class="px-3 py-2 text-right text-xs font-semibold whitespace-nowrap {{ $lc[1] }}">{{ $lc[0] }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($o['sales']['current'], 2) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ $pct($o['labour_pct']['current']) }}</td>
                                        <td class="px-3 py-2 text-right text-xs font-semibold whitespace-nowrap {{ $lp[1] }}">{{ $lp[0] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <p class="mt-2 text-[11px] text-gray-500">
                    Each employee is counted once a month, from their most settled run (paid, then approved, then draft).
                    A company-wide run is split by each employee's current outlet.
                    @if (! $labour['include_drafts'] && $labour['drafts_left_out'] > 0)
                        {{ $labour['drafts_left_out'] }} draft run(s) in these months are not included — tick "Include draft payroll" to add them.
                    @elseif ($labour['draft_used'])
                        Includes figures from draft runs, which can still change.
                    @endif
                </p>
            </section>
        @endif

        {{-- ── By outlet ───────────────────────────────────────────────── --}}
        <section class="card p-5 mb-6" x-show="! presenting || current === {{ $idx['outlets'] }}" :class="presenting && 'flex-1 !mb-0'">
            @include('livewire.reports.management.partials.wip-slide-head', ['n' => $idx['outlets'] + 1, 'title' => $slides['outlets']])

            @if ($report['outlets'] === [])
                <p class="py-8 text-center text-sm text-gray-600">No outlet figures for these two {{ $unit }}s.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table-surface min-w-full text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left">Outlet</th>
                                <th class="px-3 py-2 text-right">Sales</th>
                                <th class="px-3 py-2 text-right">{{ $vsLast }}</th>
                                <th class="px-3 py-2 text-right">Purchases</th>
                                <th class="px-3 py-2 text-right">Cost %</th>
                                <th class="px-3 py-2 text-right">{{ $vsLast }}</th>
                                <th class="px-3 py-2 text-right">Wastage</th>
                                <th class="px-3 py-2 text-right">Staff meals</th>
                                <th class="px-3 py-2 text-right">Transfers in</th>
                                <th class="px-3 py-2 text-right">Transfers out</th>
                                <th class="px-3 py-2 text-right">OT hours</th>
                                @if ($labour !== null)
                                    <th class="px-3 py-2 text-right">Labour cost</th>
                                    <th class="px-3 py-2 text-right">Labour %</th>
                                @endif
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
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($o['transfers_in']['current'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($o['transfers_out']['current'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($o['ot_hours']['current'], 1) }}</td>
                                    @if ($labour !== null)
                                        <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($o['labour_cost']['current'], 2) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ $pct($o['labour_pct']['current']) }}</td>
                                    @endif
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
