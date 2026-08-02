{{-- Manager Dashboard — Operations overview --}}

@include('livewire.dashboard.partials.alerts')
@include('livewire.dashboard.partials.stat-cards')

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Revenue vs Purchases Trend --}}
    <div class="lg:col-span-2 card p-6">
        @include('livewire.dashboard.partials.trend-chart', ['trendMonths' => $trendMonths])
    </div>

    {{-- Quick Actions --}}
    <div class="card p-6">
        @include('livewire.dashboard.partials.quick-actions', ['actions' => [
            ['route' => 'purchasing.orders.create',      'icon' => 'cart',      'label' => 'New purchase order'],
            ['route' => 'sales.create',                  'icon' => 'currency',  'label' => 'Record sales'],
            ['route' => 'inventory.stock-takes.create',  'icon' => 'database',  'label' => 'New stock take'],
            ['route' => 'purchasing.index', 'params' => ['tab' => 'grn'],
             'icon'  => 'inbox',                         'label' => 'Receive goods (GRN)'],
        ]])
    </div>
</div>
