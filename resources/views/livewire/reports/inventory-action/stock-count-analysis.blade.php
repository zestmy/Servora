<div>
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-700">Stock Count Analysis</h2>
            <p class="text-xs text-gray-400 mt-0.5">Variance analysis between expected and counted quantities</p>
        </div>
        <a href="{{ route('reports.hub') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">Back to Reports</a>
    </div>

    {{-- Filters --}}
    @include('livewire.reports.partials.report-filters', ['showOutlet' => true, 'showSupplier' => false, 'exportAction' => 'exportCsv'])

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto"><table class="min-w-[1100px] divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Ingredient</th>
                    <th class="px-4 py-3 text-left">Code</th>
                    <th class="px-4 py-3 text-left">UOM</th>
                    <th class="px-4 py-3 text-right">Expected Qty</th>
                    <th class="px-4 py-3 text-right">Counted Qty</th>
                    <th class="px-4 py-3 text-right">Variance Qty</th>
                    <th class="px-4 py-3 text-right">Variance %</th>
                    <th class="px-4 py-3 text-right">Value Variance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($lines as $line)
                    @php
                        $isNeg = floatval($line->variance_quantity) < 0;
                        $isPos = floatval($line->variance_quantity) > 0;
                    @endphp
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $line->ingredient_name }}</td>
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ $line->ingredient_code ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $line->uom ?? '-' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ number_format($line->system_quantity, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ number_format($line->actual_quantity, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium {{ $isNeg ? 'text-red-600' : ($isPos ? 'text-green-600' : 'text-gray-500') }}">
                            {{ $isPos ? '+' : '' }}{{ number_format($line->variance_quantity, 2) }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums {{ $isNeg ? 'text-red-600' : ($isPos ? 'text-green-600' : 'text-gray-500') }}">
                            {{ $isPos ? '+' : '' }}{{ number_format($line->variance_pct, 1) }}%
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums {{ $isNeg ? 'text-red-600' : ($isPos ? 'text-green-600' : 'text-gray-500') }}">
                            {{ number_format($line->variance_cost, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-gray-400">
                            <p class="font-medium">No stock count analysis data</p>
                            <p class="text-xs mt-1">Complete stock takes to see variance analysis.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table></div>

        @if ($lines->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $lines->links() }}
            </div>
        @endif
    </div>
</div>
