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
    $sp = $report['sales_performance'];

    $slides = ['glance' => $monthly ? 'Month at a glance' : 'Week at a glance'];
    if ($sp !== null) {
        $slides['sales']    = 'Sales performance';
        $slides['mtd']      = 'Month to date — sales, covers & average check';
        $slides['forecast'] = 'Sales forecast — ' . $sp['forecast']['month_label'];
    }
    $slides += [
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
<div class="wip-review" x-data="{
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
            this.clearFit();
            this.refresh();
        },
        go(i) {
            this.current = Math.max(0, Math.min(this.total - 1, i));
            this.refresh();
        },
        refresh() {
            this.$nextTick(() => {
                if (window.Chart) { Object.values(Chart.instances).forEach(c => c.resize()); }
                requestAnimationFrame(() => this.fit());
            });
        },
        slides() {
            return Array.from(this.$refs.deck.children).filter(el => el.tagName === 'SECTION');
        },
        clearFit() {
            this.slides().forEach(s => { s.style.transform = ''; s.style.transformOrigin = ''; });
        },
        fit() {
            if (! this.presenting) return;
            const deck = this.$refs.deck;
            const slide = this.slides().find(s => s.style.display !== 'none');
            if (! slide) return;
            slide.style.transform = '';
            const cs = getComputedStyle(deck);
            const availW = deck.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
            const availH = deck.clientHeight - parseFloat(cs.paddingTop) - parseFloat(cs.paddingBottom);
            const w = Math.max(slide.scrollWidth, slide.offsetWidth);
            const h = Math.max(slide.scrollHeight, slide.offsetHeight);
            const scale = Math.min(1, availW / w, availH / h);
            const shift = Math.max(0, (availW - w * scale) / 2);
            slide.style.transformOrigin = 'top left';
            slide.style.transform = 'translateX(' + shift + 'px) scale(' + scale + ')';
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
     x-on:resize.window.debounce.150ms="refresh()"
     x-init="if (window.Livewire) { Livewire.hook('commit', ({ succeed }) => succeed(() => refresh())); }"
     x-on:fullscreenchange.document="if (! document.fullscreenElement && presenting) { presenting = false; clearFit(); refresh(); }">

    @once
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    @endonce

    {{-- Presenting, every slide is scaled to fit the screen (see fit()), so a
         table must show its full width to be measured and shrunk with the
         rest rather than scrolling inside a slide nobody can scroll. --}}
    <style>
        .wip-presenting .overflow-x-auto { overflow: visible !important; }
    </style>

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

            {{-- The whole report as one PDF, with exactly the choices above. --}}
            <x-download-link :href="$pdfUrl" class="btn-secondary" title="Download the whole {{ $monthly ? 'monthly' : 'weekly' }} report as a PDF">
                Download PDF
            </x-download-link>
            <button type="button" x-on:click="start()" class="btn-primary">Present</button>
        </div>
    </div>

    <div x-ref="deck" wire:loading.class="opacity-60"
         :class="presenting ? 'wip-presenting fixed inset-0 z-[100] bg-gray-50 flex flex-col px-6 pt-6 pb-20 sm:px-10 sm:pt-8 overflow-hidden' : ''">

        {{-- ── At a glance ─────────────────────────────────────────────── --}}
        <section class="card p-5 mb-6" x-show="! presenting || current === {{ $idx['glance'] }}" :class="presenting && '!mb-0 shrink-0'">
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
                        <div class="stat rounded-surface border border-gray-200 border-t-4 border-t-brand-600 bg-white shadow-e1 p-4" wire:key="kpi-{{ $k['key'] }}">
                            <p class="stat-label">{{ $k['label'] }}</p>
                            <p class="stat-value tabular-nums" :class="presenting && 'text-3xl'">{{ $fmt($k['current'], $k['format']) }}</p>
                            <p class="text-xs mt-1">
                                <span class="font-semibold {{ $dClass }}">{{ $dText }}</span>
                                <span class="text-gray-600">vs {{ $fmt($k['previous'], $k['format']) }} last {{ $unit }}</span>
                            </p>
                            @if ($k['share'] !== null)
                                @php [$sText, $sClass] = $delta($k['share']['change'], $k['up_is_good'], true); @endphp
                                <p class="text-xs mt-1 pt-1 border-t border-gray-100">
                                    <span class="font-semibold text-gray-900 tabular-nums">{{ $pct($k['share']['current']) }}</span>
                                    <span class="text-gray-600">of sales</span>
                                    <span class="font-semibold {{ $sClass }}">{{ $sText }}</span>
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
                @unless ($canPay)
                    <p class="mt-3 text-[11px] text-gray-500">Overtime cost and labour cost are shown only to users who can see pay.</p>
                @endunless
            @endif
        </section>

        {{-- ── Sales performance (weekly) ──────────────────────────────── --}}
        @if ($sp !== null)
            <section class="card p-5 mb-6" x-show="! presenting || current === {{ $idx['sales'] }}" :class="presenting && '!mb-0 shrink-0'">
                @include('livewire.reports.management.partials.wip-slide-head', [
                    'n' => $idx['sales'] + 1, 'title' => $slides['sales'],
                    'hint' => 'by day and meal period — ' . $sp['current']['label'] . ' against ' . $sp['previous']['label'],
                ])

                @if ($sp['current']['total'] <= 0 && $sp['previous']['total'] <= 0)
                    <p class="py-8 text-center text-sm text-gray-600">No sales recorded for these two weeks.</p>
                @else
                    @php $varRows = array_merge($sp['variance']['days'], [$sp['variance']['total']]); @endphp
                    <div class="overflow-x-auto">
                        <table class="table-surface min-w-full text-sm">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left">Day</th>
                                    @foreach ($sp['day_names'] as $dn)
                                        <th class="px-3 py-2 text-right">{{ $dn }}</th>
                                    @endforeach
                                    <th class="px-3 py-2 text-right">Total</th>
                                    <th class="px-3 py-2 text-right">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (['current', 'previous'] as $which)
                                    @php $wk = $sp[$which]; @endphp
                                    @foreach ($wk['lines'] as $l)
                                        <tr wire:key="sp-{{ $which }}-{{ $l['key'] }}">
                                            <td class="px-3 py-2 whitespace-nowrap wip-label">{{ $l['label'] }}</td>
                                            @foreach ($l['days'] as $v)
                                                <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($v, 2) }}</td>
                                            @endforeach
                                            <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap font-semibold">{{ number_format($l['total'], 2) }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap italic text-gray-600">{{ $pct($l['share']) }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="wip-total-row" wire:key="sp-{{ $which }}-total">
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            {{ $wk['label'] }}
                                            <span class="block text-[11px] font-normal text-gray-500">{{ $wk['range'] }}</span>
                                        </td>
                                        @foreach ($wk['days'] as $v)
                                            <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($v, 2) }}</td>
                                        @endforeach
                                        <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($wk['total'], 2) }}</td>
                                        <td class="px-3 py-2"></td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td class="px-3 py-2 whitespace-nowrap wip-label">Var. RM</td>
                                    @foreach ($varRows as $v)
                                        @php
                                            $up = $v['amount'] > 0.005;
                                            $down = $v['amount'] < -0.005;
                                        @endphp
                                        <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap font-semibold {{ $up ? 'text-success-700' : ($down ? 'text-danger-600' : 'text-gray-600') }}">
                                            {{ $up ? '▲ ' : ($down ? '▼ ' : '') }}{{ number_format(abs($v['amount']), 2) }}
                                        </td>
                                    @endforeach
                                    <td class="px-3 py-2"></td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2 whitespace-nowrap wip-label">Var. %</td>
                                    @foreach ($varRows as $v)
                                        @php [$vt, $vc] = $delta($v['change'], true); @endphp
                                        <td class="px-3 py-2 text-right text-xs font-semibold whitespace-nowrap {{ $vc }}">{{ $vt }}</td>
                                    @endforeach
                                    <td class="px-3 py-2"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endif

                <p class="mt-2 text-[11px] text-gray-500">
                    Meal period as keyed on each sales record (none = All Day).
                </p>
            </section>

            {{-- ── Month to date (weekly) ──────────────────────────────────── --}}
            <section class="card p-5 mb-6" x-show="! presenting || current === {{ $idx['mtd'] }}" :class="presenting && '!mb-0 shrink-0'">
                @include('livewire.reports.management.partials.wip-slide-head', [
                    'n' => $idx['mtd'] + 1, 'title' => $slides['mtd'],
                    'hint' => 'to ' . $sp['current']['label'] . '\'s Sunday, against the same days of last month and last year',
                ])
                <div class="overflow-x-auto">
                    <table class="table-surface min-w-full text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left">Period</th>
                                <th class="px-3 py-2 text-left">Dates</th>
                                <th class="px-3 py-2 text-right">Sales</th>
                                <th class="px-3 py-2 text-right">Covers</th>
                                <th class="px-3 py-2 text-right">Avg check</th>
                                <th class="px-3 py-2 text-right">Sales vs this month</th>
                                <th class="px-3 py-2 text-right">Covers vs this month</th>
                                <th class="px-3 py-2 text-right">Variance (RM)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sp['mtd'] as $m)
                                <tr wire:key="mtd-{{ $m['key'] }}" class="{{ $m['key'] === 'this_month' ? 'wip-emph' : '' }}">
                                    <td class="px-3 py-2 whitespace-nowrap wip-label">{{ $m['label'] }}</td>
                                    <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $m['range'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($m['sales'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($m['covers']) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ $m['avg_check'] === null ? '—' : number_format($m['avg_check'], 2) }}</td>
                                    @if ($m['key'] === 'this_month')
                                        <td class="px-3 py-2"></td><td class="px-3 py-2"></td><td class="px-3 py-2"></td>
                                    @else
                                        @php
                                            [$st, $sc] = $delta($m['sales_change'], true);
                                            [$ct, $cc] = $delta($m['covers_change'], true);
                                            $up = $m['variance'] > 0.005;
                                            $down = $m['variance'] < -0.005;
                                        @endphp
                                        <td class="px-3 py-2 text-right text-xs font-semibold whitespace-nowrap {{ $sc }}">{{ $st }}</td>
                                        <td class="px-3 py-2 text-right text-xs font-semibold whitespace-nowrap {{ $cc }}">{{ $ct }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap font-semibold {{ $up ? 'text-success-700' : ($down ? 'text-danger-600' : 'text-gray-600') }}">
                                            {{ $up ? '▲ ' : ($down ? '▼ ' : '') }}{{ number_format(abs($m['variance']), 2) }}
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-[11px] text-gray-500">
                    Covers are the pax recorded with sales. Each month-to-date comparison stops on the same day of its own month.
                </p>

                @php
                    $fc = $sp['forecast'];
                    $paren = fn ($v) => $v === null ? '—' : ($v < 0 ? '(' . number_format(abs($v), 2) . ')' : number_format($v, 2));
                    $forecastRows = [
                        ['Days in month', number_format($fc['days_in_month']), ''],
                        ['Month to date (days)', number_format($fc['mtd_days']), ''],
                        ['No. of days left', number_format($fc['days_left']), ''],
                        ['Monthly target', $fc['target'] === null ? 'Not set' : number_format($fc['target'], 2), $fc['target'] === null ? 'text-gray-500' : ''],
                        ['Month-to-date sales', number_format($fc['mtd_sales'], 2), ''],
                        ['Balance to reach target', $paren($fc['balance']), $fc['balance'] !== null && $fc['balance'] < 0 ? 'text-danger-600' : ''],
                        ['Forecast avg daily sales', number_format($fc['avg_daily'], 2), ''],
                        ['Forecast sales for remaining days', number_format($fc['remaining'], 2), ''],
                        ['Forecast monthly sales', number_format($fc['forecast'], 2), 'font-bold text-gray-900'],
                        ['Forecast vs target', $pct($fc['forecast_vs_target']),
                            $fc['forecast_vs_target'] === null ? '' : ($fc['forecast_vs_target'] >= 100 ? 'text-success-700 font-semibold' : 'text-danger-600 font-semibold')],
                        ['Needed per day to reach target', $fc['needed_daily'] === null ? '—' : number_format($fc['needed_daily'], 2), ''],
                    ];
                @endphp
            </section>

            {{-- ── Sales forecast (weekly) ─────────────────────────────────── --}}
            <section class="card p-5 mb-6" x-show="! presenting || current === {{ $idx['forecast'] }}" :class="presenting && '!mb-0 shrink-0'">
                @include('livewire.reports.management.partials.wip-slide-head', [
                    'n' => $idx['forecast'] + 1, 'title' => $slides['forecast'],
                    'hint' => 'month-to-date daily average carried over the days left',
                ])
                <div class="overflow-x-auto max-w-2xl">

                    <table class="table-surface min-w-full text-sm">
                        <tbody>
                            @foreach ($forecastRows as [$label, $value, $class])
                                <tr wire:key="forecast-{{ $loop->index }}" class="{{ $label === 'Forecast monthly sales' ? 'wip-emph' : '' }}">
                                    <td class="px-3 py-2 whitespace-nowrap wip-label uppercase tracking-wide text-xs">{{ $label }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap {{ $class }}">{{ $value }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-[11px] text-gray-500">
                    Forecast = month-to-date sales plus the month-to-date daily average over the days left.
                    @switch($fc['target_source'])
                        @case('outlet') Target is this outlet's, from Settings &gt; Sales Targets. @break
                        @case('company') Target is the company-wide one from Settings &gt; Sales Targets. @break
                        @case('outlets') Target adds up {{ $fc['target_count'] }} outlet target(s) — no company-wide target is set. @break
                        @default No sales target is set for {{ $fc['month_label'] }} — add one in Settings &gt; Sales Targets.
                    @endswitch
                </p>
            </section>
        @endif

        {{-- ── Sales vs purchases trend ────────────────────────────────── --}}
        <section class="card p-5 mb-6" x-show="! presenting || current === {{ $idx['trend'] }}" :class="presenting && '!mb-0 shrink-0'">
            @include('livewire.reports.management.partials.wip-slide-head', ['n' => $idx['trend'] + 1, 'title' => $slides['trend'], 'hint' => 'click a ' . $unit . ' to review it'])

            <div class="relative h-72" :class="presenting && '!h-[38vh]'"
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
                // [label, total key, format, % of sales key shown under the amount]
                $trendRows = [
                    ['Sales', 'sales', 'money', null],
                    ['Purchases', 'purchases', 'money', 'cost_pct'],
                    ['Wastage', 'wastage', 'money', 'wastage_pct'],
                    ['Staff meals', 'staff_meal', 'money', 'staff_meal_pct'],
                    ['Stock transfers', 'transfers', 'money', null],
                    ['Overtime hours', 'ot_hours', 'hours', null],
                ];
                if ($canPay) {
                    $trendRows[] = ['Overtime cost', 'ot_cost', 'money', 'ot_cost_pct'];
                }
                if ($labour !== null) {
                    $trendRows[] = ['Labour cost', 'labour_cost', 'money', 'labour_pct'];
                }
            @endphp
            <div class="overflow-x-auto mt-5">
                <table class="table-surface min-w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left">{{ ucfirst($unit) }}</th>
                            @foreach ($report['periods'] as $p)
                                <th class="px-3 py-2 text-right whitespace-nowrap {{ $loop->last ? 'wip-current' : '' }}">{{ $p['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($trendRows as [$label, $key, $format, $pctKey])
                            <tr>
                                <td class="px-3 py-2 whitespace-nowrap wip-label">
                                    {{ $label }}
                                    @if ($pctKey)
                                        <span class="block text-[11px] font-normal text-gray-500">RM · % of sales</span>
                                    @endif
                                </td>
                                @foreach ($report['totals'][$key] as $i => $v)
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap {{ $loop->last ? 'font-semibold text-gray-900' : 'text-gray-700' }}">
                                        @if ($format === 'hours') {{ number_format((float) $v, 1) }} @else {{ number_format((float) $v, 2) }} @endif
                                        @if ($pctKey)
                                            <span class="block text-[11px] font-normal text-gray-500">{{ $pct($report['totals'][$pctKey][$i]) }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- ── By department ───────────────────────────────────────────── --}}
        <section class="card p-5 mb-6" x-show="! presenting || current === {{ $idx['departments'] }}" :class="presenting && '!mb-0 shrink-0'">
            @include('livewire.reports.management.partials.wip-slide-head', ['n' => $idx['departments'] + 1, 'title' => $slides['departments'], 'hint' => 'this ' . $unit . ', as % of each department\'s own sales'])

            @if ($report['departments'] === [])
                <p class="py-8 text-center text-sm text-gray-600">No department figures for these two {{ $unit }}s.</p>
            @else
                {{-- Two charts, each on its own scale: wastage runs at 1–2% and
                     purchase cost at 30–50%, so on one axis the wastage bars
                     were slivers nobody could read. --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-600">Purchase cost % of sales</h3>
                <div class="relative" style="height: {{ max(180, count($report['charts']['departments']['labels']) * 48 + 60) }}px"
                     :class="presenting && '!h-[32vh]'"
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
                                        { label: 'This ' + d.unit, data: d.cost_pct, backgroundColor: d.colors.purchases, borderRadius: 3 },
                                        { label: 'Last ' + d.unit, data: d.cost_pct_prev, backgroundColor: d.colors.previous, borderRadius: 3 },
                                    ],
                                },
                                options: {
                                    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                                    plugins: {
                                        legend: { position: 'bottom' },
                                        tooltip: { callbacks: {
                                            label: i => i.dataset.label + ': ' + (i.parsed.x === null ? '—' : i.parsed.x.toFixed(1) + '%'),
                                            afterBody: items => {
                                                const k = items[0].dataIndex;
                                                return ['Purchases: ' + rm(d.purchases[k]), 'Sales: ' + rm(d.sales[k])];
                                            },
                                        } },
                                    },
                                    scales: { x: { beginAtZero: true, ticks: { callback: v => v + '%' } }, y: { grid: { display: false } } },
                                },
                            });
                        },
                     }">
                    <canvas x-ref="c"></canvas>
                </div>

                    </div>
                    <div>
                        <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-600">Wastage % of sales</h3>
                        <div class="relative" style="height: {{ max(180, count($report['charts']['departments']['labels']) * 48 + 60) }}px"
                             :class="presenting && '!h-[32vh]'"
                             wire:key="wip-dept-waste-{{ md5(json_encode($report['charts']['departments'])) }}"
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
                                                { label: 'This ' + d.unit, data: d.wastage_pct, backgroundColor: d.colors.wastage, borderRadius: 3 },
                                                { label: 'Last ' + d.unit, data: d.wastage_pct_prev, backgroundColor: d.colors.previous, borderRadius: 3 },
                                            ],
                                        },
                                        options: {
                                            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                                            plugins: {
                                                legend: { position: 'bottom' },
                                                tooltip: { callbacks: {
                                                    label: i => i.dataset.label + ': ' + (i.parsed.x === null ? '—' : i.parsed.x.toFixed(1) + '%'),
                                                    afterBody: items => {
                                                        const k = items[0].dataIndex;
                                                        return ['Wastage: ' + rm(d.wastage[k]), 'Sales: ' + rm(d.sales[k])];
                                                    },
                                                } },
                                            },
                                            scales: { x: { beginAtZero: true, ticks: { callback: v => v + '%' } }, y: { grid: { display: false } } },
                                        },
                                    });
                                },
                             }">
                            <canvas x-ref="c"></canvas>
                        </div>
                    </div>
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
                                    <td class="px-3 py-2 font-medium text-gray-800 whitespace-nowrap">
                                        {{ $d['name'] }}
                                        @if (! empty($d['shared_with']))
                                            <span class="block text-[11px] font-normal text-gray-500">sales shared with {{ implode(', ', $d['shared_with']) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($d['sales']['current'], 2) }}</td>
                                    <td class="px-3 py-2 text-right text-xs font-semibold whitespace-nowrap {{ $s[1] }}">{{ $s[0] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($d['purchases']['current'], 2) }}</td>
                                    <td class="px-3 py-2 text-right text-xs font-semibold whitespace-nowrap {{ $p[1] }}">{{ $p[0] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ $pct($d['cost_pct']['current']) }}</td>
                                    <td class="px-3 py-2 text-right text-xs font-semibold whitespace-nowrap {{ $c[1] }}">{{ $c[0] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">
                                        {{ number_format($d['wastage']['current'], 2) }}
                                        <span class="block text-[11px] text-gray-500">{{ $pct($d['wastage_pct']['current']) }} of sales</span>
                                    </td>
                                    <td class="px-3 py-2 text-right text-xs font-semibold whitespace-nowrap {{ $w[1] }}">{{ $w[0] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-[11px] text-gray-500">
                    @if ($report['charts']['departments']['left_out'] !== [])
                        Not charted, having no sales to measure against: {{ implode(', ', $report['charts']['departments']['left_out']) }}.
                    @endif
                    Sales reach a department through its sales category (Settings &gt; Departments). Sales no department claims, and
                    purchases or wastage keyed without a department, are shown as Unassigned. Departments sharing a sales
                    category are each measured against all of its sales, so department sales can add up to more than total sales.
                </p>
            @endif
        </section>

        {{-- ── Wastage ─────────────────────────────────────────────────── --}}
        <section class="card p-5 mb-6" x-show="! presenting || current === {{ $idx['wastage'] }}" :class="presenting && '!mb-0 shrink-0'">
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
                                    <td class="px-3 py-2 font-medium text-gray-800">
                                        {{ $d['name'] }}
                                        @if (! empty($d['shared_with']))
                                            <span class="block text-[11px] font-normal text-gray-500">sales shared with {{ implode(', ', $d['shared_with']) }}</span>
                                        @endif
                                    </td>
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
        <section class="card p-5 mb-6" x-show="! presenting || current === {{ $idx['staff_meals'] }}" :class="presenting && '!mb-0 shrink-0'">
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
                                <th class="px-3 py-2 text-right">% of outlet sales</th>
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
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $pct($o['staff_meal_pct']['current']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- ── Stock transfers ─────────────────────────────────────────── --}}
        <section class="card p-5 mb-6" x-show="! presenting || current === {{ $idx['transfers'] }}" :class="presenting && '!mb-0 shrink-0'">
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
        <section class="card p-5 mb-6" x-show="! presenting || current === {{ $idx['overtime'] }}" :class="presenting && '!mb-0 shrink-0'">
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

            {{-- Side by side on a wide screen: stacked under the chart, the two
                 tables made this the tallest slide and it presented smallest. --}}
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-x-6">
            <div class="min-w-0">
            @if ($report['overtime']['sections'] !== [])
                @php $peakHours = max(0.01, collect($report['overtime']['sections'])->max(fn ($s) => $s['ot_hours']['current'])); @endphp
                <h3 class="mt-5 mb-2 text-xs font-semibold uppercase tracking-wide text-gray-600">By section</h3>
                <div class="overflow-x-auto">
                    <table class="table-surface min-w-full text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left">Section</th>
                                <th class="px-3 py-2 text-left w-1/3">Hours this {{ $unit }}</th>
                                <th class="px-3 py-2 text-right">Hours</th>
                                <th class="px-3 py-2 text-right">{{ $vsLast }}</th>
                                @if ($canPay)
                                    <th class="px-3 py-2 text-right">Cost</th>
                                    <th class="px-3 py-2 text-right">{{ $vsLast }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report['overtime']['sections'] as $s)
                                @php
                                    $h = $delta($s['ot_hours']['change'], false);
                                    $c = $canPay ? $delta($s['ot_cost']['change'], false) : null;
                                @endphp
                                <tr wire:key="ot-section-{{ $loop->index }}-{{ $s['name'] }}">
                                    <td class="px-3 py-2 font-medium text-gray-800 whitespace-nowrap">{{ $s['name'] }}</td>
                                    <td class="px-3 py-2">
                                        <div class="h-2 rounded-full bg-gray-100 min-w-[80px]">
                                            <div class="h-2 rounded-full" style="width: {{ round($s['ot_hours']['current'] / $peakHours * 100, 1) }}%; background: {{ $report['charts']['overtime']['color'] }};"></div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($s['ot_hours']['current'], 1) }}</td>
                                    <td class="px-3 py-2 text-right text-xs font-semibold whitespace-nowrap {{ $h[1] }}">{{ $h[0] }}</td>
                                    @if ($canPay)
                                        <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($s['ot_cost']['current'], 2) }}</td>
                                        <td class="px-3 py-2 text-right text-xs font-semibold whitespace-nowrap {{ $c[1] }}">{{ $c[0] }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-[11px] text-gray-500">Each claim counts under the employee's current section.</p>
            @endif
            </div>
            <div class="min-w-0">

            @php
                $otRows = array_values(array_filter($report['outlets'], fn ($o) =>
                    $o['ot_hours']['current'] > 0 || $o['ot_hours']['previous'] > 0
                    || ($canPay && ($o['ot_cost']['current'] > 0 || $o['ot_cost']['previous'] > 0))));
            @endphp
            @if ($otRows !== [])
                <h3 class="mt-5 mb-2 text-xs font-semibold uppercase tracking-wide text-gray-600">By outlet</h3>
                <div class="overflow-x-auto">
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
                                        <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">
                                            {{ number_format($o['ot_cost']['current'], 2) }}
                                            <span class="block text-[11px] text-gray-500">{{ $pct($o['ot_cost_pct']['current']) }} of sales</span>
                                        </td>
                                        <td class="px-3 py-2 text-right text-xs font-semibold whitespace-nowrap {{ $c[1] }}">{{ $c[0] }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            </div>
            </div>
        </section>

        {{-- ── Labour cost (monthly, pay viewers only) ─────────────────── --}}
        @if ($labour !== null)
            <section class="card p-5 mb-6" x-show="! presenting || current === {{ $idx['labour'] }}" :class="presenting && '!mb-0 shrink-0'">
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
                        @php $labourPct = collect($report['kpis'])->firstWhere('key', 'labour_cost')['share'] ?? null; @endphp
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
                                    <tr wire:key="labour-{{ $r['key'] }}" class="{{ $r['key'] === 'employer_cost' ? 'wip-emph' : '' }}">
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
        <section class="card p-5 mb-6" x-show="! presenting || current === {{ $idx['outlets'] }}" :class="presenting && '!mb-0 shrink-0'">
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
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">
                                        {{ number_format($o['wastage']['current'], 2) }}
                                        <span class="block text-[11px] text-gray-500">{{ $pct($o['wastage_pct']['current']) }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">
                                        {{ number_format($o['staff_meal']['current'], 2) }}
                                        <span class="block text-[11px] text-gray-500">{{ $pct($o['staff_meal_pct']['current']) }}</span>
                                    </td>
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
        <div x-show="presenting" x-cloak class="absolute inset-x-0 bottom-0 px-6 sm:px-10 py-3 flex items-center justify-between gap-3 text-sm text-gray-600 bg-gray-50 border-t border-gray-200">
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
