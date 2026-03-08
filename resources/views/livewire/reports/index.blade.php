<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-gray-700">Monthly Cost Summary</h2>
        @if (!empty($summary['categories']))
            <button wire:click="exportCsv"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export CSV
            </button>
        @endif
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
        <div class="flex flex-wrap items-center gap-4">
            {{-- Period navigation --}}
            <div class="flex items-center gap-2">
                <button wire:click="previousMonth" class="p-2 rounded-lg hover:bg-gray-100 text-gray-500 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <input type="month" wire:model.live="period"
                       class="rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <button wire:click="nextMonth" class="p-2 rounded-lg hover:bg-gray-100 text-gray-500 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>

        </div>
    </div>

    @if (!empty($summary['categories']))
        {{-- Summary cards --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="text-sm font-medium text-gray-500">Total Revenue</div>
                <div class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($summary['totals']['revenue'], 2) }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="text-sm font-medium text-gray-500">Total COGS</div>
                <div class="mt-1 text-2xl font-bold {{ $summary['totals']['cogs'] > 0 ? 'text-red-600' : 'text-gray-900' }}">
                    {{ number_format($summary['totals']['cogs'], 2) }}
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="text-sm font-medium text-gray-500">Overall Cost %</div>
                <div class="mt-1 text-2xl font-bold {{ $summary['totals']['cost_pct'] > 35 ? 'text-red-600' : ($summary['totals']['cost_pct'] > 30 ? 'text-amber-600' : 'text-green-600') }}">
                    {{ $summary['totals']['cost_pct'] }}%
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="text-sm font-medium text-gray-500">Total Wastage</div>
                <div class="mt-1 text-2xl font-bold text-orange-600">{{ number_format($summary['totals']['wastage'], 2) }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="text-sm font-medium text-gray-500">Staff Meals</div>
                <div class="mt-1 text-2xl font-bold text-purple-600">{{ number_format($summary['totals']['staff_meals'], 2) }}</div>
            </div>
        </div>

        {{-- P&L Table --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="text-left py-3 px-4 font-semibold text-gray-600 w-48">Metric</th>
                            @foreach ($summary['categories'] as $cat)
                                <th class="text-right py-3 px-4 font-semibold text-gray-600 min-w-[120px]">
                                    <div class="flex items-center justify-end gap-2">
                                        @if ($cat['color'])
                                            <span class="w-2.5 h-2.5 rounded-full inline-block" style="background:{{ $cat['color'] }}"></span>
                                        @endif
                                        {{ $cat['name'] }}
                                    </div>
                                </th>
                            @endforeach
                            <th class="text-right py-3 px-4 font-bold text-gray-800 min-w-[120px] bg-gray-100">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        {{-- Revenue --}}
                        <tr class="bg-blue-50/40">
                            <td class="py-2.5 px-4 font-semibold text-gray-700">Revenue</td>
                            @foreach ($summary['categories'] as $cat)
                                <td class="py-2.5 px-4 text-right font-medium text-gray-900">{{ number_format($cat['revenue'], 2) }}</td>
                            @endforeach
                            <td class="py-2.5 px-4 text-right font-bold text-gray-900 bg-gray-50">{{ number_format($summary['totals']['revenue'], 2) }}</td>
                        </tr>

                        {{-- Divider --}}
                        <tr>
                            <td colspan="{{ count($summary['categories']) + 2 }}" class="py-1 px-4 bg-gray-50">
                                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Cost of Goods</span>
                            </td>
                        </tr>

                        {{-- Opening Stock --}}
                        <tr>
                            <td class="py-2.5 px-4 text-gray-600 pl-6">Opening Stock</td>
                            @foreach ($summary['categories'] as $cat)
                                <td class="py-2.5 px-4 text-right text-gray-700">{{ number_format($cat['opening_stock'], 2) }}</td>
                            @endforeach
                            <td class="py-2.5 px-4 text-right font-medium text-gray-800 bg-gray-50">{{ number_format($summary['totals']['opening_stock'], 2) }}</td>
                        </tr>

                        {{-- Purchases --}}
                        <tr>
                            <td class="py-2.5 px-4 text-gray-600 pl-6">(+) Purchases</td>
                            @foreach ($summary['categories'] as $cat)
                                <td class="py-2.5 px-4 text-right text-gray-700">{{ number_format($cat['purchases'], 2) }}</td>
                            @endforeach
                            <td class="py-2.5 px-4 text-right font-medium text-gray-800 bg-gray-50">{{ number_format($summary['totals']['purchases'], 2) }}</td>
                        </tr>

                        {{-- Transfer In --}}
                        @if ($summary['outlet_id'])
                        <tr>
                            <td class="py-2.5 px-4 text-gray-600 pl-6">(+) Transfer In</td>
                            @foreach ($summary['categories'] as $cat)
                                <td class="py-2.5 px-4 text-right text-gray-700">{{ number_format($cat['transfer_in'], 2) }}</td>
                            @endforeach
                            <td class="py-2.5 px-4 text-right font-medium text-gray-800 bg-gray-50">{{ number_format($summary['totals']['transfer_in'], 2) }}</td>
                        </tr>

                        {{-- Transfer Out --}}
                        <tr>
                            <td class="py-2.5 px-4 text-gray-600 pl-6">(-) Transfer Out</td>
                            @foreach ($summary['categories'] as $cat)
                                <td class="py-2.5 px-4 text-right text-gray-700">{{ number_format($cat['transfer_out'], 2) }}</td>
                            @endforeach
                            <td class="py-2.5 px-4 text-right font-medium text-gray-800 bg-gray-50">{{ number_format($summary['totals']['transfer_out'], 2) }}</td>
                        </tr>
                        @endif

                        {{-- Closing Stock --}}
                        <tr>
                            <td class="py-2.5 px-4 text-gray-600 pl-6">(-) Closing Stock</td>
                            @foreach ($summary['categories'] as $cat)
                                <td class="py-2.5 px-4 text-right text-gray-700">{{ number_format($cat['closing_stock'], 2) }}</td>
                            @endforeach
                            <td class="py-2.5 px-4 text-right font-medium text-gray-800 bg-gray-50">{{ number_format($summary['totals']['closing_stock'], 2) }}</td>
                        </tr>

                        {{-- COGS --}}
                        <tr class="bg-red-50/50 border-t-2 border-gray-300">
                            <td class="py-3 px-4 font-bold text-gray-800">= COGS</td>
                            @foreach ($summary['categories'] as $cat)
                                <td class="py-3 px-4 text-right font-bold text-gray-900">{{ number_format($cat['cogs'], 2) }}</td>
                            @endforeach
                            <td class="py-3 px-4 text-right font-bold text-gray-900 bg-gray-100">{{ number_format($summary['totals']['cogs'], 2) }}</td>
                        </tr>

                        {{-- Cost % --}}
                        <tr class="border-t-2 border-gray-300">
                            <td class="py-3 px-4 font-bold text-gray-800">Cost %</td>
                            @foreach ($summary['categories'] as $cat)
                                <td class="py-3 px-4 text-right font-bold {{ $cat['cost_pct'] > 35 ? 'text-red-600' : ($cat['cost_pct'] > 30 ? 'text-amber-600' : 'text-green-600') }}">
                                    {{ $cat['cost_pct'] }}%
                                </td>
                            @endforeach
                            <td class="py-3 px-4 text-right font-bold bg-gray-100 {{ $summary['totals']['cost_pct'] > 35 ? 'text-red-600' : ($summary['totals']['cost_pct'] > 30 ? 'text-amber-600' : 'text-green-600') }}">
                                {{ $summary['totals']['cost_pct'] }}%
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>
        </div>

        {{-- Per-category cost % bars --}}
        <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-sm font-semibold text-gray-600 mb-4">Cost % by Category</h3>
            <div class="space-y-3">
                @foreach ($summary['categories'] as $cat)
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="flex items-center gap-2">
                                @if ($cat['color'])
                                    <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $cat['color'] }}"></span>
                                @endif
                                <span class="font-medium text-gray-700">{{ $cat['name'] }}</span>
                            </span>
                            <span class="font-semibold {{ $cat['cost_pct'] > 35 ? 'text-red-600' : ($cat['cost_pct'] > 30 ? 'text-amber-600' : 'text-green-600') }}">
                                {{ $cat['cost_pct'] }}%
                            </span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2.5">
                            <div class="h-2.5 rounded-full transition-all duration-500 {{ $cat['cost_pct'] > 35 ? 'bg-red-500' : ($cat['cost_pct'] > 30 ? 'bg-amber-500' : 'bg-green-500') }}"
                                 style="width: {{ min($cat['cost_pct'], 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Wastage Breakdown --}}
        @if (!empty($summary['wastage_detail']['groups']))
            <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-600">Wastage Breakdown</h3>
                    <span class="text-sm font-bold text-orange-600">RM {{ number_format($summary['wastage_detail']['total'], 2) }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                            <tr>
                                <th class="px-4 py-2.5 text-left">Item</th>
                                <th class="px-4 py-2.5 text-left">Type</th>
                                <th class="px-4 py-2.5 text-right">Quantity</th>
                                <th class="px-4 py-2.5 text-left">UOM</th>
                                <th class="px-4 py-2.5 text-right">Total Cost (RM)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($summary['wastage_detail']['groups'] as $catName => $group)
                                <tr class="bg-gray-50 border-t border-gray-200">
                                    <td colspan="4" class="px-4 py-2 font-semibold text-gray-700 text-xs uppercase tracking-wider">{{ $catName }}</td>
                                    <td class="px-4 py-2 text-right font-semibold text-orange-600">{{ number_format($group['total'], 2) }}</td>
                                </tr>
                                @foreach ($group['items'] as $item)
                                    <tr class="border-b border-gray-50 hover:bg-gray-50/50">
                                        <td class="px-4 py-2 pl-6 text-gray-800">
                                            <div class="flex items-center gap-2">
                                                {{ $item['name'] }}
                                                @if ($item['is_prep'])
                                                    <span class="px-1.5 py-0.5 bg-amber-100 text-amber-700 text-xs font-semibold rounded">PREP</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-2">
                                            @if ($item['type'] === 'recipe')
                                                <span class="px-1.5 py-0.5 bg-indigo-100 text-indigo-700 text-xs font-semibold rounded">Recipe</span>
                                            @else
                                                <span class="text-gray-500 text-xs">Ingredient</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ number_format($item['quantity'], 2) }}</td>
                                        <td class="px-4 py-2 text-gray-500 text-xs">{{ $item['uom'] }}</td>
                                        <td class="px-4 py-2 text-right tabular-nums font-medium text-orange-600">{{ number_format($item['total_cost'], 2) }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                            <tr>
                                <td colspan="4" class="px-4 py-2.5 text-right font-semibold text-gray-700">Total Wastage</td>
                                <td class="px-4 py-2.5 text-right font-bold text-orange-600 tabular-nums">{{ number_format($summary['wastage_detail']['total'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        {{-- Staff Meals Breakdown --}}
        @if (!empty($summary['staff_meals_detail']['groups']))
            <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-600">Staff Meals Breakdown</h3>
                    <span class="text-sm font-bold text-purple-600">RM {{ number_format($summary['staff_meals_detail']['total'], 2) }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                            <tr>
                                <th class="px-4 py-2.5 text-left">Item</th>
                                <th class="px-4 py-2.5 text-left">Type</th>
                                <th class="px-4 py-2.5 text-right">Quantity</th>
                                <th class="px-4 py-2.5 text-left">UOM</th>
                                <th class="px-4 py-2.5 text-right">Total Cost (RM)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($summary['staff_meals_detail']['groups'] as $catName => $group)
                                <tr class="bg-gray-50 border-t border-gray-200">
                                    <td colspan="4" class="px-4 py-2 font-semibold text-gray-700 text-xs uppercase tracking-wider">{{ $catName }}</td>
                                    <td class="px-4 py-2 text-right font-semibold text-purple-600">{{ number_format($group['total'], 2) }}</td>
                                </tr>
                                @foreach ($group['items'] as $item)
                                    <tr class="border-b border-gray-50 hover:bg-gray-50/50">
                                        <td class="px-4 py-2 pl-6 text-gray-800">
                                            <div class="flex items-center gap-2">
                                                {{ $item['name'] }}
                                                @if ($item['is_prep'])
                                                    <span class="px-1.5 py-0.5 bg-amber-100 text-amber-700 text-xs font-semibold rounded">PREP</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-2">
                                            @if ($item['type'] === 'recipe')
                                                <span class="px-1.5 py-0.5 bg-indigo-100 text-indigo-700 text-xs font-semibold rounded">Recipe</span>
                                            @else
                                                <span class="text-gray-500 text-xs">Ingredient</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ number_format($item['quantity'], 2) }}</td>
                                        <td class="px-4 py-2 text-gray-500 text-xs">{{ $item['uom'] }}</td>
                                        <td class="px-4 py-2 text-right tabular-nums font-medium text-purple-600">{{ number_format($item['total_cost'], 2) }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                            <tr>
                                <td colspan="4" class="px-4 py-2.5 text-right font-semibold text-gray-700">Total Staff Meals</td>
                                <td class="px-4 py-2.5 text-right font-bold text-purple-600 tabular-nums">{{ number_format($summary['staff_meals_detail']['total'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif
    @else
        {{-- Empty state --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center text-gray-400">
            <div class="text-4xl mb-3">📊</div>
            <p class="font-medium">No data for {{ \Carbon\Carbon::createFromFormat('Y-m', $period)->format('F Y') }}</p>
            <p class="text-sm mt-1">Add cost categories in Settings, then record sales and purchases to see your cost summary.</p>
        </div>
    @endif
</div>
