@assets
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
@endassets

<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-gray-700">Reports</h2>
        @if (!empty($summary['categories']))
            <div class="flex items-center gap-2">
                <button wire:click="exportPdf"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Export PDF
                </button>
                @if ($activeTab === 'cost_summary')
                    <button wire:click="exportCsv"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        CSV
                    </button>
                @endif
            </div>
        @endif
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
        <div class="flex flex-wrap items-center gap-4">
            @if ($activeTab === 'cost_summary')
                {{-- Mode toggle (cost summary only) --}}
                <div class="inline-flex rounded-lg border border-gray-200 p-0.5 bg-gray-50">
                    <button wire:click="$set('mode', 'monthly')"
                            class="px-3 py-1.5 text-sm font-medium rounded-md transition {{ $mode === 'monthly' ? 'bg-white shadow-sm text-indigo-600' : 'text-gray-500 hover:text-gray-700' }}">
                        Monthly
                    </button>
                    <button wire:click="$set('mode', 'weekly')"
                            class="px-3 py-1.5 text-sm font-medium rounded-md transition {{ $mode === 'weekly' ? 'bg-white shadow-sm text-indigo-600' : 'text-gray-500 hover:text-gray-700' }}">
                        Weekly
                    </button>
                </div>
            @endif

            @if ($mode === 'weekly' && $activeTab === 'cost_summary')
                <div class="flex items-center gap-2">
                    <button wire:click="previousWeek" class="p-2 rounded-lg hover:bg-gray-100 text-gray-500 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <input type="date" wire:model.live="weekStart"
                           class="rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <button wire:click="nextWeek" class="p-2 rounded-lg hover:bg-gray-100 text-gray-500 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
                <span class="text-sm text-gray-500">{{ $periodLabel }}</span>
            @else
                <div class="flex items-center gap-2">
                    <button wire:click="previousMonth" class="p-2 rounded-lg hover:bg-gray-100 text-gray-500 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <input type="month" wire:model.live="period"
                           class="rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <button wire:click="nextMonth" class="p-2 rounded-lg hover:bg-gray-100 text-gray-500 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            @endif

            <div class="h-6 w-px bg-gray-200"></div>

            {{-- Outlet --}}
            <select wire:model.live="outletId" class="rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">All Outlets</option>
                @foreach ($outlets as $outlet)
                    <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                @endforeach
            </select>

            @if ($mode === 'monthly')
                <div class="h-6 w-px bg-gray-200"></div>

                {{-- MTD Comparison toggle --}}
                <button wire:click="toggleCompare"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg transition {{ $compareMode ? 'bg-indigo-100 text-indigo-700 ring-1 ring-indigo-300' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border border-gray-200' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    MTD Comparison
                </button>

                @if ($compareMode)
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs text-gray-500">till</span>
                        <input type="date" wire:model.live="compareTillDate"
                               class="rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500 py-1.5">
                    </div>
                @endif
            @endif
        </div>

        @if ($mode === 'weekly' && $activeTab === 'cost_summary')
            <p class="mt-2 text-xs text-gray-400">Weekly view shows revenue, purchases, and transfers only. Stock take values (opening/closing) are excluded as they are monthly.</p>
        @endif
    </div>

    {{-- Tab Navigation --}}
    <div class="flex gap-1 mb-6 bg-gray-100 rounded-lg p-1 w-fit">
        @php
            $tabs = [
                'cost_summary'  => 'Cost Summary',
                'performance'   => 'Performance',
                'cost_analysis' => 'Cost Analysis',
                'wastage'       => 'Wastage',
            ];
        @endphp
        @foreach ($tabs as $key => $label)
            <button wire:click="switchTab('{{ $key }}')"
                    class="px-4 py-2 text-sm font-medium rounded-md transition {{ $activeTab === $key ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- TAB: Cost Summary                                          --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    @if ($activeTab === 'cost_summary')
        {{-- MTD Comparison Panel --}}
        @if ($compareMode && !empty($comparisonData))
            @php
                $cur = $comparisonData['current'] ?? null;
                $prev = $comparisonData['prev_month'] ?? null;
                $ly = $comparisonData['prev_year'] ?? null;
                $varPrev = $comparisonData['var_vs_prev'] ?? [];
                $varLy = $comparisonData['var_vs_ly'] ?? [];
            @endphp
            @if ($cur && $prev && $ly)
                <div class="bg-white rounded-xl shadow-sm border border-indigo-100 p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            MTD Period Comparison
                        </h3>
                        <div class="flex items-center gap-3 text-xs text-gray-400">
                            <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-indigo-500"></span> {{ $cur['period_label'] }}</span>
                            <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-gray-400"></span> {{ $prev['period_label'] }}</span>
                            <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-500"></span> {{ $ly['period_label'] }}</span>
                        </div>
                    </div>

                    {{-- Period date ranges --}}
                    <div class="grid grid-cols-3 gap-4 mb-5">
                        <div class="text-center p-3 bg-indigo-50 rounded-lg">
                            <div class="text-xs font-semibold text-indigo-600 uppercase tracking-wide">This Month MTD</div>
                            <div class="text-xs text-gray-500 mt-0.5">{{ $cur['label'] }}</div>
                        </div>
                        <div class="text-center p-3 bg-gray-50 rounded-lg">
                            <div class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Last Month MTD</div>
                            <div class="text-xs text-gray-500 mt-0.5">{{ $prev['label'] }}</div>
                        </div>
                        <div class="text-center p-3 bg-amber-50 rounded-lg">
                            <div class="text-xs font-semibold text-amber-700 uppercase tracking-wide">Last Year MTD</div>
                            <div class="text-xs text-gray-500 mt-0.5">{{ $ly['label'] }}</div>
                        </div>
                    </div>

                    {{-- Comparison KPI Cards --}}
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
                        {{-- Revenue --}}
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-xs font-medium text-gray-500 mb-2">Revenue</div>
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-indigo-600 font-medium">This Month</span>
                                    <span class="text-sm font-bold text-gray-900">{{ number_format($cur['summary']['totals']['revenue'], 0) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-gray-500">Last Month</span>
                                    <span class="text-sm font-medium text-gray-600">{{ number_format($prev['summary']['totals']['revenue'], 0) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-amber-600">Last Year</span>
                                    <span class="text-sm font-medium text-gray-600">{{ number_format($ly['summary']['totals']['revenue'], 0) }}</span>
                                </div>
                            </div>
                            <div class="mt-2 pt-2 border-t border-gray-200 flex gap-3 text-xs">
                                <span class="{{ $varPrev['revenue'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    vs LM: {{ $varPrev['revenue'] >= 0 ? '+' : '' }}{{ $varPrev['revenue'] }}%
                                </span>
                                <span class="{{ $varLy['revenue'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    vs LY: {{ $varLy['revenue'] >= 0 ? '+' : '' }}{{ $varLy['revenue'] }}%
                                </span>
                            </div>
                        </div>

                        {{-- COGS --}}
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-xs font-medium text-gray-500 mb-2">COGS</div>
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-indigo-600 font-medium">This Month</span>
                                    <span class="text-sm font-bold text-gray-900">{{ number_format($cur['summary']['totals']['cogs'], 0) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-gray-500">Last Month</span>
                                    <span class="text-sm font-medium text-gray-600">{{ number_format($prev['summary']['totals']['cogs'], 0) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-amber-600">Last Year</span>
                                    <span class="text-sm font-medium text-gray-600">{{ number_format($ly['summary']['totals']['cogs'], 0) }}</span>
                                </div>
                            </div>
                            <div class="mt-2 pt-2 border-t border-gray-200 flex gap-3 text-xs">
                                <span class="{{ $varPrev['cogs'] <= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    vs LM: {{ $varPrev['cogs'] >= 0 ? '+' : '' }}{{ $varPrev['cogs'] }}%
                                </span>
                                <span class="{{ $varLy['cogs'] <= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    vs LY: {{ $varLy['cogs'] >= 0 ? '+' : '' }}{{ $varLy['cogs'] }}%
                                </span>
                            </div>
                        </div>

                        {{-- Cost % --}}
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-xs font-medium text-gray-500 mb-2">Cost %</div>
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-indigo-600 font-medium">This Month</span>
                                    <span class="text-sm font-bold {{ $cur['summary']['totals']['cost_pct'] > 35 ? 'text-red-600' : ($cur['summary']['totals']['cost_pct'] > 30 ? 'text-amber-600' : 'text-green-600') }}">
                                        {{ $cur['summary']['totals']['cost_pct'] }}%
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-gray-500">Last Month</span>
                                    <span class="text-sm font-medium {{ $prev['summary']['totals']['cost_pct'] > 35 ? 'text-red-600' : ($prev['summary']['totals']['cost_pct'] > 30 ? 'text-amber-600' : 'text-green-600') }}">
                                        {{ $prev['summary']['totals']['cost_pct'] }}%
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-amber-600">Last Year</span>
                                    <span class="text-sm font-medium {{ $ly['summary']['totals']['cost_pct'] > 35 ? 'text-red-600' : ($ly['summary']['totals']['cost_pct'] > 30 ? 'text-amber-600' : 'text-green-600') }}">
                                        {{ $ly['summary']['totals']['cost_pct'] }}%
                                    </span>
                                </div>
                            </div>
                            @php
                                $costPctDiffPrev = round($cur['summary']['totals']['cost_pct'] - $prev['summary']['totals']['cost_pct'], 1);
                                $costPctDiffLy = round($cur['summary']['totals']['cost_pct'] - $ly['summary']['totals']['cost_pct'], 1);
                            @endphp
                            <div class="mt-2 pt-2 border-t border-gray-200 flex gap-3 text-xs">
                                <span class="{{ $costPctDiffPrev <= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    vs LM: {{ $costPctDiffPrev >= 0 ? '+' : '' }}{{ $costPctDiffPrev }}pp
                                </span>
                                <span class="{{ $costPctDiffLy <= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    vs LY: {{ $costPctDiffLy >= 0 ? '+' : '' }}{{ $costPctDiffLy }}pp
                                </span>
                            </div>
                        </div>

                        {{-- Pax & Avg Check --}}
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-xs font-medium text-gray-500 mb-2">Pax / Avg Check</div>
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-indigo-600 font-medium">This Month</span>
                                    <span class="text-sm font-bold text-gray-900">{{ number_format($cur['pax']) }} <span class="text-gray-400 font-normal">/ {{ number_format($cur['avg_check'], 2) }}</span></span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-gray-500">Last Month</span>
                                    <span class="text-sm font-medium text-gray-600">{{ number_format($prev['pax']) }} <span class="text-gray-400 font-normal">/ {{ number_format($prev['avg_check'], 2) }}</span></span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-amber-600">Last Year</span>
                                    <span class="text-sm font-medium text-gray-600">{{ number_format($ly['pax']) }} <span class="text-gray-400 font-normal">/ {{ number_format($ly['avg_check'], 2) }}</span></span>
                                </div>
                            </div>
                            <div class="mt-2 pt-2 border-t border-gray-200 flex gap-3 text-xs">
                                <span class="{{ $varPrev['pax'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    vs LM: {{ $varPrev['pax'] >= 0 ? '+' : '' }}{{ $varPrev['pax'] }}%
                                </span>
                                <span class="{{ $varLy['pax'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    vs LY: {{ $varLy['pax'] >= 0 ? '+' : '' }}{{ $varLy['pax'] }}%
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Detailed Comparison Table --}}
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200">
                                    <th class="text-left py-2.5 px-4 font-semibold text-gray-600 w-40">Metric</th>
                                    <th class="text-right py-2.5 px-4 font-semibold text-indigo-600 min-w-[120px]">{{ $cur['period_label'] }}</th>
                                    <th class="text-right py-2.5 px-4 font-semibold text-gray-500 min-w-[120px]">{{ $prev['period_label'] }}</th>
                                    <th class="text-right py-2.5 px-4 font-semibold text-gray-400 text-xs min-w-[80px]">vs LM</th>
                                    <th class="text-right py-2.5 px-4 font-semibold text-amber-600 min-w-[120px]">{{ $ly['period_label'] }}</th>
                                    <th class="text-right py-2.5 px-4 font-semibold text-gray-400 text-xs min-w-[80px]">vs LY</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @php
                                    $compRows = [
                                        ['label' => 'Revenue', 'key' => 'revenue', 'format' => 'number', 'good' => 'up'],
                                        ['label' => 'Purchases', 'key' => 'purchases', 'format' => 'number', 'good' => 'down'],
                                        ['label' => 'COGS', 'key' => 'cogs', 'format' => 'number', 'good' => 'down'],
                                        ['label' => 'Cost %', 'key' => 'cost_pct', 'format' => 'pct', 'good' => 'down'],
                                        ['label' => 'Wastage', 'key' => 'wastage', 'format' => 'number', 'good' => 'down'],
                                        ['label' => 'Staff Meals', 'key' => 'staff_meals', 'format' => 'number', 'good' => 'down'],
                                    ];
                                @endphp
                                @foreach ($compRows as $row)
                                    @php
                                        $curVal = $cur['summary']['totals'][$row['key']];
                                        $prevVal = $prev['summary']['totals'][$row['key']];
                                        $lyVal = $ly['summary']['totals'][$row['key']];

                                        if ($row['format'] === 'pct') {
                                            $diffPrev = round($curVal - $prevVal, 1);
                                            $diffLy = round($curVal - $lyVal, 1);
                                            $diffPrevLabel = ($diffPrev >= 0 ? '+' : '') . $diffPrev . 'pp';
                                            $diffLyLabel = ($diffLy >= 0 ? '+' : '') . $diffLy . 'pp';
                                        } else {
                                            $diffPrev = $prevVal > 0 ? round(($curVal - $prevVal) / $prevVal * 100, 1) : 0;
                                            $diffLy = $lyVal > 0 ? round(($curVal - $lyVal) / $lyVal * 100, 1) : 0;
                                            $diffPrevLabel = ($diffPrev >= 0 ? '+' : '') . $diffPrev . '%';
                                            $diffLyLabel = ($diffLy >= 0 ? '+' : '') . $diffLy . '%';
                                        }

                                        $prevGood = $row['good'] === 'up' ? $diffPrev >= 0 : $diffPrev <= 0;
                                        $lyGood = $row['good'] === 'up' ? $diffLy >= 0 : $diffLy <= 0;
                                    @endphp
                                    <tr class="{{ $row['key'] === 'revenue' ? 'bg-blue-50/40' : '' }} {{ $row['key'] === 'cogs' ? 'bg-red-50/30' : '' }}">
                                        <td class="py-2.5 px-4 font-medium text-gray-700">{{ $row['label'] }}</td>
                                        <td class="py-2.5 px-4 text-right font-bold text-gray-900">
                                            {{ $row['format'] === 'pct' ? $curVal . '%' : number_format($curVal, 2) }}
                                        </td>
                                        <td class="py-2.5 px-4 text-right text-gray-600">
                                            {{ $row['format'] === 'pct' ? $prevVal . '%' : number_format($prevVal, 2) }}
                                        </td>
                                        <td class="py-2.5 px-4 text-right text-xs font-medium {{ $prevGood ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $diffPrevLabel }}
                                        </td>
                                        <td class="py-2.5 px-4 text-right text-gray-600">
                                            {{ $row['format'] === 'pct' ? $lyVal . '%' : number_format($lyVal, 2) }}
                                        </td>
                                        <td class="py-2.5 px-4 text-right text-xs font-medium {{ $lyGood ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $diffLyLabel }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Per-Category Comparison --}}
                    @if (!empty($cur['summary']['categories']))
                        <div class="mt-5 pt-5 border-t border-gray-100">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Revenue by Category</h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-xs">
                                    <thead>
                                        <tr class="bg-gray-50 border-b border-gray-100">
                                            <th class="text-left py-2 px-3 font-semibold text-gray-600">Category</th>
                                            <th class="text-right py-2 px-3 font-semibold text-indigo-600">{{ $cur['period_label'] }}</th>
                                            <th class="text-right py-2 px-3 font-semibold text-gray-500">{{ $prev['period_label'] }}</th>
                                            <th class="text-right py-2 px-3 font-semibold text-gray-400">vs LM</th>
                                            <th class="text-right py-2 px-3 font-semibold text-amber-600">{{ $ly['period_label'] }}</th>
                                            <th class="text-right py-2 px-3 font-semibold text-gray-400">vs LY</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        @foreach ($cur['summary']['categories'] as $idx => $cat)
                                            @php
                                                $prevCatRev = $prev['summary']['categories'][$idx]['revenue'] ?? 0;
                                                $lyCatRev = $ly['summary']['categories'][$idx]['revenue'] ?? 0;
                                                $catDiffPrev = $prevCatRev > 0 ? round(($cat['revenue'] - $prevCatRev) / $prevCatRev * 100, 1) : 0;
                                                $catDiffLy = $lyCatRev > 0 ? round(($cat['revenue'] - $lyCatRev) / $lyCatRev * 100, 1) : 0;
                                            @endphp
                                            <tr class="hover:bg-gray-50">
                                                <td class="py-2 px-3 font-medium text-gray-800">
                                                    <span class="inline-block w-2 h-2 rounded-full mr-1.5" style="background:{{ $cat['color'] ?? '#6b7280' }}"></span>
                                                    {{ $cat['name'] }}
                                                </td>
                                                <td class="py-2 px-3 text-right font-bold text-gray-900 tabular-nums">{{ number_format($cat['revenue'], 2) }}</td>
                                                <td class="py-2 px-3 text-right text-gray-600 tabular-nums">{{ number_format($prevCatRev, 2) }}</td>
                                                <td class="py-2 px-3 text-right font-medium {{ $catDiffPrev >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                    {{ $catDiffPrev >= 0 ? '+' : '' }}{{ $catDiffPrev }}%
                                                </td>
                                                <td class="py-2 px-3 text-right text-gray-600 tabular-nums">{{ number_format($lyCatRev, 2) }}</td>
                                                <td class="py-2 px-3 text-right font-medium {{ $catDiffLy >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                    {{ $catDiffLy >= 0 ? '+' : '' }}{{ $catDiffLy }}%
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <p class="mt-4 text-xs text-gray-400">MTD = Month-to-Date. Comparing day 1–{{ $cur['mtd_day'] }} across periods. Stock values excluded in MTD view.</p>
                </div>
            @endif
        @endif

        @if (!empty($summary['categories']))
            {{-- Summary cards --}}
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <div class="text-sm font-medium text-gray-500">Total Revenue</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($summary['totals']['revenue'], 2) }}</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <div class="text-sm font-medium text-gray-500">Total COGS</div>
                    <div class="mt-1 text-2xl font-bold {{ $summary['totals']['cogs'] > 0 ? 'text-red-600' : 'text-gray-900' }}">
                        {{ number_format($summary['totals']['cogs'], 2) }}
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <div class="text-sm font-medium text-gray-500">Overall Cost %</div>
                    <div class="mt-1 text-2xl font-bold {{ $summary['totals']['cost_pct'] > 35 ? 'text-red-600' : ($summary['totals']['cost_pct'] > 30 ? 'text-amber-600' : 'text-green-600') }}">
                        {{ $summary['totals']['cost_pct'] }}%
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <div class="text-sm font-medium text-gray-500">Total Wastage</div>
                    <div class="mt-1 text-2xl font-bold text-orange-600">{{ number_format($summary['totals']['wastage'], 2) }}</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <div class="text-sm font-medium text-gray-500">Staff Meals</div>
                    <div class="mt-1 text-2xl font-bold text-purple-600">{{ number_format($summary['totals']['staff_meals'], 2) }}</div>
                </div>
            </div>

            {{-- P&L Table --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="text-left py-3 px-4 font-semibold text-gray-600 w-48">Metric</th>
                                @foreach ($summary['categories'] as $cat)
                                    <th class="text-right py-3 px-4 font-semibold text-gray-600 min-w-[120px]">
                                        <div class="flex items-center justify-end gap-2">
                                            @if ($cat['color'])
                                                <span class="w-2.5 h-2.5 rounded-full inline-block" style="background:{{ $cat['color'] }}"></span>
                                            @endif
                                            {{ $cat['name'] }}
                                        </div>
                                    </th>
                                @endforeach
                                <th class="text-right py-3 px-4 font-bold text-gray-800 min-w-[120px] bg-gray-100">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr class="bg-blue-50/40">
                                <td class="py-2.5 px-4 font-semibold text-gray-700">Revenue</td>
                                @foreach ($summary['categories'] as $cat)
                                    <td class="py-2.5 px-4 text-right font-medium text-gray-900">{{ number_format($cat['revenue'], 2) }}</td>
                                @endforeach
                                <td class="py-2.5 px-4 text-right font-bold text-gray-900 bg-gray-50">{{ number_format($summary['totals']['revenue'], 2) }}</td>
                            </tr>
                            <tr>
                                <td colspan="{{ count($summary['categories']) + 2 }}" class="py-1 px-4 bg-gray-50">
                                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Cost of Goods</span>
                                </td>
                            </tr>
                            @if ($mode === 'monthly')
                                <tr>
                                    <td class="py-2.5 px-4 text-gray-600 pl-6">Opening Stock</td>
                                    @foreach ($summary['categories'] as $cat)
                                        <td class="py-2.5 px-4 text-right text-gray-700">{{ number_format($cat['opening_stock'], 2) }}</td>
                                    @endforeach
                                    <td class="py-2.5 px-4 text-right font-medium text-gray-800 bg-gray-50">{{ number_format($summary['totals']['opening_stock'], 2) }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td class="py-2.5 px-4 text-gray-600 pl-6">(+) Purchases</td>
                                @foreach ($summary['categories'] as $cat)
                                    <td class="py-2.5 px-4 text-right text-gray-700">{{ number_format($cat['purchases'], 2) }}</td>
                                @endforeach
                                <td class="py-2.5 px-4 text-right font-medium text-gray-800 bg-gray-50">{{ number_format($summary['totals']['purchases'], 2) }}</td>
                            </tr>
                            @if ($summary['outlet_id'])
                                <tr>
                                    <td class="py-2.5 px-4 text-gray-600 pl-6">(+) Transfer In</td>
                                    @foreach ($summary['categories'] as $cat)
                                        <td class="py-2.5 px-4 text-right text-gray-700">{{ number_format($cat['transfer_in'], 2) }}</td>
                                    @endforeach
                                    <td class="py-2.5 px-4 text-right font-medium text-gray-800 bg-gray-50">{{ number_format($summary['totals']['transfer_in'], 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="py-2.5 px-4 text-gray-600 pl-6">(-) Transfer Out</td>
                                    @foreach ($summary['categories'] as $cat)
                                        <td class="py-2.5 px-4 text-right text-gray-700">{{ number_format($cat['transfer_out'], 2) }}</td>
                                    @endforeach
                                    <td class="py-2.5 px-4 text-right font-medium text-gray-800 bg-gray-50">{{ number_format($summary['totals']['transfer_out'], 2) }}</td>
                                </tr>
                            @endif
                            @if ($mode === 'monthly')
                                <tr>
                                    <td class="py-2.5 px-4 text-gray-600 pl-6">(-) Closing Stock</td>
                                    @foreach ($summary['categories'] as $cat)
                                        <td class="py-2.5 px-4 text-right text-gray-700">{{ number_format($cat['closing_stock'], 2) }}</td>
                                    @endforeach
                                    <td class="py-2.5 px-4 text-right font-medium text-gray-800 bg-gray-50">{{ number_format($summary['totals']['closing_stock'], 2) }}</td>
                                </tr>
                            @endif
                            <tr class="bg-red-50/50 border-t-2 border-gray-300">
                                <td class="py-3 px-4 font-bold text-gray-800">= COGS</td>
                                @foreach ($summary['categories'] as $cat)
                                    <td class="py-3 px-4 text-right font-bold text-gray-900">{{ number_format($cat['cogs'], 2) }}</td>
                                @endforeach
                                <td class="py-3 px-4 text-right font-bold text-gray-900 bg-gray-100">{{ number_format($summary['totals']['cogs'], 2) }}</td>
                            </tr>
                            <tr class="border-t-2 border-gray-300">
                                <td class="py-3 px-4 font-bold text-gray-800">Cost %</td>
                                @foreach ($summary['categories'] as $cat)
                                    <td class="py-3 px-4 text-right font-bold {{ $cat['cost_pct'] > 35 ? 'text-red-600' : ($cat['cost_pct'] > 30 ? 'text-amber-600' : 'text-green-600') }}">
                                        {{ $cat['cost_pct'] }}%
                                    </td>
                                @endforeach
                                <td class="py-3 px-4 text-right font-bold bg-gray-100 {{ $summary['totals']['cost_pct'] > 35 ? 'text-red-600' : ($summary['totals']['cost_pct'] > 30 ? 'text-amber-600' : 'text-green-600') }}">
                                    {{ $summary['totals']['cost_pct'] }}%
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Cost % bars --}}
            <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-sm font-semibold text-gray-600 mb-4">Cost % by Category</h3>
                <div class="space-y-3">
                    @foreach ($summary['categories'] as $cat)
                        <div>
                            <div class="flex items-center justify-between text-sm mb-1">
                                <span class="flex items-center gap-2">
                                    @if ($cat['color'])
                                        <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $cat['color'] }}"></span>
                                    @endif
                                    <span class="font-medium text-gray-700">{{ $cat['name'] }}</span>
                                </span>
                                <span class="font-semibold {{ $cat['cost_pct'] > 35 ? 'text-red-600' : ($cat['cost_pct'] > 30 ? 'text-amber-600' : 'text-green-600') }}">
                                    {{ $cat['cost_pct'] }}%
                                </span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2.5">
                                <div class="h-2.5 rounded-full transition-all duration-500 {{ $cat['cost_pct'] > 35 ? 'bg-red-500' : ($cat['cost_pct'] > 30 ? 'bg-amber-500' : 'bg-green-500') }}"
                                     style="width: {{ min($cat['cost_pct'], 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            @include('livewire.reports._wastage-table', ['detail' => $summary['wastage_detail'] ?? null, 'label' => 'Wastage', 'color' => 'orange'])
            @include('livewire.reports._wastage-table', ['detail' => $summary['staff_meals_detail'] ?? null, 'label' => 'Staff Meals', 'color' => 'purple'])
        @else
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center text-gray-400">
                <p class="font-medium">No data for {{ $periodLabel }}</p>
                <p class="text-sm mt-1">Add cost categories in Settings, then record sales and purchases to see your cost summary.</p>
            </div>
        @endif

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- TAB: Performance                                           --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    @elseif ($activeTab === 'performance')
        @php
            $ds = $dashboardData;
            $hasDashboard = !empty($ds['cost_summary']['categories']);
        @endphp

        @if ($hasDashboard)
            {{-- KPI Cards --}}
            @php
                $totals = $ds['cost_summary']['totals'];
            @endphp
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <div class="text-sm font-medium text-gray-500">Revenue</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($totals['revenue'], 0) }}</div>
                    <div class="mt-1 flex items-center gap-1 text-xs {{ $ds['rev_change'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            @if ($ds['rev_change'] >= 0)
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                            @else
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                            @endif
                        </svg>
                        {{ abs($ds['rev_change']) }}% MoM
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <div class="text-sm font-medium text-gray-500">Total Pax</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($ds['total_pax']) }}</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <div class="text-sm font-medium text-gray-500">Avg Check / Pax</div>
                    <div class="mt-1 text-2xl font-bold text-indigo-600">{{ number_format($ds['avg_check'], 2) }}</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <div class="text-sm font-medium text-gray-500">Cost %</div>
                    <div class="mt-1 text-2xl font-bold {{ $totals['cost_pct'] > 35 ? 'text-red-600' : ($totals['cost_pct'] > 30 ? 'text-amber-600' : 'text-green-600') }}">
                        {{ $totals['cost_pct'] }}%
                    </div>
                </div>
            </div>

            {{-- Charts --}}
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
                <div class="xl:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <h3 class="text-sm font-semibold text-gray-600 mb-3">Daily Revenue & Pax Trend</h3>
                    <div style="height: 280px;" wire:ignore>
                        <canvas id="perfRevenueChart"></canvas>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <h3 class="text-sm font-semibold text-gray-600 mb-3">Day-of-Week Performance</h3>
                    <div style="height: 280px;" wire:ignore>
                        <canvas id="perfDowChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-semibold text-gray-600 mb-3">Average Check per Pax Trend</h3>
                <div style="height: 260px;" wire:ignore>
                    <canvas id="perfAvgChart"></canvas>
                </div>
            </div>
        @else
            @include('livewire.reports._empty-state')
        @endif

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- TAB: Cost Analysis                                         --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    @elseif ($activeTab === 'cost_analysis')
        @php
            $ds = $dashboardData;
            $hasDashboard = !empty($ds['cost_summary']['categories']);
        @endphp

        @if ($hasDashboard)
            @php
                $curTotals = $ds['cost_summary']['totals'];
                $prevTotals = $ds['prev_summary']['totals'];
            @endphp

            {{-- KPI Cards --}}
            <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-6 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                    <div class="text-xs font-medium text-gray-500">Revenue</div>
                    <div class="mt-1 text-xl font-bold text-gray-900">{{ number_format($curTotals['revenue'], 0) }}</div>
                    <div class="mt-1 flex items-center gap-1 text-xs {{ $ds['rev_change'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $ds['rev_change'] >= 0 ? '+' : '' }}{{ $ds['rev_change'] }}% MoM
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                    <div class="text-xs font-medium text-gray-500">COGS</div>
                    <div class="mt-1 text-xl font-bold text-red-600">{{ number_format($curTotals['cogs'], 0) }}</div>
                    <div class="mt-1 text-xs {{ $ds['cogs_change'] <= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $ds['cogs_change'] >= 0 ? '+' : '' }}{{ $ds['cogs_change'] }}% MoM
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                    <div class="text-xs font-medium text-gray-500">Cost %</div>
                    <div class="mt-1 text-xl font-bold {{ $curTotals['cost_pct'] > 35 ? 'text-red-600' : ($curTotals['cost_pct'] > 30 ? 'text-amber-600' : 'text-green-600') }}">
                        {{ $curTotals['cost_pct'] }}%
                    </div>
                    <div class="mt-1 text-xs text-gray-400">Prev: {{ $prevTotals['cost_pct'] }}%</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                    <div class="text-xs font-medium text-gray-500">Wastage</div>
                    <div class="mt-1 text-xl font-bold text-orange-600">{{ number_format($curTotals['wastage'], 0) }}</div>
                    @if ($curTotals['revenue'] > 0)
                        <div class="mt-1 text-xs text-gray-400">{{ round($curTotals['wastage'] / $curTotals['revenue'] * 100, 1) }}% of rev</div>
                    @endif
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                    <div class="text-xs font-medium text-gray-500">Staff Meals</div>
                    <div class="mt-1 text-xl font-bold text-purple-600">{{ number_format($curTotals['staff_meals'], 0) }}</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                    <div class="text-xs font-medium text-gray-500">Avg Check</div>
                    <div class="mt-1 text-xl font-bold text-indigo-600">{{ number_format($ds['avg_check'], 2) }}</div>
                    <div class="mt-1 text-xs text-gray-400">{{ number_format($ds['total_pax']) }} pax</div>
                </div>
            </div>

            {{-- Charts --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <h3 class="text-sm font-semibold text-gray-600 mb-3">Revenue by Category</h3>
                    <div style="height: 240px;" wire:ignore>
                        <canvas id="costRevCatChart"></canvas>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <h3 class="text-sm font-semibold text-gray-600 mb-3">Cost % by Category</h3>
                    <div style="height: 240px;" wire:ignore>
                        <canvas id="costPctChart"></canvas>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <h3 class="text-sm font-semibold text-gray-600 mb-3">Month-over-Month</h3>
                    <div style="height: 240px;" wire:ignore>
                        <canvas id="costMomChart"></canvas>
                    </div>
                </div>
            </div>

            {{-- Category P&L Table --}}
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-600">Category P&L Summary</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="text-left py-2 px-3 font-semibold text-gray-600">Category</th>
                                    <th class="text-right py-2 px-3 font-semibold text-gray-600">Revenue</th>
                                    <th class="text-right py-2 px-3 font-semibold text-gray-600">Purchases</th>
                                    <th class="text-right py-2 px-3 font-semibold text-gray-600">COGS</th>
                                    <th class="text-right py-2 px-3 font-semibold text-gray-600">Cost %</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach ($ds['cost_summary']['categories'] as $cat)
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2 px-3 font-medium text-gray-800">
                                            <span class="inline-block w-2 h-2 rounded-full mr-1.5" style="background:{{ $cat['color'] ?? '#6b7280' }}"></span>
                                            {{ $cat['name'] }}
                                        </td>
                                        <td class="py-2 px-3 text-right text-gray-700 tabular-nums">{{ number_format($cat['revenue'], 2) }}</td>
                                        <td class="py-2 px-3 text-right text-gray-700 tabular-nums">{{ number_format($cat['purchases'], 2) }}</td>
                                        <td class="py-2 px-3 text-right text-gray-700 tabular-nums">{{ number_format($cat['cogs'], 2) }}</td>
                                        <td class="py-2 px-3 text-right font-semibold {{ $cat['cost_pct'] > 35 ? 'text-red-600' : ($cat['cost_pct'] > 30 ? 'text-amber-600' : 'text-green-600') }}">
                                            {{ $cat['cost_pct'] }}%
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                                <tr>
                                    <td class="py-2 px-3 font-bold text-gray-800">Total</td>
                                    <td class="py-2 px-3 text-right font-bold text-gray-800 tabular-nums">{{ number_format($curTotals['revenue'], 2) }}</td>
                                    <td class="py-2 px-3 text-right font-bold text-gray-800 tabular-nums">{{ number_format($curTotals['purchases'], 2) }}</td>
                                    <td class="py-2 px-3 text-right font-bold text-gray-800 tabular-nums">{{ number_format($curTotals['cogs'], 2) }}</td>
                                    <td class="py-2 px-3 text-right font-bold {{ $curTotals['cost_pct'] > 35 ? 'text-red-600' : ($curTotals['cost_pct'] > 30 ? 'text-amber-600' : 'text-green-600') }}">
                                        {{ $curTotals['cost_pct'] }}%
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {{-- Inventory Position --}}
                @php
                    $hasStock = false;
                    foreach ($ds['cost_summary']['categories'] as $cat) {
                        if ($cat['opening_stock'] > 0 || $cat['closing_stock'] > 0) { $hasStock = true; break; }
                    }
                @endphp
                @if ($hasStock)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-5 py-3 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-600">Inventory Position</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-xs">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="text-left py-2 px-3 font-semibold text-gray-600">Category</th>
                                        <th class="text-right py-2 px-3 font-semibold text-gray-600">Opening</th>
                                        <th class="text-right py-2 px-3 font-semibold text-gray-600">Closing</th>
                                        <th class="text-right py-2 px-3 font-semibold text-gray-600">Change</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @foreach ($ds['cost_summary']['categories'] as $cat)
                                        @if ($cat['opening_stock'] > 0 || $cat['closing_stock'] > 0)
                                            @php $stockChange = $cat['closing_stock'] - $cat['opening_stock']; @endphp
                                            <tr class="hover:bg-gray-50">
                                                <td class="py-2 px-3 font-medium text-gray-800">{{ $cat['name'] }}</td>
                                                <td class="py-2 px-3 text-right tabular-nums">{{ number_format($cat['opening_stock'], 2) }}</td>
                                                <td class="py-2 px-3 text-right tabular-nums">{{ number_format($cat['closing_stock'], 2) }}</td>
                                                <td class="py-2 px-3 text-right font-medium tabular-nums {{ $stockChange >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                    {{ $stockChange >= 0 ? '+' : '' }}{{ number_format($stockChange, 2) }}
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        @else
            @include('livewire.reports._empty-state')
        @endif

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- TAB: Wastage                                               --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    @elseif ($activeTab === 'wastage')
        @php
            $ds = $dashboardData;
            $hasDashboard = !empty($ds['cost_summary']['categories']);
        @endphp

        @if ($hasDashboard)
            @php
                $totals = $ds['cost_summary']['totals'];
                $wastPct = $totals['revenue'] > 0 ? round($totals['wastage'] / $totals['revenue'] * 100, 1) : 0;
                $smPct = $totals['revenue'] > 0 ? round($totals['staff_meals'] / $totals['revenue'] * 100, 1) : 0;
            @endphp

            {{-- KPI Cards --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <div class="text-sm font-medium text-gray-500">Total Wastage</div>
                    <div class="mt-1 text-2xl font-bold text-orange-600">{{ number_format($totals['wastage'], 2) }}</div>
                    <div class="mt-1 text-xs text-gray-400">{{ $wastPct }}% of revenue</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <div class="text-sm font-medium text-gray-500">Staff Meals</div>
                    <div class="mt-1 text-2xl font-bold text-purple-600">{{ number_format($totals['staff_meals'], 2) }}</div>
                    <div class="mt-1 text-xs text-gray-400">{{ $smPct }}% of revenue</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <div class="text-sm font-medium text-gray-500">Revenue</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($totals['revenue'], 0) }}</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <div class="text-sm font-medium text-gray-500">Top Item Cost</div>
                    <div class="mt-1 text-2xl font-bold text-orange-600">
                        {{ !empty($ds['top_wastage']) ? number_format($ds['top_wastage'][0]['total_cost'], 2) : '0.00' }}
                    </div>
                    <div class="mt-1 text-xs text-gray-400 truncate">{{ !empty($ds['top_wastage']) ? $ds['top_wastage'][0]['name'] : '—' }}</div>
                </div>
            </div>

            {{-- Charts --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <h3 class="text-sm font-semibold text-gray-600 mb-3">Wastage by Category</h3>
                    <div style="height: 260px;" wire:ignore>
                        <canvas id="wastWastageChart"></canvas>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-600">Top Wastage Items</h3>
                        @if ($totals['wastage'] > 0)
                            <span class="text-xs font-bold text-orange-600">RM {{ number_format($totals['wastage'], 2) }}</span>
                        @endif
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="text-left py-2 px-3 font-semibold text-gray-600">Item</th>
                                    <th class="text-left py-2 px-3 font-semibold text-gray-600">Category</th>
                                    <th class="text-right py-2 px-3 font-semibold text-gray-600">Qty</th>
                                    <th class="text-right py-2 px-3 font-semibold text-gray-600">Cost (RM)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @forelse ($ds['top_wastage'] as $item)
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2 px-3 font-medium text-gray-800">{{ $item['name'] }}</td>
                                        <td class="py-2 px-3 text-gray-500">{{ $item['category'] }}</td>
                                        <td class="py-2 px-3 text-right text-gray-700 tabular-nums">{{ number_format($item['quantity'], 2) }} {{ $item['uom'] }}</td>
                                        <td class="py-2 px-3 text-right font-medium text-orange-600 tabular-nums">{{ number_format($item['total_cost'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-6 text-center text-gray-400 text-xs">No wastage recorded this period</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Full breakdown tables --}}
            @include('livewire.reports._wastage-table', ['detail' => $summary['wastage_detail'] ?? null, 'label' => 'Wastage', 'color' => 'orange'])
            @include('livewire.reports._wastage-table', ['detail' => $summary['staff_meals_detail'] ?? null, 'label' => 'Staff Meals', 'color' => 'purple'])
        @else
            @include('livewire.reports._empty-state')
        @endif
    @endif
</div>

@script
<script>
    const chartInstances = {};

    function destroyAllCharts() {
        Object.keys(chartInstances).forEach(key => {
            if (chartInstances[key]) {
                chartInstances[key].destroy();
                delete chartInstances[key];
            }
        });
    }

    function initCharts() {
        destroyAllCharts();

        const tab = $wire.activeTab;
        const dd = $wire.dashboardData;
        if (!dd || !dd.cost_summary || !dd.cost_summary.categories || dd.cost_summary.categories.length === 0) return;

        const dailySales = dd.daily_sales || [];
        const dayOfWeek = dd.day_of_week || [];
        const categories = dd.cost_summary.categories || [];
        const wastageByCat = dd.wastage_by_cat || [];
        const mom = dd.mom_comparison || {};
        const legendOpts = { position: 'top', labels: { usePointStyle: true, boxWidth: 6, font: { size: 11 } } };
        const smallTick = { font: { size: 10 } };

        if (tab === 'performance') {
            // Revenue & Pax
            const el1 = document.getElementById('perfRevenueChart');
            if (el1) {
                chartInstances.rev = new Chart(el1.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: dailySales.map(d => d.label),
                        datasets: [
                            { label: 'Revenue (RM)', data: dailySales.map(d => d.revenue), borderColor: '#4f46e5', backgroundColor: 'rgba(79,70,229,0.1)', fill: true, tension: 0.3, yAxisID: 'y' },
                            { label: 'Pax', data: dailySales.map(d => d.pax), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)', fill: false, tension: 0.3, yAxisID: 'y1', borderDash: [5,5] }
                        ]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: { legend: legendOpts },
                        scales: {
                            x: { ticks: { ...smallTick, maxRotation: 45 } },
                            y: { position: 'left', ticks: smallTick, title: { display: true, text: 'Revenue (RM)', font: { size: 10 } } },
                            y1: { position: 'right', grid: { drawOnChartArea: false }, ticks: smallTick, title: { display: true, text: 'Pax', font: { size: 10 } } }
                        }
                    }
                });
            }
            // Day of Week
            const el2 = document.getElementById('perfDowChart');
            if (el2) {
                chartInstances.dow = new Chart(el2.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: dayOfWeek.map(d => d.day),
                        datasets: [
                            { label: 'Avg Revenue', data: dayOfWeek.map(d => d.avg_revenue), backgroundColor: 'rgba(79,70,229,0.7)', borderRadius: 4, yAxisID: 'y' },
                            { label: 'Avg Pax', data: dayOfWeek.map(d => d.avg_pax), backgroundColor: 'rgba(16,185,129,0.7)', borderRadius: 4, yAxisID: 'y1' }
                        ]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: legendOpts },
                        scales: { x: { ticks: smallTick }, y: { position: 'left', ticks: smallTick }, y1: { position: 'right', grid: { drawOnChartArea: false }, ticks: smallTick } }
                    }
                });
            }
            // Avg Check
            const el3 = document.getElementById('perfAvgChart');
            if (el3) {
                chartInstances.avg = new Chart(el3.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: dailySales.map(d => d.label),
                        datasets: [{ label: 'Avg Check (RM)', data: dailySales.map(d => d.avg), borderColor: '#8b5cf6', backgroundColor: 'rgba(139,92,246,0.1)', fill: true, tension: 0.3, pointRadius: 3, pointBackgroundColor: '#8b5cf6' }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { x: { ticks: { ...smallTick, maxRotation: 45 } }, y: { ticks: { ...smallTick, callback: v => 'RM ' + v } } }
                    }
                });
            }
        }

        if (tab === 'cost_analysis') {
            const colors = ['#4f46e5','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#ec4899','#84cc16'];
            // Revenue by Category
            const el4 = document.getElementById('costRevCatChart');
            if (el4) {
                chartInstances.revCat = new Chart(el4.getContext('2d'), {
                    type: 'doughnut',
                    data: { labels: categories.map(c => c.name), datasets: [{ data: categories.map(c => c.revenue), backgroundColor: categories.map((c, i) => c.color || colors[i % colors.length]), borderWidth: 2, borderColor: '#fff' }] },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '55%', plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, font: { size: 10 }, padding: 12 } } } }
                });
            }
            // Cost %
            const el5 = document.getElementById('costPctChart');
            if (el5) {
                const maxCost = Math.max(50, ...categories.map(c => c.cost_pct)) + 5;
                chartInstances.costPct = new Chart(el5.getContext('2d'), {
                    type: 'bar',
                    data: { labels: categories.map(c => c.name), datasets: [{ label: 'Cost %', data: categories.map(c => c.cost_pct), backgroundColor: categories.map(c => c.cost_pct > 35 ? '#ef4444' : (c.cost_pct > 30 ? '#f59e0b' : '#10b981')), borderRadius: 4 }] },
                    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ctx.parsed.x + '%' } } }, scales: { x: { max: maxCost, ticks: { callback: v => v + '%', ...smallTick } }, y: { ticks: smallTick } } }
                });
            }
            // MoM
            const el6 = document.getElementById('costMomChart');
            if (el6 && mom.current) {
                chartInstances.mom = new Chart(el6.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: ['Revenue', 'COGS', 'Wastage', 'Staff Meals'],
                        datasets: [
                            { label: 'Current', data: [mom.current.revenue, mom.current.cogs, mom.current.wastage, mom.current.staff_meals], backgroundColor: 'rgba(79,70,229,0.8)', borderRadius: 4 },
                            { label: 'Previous', data: [mom.previous.revenue, mom.previous.cogs, mom.previous.wastage, mom.previous.staff_meals], backgroundColor: 'rgba(156,163,175,0.5)', borderRadius: 4 }
                        ]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: legendOpts }, scales: { x: { ticks: smallTick }, y: { ticks: smallTick } } }
                });
            }
        }

        if (tab === 'wastage') {
            const el7 = document.getElementById('wastWastageChart');
            if (el7) {
                const wColors = ['#f97316','#ef4444','#eab308','#a855f7','#06b6d4','#ec4899','#84cc16','#6366f1'];
                if (wastageByCat.length === 0) {
                    el7.parentElement.innerHTML = '<div class="flex items-center justify-center h-full text-sm text-gray-400">No wastage recorded</div>';
                } else {
                    chartInstances.wastage = new Chart(el7.getContext('2d'), {
                        type: 'doughnut',
                        data: { labels: wastageByCat.map(d => d.name), datasets: [{ data: wastageByCat.map(d => d.total), backgroundColor: wastageByCat.map((d, i) => wColors[i % wColors.length]), borderWidth: 2, borderColor: '#fff' }] },
                        options: { responsive: true, maintainAspectRatio: false, cutout: '55%', plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, font: { size: 10 }, padding: 12 } } } }
                    });
                }
            }
        }
    }

    initCharts();

    $wire.$watch('activeTab', () => {
        setTimeout(() => initCharts(), 50);
    });

    $wire.$watch('dashboardData', () => {
        setTimeout(() => initCharts(), 50);
    });
</script>
@endscript
