<div>
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-gray-700">Invoices</h2>
        <div class="text-sm text-gray-500">Outstanding: <span class="font-bold text-gray-800">RM {{ number_format($totalOutstanding, 2) }}</span></div>
    </div>

    <div class="mb-4">
        <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm">
            <option value="">All Status</option>
            <option value="issued">Issued</option>
            <option value="paid">Paid</option>
            <option value="overdue">Overdue</option>
        </select>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Invoice #</th>
                    <th class="px-4 py-3 text-center">Issued</th>
                    <th class="px-4 py-3 text-center">Due</th>
                    <th class="px-4 py-3 text-center">Items</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($invoices as $inv)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs font-medium text-gray-700">{{ $inv->invoice_number }}</td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $inv->issued_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-center {{ $inv->due_date?->isPast() && $inv->status !== 'paid' ? 'text-red-500 font-medium' : 'text-gray-500' }}">
                            {{ $inv->due_date?->format('d M Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-center text-gray-600">{{ $inv->lines_count }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">{{ number_format($inv->total_amount, 2) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                {{ match($inv->status) { 'issued' => 'bg-blue-100 text-blue-700', 'paid' => 'bg-green-100 text-green-700', 'overdue' => 'bg-red-100 text-red-600', default => 'bg-gray-100 text-gray-600' } }}">
                                {{ ucfirst($inv->status) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No invoices found.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($invoices->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $invoices->links() }}</div>
        @endif
    </div>
</div>
