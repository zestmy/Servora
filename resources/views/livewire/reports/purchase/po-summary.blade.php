<div>
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-700">PO Summary</h2>
            <p class="text-xs text-gray-400 mt-0.5">Purchase order overview by status and period</p>
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

    {{-- By Status --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
        <div class="px-4 py-3 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-600">By Status</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Count</th>
                        <th class="px-4 py-3 text-right">Total Value</th>
                        <th class="px-4 py-3 text-right">Avg Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($byStatus as $row)
                        <tr class="odd:bg-white even:bg-gray-50/50 hover:bg-gray-50 transition">
                            <td class="px-4 py-2.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ match($row->status) {
                                        'draft'     => 'bg-gray-100 text-gray-700',
                                        'pending'   => 'bg-yellow-100 text-yellow-700',
                                        'approved'  => 'bg-blue-100 text-blue-700',
                                        'sent'      => 'bg-indigo-100 text-indigo-700',
                                        'received'  => 'bg-green-100 text-green-700',
                                        'cancelled' => 'bg-red-100 text-red-700',
                                        default     => 'bg-gray-100 text-gray-700',
                                    } }}">
                                    {{ ucfirst($row->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-right text-gray-700">{{ $row->po_count }}</td>
                            <td class="px-4 py-2.5 text-right text-gray-700">{{ number_format($row->total_value, 2) }}</td>
                            <td class="px-4 py-2.5 text-right text-gray-700">{{ number_format($row->avg_value, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-400">No purchase orders found for the selected filters.</td>
                        </tr>
                    @endforelse

                    {{-- Summary Row --}}
                    @if ($byStatus->isNotEmpty())
                        <tr class="bg-gray-100 font-semibold text-gray-800">
                            <td class="px-4 py-2.5">Totals</td>
                            <td class="px-4 py-2.5 text-right">{{ $totals->grand_count ?? 0 }}</td>
                            <td class="px-4 py-2.5 text-right">{{ number_format($totals->grand_total ?? 0, 2) }}</td>
                            <td class="px-4 py-2.5 text-right">&mdash;</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($byStatus->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $byStatus->links() }}
            </div>
        @endif
    </div>

    {{-- By Period (Monthly) --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-600">By Period (Monthly)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3">Month</th>
                        <th class="px-4 py-3 text-right">Count</th>
                        <th class="px-4 py-3 text-right">Total Value</th>
                        <th class="px-4 py-3 text-right">Avg Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($byPeriod as $row)
                        <tr class="odd:bg-white even:bg-gray-50/50 hover:bg-gray-50 transition">
                            <td class="px-4 py-2.5 font-medium text-gray-800">{{ $row->period }}</td>
                            <td class="px-4 py-2.5 text-right text-gray-700">{{ $row->po_count }}</td>
                            <td class="px-4 py-2.5 text-right text-gray-700">{{ number_format($row->total_value, 2) }}</td>
                            <td class="px-4 py-2.5 text-right text-gray-700">{{ number_format($row->avg_value, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-400">No data for the selected period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
