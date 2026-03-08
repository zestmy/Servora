{{-- Chef Dashboard — Kitchen-focused --}}

@include('livewire.dashboard.partials.alerts')
@include('livewire.dashboard.partials.stat-cards')

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Last Stock Take --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
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
                    <dd class="font-bold {{ $lastStockTake->total_variance_cost < 0 ? 'text-red-600' : 'text-green-600' }}">
                        RM {{ number_format($lastStockTake->total_variance_cost, 2) }}
                    </dd>
                </div>
            </dl>
        @else
            <p class="text-gray-400 text-sm">No stock take completed yet this month.</p>
        @endif
    </div>

    {{-- Quick Actions --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-sm font-semibold text-gray-600 mb-4">Quick Actions</h3>
        <div class="space-y-3">
            <a href="{{ route('recipes.index') }}" class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 transition text-sm">
                <span class="text-lg">📋</span>
                <span class="font-medium text-gray-700">View Recipes</span>
            </a>
            <a href="{{ route('inventory.stock-takes.create') }}" class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 transition text-sm">
                <span class="text-lg">📦</span>
                <span class="font-medium text-gray-700">New Stock Take</span>
            </a>
            <a href="{{ route('inventory.wastage.create') }}" class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 transition text-sm">
                <span class="text-lg">🗑️</span>
                <span class="font-medium text-gray-700">Record Wastage</span>
            </a>
            <a href="{{ route('purchasing.index', ['tab' => 'grn']) }}" class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-green-300 hover:bg-green-50 transition text-sm">
                <span class="text-lg">📥</span>
                <span class="font-medium text-gray-700">Receive Goods (GRN)</span>
            </a>
        </div>
    </div>

    {{-- Over-cost Recipes Alert --}}
    @if ($overCostRecipes > 0)
        <div class="lg:col-span-2 bg-amber-50 rounded-xl border border-amber-200 p-6">
            <div class="flex items-center gap-3">
                <svg class="w-6 h-6 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                <div>
                    <p class="font-semibold text-amber-800">{{ $overCostRecipes }} recipe(s) above 35% food cost</p>
                    <p class="text-sm text-amber-700 mt-1">Review recipe ingredients and pricing to improve margins.</p>
                </div>
                <a href="{{ route('recipes.index') }}" class="ml-auto px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 transition">
                    Review Recipes
                </a>
            </div>
        </div>
    @endif
</div>
