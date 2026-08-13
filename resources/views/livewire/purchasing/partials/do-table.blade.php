<div class="card overflow-hidden">
    <table class="table-surface min-w-full">
        <thead>
            <tr>
                <th class="px-4 py-3 text-left">DO Number</th>
                <th class="px-4 py-3 text-left">PO Reference</th>
                <th class="px-4 py-3 text-left">Outlet</th>
                <th class="px-4 py-3 text-left">Supplier</th>
                <th class="px-4 py-3 text-center">Delivery Date</th>
                <th class="px-4 py-3 text-center">Items</th>
                <th class="px-4 py-3 text-center">Status</th>
                <th class="px-4 py-3 text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($deliveryOrders as $do)
                @php
                    $badge = match($do->status) {
                        'pending'  => 'bg-yellow-100 text-yellow-700',
                        'received' => 'bg-success-100 text-success-700',
                        'partial'  => 'bg-blue-100 text-blue-700',
                        'rejected' => 'bg-danger-100 text-danger-600',
                        default    => 'bg-gray-100 text-gray-500',
                    };
                @endphp
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-4 py-3 font-mono text-xs font-medium text-gray-700">{{ $do->do_number }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $do->purchaseOrder?->po_number ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600 text-xs">{{ $do->outlet?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-700">{{ $do->supplier?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-center text-gray-500">{{ $do->delivery_date?->format('d M Y') ?? '—' }}</td>
                    <td class="px-4 py-3 text-center text-gray-600">{{ $do->lines_count }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                            {{ ucfirst($do->status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-center gap-1">
                            {{-- Action menu (PDF, Print, Share) --}}
                            <x-doc-action-menu
                                :pdfUrl="route('purchasing.pdf', ['type' => 'do', 'id' => $do->id])"
                                :docNumber="$do->do_number"
                                docType="Delivery Order"
                            />
                            @if ($isSystemAdmin)
                                <button wire:click="adminDeleteDo({{ $do->id }})"
                                        data-confirm-delete="Delete '{{ $do->do_number }}' and related GRN? This action cannot be undone."
                                        title="Admin Delete"
                                        class="text-danger-400 hover:text-danger-600 transition p-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-4 py-12 text-center text-gray-600">
                        <p class="font-medium">No deliveries yet</p>
                        <p class="text-xs mt-1">Deliveries appear here when goods arrive against an approved purchase order — use "Receive" on an order in the Orders (PO) tab.</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if ($deliveryOrders->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $deliveryOrders->links() }}</div>
    @endif
</div>
