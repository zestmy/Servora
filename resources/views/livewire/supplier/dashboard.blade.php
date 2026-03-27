<div>
    <h2 class="text-lg font-semibold text-gray-700 mb-6">Welcome, {{ Auth::guard('supplier')->user()->name }}</h2>

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Pending Orders</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $pendingPos }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Total Orders</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $totalPos }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Active Products</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $activeProducts }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Outstanding Amount</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">RM {{ number_format($outstandingInvoices, 2) }}</p>
        </div>
    </div>

    {{-- Recent Orders --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700">Recent Orders</h3>
        </div>
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">PO Number</th>
                    <th class="px-4 py-3 text-left">Outlet</th>
                    <th class="px-4 py-3 text-center">Date</th>
                    <th class="px-4 py-3 text-right">Amount</th>
                    <th class="px-4 py-3 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($recentOrders as $po)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('supplier.orders.show', $po->id) }}" class="text-indigo-600 hover:underline font-mono text-xs">{{ $po->po_number }}</a>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $po->outlet?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $po->order_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium">{{ number_format($po->total_amount, 2) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                {{ match($po->status) { 'approved' => 'bg-indigo-100 text-indigo-700', 'sent' => 'bg-blue-100 text-blue-700', 'received' => 'bg-green-100 text-green-700', default => 'bg-gray-100 text-gray-600' } }}">
                                {{ ucfirst($po->status) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
