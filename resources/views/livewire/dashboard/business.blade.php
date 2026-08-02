{{-- Business Manager Dashboard — Full P&L view --}}

{{-- The alert strip was pasted in full here, a second copy of
     partials/alerts.blade.php that had already drifted (it used count() rather
     than !empty, and had no role). One copy now. --}}
@include('livewire.dashboard.partials.alerts')

{{-- Eight tiles were hand-rolled here rather than going through the stat
     partial, each with its own hue: revenue green, purchases red, wastage
     orange, staff meals purple.

     Two problems with that. Colour was doing no work — these are all just
     figures, and painting Month Purchases in danger-red says a routine
     operating number is an error. And orange and purple are not in the
     product's palette at all.

     Pending POs keeps a tone because a queue with something in it IS a state
     worth flagging. The rest read in ink. --}}
@include('livewire.dashboard.partials.stat-cards', ['stats' => [
    ['label' => 'Products',        'value' => number_format($totalIngredients)],
    ['label' => 'Recipes',         'value' => number_format($activeRecipes)],
    ['label' => 'Pending POs',     'value' => $pendingPOs, 'color' => $pendingPOs > 0 ? 'amber' : null],
    ['label' => 'Today revenue',   'value' => number_format($todayRevenue, 0)],
    ['label' => 'Month revenue',   'value' => number_format($monthRevenue, 0)],
    ['label' => 'Month purchases', 'value' => number_format($monthPurchases, 0)],
    ['label' => 'Wastage',         'value' => number_format($monthWastage, 0)],
    ['label' => 'Staff meals',     'value' => number_format($monthStaffMeals, 0)],
]])

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    {{-- Cost % Gauges --}}
    <div class="card p-6">
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
                            <span class="font-bold {{ $cat['cost_pct'] > 35 ? 'text-danger-600' : ($cat['cost_pct'] > 30 ? 'text-warning-600' : 'text-success-600') }}">{{ $cat['cost_pct'] }}%</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-3">
                            <div class="h-3 rounded-full {{ $cat['cost_pct'] > 35 ? 'bg-danger-500' : ($cat['cost_pct'] > 30 ? 'bg-warning-500' : 'bg-success-500') }}"
                                 style="width: {{ min($cat['cost_pct'], 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
                <div class="pt-3 border-t border-gray-200">
                    <div class="flex items-center justify-between text-sm mb-1.5">
                        <span class="font-semibold text-gray-700">Overall</span>
                        <span class="font-bold text-lg {{ $costSummary['totals']['cost_pct'] > 35 ? 'text-danger-600' : ($costSummary['totals']['cost_pct'] > 30 ? 'text-warning-600' : 'text-success-600') }}">{{ $costSummary['totals']['cost_pct'] }}%</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-3">
                        <div class="h-3 rounded-full {{ $costSummary['totals']['cost_pct'] > 35 ? 'bg-danger-500' : ($costSummary['totals']['cost_pct'] > 30 ? 'bg-warning-500' : 'bg-success-500') }}"
                             style="width: {{ min($costSummary['totals']['cost_pct'], 100) }}%"></div>
                    </div>
                </div>
            </div>
        @else
            <p class="text-gray-600 text-sm">No cost data yet.</p>
        @endif
    </div>

    {{-- Revenue vs Purchases Trend --}}
    <div class="lg:col-span-2 card p-6">
        @include('livewire.dashboard.partials.trend-chart', ['trendMonths' => $trendMonths])
    </div>
</div>

{{-- COGS Breakdown --}}
@if (!empty($costSummary['categories']))
    <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-gray-600">This Month's COGS Breakdown</h3>
            <a href="{{ route('reports.index') }}" class="text-sm text-brand-600 hover:text-brand-800 font-medium">View Full Report &rarr;</a>
        </div>
        @include('livewire.dashboard.partials.cogs-table', ['costSummary' => $costSummary])
    </div>
@endif
