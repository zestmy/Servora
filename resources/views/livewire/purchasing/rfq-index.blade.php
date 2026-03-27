<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Top bar --}}
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('purchasing.index') }}" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div>
                <h1 class="text-lg font-semibold text-gray-800">Request for Quotations</h1>
                <p class="text-xs text-gray-400 mt-0.5">Manage RFQs sent to suppliers</p>
            </div>
        </div>
        <a href="{{ route('purchasing.rfq.create') }}"
           class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            New RFQ
        </a>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex flex-wrap items-end gap-3">
            {{-- Search --}}
            <div class="flex-1 min-w-[200px]">
                <label class="text-xs font-medium text-gray-500 mb-1 block">Search</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                        </svg>
                    </div>
                    <input type="text" wire:model.live.debounce.300ms="search"
                           placeholder="RFQ number or title..."
                           class="w-full pl-9 pr-4 py-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>
            </div>

            {{-- Status --}}
            <div class="w-40">
                <label class="text-xs font-medium text-gray-500 mb-1 block">Status</label>
                <select wire:model.live="statusFilter"
                        class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-2">
                    <option value="">All Statuses</option>
                    <option value="draft">Draft</option>
                    <option value="sent">Sent</option>
                    <option value="closed">Closed</option>
                </select>
            </div>

            {{-- Date From --}}
            <div class="w-40">
                <label class="text-xs font-medium text-gray-500 mb-1 block">Needed From</label>
                <input type="date" wire:model.live="dateFrom"
                       class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-2" />
            </div>

            {{-- Date To --}}
            <div class="w-40">
                <label class="text-xs font-medium text-gray-500 mb-1 block">Needed To</label>
                <input type="date" wire:model.live="dateTo"
                       class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-2" />
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50/60">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RFQ #</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Needed By</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Invited</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Quoted</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($rfqs as $rfq)
                    <tr class="hover:bg-gray-50/40 cursor-pointer"
                        wire:click="$dispatch('navigate', { url: '{{ route('purchasing.rfq.show', $rfq->id) }}' })"
                        onclick="window.location='{{ route('purchasing.rfq.show', $rfq->id) }}'">
                        <td class="px-6 py-3 font-mono text-sm text-gray-700">{{ $rfq->rfq_number }}</td>
                        <td class="px-6 py-3 font-medium text-gray-800 max-w-[200px] truncate">{{ $rfq->title }}</td>
                        <td class="px-6 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                {{ match($rfq->status) {
                                    'draft'  => 'bg-gray-100 text-gray-600',
                                    'sent'   => 'bg-blue-100 text-blue-700',
                                    'closed' => 'bg-green-100 text-green-700',
                                    default  => 'bg-gray-100 text-gray-500',
                                } }}">
                                {{ ucfirst($rfq->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-gray-600">{{ $rfq->needed_by_date?->format('d M Y') ?? '—' }}</td>
                        <td class="px-6 py-3 text-center tabular-nums text-gray-600">{{ $rfq->lines_count }}</td>
                        <td class="px-6 py-3 text-center tabular-nums text-gray-600">{{ $rfq->suppliers_count }}</td>
                        <td class="px-6 py-3 text-center tabular-nums text-gray-600">{{ $rfq->quotations_count }}</td>
                        <td class="px-6 py-3 text-gray-500 text-xs">{{ $rfq->createdBy?->name ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                            No RFQs found. Create your first RFQ to get started.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($rfqs->hasPages())
            <div class="px-6 py-4 border-t border-gray-100">
                {{ $rfqs->links() }}
            </div>
        @endif
    </div>
</div>
