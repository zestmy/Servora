<div>
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-700">Purchase Analysis</h2>
            <p class="text-xs text-gray-400 mt-0.5">Spend breakdown by supplier and ingredient category</p>
        </div>
        <a href="{{ route('reports.index') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">Back to Reports</a>
    </div>

    {{-- Filters --}}
    @include('livewire.reports.partials.report-filters', [
        'outlets'      => $outlets,
        'suppliers'    => $suppliers,
        'showSupplier' => true,
        'exportAction' => 'exportCsv',
    ])

    {{-- Data Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3">Supplier</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3 text-right">Total Spend</th>
                        <th class="px-4 py-3 text-right">Item Count</th>
                        <th class="px-4 py-3 text-right">Avg Cost/Item</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($rows as $row)
                        <tr class="odd:bg-white even:bg-gray-50/50 hover:bg-gray-50 transition">
                            <td class="px-4 py-2.5 font-medium text-gray-800">{{ $row->supplier_name }}</td>
                            <td class="px-4 py-2.5 text-gray-600">{{ $row->category_name }}</td>
                            <td class="px-4 py-2.5 text-right text-gray-700">{{ number_format($row->total_spend, 2) }}</td>
                            <td class="px-4 py-2.5 text-right text-gray-700">{{ $row->item_count }}</td>
                            <td class="px-4 py-2.5 text-right text-gray-700">{{ number_format($row->avg_cost_per_item, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-400">No purchase records found for the selected filters.</td>
                        </tr>
                    @endforelse

                    {{-- Summary Row --}}
                    @if ($rows->isNotEmpty())
                        <tr class="bg-gray-100 font-semibold text-gray-800">
                            <td class="px-4 py-2.5" colspan="2">Totals</td>
                            <td class="px-4 py-2.5 text-right">{{ number_format($totals->grand_total_spend ?? 0, 2) }}</td>
                            <td class="px-4 py-2.5 text-right">{{ $totals->grand_item_count ?? 0 }}</td>
                            <td class="px-4 py-2.5 text-right">&mdash;</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($rows->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $rows->links() }}
            </div>
        @endif
    </div>
</div>
