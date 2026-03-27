<div>
    <h2 class="text-lg font-semibold text-gray-700 mb-6">Quotation Requests</h2>

    <div class="flex gap-3 mb-4">
        <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="quoted">Quoted</option>
        </select>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">RFQ Number</th>
                    <th class="px-4 py-3 text-left">Title</th>
                    <th class="px-4 py-3 text-center">Items</th>
                    <th class="px-4 py-3 text-center">Needed By</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center">Responded</th>
                    <th class="px-4 py-3 text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($rfqs as $rqs)
                    @php $rfq = $rqs->quotationRequest; @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs font-medium text-gray-800">{{ $rfq?->rfq_number ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $rfq?->title ?? '—' }}</td>
                        <td class="px-4 py-3 text-center text-gray-600">{{ $rfq?->lines_count ?? 0 }}</td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $rfq?->needed_by_date?->format('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                {{ match($rqs->status) { 'pending' => 'bg-amber-100 text-amber-700', 'quoted' => 'bg-green-100 text-green-700', default => 'bg-gray-100 text-gray-600' } }}">
                                {{ ucfirst($rqs->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-500 text-xs">{{ $rqs->responded_at?->format('d M Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            @if ($rqs->status === 'pending')
                                <a href="{{ route('supplier.quotations.respond', $rqs->id) }}"
                                   class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition">
                                    Respond
                                </a>
                            @else
                                <span class="text-xs text-gray-400">Submitted</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No quotation requests found.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($rfqs->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $rfqs->links() }}</div>
        @endif
    </div>
</div>
