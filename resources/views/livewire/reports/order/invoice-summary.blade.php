<div>
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('reports.hub') }}" class="text-gray-400 hover:text-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h2 class="text-lg font-semibold text-gray-700">Invoice Summary</h2>
        </div>
    </div>

    @include('livewire.reports.partials.report-filters', [
        'outlets'      => $outlets,
        'suppliers'    => $suppliers,
        'showSupplier' => false,
        'exportAction' => 'exportCsv',
    ])

    {{-- Type & Status filters --}}
    <div class="flex flex-wrap gap-3 mb-4">
        <select wire:model.live="typeFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">All Types</option>
            <option value="supplier">Supplier</option>
            <option value="cpu_to_outlet">CPU to Outlet</option>
        </select>
        <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="pending">Pending</option>
            <option value="paid">Paid</option>
            <option value="overdue">Overdue</option>
            <option value="cancelled">Cancelled</option>
        </select>
    </div>

    {{-- Summary stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Outstanding</p>
            <p class="text-xl font-bold text-yellow-600 mt-1">{{ number_format((float) $totalOutstanding, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Paid</p>
            <p class="text-xl font-bold text-green-600 mt-1">{{ number_format((float) $totalPaid, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Overdue</p>
            <p class="text-xl font-bold text-red-600 mt-1">{{ number_format((float) $totalOverdue, 2) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Invoice Number</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Type</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Outlet / Supplier</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Issued Date</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Due Date</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500">Total Amount</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($invoices as $inv)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $inv->invoice_number }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                @if ($inv->type === 'cpu_to_outlet')
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">CPU to Outlet</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">Supplier</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ $inv->type === 'cpu_to_outlet' ? ($inv->outlet?->name ?? '-') : ($inv->supplier?->name ?? '-') }}
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $inv->issued_date?->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $inv->due_date?->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-right font-medium text-gray-800">{{ number_format((float) $inv->total_amount, 2) }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'px-2 py-0.5 rounded-full text-xs font-medium',
                                    'bg-gray-100 text-gray-600'     => $inv->status === 'draft',
                                    'bg-yellow-100 text-yellow-700' => $inv->status === 'pending',
                                    'bg-green-100 text-green-700'   => $inv->status === 'paid',
                                    'bg-red-100 text-red-700'       => in_array($inv->status, ['overdue', 'cancelled']),
                                ])>{{ ucfirst($inv->status) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-400">No invoices found for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($invoices->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
</div>
