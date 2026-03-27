<div>
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('supplier.orders') }}" class="text-gray-400 hover:text-gray-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h2 class="text-lg font-semibold text-gray-700">{{ $order->po_number }}</h2>
            <p class="text-sm text-gray-400">{{ $order->outlet?->name }} &middot; {{ $order->order_date->format('d M Y') }}</p>
        </div>
        <span class="ml-auto px-3 py-1 rounded-full text-xs font-medium
            {{ match($order->status) { 'approved' => 'bg-indigo-100 text-indigo-700', 'sent' => 'bg-blue-100 text-blue-700', 'partial' => 'bg-orange-100 text-orange-700', 'received' => 'bg-green-100 text-green-700', default => 'bg-gray-100 text-gray-600' } }}">
            {{ ucfirst($order->status) }}
        </span>
    </div>

    {{-- Order Details --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs text-gray-400 uppercase tracking-wider mb-2">Order Info</p>
            <div class="space-y-1.5 text-sm">
                <div class="flex justify-between"><span class="text-gray-500">Expected Delivery</span><span class="text-gray-700">{{ $order->expected_delivery_date?->format('d M Y') ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Receiver</span><span class="text-gray-700">{{ $order->receiver_name ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Created By</span><span class="text-gray-700">{{ $order->createdBy?->name ?? '—' }}</span></div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs text-gray-400 uppercase tracking-wider mb-2">Financials</p>
            <div class="space-y-1.5 text-sm">
                <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span class="text-gray-700">{{ number_format($order->subtotal, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Tax</span><span class="text-gray-700">{{ number_format($order->tax_amount, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Delivery</span><span class="text-gray-700">{{ number_format($order->delivery_charges, 2) }}</span></div>
                <div class="flex justify-between font-bold border-t pt-1.5"><span class="text-gray-700">Total</span><span class="text-gray-900">{{ number_format($order->total_amount, 2) }}</span></div>
            </div>
        </div>
        @if ($order->notes)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-xs text-gray-400 uppercase tracking-wider mb-2">Notes</p>
                <p class="text-sm text-gray-600">{{ $order->notes }}</p>
            </div>
        @endif
    </div>

    {{-- Line Items --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">#</th>
                    <th class="px-4 py-3 text-left">Item</th>
                    <th class="px-4 py-3 text-center">Quantity</th>
                    <th class="px-4 py-3 text-left">UOM</th>
                    <th class="px-4 py-3 text-right">Unit Cost</th>
                    <th class="px-4 py-3 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach ($order->lines as $i => $line)
                    <tr>
                        <td class="px-4 py-3 text-gray-400">{{ $i + 1 }}</td>
                        <td class="px-4 py-3 font-medium text-gray-700">{{ $line->ingredient?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-center tabular-nums">{{ number_format($line->quantity, 2) }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $line->uom?->abbreviation ?? '' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($line->unit_cost, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">{{ number_format($line->total_cost, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
