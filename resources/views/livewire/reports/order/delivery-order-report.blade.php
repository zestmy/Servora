<div>
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('reports.hub') }}" class="text-gray-400 hover:text-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h2 class="text-lg font-semibold text-gray-700">Delivery Order Report</h2>
        </div>
    </div>

    @include('livewire.reports.partials.report-filters', [
        'outlets'      => $outlets,
        'suppliers'    => $suppliers,
        'showSupplier' => false,
        'exportAction' => 'exportCsv',
    ])

    {{-- Status filter --}}
    <div class="mb-4">
        <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="pending">Pending</option>
            <option value="in_transit">In Transit</option>
            <option value="delivered">Delivered</option>
            <option value="cancelled">Cancelled</option>
        </select>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">DO Number</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">PO Number</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Outlet</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Supplier</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Delivery Date</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500">Sequence</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500">Items</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($deliveries as $do)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $do->do_number }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $do->purchaseOrder?->po_number ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $do->outlet?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $do->supplier?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $do->delivery_date?->format('d M Y') }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'px-2 py-0.5 rounded-full text-xs font-medium',
                                    'bg-gray-100 text-gray-600'     => $do->status === 'draft',
                                    'bg-yellow-100 text-yellow-700' => $do->status === 'pending',
                                    'bg-blue-100 text-blue-700'     => $do->status === 'in_transit',
                                    'bg-green-100 text-green-700'   => $do->status === 'delivered',
                                    'bg-red-100 text-red-700'       => $do->status === 'cancelled',
                                ])>{{ ucfirst(str_replace('_', ' ', $do->status)) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ $do->delivery_sequence }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ $do->lines_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-400">No delivery orders found for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($deliveries->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $deliveries->links() }}
            </div>
        @endif
    </div>
</div>
