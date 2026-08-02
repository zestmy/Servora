{{-- Chef Dashboard — Kitchen-focused --}}

@include('livewire.dashboard.partials.alerts')
@include('livewire.dashboard.partials.stat-cards')

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Last Stock Take --}}
    <div class="card p-6">
        <h3 class="text-sm font-semibold text-gray-600 mb-4">Last Stock Take</h3>
        @if ($lastStockTake)
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">Date</dt>
                    <dd class="font-medium text-gray-800">{{ $lastStockTake->stock_take_date->format('d M Y') }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Reference</dt>
                    <dd class="font-mono text-gray-700 text-xs">{{ $lastStockTake->reference_number }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Stock Value</dt>
                    <dd class="font-bold text-gray-800">RM {{ number_format($lastStockTake->total_stock_cost, 2) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Variance</dt>
                    <dd class="font-bold {{ $lastStockTake->total_variance_cost < 0 ? 'text-danger-600' : 'text-success-600' }}">
                        RM {{ number_format($lastStockTake->total_variance_cost, 2) }}
                    </dd>
                </div>
            </dl>
        @else
            <p class="text-gray-600 text-sm">No stock take completed yet this month.</p>
        @endif
    </div>

    {{-- Quick Actions --}}
    <div class="card p-6">
        @include('livewire.dashboard.partials.quick-actions', ['actions' => [
            ['route' => 'recipes.index',                 'icon' => 'clipboard', 'label' => 'View recipes'],
            ['route' => 'inventory.stock-takes.create',  'icon' => 'database',  'label' => 'New stock take'],
            ['route' => 'inventory.wastage.create',      'icon' => 'trash',     'label' => 'Record wastage'],
            ['route' => 'purchasing.index', 'params' => ['tab' => 'grn'],
             'icon'  => 'inbox',                         'label' => 'Receive goods (GRN)'],
        ]])
    </div>

    {{-- Over-cost recipes. Last of the hand-pasted solid alert icons; the
         glyph now comes from the icon set and the surface from .alert-warning,
         so this matches the alert strip at the top of the page. --}}
    @if ($overCostRecipes > 0)
        <div class="alert-warning lg:col-span-2">
            <x-icon name="warning" size="h-5 w-5" stroke="1.8" class="mt-px flex-none" />
            <div class="min-w-0 flex-1">
                <p class="font-semibold">
                    {{ $overCostRecipes }} {{ Str::plural('recipe', $overCostRecipes) }} above 35% food cost
                </p>
                <p class="mt-1 text-sm">Review the ingredients and pricing on these to bring the margin back.</p>
            </div>
            <a href="{{ route('recipes.index') }}" class="btn-secondary btn-sm flex-none self-start">
                Review recipes
            </a>
        </div>
    @endif
</div>
