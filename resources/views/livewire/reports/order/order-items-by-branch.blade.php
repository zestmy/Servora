<div>
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('reports.hub') }}" class="text-gray-400 hover:text-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h2 class="text-lg font-semibold text-gray-700">Order Items By Branch</h2>
        </div>
    </div>

    @include('livewire.reports.partials.report-filters', [
        'outlets'      => $outlets,
        'suppliers'    => $suppliers,
        'showSupplier' => true,
        'exportAction' => 'exportCsv',
    ])

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Outlet</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Ingredient</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500">Total Quantity</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500">Total Cost</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php $currentOutlet = null; @endphp
                    @forelse ($items as $item)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-800">
                                @if ($currentOutlet !== $item->outlet_name)
                                    {{ $item->outlet_name }}
                                    @php $currentOutlet = $item->outlet_name; @endphp
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $item->ingredient_name }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ number_format((float) $item->total_quantity, 2) }}</td>
                            <td class="px-4 py-3 text-right font-medium text-gray-800">{{ number_format((float) $item->total_cost, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-400">No order items found for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($items->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $items->links() }}
            </div>
        @endif
    </div>
</div>
