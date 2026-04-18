<div>
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-700">Stock Balance (Package)</h2>
            <p class="text-xs text-gray-400 mt-0.5">Current stock levels grouped by packaging and pack size</p>
        </div>
        <a href="{{ route('reports.hub') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">Back to Reports</a>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
            <select wire:model.live="outletFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All Outlets</option>
                @foreach ($outlets as $o)
                    <option value="{{ $o->id }}">{{ $o->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="categoryFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All Categories</option>
                @foreach ($categories as $cat)
                    @if ($cat->children->isNotEmpty())
                        <optgroup label="{{ $cat->name }}">
                            <option value="{{ $cat->id }}">All {{ $cat->name }}</option>
                            @foreach ($cat->children as $sub)
                                <option value="{{ $sub->id }}">{{ $sub->name }}</option>
                            @endforeach
                        </optgroup>
                    @else
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endif
                @endforeach
            </select>
            <button wire:click="exportCsv" class="px-3 py-2 text-sm text-gray-600 hover:text-gray-800 border border-gray-300 rounded-lg hover:bg-gray-50 transition ml-auto">
                Export CSV
            </button>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto"><table class="min-w-[1100px] divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Ingredient</th>
                    <th class="px-4 py-3 text-left">Code</th>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-right">Pack Size</th>
                    <th class="px-4 py-3 text-left">UOM</th>
                    <th class="px-4 py-3 text-right">Purchase Price</th>
                    <th class="px-4 py-3 text-right">Current Cost</th>
                    <th class="px-4 py-3 text-right">Last ST Qty</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($items as $item)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $item->name }}</td>
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ $item->code ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $item->category_name ?? '-' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ $item->pack_size ? number_format($item->pack_size, 2) : '-' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $item->uom ?? '-' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ number_format($item->purchase_price, 4) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ number_format($item->current_cost, 4) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium {{ ($item->last_qty ?? 0) > 0 ? 'text-gray-800' : 'text-gray-400' }}">
                            {{ $item->last_qty !== null ? number_format($item->last_qty, 2) : '-' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-gray-400">
                            <p class="font-medium">No ingredients found</p>
                            <p class="text-xs mt-1">Adjust your filters or add ingredients to see stock balances.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table></div>

        @if ($items->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $items->links() }}
            </div>
        @endif
    </div>
</div>
