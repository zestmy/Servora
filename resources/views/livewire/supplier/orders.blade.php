<div>
    <h2 class="text-lg font-semibold text-gray-700 mb-6">Purchase Orders</h2>

    <div class="flex gap-3 mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search PO number..." class="flex-1 max-w-md rounded-lg border-gray-300 text-sm" />
        <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm">
            <option value="">All Status</option>
            <option value="approved">Approved</option>
            <option value="sent">Sent</option>
            <option value="partial">Partial</option>
            <option value="received">Received</option>
        </select>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">PO Number</th>
                    <th class="px-4 py-3 text-left">Outlet</th>
                    <th class="px-4 py-3 text-center">Date</th>
                    <th class="px-4 py-3 text-center">Items</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($orders as $po)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('supplier.orders.show', $po->id) }}" class="text-indigo-600 hover:underline font-mono text-xs font-medium">{{ $po->po_number }}</a>
                        </td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $po->outlet?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $po->order_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-center text-gray-600">{{ $po->lines_count }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">{{ number_format($po->total_amount, 2) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                {{ match($po->status) { 'approved' => 'bg-indigo-100 text-indigo-700', 'sent' => 'bg-blue-100 text-blue-700', 'partial' => 'bg-orange-100 text-orange-700', 'received' => 'bg-green-100 text-green-700', default => 'bg-gray-100 text-gray-600' } }}">
                                {{ ucfirst($po->status) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No orders found.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($orders->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $orders->links() }}</div>
        @endif
    </div>
</div>
