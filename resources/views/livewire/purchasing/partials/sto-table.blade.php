<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
            <tr>
                <th class="px-4 py-3 text-left">STO Number</th>
                <th class="px-4 py-3 text-left">From</th>
                <th class="px-4 py-3 text-left">To Outlet</th>
                <th class="px-4 py-3 text-center">Date</th>
                <th class="px-4 py-3 text-center">Items</th>
                <th class="px-4 py-3 text-center">Type</th>
                <th class="px-4 py-3 text-right">Total</th>
                <th class="px-4 py-3 text-center">Status</th>
                <th class="px-4 py-3 text-center">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse ($stockTransfers as $sto)
                @php
                    $badge = match($sto->status) {
                        'draft'     => 'bg-gray-100 text-gray-600',
                        'sent'      => 'bg-blue-100 text-blue-700',
                        'received'  => 'bg-green-100 text-green-700',
                        'cancelled' => 'bg-red-100 text-red-600',
                        default     => 'bg-gray-100 text-gray-500',
                    };
                @endphp
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-4 py-3 font-mono text-xs font-medium text-indigo-600">{{ $sto->sto_number }}</td>
                    <td class="px-4 py-3 text-gray-600 text-xs">{{ $sto->cpu?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-700 text-xs">{{ $sto->toOutlet?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-center text-gray-500">{{ $sto->transfer_date->format('d M Y') }}</td>
                    <td class="px-4 py-3 text-center text-gray-600">{{ $sto->lines_count }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 rounded text-xs {{ $sto->is_chargeable ? 'bg-amber-50 text-amber-600' : 'bg-green-50 text-green-600' }}">
                            {{ $sto->is_chargeable ? 'Chargeable' : 'Free' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">
                        {{ $sto->is_chargeable ? number_format($sto->total_amount, 2) : '—' }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                            {{ ucfirst($sto->status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-center gap-1">
                            {{-- Action menu (Duplicate, Share) --}}
                            <x-doc-action-menu
                                :duplicateUrl="route('purchasing.transfers.create', ['duplicate' => $sto->id])"
                                :docNumber="$sto->sto_number"
                                docType="Stock Transfer"
                            />
                            @if ($sto->status === 'sent')
                                <button wire:click="receiveSto({{ $sto->id }})" wire:confirm="Confirm receipt of this transfer?"
                                        title="Receive" class="text-green-500 hover:text-green-700 transition p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </button>
                            @endif
                            @if ($sto->status === 'draft')
                                <button wire:click="sendSto({{ $sto->id }})" wire:confirm="Send this STO?"
                                        title="Send" class="text-blue-500 hover:text-blue-700 transition p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                </button>
                                <button wire:click="cancelSto({{ $sto->id }})" wire:confirm="Cancel this STO?"
                                        title="Cancel" class="text-red-400 hover:text-red-600 transition p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">No stock transfer orders found.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if ($stockTransfers->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $stockTransfers->links() }}</div>
    @endif
</div>
