<div>
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('reports.hub') }}" class="text-gray-400 hover:text-gray-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h2 class="text-lg font-semibold text-gray-700">Production History</h2>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex flex-wrap gap-3">
            <div class="flex items-center gap-1">
                <input type="date" wire:model.live="dateFrom" class="rounded-lg border-gray-300 text-sm" />
                <span class="text-gray-400 text-xs">to</span>
                <input type="date" wire:model.live="dateTo" class="rounded-lg border-gray-300 text-sm" />
            </div>
            <select wire:model.live="kitchenFilter" class="rounded-lg border-gray-300 text-sm">
                <option value="">All Kitchens</option>
                @foreach ($kitchens as $k)
                    <option value="{{ $k->id }}">{{ $k->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm">
                <option value="">All Status</option>
                <option value="scheduled">Scheduled</option>
                <option value="in_progress">In Progress</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <button wire:click="exportCsv" class="px-3 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 ml-auto">Export CSV</button>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto"><table class="min-w-[1100px] divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Order #</th>
                    <th class="px-4 py-3 text-left">Kitchen</th>
                    <th class="px-4 py-3 text-center">Date</th>
                    <th class="px-4 py-3 text-center">Items</th>
                    <th class="px-4 py-3 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($orders as $o)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs font-medium text-indigo-600">{{ $o->order_number }}</td>
                        <td class="px-4 py-3 text-gray-700 text-xs">{{ $o->kitchen?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $o->production_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-center text-gray-600">{{ $o->lines_count }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                {{ match($o->status) { 'draft' => 'bg-gray-100 text-gray-600', 'scheduled' => 'bg-blue-100 text-blue-700', 'in_progress' => 'bg-amber-100 text-amber-700', 'completed' => 'bg-green-100 text-green-700', 'cancelled' => 'bg-red-100 text-red-600', default => 'bg-gray-100 text-gray-500' } }}">
                                {{ ucfirst(str_replace('_', ' ', $o->status)) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No production orders found.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        @if ($orders->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $orders->links() }}</div>
        @endif
    </div>
</div>
