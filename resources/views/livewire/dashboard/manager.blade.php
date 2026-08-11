{{-- Manager Dashboard — the outlet floor.

     This was the thinnest of the seven and the one whose reader makes the most
     decisions in a day: six absolutes, one chart, four links, and no way to
     tell whether today was going well.

     The lead row is now today against a normal day, because that is the only
     comparison a floor manager can act on inside a shift. Everything slower
     than a shift sits below it. --}}

@include('livewire.dashboard.partials.alerts')

@php
    /*
     * Average check is the addition that matters here. It is the number a
     * floor manager can actually move between now and closing — upsell, course
     * suggestion, table turn — and it appeared nowhere in the product, despite
     * both of its inputs already being summed on this page.
     */
    $avgCheck        = ($today && $today['pax'] > 0) ? $today['revenue'] / $today['pax'] : null;
    $typicalAvgCheck = ($typical && $typical['pax'] > 0) ? $typical['revenue'] / $typical['pax'] : null;

    $leadTiles = [];

    if ($today) {
        /*
         * Compared against the trailing average for the SAME WEEKDAY, not
         * against yesterday and not against the month's daily mean. A Monday
         * measured against a Saturday reports the calendar, not the trading.
         */
        $leadTiles = [
            [
                'label'   => 'Revenue today',
                'prefix'  => 'RM',
                'value'   => number_format($today['revenue'], 0),
                'current' => $today['revenue'],
                'prior'   => $typical['revenue'],
                'compare' => 'vs a typical ' . now()->format('l'),
            ],
            [
                'label'   => 'Covers today',
                'value'   => number_format($today['pax']),
                'current' => $today['pax'],
                'prior'   => $typical['pax'],
                'compare' => 'vs a typical ' . now()->format('l'),
            ],
            [
                'label' => 'Average check',
                // No prefix on the em dash: "RM —" reads as a currency amount
                // that failed to load rather than as "nothing to divide yet".
                'prefix'  => $avgCheck !== null ? 'RM' : null,
                'value'   => $avgCheck !== null ? number_format($avgCheck, 2) : '—',
                'current' => $avgCheck ?? 0,
                'prior'   => $typicalAvgCheck,
                'compare' => 'vs a typical ' . now()->format('l'),
                'sub'     => $today['pax'] > 0 ? null : 'No covers recorded yet',
            ],
        ];
    }

    $periodTiles = [
        [
            'label'   => $periodLabel . ' revenue',
            'prefix'  => 'RM',
            'value'   => number_format($revenue['value'], 0),
            'current' => $revenue['value'],
            'prior'   => $revenue['prior'],
            'compare' => $compareLabel,
            'spark'   => $revenueSpark,
            // The projection is the honest way to read a month-to-date figure:
            // without it, day 12 of the month gets compared against a whole
            // month in the reader's head.
            'sub'     => $runRate !== null ? 'On track for RM ' . number_format($runRate, 0) : null,
        ],
        [
            'label' => 'Pending POs',
            'value' => number_format($pendingPOs),
            'color' => $pendingPOs > 0 ? 'amber' : null,
            'sub'   => $oldestPoWait !== null
                        ? 'Oldest waiting ' . $oldestPoWait . ' ' . Str::plural('day', $oldestPoWait)
                        : null,
            'href'  => route('purchasing.index', ['tab' => 'po']),
        ],
        [
            'label' => 'GRN to receive',
            'value' => number_format($pendingGrns),
            'color' => $pendingGrns > 0 ? 'amber' : null,
            'href'  => route('purchasing.index', ['tab' => 'grn']),
        ],
    ];
@endphp

@if ($leadTiles)
    @include('livewire.dashboard.partials.stat-cards', ['stats' => $leadTiles])
@endif

@include('livewire.dashboard.partials.stat-cards', ['stats' => $periodTiles])

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    {{-- Revenue vs cost of sales --}}
    <div class="card p-6 lg:col-span-2">
        @include('livewire.dashboard.partials.trend-chart', ['trendMonths' => $trendMonths])
    </div>

    {{-- Quick Actions --}}
    <div class="card p-6">
        @include('livewire.dashboard.partials.quick-actions', ['actions' => [
            ['route' => 'purchasing.orders.create',      'icon' => 'cart',      'label' => 'New purchase order'],
            ['route' => 'sales.create',                  'icon' => 'currency',  'label' => 'Record sales'],
            ['route' => 'inventory.stock-takes.create',  'icon' => 'database',  'label' => 'New stock take'],
            ['route' => 'purchasing.index', 'params' => ['tab' => 'grn'],
             'icon'  => 'inbox',                         'label' => 'Receive goods (GRN)', 'count' => $pendingGrns],
        ]])
    </div>
</div>

{{-- Catalogue sizes are real but they are not news: they move perhaps monthly
     and nobody acts on them. They were taking two of the six prime tile slots
     on a dashboard whose reader has a shift to run. --}}
<p class="mt-6 text-xs text-gray-600">
    {{ number_format($totalIngredients) }} active {{ Str::plural('product', $totalIngredients) }} ·
    {{ number_format($activeRecipes) }} active {{ Str::plural('recipe', $activeRecipes) }}
</p>
