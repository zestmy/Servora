{{-- Chef Dashboard — the kitchen.

     Three of the four tiles here were catalogue sizes: how many recipes exist,
     how many prep recipes exist, how many ingredients exist. A chef opening
     this at 8am learned the size of the catalogue — not what is about to go
     off, what came in, or what is losing money.

     The one thing this screen should have opened with is the one thing it did
     not have. <x-meter> is the product's own component, built for exactly this
     question, and the kitchen dashboard never used it. --}}

@include('livewire.dashboard.partials.alerts')

{{-- Expiring first.

     The data was already there: every label print writes its computed use-by,
     which is what makes the expiring list possible at all. This is the correct
     place for the meter — elapsed life is real here, unlike a print queue
     where everything is 100% fresh by definition. --}}
<div class="card mb-6 p-6">
    <x-card-title :action="$expiringCount > $expiring->count() ? 'View all (' . $expiringCount . ')' : ($expiringCount > 0 ? 'Open list' : null)"
                  :action-href="route('labels.expiring')">
        Expiring within 24 hours
    </x-card-title>

    @if ($expiring->isNotEmpty())
        <ul class="stack -mx-2">
            @foreach ($expiring as $print)
                <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-2 py-3">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-gray-900">{{ $print->printedName() }}</p>
                        <div class="mt-1.5 max-w-xs">
                            <x-meter :start="$print->start_at" :end="$print->end_at" :time="false" />
                        </div>
                    </div>

                    <div class="text-right">
                        <x-meter :start="$print->start_at" :end="$print->end_at" :bar="false" />
                        <p class="mt-0.5 text-xs text-gray-600">
                            {{ $print->end_at?->format('d M, H:i') }}
                        </p>
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <div class="empty-state">
            <x-icon name="check" size="h-8 w-8" stroke="1.8" class="text-success-600" />
            <p class="empty-title">Nothing expiring in the next 24 hours</p>
            <p class="empty-body">Labelled items approaching their use-by will appear here, soonest first.</p>
        </div>
    @endif
</div>

@php
    $tiles = [
        [
            'label' => 'Expiring soon',
            'value' => number_format($expiringCount),
            'color' => $expiringCount > 0 ? 'amber' : null,
            'sub'   => 'Within 24 hours',
            'href'  => route('labels.expiring'),
        ],
        [
            'label' => 'GRN to receive',
            'value' => number_format($pendingGrns),
            'color' => $pendingGrns > 0 ? 'amber' : null,
            'href'  => route('purchasing.index', ['tab' => 'grn']),
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
            'label' => 'Over food cost',
            'value' => number_format($overCostCount),
            'color' => $overCostCount > 0 ? 'amber' : null,
            'sub'   => 'Above ' . rtrim(rtrim(number_format((float) config('costing.target.over'), 1), '0'), '.') . '% target',
            'href'  => route('recipes.index'),
        ],
    ];
@endphp

@include('livewire.dashboard.partials.stat-cards', ['stats' => $tiles])

