<div>
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-700">Inventory Variance</h2>
            <p class="text-xs text-gray-400 mt-0.5">Expected vs actual inventory comparison with value impact</p>
        </div>
        <a href="{{ route('reports.hub') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">Back to Reports</a>
    </div>

    {{-- Filters --}}
    @include('livewire.reports.partials.report-filters', ['showOutlet' => true, 'showSupplier' => false, 'exportAction' => 'exportCsv'])

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Ingredient</th>
                    <th class="px-4 py-3 text-right">Expected Qty</th>
                    <th class="px-4 py-3 text-right">Actual Qty</th>
                    <th class="px-4 py-3 text-right">Variance Qty</th>
                    <th class="px-4 py-3 text-right">Variance %</th>
                    <th class="px-4 py-3 text-right">Value Impact</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($items as $item)
                    @php
                        $vqty = floatval($item->variance_qty);
                        $isNeg = $vqty < 0;
                        $isPos = $vqty > 0;
                    @endphp
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $item->name }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ number_format($item->expected_qty, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ number_format($item->actual_qty, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium {{ $isNeg ? 'text-red-600' : ($isPos ? 'text-green-600' : 'text-gray-500') }}">
                            {{ $isPos ? '+' : '' }}{{ number_format($vqty, 2) }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums {{ $isNeg ? 'text-red-600' : ($isPos ? 'text-green-600' : 'text-gray-500') }}">
                            {{ floatval($item->variance_pct) > 0 ? '+' : '' }}{{ number_format($item->variance_pct, 1) }}%
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium {{ $isNeg ? 'text-red-600' : ($isPos ? 'text-green-600' : 'text-gray-500') }}">
                            {{ number_format($item->value_impact, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-gray-400">
                            <p class="font-medium">No variance data</p>
                            <p class="text-xs mt-1">Record stock takes and purchases to see inventory variance.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($items->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $items->links() }}
            </div>
        @endif
    </div>
</div>
