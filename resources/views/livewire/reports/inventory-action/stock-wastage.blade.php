<div>
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-700">Stock Wastage</h2>
            <p class="text-xs text-gray-400 mt-0.5">Wastage records with ingredient details and costs</p>
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
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Outlet</th>
                    <th class="px-4 py-3 text-left">Reference</th>
                    <th class="px-4 py-3 text-left">Ingredient</th>
                    <th class="px-4 py-3 text-right">Quantity</th>
                    <th class="px-4 py-3 text-left">UOM</th>
                    <th class="px-4 py-3 text-left">Reason</th>
                    <th class="px-4 py-3 text-right">Cost</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($lines as $line)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 text-gray-700">{{ $line->wastageRecord->wastage_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $line->wastageRecord->outlet?->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $line->wastageRecord->reference_number }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $line->ingredient?->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ number_format($line->quantity, 2) }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $line->uom?->abbreviation ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $line->reason ?? '-' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-red-600 font-medium">{{ number_format($line->total_cost, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-gray-400">
                            <p class="font-medium">No wastage records</p>
                            <p class="text-xs mt-1">No wastage data found for the selected period.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($lines->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $lines->links() }}
            </div>
        @endif
    </div>
</div>
