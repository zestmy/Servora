<div>
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('reports.hub') }}" class="text-gray-400 hover:text-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h2 class="text-lg font-semibold text-gray-700">Order History</h2>
        </div>
    </div>

    @include('livewire.reports.partials.report-filters', [
        'outlets'      => $outlets,
        'suppliers'    => $suppliers,
        'showSupplier' => true,
        'exportAction' => 'exportCsv',
    ])

    {{-- Status filter --}}
    <div class="mb-4">
        <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="sent">Sent</option>
            <option value="partial">Partial</option>
            <option value="received">Received</option>
            <option value="cancelled">Cancelled</option>
        </select>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">PO Number</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Outlet</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Supplier</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Order Date</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500">Items</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500">Total Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($orders as $order)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $order->po_number }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $order->outlet?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $order->supplier?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $order->order_date?->format('d M Y') }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'px-2 py-0.5 rounded-full text-xs font-medium',
                                    'bg-gray-100 text-gray-600'    => $order->status === 'draft',
                                    'bg-yellow-100 text-yellow-700' => $order->status === 'pending',
                                    'bg-blue-100 text-blue-700'    => in_array($order->status, ['approved', 'sent']),
                                    'bg-indigo-100 text-indigo-700' => $order->status === 'partial',
                                    'bg-green-100 text-green-700'  => $order->status === 'received',
                                    'bg-red-100 text-red-700'      => $order->status === 'cancelled',
                                ])>{{ ucfirst($order->status) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ $order->lines_count }}</td>
                            <td class="px-4 py-3 text-right font-medium text-gray-800">{{ number_format((float) $order->total_amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-400">No orders found for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($orders->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</div>
