<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
            <tr>
                <th class="px-4 py-3 text-left">PO Number</th>
                @if ($seesAllOutlets)<th class="px-4 py-3 text-left">Outlet</th>@endif
                <th class="px-4 py-3 text-left">Supplier</th>
                <th class="px-4 py-3 text-center">Order Date</th>
                <th class="px-4 py-3 text-center">Expected</th>
                <th class="px-4 py-3 text-center">Items</th>
                <th class="px-4 py-3 text-right">Total (RM)</th>
                <th class="px-4 py-3 text-center">Status</th>
                <th class="px-4 py-3 text-center">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse ($orders as $po)
                @php
                    $badge = match($po->status) {
                        'draft'     => 'bg-gray-100 text-gray-600',
                        'submitted' => 'bg-yellow-100 text-yellow-700',
                        'approved'  => 'bg-indigo-100 text-indigo-700',
                        'sent'      => 'bg-blue-100 text-blue-700',
                        'partial'   => 'bg-orange-100 text-orange-700',
                        'received'  => 'bg-green-100 text-green-700',
                        'cancelled' => 'bg-red-100 text-red-600',
                        default     => 'bg-gray-100 text-gray-500',
                    };
                    $statusLabel = match($po->status) {
                        'submitted' => 'Pending Approval',
                        'approved'  => 'Approved',
                        'sent'      => 'Processing',
                        default     => ucfirst($po->status),
                    };
                    $canApproveThis = $isAppointed && collect($approverAssignments)->contains(function ($a) use ($po) {
                        return $a['outlet_id'] == $po->outlet_id
                            && ($a['department_id'] === null || $a['department_id'] == $po->department_id);
                    });
                @endphp
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-4 py-3">
                        <a href="{{ route('purchasing.orders.edit', $po->id) }}" class="font-mono text-xs font-medium text-indigo-600 hover:underline">{{ $po->po_number }}</a>
                    </td>
                    @if ($seesAllOutlets)
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $po->outlet?->name ?? '—' }}</td>
                    @endif
                    <td class="px-4 py-3 text-gray-700">{{ $po->supplier?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-center text-gray-500">{{ $po->order_date->format('d M Y') }}</td>
                    <td class="px-4 py-3 text-center">
                        @if ($po->expected_delivery_date)
                            <span class="{{ $po->expected_delivery_date->isPast() && ! in_array($po->status, ['received','cancelled']) ? 'text-red-500 font-medium' : 'text-gray-500' }}">
                                {{ $po->expected_delivery_date->format('d M Y') }}
                            </span>
                        @else
                            <span class="text-gray-300">&mdash;</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center text-gray-600">{{ $po->lines_count }}</td>
                    <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">{{ number_format($po->total_amount, 2) }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                            {{ $statusLabel }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-center gap-1">
                            {{-- PDF --}}
                            <a href="{{ route('purchasing.pdf', ['type' => 'po', 'id' => $po->id]) }}" target="_blank" title="Download PDF"
                               class="text-gray-400 hover:text-gray-600 transition p-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </a>

                            @if ($canApproveThis && $po->status === 'submitted')
                                {{-- Appointed approver actions --}}
                                <button wire:click="approvePo({{ $po->id }})"
                                        wire:confirm="Approve '{{ $po->po_number }}'?"
                                        title="Approve"
                                        class="text-green-500 hover:text-green-700 transition p-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </button>
                                <button wire:click="rejectPo({{ $po->id }})"
                                        wire:confirm="Reject '{{ $po->po_number }}'? This will cancel the PO."
                                        title="Reject"
                                        class="text-red-400 hover:text-red-600 transition p-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            @elseif ($isPurchasing)
                                {{-- Purchasing actions --}}
                                @if ($po->status === 'approved')
                                    <a href="{{ route('purchasing.convert-to-do', $po->id) }}" title="Convert to DO"
                                       class="text-green-500 hover:text-green-700 transition p-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </a>
                                @endif
                            @elseif (! $isPurchasing && ! $isAppointed)
                                {{-- Outlet actions --}}
                                @if ($po->status === 'draft')
                                    <a href="{{ route('purchasing.orders.edit', $po->id) }}" title="Edit"
                                       class="text-indigo-500 hover:text-indigo-700 transition p-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    <button wire:click="submitPo({{ $po->id }})"
                                            wire:confirm="{{ $requirePoApproval ? "Submit '{$po->po_number}' for approval?" : "Submit & approve '{$po->po_number}'?" }}"
                                            title="{{ $requirePoApproval ? 'Submit for Approval' : 'Submit & Approve' }}"
                                            class="text-blue-500 hover:text-blue-700 transition p-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                        </svg>
                                    </button>
                                @endif
                                @if (in_array($po->status, ['draft', 'submitted']))
                                    <button wire:click="cancel({{ $po->id }})"
                                            wire:confirm="Cancel '{{ $po->po_number }}'?"
                                            title="Cancel"
                                            class="text-orange-400 hover:text-orange-600 transition p-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                        </svg>
                                    </button>
                                @endif
                                @if ($po->status === 'draft')
                                    <button wire:click="delete({{ $po->id }})"
                                            wire:confirm="Permanently delete '{{ $po->po_number }}'?"
                                            title="Delete"
                                            class="text-red-400 hover:text-red-600 transition p-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                @endif
                            @endif

                            {{-- Rollback approved PO to draft --}}
                            @if ($canRollbackPo && $po->status === 'approved')
                                <button wire:click="rollbackPo({{ $po->id }})"
                                        wire:confirm="Roll back '{{ $po->po_number }}' to draft for amendment?"
                                        title="Rollback to Draft"
                                        class="text-amber-500 hover:text-amber-700 transition p-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a5 5 0 010 10H9m4-10l-4-4m4 4l-4 4"/>
                                    </svg>
                                </button>
                            @endif

                            {{-- System Admin delete (any status) --}}
                            @if ($isSystemAdmin)
                                <button wire:click="adminDeletePo({{ $po->id }})"
                                        wire:confirm="Delete '{{ $po->po_number }}' and all related DO/GRN? This action cannot be undone."
                                        title="Admin Delete"
                                        class="text-red-400 hover:text-red-600 transition p-1">
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
                    <td colspan="{{ $seesAllOutlets ? 9 : 8 }}" class="px-4 py-12 text-center text-gray-400">
                        <p class="font-medium">No purchase orders found</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if ($orders->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $orders->links() }}</div>
    @endif
</div>
