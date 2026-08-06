<div>
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a data-back href="{{ route('reports.hub') }}" class="text-gray-600 hover:text-gray-900 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h2 class="page-title">GRN Report</h2>
        </div>
    </div>

    @include('livewire.reports.partials.report-filters', [
        'outlets'      => $outlets,
        'suppliers'    => $suppliers,
        'showSupplier' => false,
        'exportAction' => 'exportCsv',
    ])

    {{-- Status filter --}}
    <div class="mb-4">
        <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="pending">Pending</option>
            <option value="verified">Verified</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
        </select>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">GRN Number</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">PO Number</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">DO Number</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Outlet</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Supplier</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Received Date</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500">Total Amount</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-500">Variance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($grns as $grn)
                        @php
                            $hasVariance = $grn->lines->contains(fn ($l) => floatval($l->received_quantity) != floatval($l->expected_quantity));
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $grn->grn_number }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $grn->purchaseOrder?->po_number ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $grn->deliveryOrder?->do_number ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $grn->outlet?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $grn->supplier?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $grn->received_date?->format('d M Y') }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'px-2 py-0.5 rounded-full text-xs font-medium',
                                    'bg-gray-100 text-gray-600'     => $grn->status === 'draft',
                                    'bg-yellow-100 text-yellow-700' => $grn->status === 'pending',
                                    'bg-blue-100 text-blue-700'     => $grn->status === 'verified',
                                    'bg-success-100 text-success-700'   => $grn->status === 'completed',
                                    'bg-danger-100 text-danger-700'       => $grn->status === 'cancelled',
                                ])>{{ ucfirst($grn->status) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-gray-800">{{ number_format((float) $grn->total_amount, 2) }}</td>
                            <td class="px-4 py-3 text-center">
                                @if ($hasVariance)
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700">Variance</span>
                                @else
                                    <span class="text-gray-500">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-gray-600">No GRNs found for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($grns->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $grns->links() }}
            </div>
        @endif
    </div>
</div>
