<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
            <tr>
                <th class="px-4 py-3 text-left">PR Number</th>
                @if ($seesAllOutlets)<th class="px-4 py-3 text-left">Outlet</th>@endif
                <th class="px-4 py-3 text-center">Request Date</th>
                <th class="px-4 py-3 text-center">Needed By</th>
                <th class="px-4 py-3 text-center">Items</th>
                <th class="px-4 py-3 text-left">Requested By</th>
                <th class="px-4 py-3 text-center">Status</th>
                <th class="px-4 py-3 text-center">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse ($purchaseRequests as $pr)
                @php
                    $badge = match($pr->status) {
                        'draft'     => 'bg-gray-100 text-gray-600',
                        'submitted' => 'bg-yellow-100 text-yellow-700',
                        'approved'  => 'bg-green-100 text-green-700',
                        'rejected'  => 'bg-red-100 text-red-600',
                        'converted' => 'bg-indigo-100 text-indigo-700',
                        'cancelled' => 'bg-gray-100 text-gray-500',
                        default     => 'bg-gray-100 text-gray-500',
                    };
                    $canApprovePr = $isPrApprover && $pr->status === 'submitted';
                @endphp
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-4 py-3">
                        <a href="{{ route('purchasing.requests.edit', $pr->id) }}" class="font-mono text-xs font-medium text-indigo-600 hover:underline">{{ $pr->pr_number }}</a>
                    </td>
                    @if ($seesAllOutlets)
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $pr->outlet?->name ?? '—' }}</td>
                    @endif
                    <td class="px-4 py-3 text-center text-gray-500">{{ $pr->requested_date->format('d M Y') }}</td>
                    <td class="px-4 py-3 text-center">
                        @if ($pr->needed_by_date)
                            <span class="{{ $pr->needed_by_date->isPast() && ! in_array($pr->status, ['approved','converted','cancelled','rejected']) ? 'text-red-500 font-medium' : 'text-gray-500' }}">
                                {{ $pr->needed_by_date->format('d M Y') }}
                            </span>
                        @else
                            <span class="text-gray-300">&mdash;</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center text-gray-600">{{ $pr->lines_count }}</td>
                    <td class="px-4 py-3 text-gray-600 text-xs">{{ $pr->createdBy?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                            {{ ucfirst($pr->status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-center gap-1">
                            {{-- Action menu (PDF, Print, Duplicate, Share) --}}
                            <x-doc-action-menu
                                :pdfUrl="route('purchasing.pdf', ['type' => 'pr', 'id' => $pr->id])"
                                :duplicateUrl="route('purchasing.requests.create', ['duplicate' => $pr->id])"
                                :docNumber="$pr->pr_number"
                                docType="Purchase Request"
                            />

                            {{-- Approver: Approve / Reject --}}
                            @if ($canApprovePr)
                                <button wire:click="approvePr({{ $pr->id }})" wire:confirm="Approve '{{ $pr->pr_number }}'?"
                                        title="Approve" class="text-green-500 hover:text-green-700 transition p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                </button>
                                <div x-data="{ showReject: false }" class="relative inline-block">
                                    <button @click="showReject = !showReject" title="Reject" class="text-red-400 hover:text-red-600 transition p-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                    </button>
                                    <div x-show="showReject" x-cloak @click.outside="showReject = false"
                                         class="absolute right-0 top-8 z-20 bg-white border border-gray-200 rounded-lg shadow-lg p-3 w-64">
                                        <p class="text-xs font-medium text-gray-700 mb-2">Rejection Reason</p>
                                        <textarea wire:model="rejectReason" rows="2" class="w-full rounded-lg border-gray-300 text-xs mb-2" placeholder="Why is this being rejected?"></textarea>
                                        <button wire:click="rejectPr({{ $pr->id }})" @click="showReject = false"
                                                class="w-full px-3 py-1.5 bg-red-600 text-white text-xs font-medium rounded-lg hover:bg-red-700">
                                            Reject PR
                                        </button>
                                    </div>
                                </div>
                            @endif

                            {{-- Show rejection reason --}}
                            @if ($pr->status === 'rejected' && $pr->rejected_reason)
                                <span class="text-xs text-red-500 ml-1" title="{{ $pr->rejected_reason }}">{{ Str::limit($pr->rejected_reason, 25) }}</span>
                            @endif

                            {{-- Draft: Edit / Submit / Delete --}}
                            @if ($pr->status === 'draft')
                                <a href="{{ route('purchasing.requests.edit', $pr->id) }}" title="Edit"
                                   class="text-gray-400 hover:text-indigo-600 transition p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                <button wire:click="submitPr({{ $pr->id }})" wire:confirm="Submit this purchase request?"
                                        title="Submit for Approval" class="text-gray-400 hover:text-green-600 transition p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                </button>
                                <button wire:click="deletePr({{ $pr->id }})" wire:confirm="Delete this draft?"
                                        title="Delete" class="text-gray-400 hover:text-red-600 transition p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            @endif

                            {{-- Approved: Convert to PO --}}
                            @if ($pr->status === 'approved')
                                <a href="{{ route('purchasing.orders.create', ['pr_id' => $pr->id]) }}" title="Convert to Purchase Order"
                                   class="text-indigo-500 hover:text-indigo-700 transition p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                </a>
                            @endif

                            {{-- Cancel (draft or submitted) --}}
                            @if (in_array($pr->status, ['draft', 'submitted']) && $pr->status !== 'draft')
                                <button wire:click="cancelPr({{ $pr->id }})" wire:confirm="Cancel this purchase request?"
                                        title="Cancel" class="text-gray-400 hover:text-red-600 transition p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            @endif

                            {{-- Submitted: Awaiting label --}}
                            @if ($pr->status === 'submitted' && !$isPrApprover)
                                <span class="text-xs text-yellow-600 ml-1">Awaiting approval</span>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-gray-400">No purchase requests found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    @if ($purchaseRequests->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $purchaseRequests->links() }}
        </div>
    @endif
</div>
