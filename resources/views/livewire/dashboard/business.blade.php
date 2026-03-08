{{-- Business Manager Dashboard — Full P&L view --}}

{{-- Alerts --}}
@if (count($alerts) > 0)
    <div class="mb-6 space-y-2">
        @foreach ($alerts as $alert)
            <div class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium
                {{ $alert['type'] === 'warning' ? 'bg-amber-50 text-amber-800 border border-amber-200' : '' }}
                {{ $alert['type'] === 'info' ? 'bg-blue-50 text-blue-800 border border-blue-200' : '' }}
                {{ $alert['type'] === 'alert' ? 'bg-red-50 text-red-800 border border-red-200' : '' }}">
                @if ($alert['type'] === 'warning')
                    <svg class="w-5 h-5 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                @elseif ($alert['type'] === 'alert')
                    <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                @else
                    <svg class="w-5 h-5 text-blue-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                @endif
                {{ $alert['message'] }}
            </div>
        @endforeach
    </div>
@endif

{{-- Quick Stats --}}
<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Ingredients</div>
        <div class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($totalIngredients) }}</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Recipes</div>
        <div class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($activeRecipes) }}</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Pending POs</div>
        <div class="mt-1 text-2xl font-bold {{ $pendingPOs > 0 ? 'text-amber-600' : 'text-gray-900' }}">{{ $pendingPOs }}</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Today Revenue</div>
        <div class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($todayRevenue, 0) }}</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Month Revenue</div>
        <div class="mt-1 text-2xl font-bold text-green-600">{{ number_format($monthRevenue, 0) }}</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Month Purchases</div>
        <div class="mt-1 text-2xl font-bold text-red-600">{{ number_format($monthPurchases, 0) }}</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Wastage</div>
        <div class="mt-1 text-2xl font-bold text-orange-600">{{ number_format($monthWastage, 0) }}</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Staff Meals</div>
        <div class="mt-1 text-2xl font-bold text-purple-600">{{ number_format($monthStaffMeals, 0) }}</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    {{-- Cost % Gauges --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-sm font-semibold text-gray-600 mb-4">Cost % This Month</h3>
        @if (!empty($costSummary['categories']))
            <div class="space-y-4">
                @foreach ($costSummary['categories'] as $cat)
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1.5">
                            <span class="flex items-center gap-2">
                                @if ($cat['color'])
                                    <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $cat['color'] }}"></span>
                                @endif
                                <span class="font-medium text-gray-700">{{ $cat['name'] }}</span>
                            </span>
                            <span class="font-bold {{ $cat['cost_pct'] > 35 ? 'text-red-600' : ($cat['cost_pct'] > 30 ? 'text-amber-600' : 'text-green-600') }}">{{ $cat['cost_pct'] }}%</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-3">
                            <div class="h-3 rounded-full {{ $cat['cost_pct'] > 35 ? 'bg-red-500' : ($cat['cost_pct'] > 30 ? 'bg-amber-500' : 'bg-green-500') }}"
                                 style="width: {{ min($cat['cost_pct'], 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
                <div class="pt-3 border-t border-gray-200">
                    <div class="flex items-center justify-between text-sm mb-1.5">
                        <span class="font-semibold text-gray-700">Overall</span>
                        <span class="font-bold text-lg {{ $costSummary['totals']['cost_pct'] > 35 ? 'text-red-600' : ($costSummary['totals']['cost_pct'] > 30 ? 'text-amber-600' : 'text-green-600') }}">{{ $costSummary['totals']['cost_pct'] }}%</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-3">
                        <div class="h-3 rounded-full {{ $costSummary['totals']['cost_pct'] > 35 ? 'bg-red-500' : ($costSummary['totals']['cost_pct'] > 30 ? 'bg-amber-500' : 'bg-green-500') }}"
                             style="width: {{ min($costSummary['totals']['cost_pct'], 100) }}%"></div>
                    </div>
                </div>
            </div>
        @else
            <p class="text-gray-400 text-sm">No cost data yet.</p>
        @endif
    </div>

    {{-- Revenue vs Purchases Trend --}}
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        @include('livewire.dashboard.partials.trend-chart', ['trendMonths' => $trendMonths])
    </div>
</div>

{{-- COGS Breakdown --}}
@if (!empty($costSummary['categories']))
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-gray-600">This Month's COGS Breakdown</h3>
            <a href="{{ route('reports.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">View Full Report &rarr;</a>
        </div>
        @include('livewire.dashboard.partials.cogs-table', ['costSummary' => $costSummary])
    </div>
@endif
