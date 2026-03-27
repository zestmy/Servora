<div>
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('purchasing.index') }}" class="text-gray-400 hover:text-gray-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h2 class="text-lg font-semibold text-gray-700">Price Comparison</h2>
    </div>

    {{-- Ingredient Search --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Select Ingredient</h3>
        @if ($selectedIngredientId)
            <div class="flex items-center gap-3">
                <span class="px-3 py-1.5 bg-indigo-50 text-indigo-700 rounded-lg text-sm font-medium">{{ $selectedIngredientName }}</span>
                <button wire:click="clearSelection" class="text-sm text-gray-400 hover:text-gray-600">Change</button>
            </div>
        @else
            <div class="relative">
                <input type="text" wire:model.live.debounce.300ms="ingredientSearch" placeholder="Search ingredient by name or code..."
                       class="w-full max-w-md rounded-lg border-gray-300 text-sm" />
                @if (count($searchResults) > 0)
                    <div class="absolute z-20 mt-1 w-full max-w-md bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                        @foreach ($searchResults as $item)
                            <button wire:click="selectIngredient({{ $item->id }})" type="button"
                                    class="w-full text-left px-4 py-2.5 hover:bg-indigo-50 text-sm flex justify-between border-b border-gray-50 last:border-0">
                                <span class="font-medium text-gray-700">{{ $item->name }}</span>
                                <span class="text-xs text-gray-400">{{ $item->code }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>

    @if ($selectedIngredientId && count($comparisonData) > 0)
        {{-- Price Comparison Table --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">Supplier Prices for {{ $selectedIngredientName }}</h3>
            </div>
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Supplier</th>
                        <th class="px-4 py-3 text-right">Last Cost</th>
                        <th class="px-4 py-3 text-center">Change</th>
                        <th class="px-4 py-3 text-center">Pack Size</th>
                        <th class="px-4 py-3 text-center">Preferred</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($comparisonData as $i => $s)
                        <tr class="hover:bg-gray-50 {{ $i === 0 ? 'bg-green-50/50' : '' }}">
                            <td class="px-4 py-3 font-medium text-gray-700">
                                {{ $s['supplier_name'] }}
                                @if ($i === 0)
                                    <span class="ml-1 px-1.5 py-0.5 bg-green-100 text-green-700 text-[10px] rounded font-medium">Best Price</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">
                                {{ number_format($s['last_cost'], 4) }}
                                <span class="text-gray-400 text-xs">{{ $s['uom'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($s['change_pct'] !== null)
                                    <span class="text-xs font-medium {{ $s['change_pct'] > 0 ? 'text-red-500' : ($s['change_pct'] < 0 ? 'text-green-500' : 'text-gray-400') }}">
                                        {{ $s['change_pct'] > 0 ? '+' : '' }}{{ $s['change_pct'] }}%
                                    </span>
                                @else
                                    <span class="text-gray-300">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ $s['pack_size'] > 1 ? $s['pack_size'] : '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                @if ($s['is_preferred'])
                                    <span class="text-indigo-600 font-medium text-xs">Preferred</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Price History per Supplier --}}
        @foreach ($comparisonData as $s)
            @if (count($s['history']) > 1)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ $s['supplier_name'] }} — Price History</h4>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($s['history'] as $h)
                            <div class="px-3 py-2 bg-gray-50 rounded-lg text-center">
                                <p class="text-xs text-gray-400">{{ $h['date'] }}</p>
                                <p class="text-sm font-medium text-gray-700 tabular-nums">{{ number_format($h['cost'], 4) }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    @elseif ($selectedIngredientId)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center text-gray-400 text-sm mb-6">
            No supplier pricing data found for this ingredient.
        </div>
    @endif

    {{-- Recent Significant Price Changes --}}
    @if (count($recentChanges) > 0)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">Significant Price Changes (Last 30 Days)</h3>
                <p class="text-xs text-gray-400 mt-0.5">Ingredients with 5%+ price movement</p>
            </div>
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Ingredient</th>
                        <th class="px-4 py-3 text-left">Supplier</th>
                        <th class="px-4 py-3 text-right">Old Price</th>
                        <th class="px-4 py-3 text-right">New Price</th>
                        <th class="px-4 py-3 text-center">Change</th>
                        <th class="px-4 py-3 text-center">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($recentChanges as $change)
                        <tr class="hover:bg-gray-50 cursor-pointer" wire:click="selectIngredient({{ $change['ingredient_id'] }})">
                            <td class="px-4 py-3 font-medium text-gray-700">{{ $change['ingredient_name'] }}</td>
                            <td class="px-4 py-3 text-gray-600 text-xs">{{ $change['supplier_name'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-500">{{ number_format($change['old_price'], 4) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">{{ number_format($change['new_price'], 4) }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $change['change_pct'] > 0 ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                                    {{ $change['change_pct'] > 0 ? '+' : '' }}{{ $change['change_pct'] }}%
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-500 text-xs">{{ $change['date'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
