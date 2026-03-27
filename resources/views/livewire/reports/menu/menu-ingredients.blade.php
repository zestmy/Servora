<div>
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-700">Menu & Ingredients</h2>
            <p class="text-xs text-gray-400 mt-0.5">Recipe-to-ingredient mapping with quantities and costs</p>
        </div>
        <a href="{{ route('reports.hub') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">Back to Reports</a>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
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

    {{-- Recipe Cards --}}
    <div class="space-y-4">
        @forelse ($recipes as $recipe)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">{{ $recipe->name }}</h3>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ $recipe->ingredientCategory?->name ?? '-' }}
                            &middot; {{ $recipe->lines->count() }} ingredient{{ $recipe->lines->count() !== 1 ? 's' : '' }}
                            &middot; Total Cost: {{ number_format($recipe->total_cost, 4) }}
                        </p>
                    </div>
                    @if ($recipe->selling_price)
                        <div class="text-right">
                            <p class="text-xs text-gray-400">Selling Price</p>
                            <p class="text-sm font-semibold text-gray-800">{{ number_format($recipe->selling_price, 2) }}</p>
                        </div>
                    @endif
                </div>

                @if ($recipe->lines->isNotEmpty())
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                            <tr>
                                <th class="px-4 py-2 text-left">Ingredient</th>
                                <th class="px-4 py-2 text-right">Quantity</th>
                                <th class="px-4 py-2 text-left">UOM</th>
                                <th class="px-4 py-2 text-right">Cost</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($recipe->lines as $line)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 text-gray-700">{{ $line->ingredient?->name ?? '-' }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ number_format($line->quantity, 4) }}</td>
                                    <td class="px-4 py-2 text-gray-600">{{ $line->uom?->abbreviation ?? '-' }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ number_format($line->line_total_cost, 4) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="px-4 py-6 text-center text-gray-400 text-xs">No ingredients defined</div>
                @endif
            </div>
        @empty
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center text-gray-400">
                <p class="font-medium">No recipes found</p>
                <p class="text-xs mt-1">Create recipes with ingredient lines to see them here.</p>
            </div>
        @endforelse
    </div>

    @if ($recipes->hasPages())
        <div class="mt-4">
            {{ $recipes->links() }}
        </div>
    @endif
</div>
