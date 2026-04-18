<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

    {{-- ── Mobile cards (md:hidden) ──────────────────────────────────────── --}}
    <div class="md:hidden divide-y divide-gray-100">
        @forelse ($grns as $grn)
            @php
                $mBadge = match($grn->status) {
                    'pending'  => 'bg-yellow-100 text-yellow-700',
                    'received' => 'bg-green-100 text-green-700',
                    'partial'  => 'bg-blue-100 text-blue-700',
                    'rejected' => 'bg-red-100 text-red-600',
                    default    => 'bg-gray-100 text-gray-500',
                };
            @endphp
            <div class="p-3 space-y-2">
                <div class="flex items-start justify-between gap-2">
                    <span class="font-mono text-sm font-medium text-gray-800">{{ $grn->grn_number }}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium flex-shrink-0 {{ $mBadge }}">{{ ucfirst($grn->status) }}</span>
                </div>
                <div class="text-sm text-gray-700 truncate">{{ $grn->supplier?->name ?? '—' }}</div>
                <div class="text-xs text-gray-500 truncate">{{ $grn->outlet?->name ?? '—' }} · DO {{ $grn->deliveryOrder?->do_number ?? '—' }}</div>
                <div class="flex items-center justify-between text-xs text-gray-500">
                    <div class="flex items-center gap-3">
                        <span>{{ $grn->received_date?->format('d M Y') ?? '—' }}</span>
                        <span>{{ $grn->lines_count }} item{{ $grn->lines_count !== 1 ? 's' : '' }}</span>
                    </div>
                    @if ($showPrice)
                        <span class="tabular-nums font-semibold text-gray-900">RM {{ number_format($grn->total_amount, 2) }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-2 pt-2 border-t border-gray-100">
                    <x-doc-action-menu
                        :pdfUrl="route('purchasing.pdf', ['type' => 'grn', 'id' => $grn->id])"
                        :docNumber="$grn->grn_number"
                        docType="Goods Received Note"
                    />
                    @if ($grn->status === 'pending')
                        <a href="{{ route('purchasing.grn.receive', $grn->id) }}"
                           class="flex-1 text-center px-3 py-1.5 text-xs font-medium rounded-lg bg-green-50 text-green-700 hover:bg-green-100">
                            Receive
                        </a>
                    @endif
                </div>
            </div>
        @empty
            <div class="p-8 text-center text-gray-400 text-sm font-medium">No goods received notes found</div>
        @endforelse
    </div>

    {{-- ── Desktop table (md+) ───────────────────────────────────────────── --}}
    <table class="hidden md:table min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
            <tr>
                <th class="px-4 py-3 text-left">GRN Number</th>
                <th class="px-4 py-3 text-left">DO Reference</th>
                <th class="px-4 py-3 text-left">Outlet</th>
                <th class="px-4 py-3 text-left">Supplier</th>
                <th class="px-4 py-3 text-center">Date</th>
                <th class="px-4 py-3 text-center">Items</th>
                @if ($showPrice)
                    <th class="px-4 py-3 text-right">Total (RM)</th>
                @endif
                <th class="px-4 py-3 text-center">Status</th>
                <th class="px-4 py-3 text-center">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse ($grns as $grn)
                @php
                    $badge = match($grn->status) {
                        'pending'  => 'bg-yellow-100 text-yellow-700',
                        'received' => 'bg-green-100 text-green-700',
                        'partial'  => 'bg-blue-100 text-blue-700',
                        'rejected' => 'bg-red-100 text-red-600',
                        default    => 'bg-gray-100 text-gray-500',
                    };
                @endphp
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-4 py-3 font-mono text-xs font-medium text-gray-700">{{ $grn->grn_number }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $grn->deliveryOrder?->do_number ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600 text-xs">{{ $grn->outlet?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-700">{{ $grn->supplier?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-center text-gray-500">{{ $grn->received_date?->format('d M Y') ?? '—' }}</td>
                    <td class="px-4 py-3 text-center text-gray-600">{{ $grn->lines_count }}</td>
                    @if ($showPrice)
                        <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">{{ number_format($grn->total_amount, 2) }}</td>
                    @endif
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                            {{ ucfirst($grn->status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-center gap-1">
                            {{-- Action menu (PDF, Print, Share) --}}
                            <x-doc-action-menu
                                :pdfUrl="route('purchasing.pdf', ['type' => 'grn', 'id' => $grn->id])"
                                :docNumber="$grn->grn_number"
                                docType="Goods Received Note"
                            />
                            @if ($grn->status === 'pending')
                                <a href="{{ route('purchasing.grn.receive', $grn->id) }}" title="Receive"
                                   class="text-green-500 hover:text-green-700 transition p-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </a>
                            @endif
                            @if ($isSystemAdmin)
                                <button wire:click="adminDeleteGrn({{ $grn->id }})"
                                        wire:confirm="Delete '{{ $grn->grn_number }}'? This action cannot be undone."
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
                    <td colspan="{{ $showPrice ? 9 : 8 }}" class="px-4 py-12 text-center text-gray-400">
                        <p class="font-medium">No goods received notes found</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if ($grns->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $grns->links() }}</div>
    @endif
</div>
