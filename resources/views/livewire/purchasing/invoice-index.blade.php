<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('purchasing.index') }}" class="text-gray-400 hover:text-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="text-lg font-semibold text-gray-700">Procurement Invoices</h2>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        @foreach ($stats as $stat)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-xs text-gray-400 uppercase tracking-wider">{{ $stat['label'] }}</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
            <div class="flex-1 min-w-48">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search invoice number..."
                       class="w-full rounded-lg border-gray-300 text-sm" />
            </div>
            <select wire:model.live="typeFilter" class="rounded-lg border-gray-300 text-sm">
                <option value="">All Types</option>
                <option value="supplier">Supplier</option>
                <option value="cpu_to_outlet">CPU to Outlet</option>
            </select>
            <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm">
                <option value="">All Status</option>
                <option value="draft">Draft</option>
                <option value="issued">Issued</option>
                <option value="paid">Paid</option>
                <option value="overdue">Overdue</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <div class="flex items-center gap-1">
                <input type="date" wire:model.live="dateFrom" class="rounded-lg border-gray-300 text-sm" />
                <span class="text-gray-400 text-xs">to</span>
                <input type="date" wire:model.live="dateTo" class="rounded-lg border-gray-300 text-sm" />
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Invoice #</th>
                    <th class="px-4 py-3 text-left">Type</th>
                    <th class="px-4 py-3 text-left">Outlet / Supplier</th>
                    <th class="px-4 py-3 text-center">Date</th>
                    <th class="px-4 py-3 text-center">Due</th>
                    <th class="px-4 py-3 text-center">Items</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($invoices as $inv)
                    @php
                        $badge = match($inv->status) {
                            'draft'     => 'bg-gray-100 text-gray-600',
                            'issued'    => 'bg-blue-100 text-blue-700',
                            'paid'      => 'bg-green-100 text-green-700',
                            'overdue'   => 'bg-red-100 text-red-600',
                            'cancelled' => 'bg-gray-100 text-gray-500',
                            default     => 'bg-gray-100 text-gray-500',
                        };
                    @endphp
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 font-mono text-xs font-medium text-indigo-600">{{ $inv->invoice_number }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded text-xs {{ $inv->type === 'supplier' ? 'bg-purple-50 text-purple-600' : 'bg-amber-50 text-amber-600' }}">
                                {{ $inv->type === 'supplier' ? 'Supplier' : 'CPU → Outlet' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-700 text-xs">
                            {{ $inv->type === 'supplier' ? ($inv->supplier?->name ?? '—') : ($inv->outlet?->name ?? '—') }}
                        </td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $inv->issued_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-center">
                            @if ($inv->due_date)
                                <span class="{{ $inv->due_date->isPast() && $inv->status !== 'paid' ? 'text-red-500 font-medium' : 'text-gray-500' }}">
                                    {{ $inv->due_date->format('d M Y') }}
                                </span>
                            @else
                                <span class="text-gray-300">&mdash;</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center text-gray-600">{{ $inv->lines_count }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">{{ number_format($inv->total_amount, 2) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                {{ ucfirst($inv->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1">
                                @if ($inv->status === 'issued')
                                    <button wire:click="markPaid({{ $inv->id }})" wire:confirm="Mark as paid?"
                                            title="Mark Paid" class="text-green-500 hover:text-green-700 transition p-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                    <button wire:click="cancelInvoice({{ $inv->id }})" wire:confirm="Cancel this invoice?"
                                            title="Cancel" class="text-red-400 hover:text-red-600 transition p-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">No invoices found.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($invoices->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $invoices->links() }}</div>
        @endif
    </div>
</div>
