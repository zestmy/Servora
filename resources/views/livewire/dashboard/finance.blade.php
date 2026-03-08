{{-- Finance Dashboard — Financial overview --}}

@include('livewire.dashboard.partials.stat-cards')

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    {{-- Cost % Gauges --}}
    @if (!empty($costSummary['categories']))
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-sm font-semibold text-gray-600 mb-4">Cost % This Month</h3>
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
        </div>
    @endif

    {{-- Revenue vs Purchases Trend --}}
    <div class="{{ !empty($costSummary['categories']) ? 'lg:col-span-2' : 'lg:col-span-3' }} bg-white rounded-xl shadow-sm border border-gray-100 p-6">
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
