<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-gray-700">Credit & Debit Notes</h2>
        <div class="flex items-center gap-4">
            <div class="text-sm text-gray-500">Outstanding: <span class="font-bold text-gray-800">RM {{ number_format($totalOutstanding, 2) }}</span></div>
            <a href="{{ route('supplier.credit-notes.create') }}"
               class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                Issue Credit Note
            </a>
        </div>
    </div>

    <div class="mb-4">
        <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm">
            <option value="">All Status</option>
            <option value="draft">Draft</option>
            <option value="issued">Issued</option>
            <option value="acknowledged">Acknowledged</option>
            <option value="applied">Applied</option>
            <option value="cancelled">Cancelled</option>
        </select>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Number</th>
                    <th class="px-4 py-3 text-left">Type</th>
                    <th class="px-4 py-3 text-left">Direction</th>
                    <th class="px-4 py-3 text-center">Issued</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($creditNotes as $cn)
                    @php
                        $statusBadge = match($cn->status) {
                            'draft'        => 'bg-gray-100 text-gray-600',
                            'issued'       => 'bg-blue-100 text-blue-700',
                            'acknowledged' => 'bg-teal-100 text-teal-700',
                            'applied'      => 'bg-green-100 text-green-700',
                            'cancelled'    => 'bg-red-100 text-red-600',
                            default        => 'bg-gray-100 text-gray-500',
                        };
                        $typeBadge = $cn->type === 'debit_note'
                            ? 'bg-orange-50 text-orange-600'
                            : 'bg-purple-50 text-purple-600';
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs font-medium text-gray-700">{{ $cn->credit_note_number }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded text-xs {{ $typeBadge }}">
                                {{ $cn->type === 'debit_note' ? 'Debit' : 'Credit' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600">{{ ucfirst($cn->direction ?? '—') }}</td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $cn->issued_date?->format('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">{{ number_format($cn->total_amount, 2) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusBadge }}">
                                {{ ucfirst($cn->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if ($cn->status === 'issued' && $cn->direction === 'issued')
                                <button wire:click="acknowledge({{ $cn->id }})" wire:confirm="Acknowledge this debit note?"
                                        class="text-xs text-teal-600 hover:text-teal-800 font-medium transition">
                                    Acknowledge
                                </button>
                            @else
                                <span class="text-gray-300">&mdash;</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No credit/debit notes found.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($creditNotes->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $creditNotes->links() }}</div>
        @endif
    </div>
</div>
