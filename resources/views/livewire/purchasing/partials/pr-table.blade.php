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
                            @if ($pr->status === 'draft')
                                <a href="{{ route('purchasing.requests.edit', $pr->id) }}" title="Edit"
                                   class="text-gray-400 hover:text-indigo-600 transition p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                <button wire:click="submitPr({{ $pr->id }})" wire:confirm="Submit this purchase request?"
                                        title="Submit" class="text-gray-400 hover:text-green-600 transition p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </button>
                                <button wire:click="cancelPr({{ $pr->id }})" wire:confirm="Cancel this purchase request?"
                                        title="Cancel" class="text-gray-400 hover:text-red-600 transition p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            @endif

                            @if ($canApprovePr)
                                <button wire:click="approvePr({{ $pr->id }})" wire:confirm="Approve '{{ $pr->pr_number }}'?"
                                        title="Approve" class="text-green-500 hover:text-green-700 transition p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </button>
                                <button wire:click="rejectPr({{ $pr->id }})" wire:confirm="Reject '{{ $pr->pr_number }}'?"
                                        title="Reject" class="text-red-400 hover:text-red-600 transition p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            @endif

                            @if ($pr->status === 'submitted')
                                <span class="text-xs text-yellow-600">Awaiting approval</span>
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
