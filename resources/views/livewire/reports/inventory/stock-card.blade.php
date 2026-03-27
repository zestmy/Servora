<div>
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-700">Stock Card</h2>
            <p class="text-xs text-gray-400 mt-0.5">Full movement history for a single ingredient</p>
        </div>
        <a href="{{ route('reports.hub') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">Back to Reports</a>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
            <select wire:model.live="ingredientFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 min-w-[200px]">
                <option value="">Select Ingredient...</option>
                @foreach ($ingredients as $ing)
                    <option value="{{ $ing->id }}">{{ $ing->name }}</option>
                @endforeach
            </select>
            <div class="flex items-center gap-1">
                <input type="date" wire:model.live="dateFrom" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                <span class="text-gray-400 text-xs">to</span>
                <input type="date" wire:model.live="dateTo" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>
            <select wire:model.live="outletFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All Outlets</option>
                @foreach ($outlets as $o)
                    <option value="{{ $o->id }}">{{ $o->name }}</option>
                @endforeach
            </select>
            @if ($ingredientFilter)
                <button wire:click="exportCsv" class="px-3 py-2 text-sm text-gray-600 hover:text-gray-800 border border-gray-300 rounded-lg hover:bg-gray-50 transition ml-auto">
                    Export CSV
                </button>
            @endif
        </div>
    </div>

    @if ($ingredient)
        {{-- Ingredient Info --}}
        <div class="bg-indigo-50 rounded-xl border border-indigo-200 p-4 mb-4">
            <div class="flex items-center gap-4">
                <div>
                    <h3 class="text-sm font-semibold text-indigo-800">{{ $ingredient->name }}</h3>
                    <p class="text-xs text-indigo-500">{{ $ingredient->code ?? '' }} &middot; UOM: {{ $ingredient->baseUom?->abbreviation ?? '-' }}</p>
                </div>
                <div class="ml-auto text-right">
                    <p class="text-xs text-indigo-400">{{ $movements->count() }} movements</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Movement Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        @if (!$ingredientFilter)
            <div class="px-4 py-12 text-center text-gray-400">
                <p class="font-medium">Select an ingredient</p>
                <p class="text-xs mt-1">Choose an ingredient above to view its stock card.</p>
            </div>
        @elseif ($movements->isEmpty())
            <div class="px-4 py-12 text-center text-gray-400">
                <p class="font-medium">No movements found</p>
                <p class="text-xs mt-1">No stock movements recorded for this ingredient in the selected period.</p>
            </div>
        @else
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Reference</th>
                        <th class="px-4 py-3 text-center">Type</th>
                        <th class="px-4 py-3 text-right">Quantity</th>
                        <th class="px-4 py-3 text-right">Running Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($movements as $m)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 text-gray-700">{{ \Carbon\Carbon::parse($m['date'])->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-gray-600 text-xs">{{ $m['reference'] }}</td>
                            <td class="px-4 py-3 text-center">
                                @if ($m['type'] === 'IN')
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">IN</span>
                                @elseif ($m['type'] === 'OUT')
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">OUT</span>
                                @else
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">COUNT</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums {{ $m['quantity'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $m['quantity'] >= 0 ? '+' : '' }}{{ number_format($m['quantity'], 4) }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-gray-800">{{ number_format($m['balance'], 4) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
