{{-- Business Manager Dashboard — the owner-operator view.

     The eight tiles here read as a flat list of unrelated figures, and the two
     least useful led: Products and Recipes took the first two slots, which is
     where the eye lands, while the P&L chain was scattered across positions
     four to eight and out of order.

     The chain leads now, in the order it is read in — revenue, what it cost,
     what is left, and what that is as a percentage — and the catalogue counts
     are a footer line. --}}

@include('livewire.dashboard.partials.alerts')

{{-- The P&L band.

     One card rather than four tiles, because these four figures are one
     sentence: this came in, that went out, this is what remains. Four separate
     boxes with four separate borders is the layout arguing they are unrelated
     measurements, which is exactly the reading to avoid. --}}
<div class="card mb-6 p-6">
    <x-card-title :action="'Full report'" :action-href="route('reports.index')">
        Profit and loss
    </x-card-title>

    <dl class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-4">
        @php
            $chain = [
                ['label' => 'Revenue',       'm' => $pnl['revenue'],     'prefix' => 'RM', 'dp' => 0, 'inverse' => false],
                ['label' => 'Cost of sales', 'm' => $pnl['cogs'],        'prefix' => 'RM', 'dp' => 0, 'inverse' => true],
                ['label' => 'Gross profit',  'm' => $pnl['grossProfit'], 'prefix' => 'RM', 'dp' => 0, 'inverse' => false],
                ['label' => 'Gross margin',  'm' => $pnl['margin'],      'suffix' => '%',  'dp' => 1, 'inverse' => false],
            ];
        @endphp

        @foreach ($chain as $i => $item)
            {{-- A rule between the steps rather than four boxes: it groups them
                 as one reading while still separating them. --}}
            <div class="stat {{ $i > 0 ? 'lg:border-l lg:border-gray-200 lg:pl-6' : '' }}">
                <dt class="stat-label">{{ $item['label'] }}</dt>
                <dd class="stat-value">
                    @if (! empty($item['prefix']))
                        <span class="text-base font-medium text-gray-600">{{ $item['prefix'] }}</span>
                    @endif
                    {{ number_format($item['m']['value'], $item['dp']) }}{{ $item['suffix'] ?? '' }}
                </dd>
                <dd>
                    <x-delta :current="$item['m']['value']" :previous="$item['m']['prior']"
                             :inverse="$item['inverse']" :label="$compareLabel" />
                </dd>
            </div>
        @endforeach
    </dl>

    @if ($runRate !== null)
        <p class="mt-5 border-t border-gray-200 pt-3 text-xs text-gray-600">
            At the current daily rate this period finishes around
            <span class="font-semibold tabular-nums text-gray-900">RM {{ number_format($runRate, 0) }}</span>
            in revenue.
        </p>
    @endif
</div>

@php
    $tiles = [];

    if ($today) {
        $tiles[] = [
            'label'  => 'Revenue today',
            'prefix' => 'RM',
            'value'  => number_format($today['revenue'], 0),
            'sub'    => number_format($today['pax']) . ' ' . Str::plural('cover', $today['pax']),
        ];
    }

    /*
     * Wastage as a share of revenue, not as a bare ringgit figure. RM 2,140
     * is unremarkable at one volume and alarming at another; 1.7% against
     * last period's 0.9% is a conversation with the kitchen. Both inputs were
     * already being summed on this page and the ratio was never taken.
     */
    $tiles[] = [
        'label'   => 'Wastage',
        'value'   => $wastagePct . '%',
        'current' => $wastagePct,
        'prior'   => $priorWastagePct,
        'inverse' => true,
        'compare' => $compareLabel,
        'sub'     => 'RM ' . number_format($wastage['value'], 0) . ' of revenue',
        'color'   => $wastagePct > (float) config('costing.wastage_warn_pct') ? 'amber' : null,
    ];

    $tiles[] = [
        'label'   => 'Staff meals',
        'prefix'  => 'RM',
        'value'   => number_format($staffMeals['value'], 0),
        'current' => $staffMeals['value'],
        'prior'   => $staffMeals['prior'],
        'inverse' => true,
        'compare' => $compareLabel,
    ];

    $tiles[] = [
        'label' => 'Pending POs',
        'value' => number_format($pendingPOs),
        'color' => $pendingPOs > 0 ? 'amber' : null,
        'href'  => route('purchasing.index', ['tab' => 'po']),
    ];
@endphp

@include('livewire.dashboard.partials.stat-cards', ['stats' => $tiles])

<div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
    {{-- Cost % gauges — the shared partial. This block existed twice, thirty
         five lines each, here and in finance.blade.php. --}}
    <div class="card p-6">
        @include('livewire.dashboard.partials.cost-gauges', [
            'costSummary'    => $costSummary,
            'daysElapsed'    => $daysElapsed,
            'monthlyCosting' => $monthlyCosting,
        ])
    </div>

    {{-- Revenue vs cost of sales --}}
    <div class="card p-6 lg:col-span-2">
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

<p class="mt-6 text-xs text-gray-600">
    {{ number_format($totalIngredients) }} active {{ Str::plural('product', $totalIngredients) }} ·
    {{ number_format($activeRecipes) }} active {{ Str::plural('recipe', $activeRecipes) }} ·
    RM {{ number_format($purchases['value'], 0) }} purchased this period
</p>
