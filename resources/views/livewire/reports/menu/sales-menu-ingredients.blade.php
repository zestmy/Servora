<div>
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="page-title">Sales Menu & Ingredients</h2>
            <p class="text-xs text-gray-600 mt-0.5">Sales by menu item with ingredient cost breakdown</p>
        </div>
        <a href="{{ route('reports.hub') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">Back to Reports</a>
    </div>

    {{-- Filters --}}
    @include('livewire.reports.partials.report-filters', ['showOutlet' => true, 'showSupplier' => false, 'exportAction' => 'exportCsv'])

    {{-- Table --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto"><table class="table-surface min-w-[1100px]">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left">Recipe / Item</th>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-right">Units Sold</th>
                    <th class="px-4 py-3 text-right">Revenue</th>
                    <th class="px-4 py-3 text-right">Ingredient Cost</th>
                    <th class="px-4 py-3 text-right">Gross Profit</th>
                    <th class="px-4 py-3 text-right">Cost %</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    @php
                        $costPct = floatval($item->cost_pct);
                    @endphp
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $item->item_name ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $item->category_name ?: '-' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ number_format($item->units_sold, 0) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ number_format($item->revenue, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-danger-600">{{ number_format($item->ingredient_cost, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium {{ floatval($item->gross_profit) >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                            {{ number_format($item->gross_profit, 2) }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            <span class="{{ $costPct > 35 ? 'text-danger-600 font-medium' : ($costPct > 25 ? 'text-yellow-600' : 'text-success-600') }}">
                                {{ number_format($costPct, 1) }}%
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-600">
                            <p class="font-medium">No sales data</p>
                            <p class="text-xs mt-1">Record sales to see menu ingredient cost analysis.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table></div>

        @if ($items->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $items->links() }}
            </div>
        @endif
    </div>
</div>
