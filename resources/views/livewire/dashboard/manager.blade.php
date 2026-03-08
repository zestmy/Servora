{{-- Manager Dashboard — Operations overview --}}

@include('livewire.dashboard.partials.alerts')
@include('livewire.dashboard.partials.stat-cards')

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Revenue vs Purchases Trend --}}
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        @include('livewire.dashboard.partials.trend-chart', ['trendMonths' => $trendMonths])
    </div>

    {{-- Quick Actions --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-sm font-semibold text-gray-600 mb-4">Quick Actions</h3>
        <div class="space-y-3">
            <a href="{{ route('purchasing.orders.create') }}" class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 transition text-sm">
                <span class="text-lg">🛒</span>
                <span class="font-medium text-gray-700">New Purchase Order</span>
            </a>
            <a href="{{ route('sales.create') }}" class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 transition text-sm">
                <span class="text-lg">💰</span>
                <span class="font-medium text-gray-700">Record Sales</span>
            </a>
            <a href="{{ route('inventory.stock-takes.create') }}" class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 transition text-sm">
                <span class="text-lg">📦</span>
                <span class="font-medium text-gray-700">New Stock Take</span>
            </a>
            <a href="{{ route('purchasing.index', ['tab' => 'grn']) }}" class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-green-300 hover:bg-green-50 transition text-sm">
                <span class="text-lg">📋</span>
                <span class="font-medium text-gray-700">Receive Goods (GRN)</span>
            </a>
        </div>
    </div>
</div>
