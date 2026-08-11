{{-- Finance Dashboard.

     The figures here were right and the ordering was nearly right. Two things
     were not: margin — the one number this role lives on — sat fifth, in the
     second row, with no target and no trend; and this was the only role view
     in the product that omitted the alerts include, against the only builder
     that returned no alerts key. The person whose job is margin was the one
     person never told it had moved. --}}

@include('livewire.dashboard.partials.alerts')

@php
    $belowFloor = $margin['value'] > 0 && $margin['value'] < $marginFloor;
    $marginTone = $belowFloor ? 'text-danger-700' : 'text-gray-900';
@endphp

{{-- Margin, led.

     One figure at display size with its own trend, rather than one tile among
     eight. A hero figure is only justified when a role really does have a
     single number, and finance does. --}}
<div class="card mb-6 p-6">
    <div class="flex flex-wrap items-end justify-between gap-6">
        <div class="stat">
            <span class="stat-label">Gross margin</span>
            <span class="display-3 tabular-nums {{ $marginTone }}">
                {{ number_format($margin['value'], 1) }}%
            </span>
            <span>
                <x-delta :current="$margin['value']" :previous="$margin['prior']" :label="$compareLabel" />
            </span>
            <span class="stat-meta">
                Floor {{ rtrim(rtrim(number_format($marginFloor, 1), '0'), '.') }}%
                @if ($belowFloor)
                    <span class="text-danger-700">— currently below it</span>
                @endif
            </span>
        </div>

        <div class="text-brand-600">
            <x-sparkline :values="$marginSpark" :width="220" :height="56" />
            <p class="mt-1 text-right text-xs text-gray-600">Last 6 months</p>
        </div>
    </div>
</div>

@php
    /*
     * The P&L in reading order — what came in, what it cost, what is left,
     * what was spent restocking. Cost of sales and purchases are marked
     * inverse so a rise reads as bad news rather than as growth.
     */
    $tiles = [
        [
            'label'   => 'Revenue',
            'prefix'  => 'RM',
            'value'   => number_format($revenue['value'], 0),
            'current' => $revenue['value'],
            'prior'   => $revenue['prior'],
            'compare' => $compareLabel,
        ],
        [
            'label'   => 'Cost of sales',
            'prefix'  => 'RM',
            'value'   => number_format($cogs['value'], 0),
            'current' => $cogs['value'],
            'prior'   => $cogs['prior'],
            'inverse' => true,
            'compare' => $compareLabel,
        ],
        [
            'label'   => 'Gross profit',
            'prefix'  => 'RM',
            'value'   => number_format($grossProfit['value'], 0),
            'current' => $grossProfit['value'],
            'prior'   => $grossProfit['prior'],
            'compare' => $compareLabel,
            'color'   => $grossProfit['value'] < 0 ? 'red' : null,
        ],
        [
            'label'   => 'Purchases',
            'prefix'  => 'RM',
            'value'   => number_format($purchases['value'], 0),
            'current' => $purchases['value'],
            'prior'   => $purchases['prior'],
            'inverse' => true,
            'compare' => $compareLabel,
            'sub'     => 'Cash out, not cost of sales',
        ],
        [
            'label'   => 'Wastage',
            'value'   => $wastagePct . '%',
            'current' => $wastage['value'],
            'prior'   => $wastage['prior'],
            'inverse' => true,
            'compare' => $compareLabel,
            'sub'     => 'RM ' . number_format($wastage['value'], 0) . ' of revenue',
            'color'   => $wastagePct > (float) config('costing.wastage_warn_pct') ? 'amber' : null,
        ],
        [
            'label'   => 'Staff meals',
            'prefix'  => 'RM',
            'value'   => number_format($staffMeals['value'], 0),
            'current' => $staffMeals['value'],
            'prior'   => $staffMeals['prior'],
            'inverse' => true,
            'compare' => $compareLabel,
        ],
    ];
@endphp

@include('livewire.dashboard.partials.stat-cards', ['stats' => $tiles])

<div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
    @if (! empty($costSummary['categories']))
        <div class="card p-6">
            @include('livewire.dashboard.partials.cost-gauges', [
                'costSummary'    => $costSummary,
                'daysElapsed'    => $daysElapsed,
                'monthlyCosting' => $monthlyCosting,
            ])
        </div>
    @endif

    <div class="{{ ! empty($costSummary['categories']) ? 'lg:col-span-2' : 'lg:col-span-3' }} card p-6">
        @include('livewire.dashboard.partials.trend-chart', ['trendMonths' => $trendMonths])
    </div>
</div>

{{-- COGS Breakdown --}}
@if (! empty($costSummary['categories']))
    <div class="card p-6">
        <x-card-title :action="'View full report'" :action-href="route('reports.index')">
            Cost of goods sold by category
        </x-card-title>

        @include('livewire.dashboard.partials.cogs-table', [
            'costSummary'      => $costSummary,
            'priorCostSummary' => $priorCostSummary,
            'daysElapsed'      => $daysElapsed,
        ])
    </div>
@endif