<div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
    {{-- The list, not the count.

         This was a bare number with no way to find out which recipes it meant
         — the dashboard knew and made the chef go and work it out. The scan
         has to load and cost every recipe to produce the count in the first
         place, so ranking the worst five costs nothing extra. --}}
    <div class="card p-6">
        <x-card-title :action="$overCostCount > count($overCostRecipes) ? 'All ' . $overCostCount : null"
                      :action-href="route('recipes.index')">
            Worst food cost
        </x-card-title>

        @if (! empty($overCostRecipes))
            <ul class="stack -mx-2">
                @foreach ($overCostRecipes as $recipe)
                    <li class="flex items-center justify-between gap-4 px-2 py-2.5">
                        <a href="{{ route('recipes.edit', $recipe['id']) }}"
                           class="min-w-0 flex-1 truncate text-sm font-medium text-gray-900 hover:text-brand-700 hover:underline">
                            {{ $recipe['name'] }}
                        </a>
                        <span class="flex-none font-semibold tabular-nums text-danger-700">
                            {{ $recipe['cost_pct'] }}%
                            <span class="sr-only">, over target</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="empty-state">
                <x-icon name="check" size="h-8 w-8" stroke="1.8" class="text-success-600" />
                <p class="empty-title">Every recipe is on target</p>
                <p class="empty-body">
                    Recipes selling above the
                    {{ rtrim(rtrim(number_format((float) config('costing.target.over'), 1), '0'), '.') }}%
                    food cost target will be listed here, worst first.
                </p>
            </div>
        @endif
    </div>

    {{-- Last Stock Take --}}
    <div class="card p-6">
        <x-card-title :action="'New stock take'" :action-href="route('inventory.stock-takes.create')">
            Last stock take
        </x-card-title>

        @if ($lastStockTake)
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-600">Date</dt>
                    <dd class="font-medium text-gray-900">
                        {{ $lastStockTake->stock_take_date->format('d M Y') }}
                        @if ($daysSinceStockTake !== null)
                            <span class="text-gray-600">
                                ({{ $daysSinceStockTake === 0 ? 'today' : $daysSinceStockTake . ' ' . Str::plural('day', $daysSinceStockTake) . ' ago' }})
                            </span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-600">Reference</dt>
                    <dd class="font-mono text-xs text-gray-700">{{ $lastStockTake->reference_number }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-600">Stock value</dt>
                    <dd class="font-semibold tabular-nums text-gray-900">
                        RM {{ number_format($lastStockTake->total_stock_cost, 2) }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-600">Variance</dt>
                    <dd class="font-semibold tabular-nums {{ $lastStockTake->total_variance_cost < 0 ? 'text-danger-700' : 'text-success-700' }}">
                        RM {{ number_format($lastStockTake->total_variance_cost, 2) }}
                        <span class="sr-only">
                            , {{ $lastStockTake->total_variance_cost < 0 ? 'stock short' : 'stock over' }}
                        </span>
                    </dd>
                </div>
            </dl>
        @else
            <div class="empty-state">
                <x-icon name="database" size="h-8 w-8" stroke="1.5" class="text-gray-500" />
                <p class="empty-title">No completed stock take yet</p>
                <p class="empty-body">Counting stock is what turns purchases into a real cost of sales.</p>
            </div>
        @endif
    </div>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    {{-- Wastage over time. The tile says what it is now; this says whether
         that is a spike or a trend, which is a different question and the one
         that decides whether anything needs changing. --}}
    <div class="card p-6 lg:col-span-2">
        @include('livewire.dashboard.partials.trend-chart', [
            'trendMonths' => $trendMonths,
            'series'      => [
                ['key' => 'revenue', 'label' => 'Revenue',       'bar' => 'bg-chart-1'],
                ['key' => 'cogs',    'label' => 'Cost of sales', 'bar' => 'bg-chart-2'],
            ],
        ])
    </div>

    <div class="card p-6">
        @include('livewire.dashboard.partials.quick-actions', ['actions' => [
            ['route' => 'recipes.index',                 'icon' => 'clipboard', 'label' => 'View recipes'],
            ['route' => 'inventory.stock-takes.create',  'icon' => 'database',  'label' => 'New stock take'],
            ['route' => 'inventory.wastage.create',      'icon' => 'trash',     'label' => 'Record wastage'],
            ['route' => 'purchasing.index', 'params' => ['tab' => 'grn'],
             'icon'  => 'inbox',                         'label' => 'Receive goods (GRN)', 'count' => $pendingGrns],
        ]])
    </div>
</div>

<p class="mt-6 text-xs text-gray-600">
    {{ number_format($activeRecipes) }} active {{ Str::plural('recipe', $activeRecipes) }} ·
    {{ number_format($prepRecipes) }} prep {{ Str::plural('recipe', $prepRecipes) }} ·
    {{ number_format($totalIngredients) }} {{ Str::plural('ingredient', $totalIngredients) }}
</p>
