<div>
    {{-- Top bar --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('recipes.index') }}" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1 min-w-0">
            <p class="text-xs text-gray-400"><a href="{{ route('recipes.index') }}" class="hover:underline">Recipes</a> / {{ $recipe->name }}</p>
            <h1 class="text-lg font-bold text-gray-800 mt-0.5">{{ $recipe->name }}</h1>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
            {{-- PDF dropdown --}}
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Print PDF
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" @click.away="open = false" x-transition
                     class="absolute right-0 mt-1 w-56 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                    <a href="{{ route('recipes.cost-pdf', $recipe->id) }}" target="_blank"
                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Recipe Cost Card</a>
                    <a href="{{ route('recipes.cost-pdf-all') }}" target="_blank"
                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">All Recipe Costs</a>
                    <a href="{{ route('recipes.cost-pdf-summary') }}" target="_blank"
                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Recipe Cost Summary List</a>
                </div>
            </div>
            <a href="{{ route('recipes.edit', $recipe->id) }}"
               class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                Edit Recipe
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Left: Recipe info + ingredients (2/3) --}}
        <div class="lg:col-span-2 space-y-4">

            {{-- Recipe Info --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    @if ($recipe->code)
                        <div>
                            <span class="text-gray-500 text-xs uppercase tracking-wider">Code</span>
                            <p class="font-medium text-gray-800 mt-0.5">{{ $recipe->code }}</p>
                        </div>
                    @endif
                    @if ($recipe->category)
                        <div>
                            <span class="text-gray-500 text-xs uppercase tracking-wider">Category</span>
                            <p class="font-medium text-gray-800 mt-0.5">{{ $recipe->category }}</p>
                        </div>
                    @endif
                    @if ($recipe->department)
                        <div>
                            <span class="text-gray-500 text-xs uppercase tracking-wider">Department</span>
                            <p class="font-medium text-gray-800 mt-0.5">{{ $recipe->department->name }}</p>
                        </div>
                    @endif
                    <div>
                        <span class="text-gray-500 text-xs uppercase tracking-wider">Yield</span>
                        <p class="font-medium text-gray-800 mt-0.5">{{ rtrim(rtrim(number_format($yieldQty, 4), '0'), '.') }} {{ $recipe->yieldUom?->abbreviation }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500 text-xs uppercase tracking-wider">Status</span>
                        <p class="mt-0.5">
                            <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $recipe->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $recipe->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </p>
                    </div>
                </div>
                @if ($recipe->description)
                    <p class="text-sm text-gray-600 mt-4 pt-4 border-t border-gray-100">{{ $recipe->description }}</p>
                @endif
                @if ($recipe->outlets->isNotEmpty())
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <span class="text-gray-500 text-xs uppercase tracking-wider">Outlets</span>
                        <div class="flex flex-wrap gap-1 mt-1">
                            @foreach ($recipe->outlets as $outlet)
                                <span class="px-2 py-0.5 bg-blue-50 text-blue-700 text-xs font-medium rounded">{{ $outlet->code ?? $outlet->name }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Ingredient Lines --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Ingredients</h3>
                    <p class="text-xs text-gray-400 mt-0.5">{{ count($lineData) }} item{{ count($lineData) !== 1 ? 's' : '' }}</p>
                </div>

                @if (count($lineData))
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                                <tr>
                                    <th class="px-4 py-2 text-left w-8">#</th>
                                    <th class="px-4 py-2 text-left">Ingredient</th>
                                    <th class="px-4 py-2 text-right w-24">Qty</th>
                                    <th class="px-4 py-2 text-left w-16">UOM</th>
                                    <th class="px-4 py-2 text-right w-20">Waste</th>
                                    <th class="px-4 py-2 text-right w-28">Unit Cost</th>
                                    <th class="px-4 py-2 text-right w-28">Line Cost</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach ($lineData as $idx => $ld)
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="px-4 py-2 text-gray-400 text-xs">{{ $idx + 1 }}</td>
                                        <td class="px-4 py-2">
                                            <span class="font-medium text-gray-800">{{ $ld['ingredient'] }}</span>
                                            @if ($ld['is_prep'])
                                                <span class="ml-1 px-1.5 py-0.5 bg-amber-100 text-amber-700 text-xs font-semibold rounded">PREP</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ rtrim(rtrim(number_format($ld['quantity'], 4), '0'), '.') }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $ld['uom'] }}</td>
                                        <td class="px-4 py-2 text-right tabular-nums text-gray-500">
                                            @if ($ld['waste_percentage'] > 0)
                                                {{ rtrim(rtrim(number_format($ld['waste_percentage'], 2), '0'), '.') }}%
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-right tabular-nums text-gray-600">{{ number_format($ld['unit_cost'], 4) }}</td>
                                        <td class="px-4 py-2 text-right tabular-nums font-medium text-gray-800">{{ number_format($ld['line_cost'], 4) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                                <tr>
                                    <td colspan="6" class="px-4 py-3 text-right text-sm font-semibold text-gray-600">Ingredient Cost</td>
                                    <td class="px-4 py-3 text-right font-bold text-gray-900 tabular-nums">{{ number_format($totalCost, 2) }}</td>
                                </tr>
                                @if (count($extraCosts))
                                    @foreach ($extraCosts as $ec)
                                        <tr>
                                            <td colspan="6" class="px-4 py-1.5 text-right text-sm text-gray-500">
                                                {{ $ec['label'] ?? 'Extra Cost' }}
                                                @if (($ec['type'] ?? 'value') === 'percent')
                                                    <span class="text-xs text-gray-400">({{ $ec['amount'] }}%)</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-1.5 text-right text-sm tabular-nums text-gray-600">
                                                @if (($ec['type'] ?? 'value') === 'percent')
                                                    {{ number_format($totalCost * floatval($ec['amount'] ?? 0) / 100, 2) }}
                                                @else
                                                    {{ number_format(floatval($ec['amount'] ?? 0), 2) }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    <tr>
                                        <td colspan="6" class="px-4 py-3 text-right text-sm font-bold text-gray-700">Total Cost</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900 tabular-nums text-base">{{ number_format($grandCost, 2) }}</td>
                                    </tr>
                                @endif
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Right: Cost & Pricing Analysis (1/3) --}}
        <div class="lg:col-span-1 space-y-4">

            {{-- Cost Summary --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Cost Summary</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Ingredient Cost</dt>
                        <dd class="text-gray-700 tabular-nums">{{ number_format($totalCost, 2) }}</dd>
                    </div>
                    @if ($extraCostTotal > 0)
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Extra Costs</dt>
                            <dd class="text-gray-700 tabular-nums">{{ number_format($extraCostTotal, 2) }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between border-t border-gray-100 pt-2">
                        <dt class="text-gray-500 font-medium">Total Cost</dt>
                        <dd class="font-semibold text-gray-800 tabular-nums">{{ number_format($grandCost, 2) }}</dd>
                    </div>
                    @if ($totalTax > 0)
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Tax</dt>
                            <dd class="text-gray-700 tabular-nums">{{ number_format($totalTax, 2) }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between border-t border-gray-100 pt-2">
                        <dt class="text-gray-500">Cost / {{ $recipe->yieldUom?->abbreviation ?? 'serving' }}</dt>
                        <dd class="text-gray-700 tabular-nums">{{ number_format($costPerServing, 4) }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Pricing Analysis --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Pricing Analysis</h3>

                @if (count($pricingAnalysis))
                    <div class="space-y-3">
                        @foreach ($pricingAnalysis as $pa)
                            @if ($pa['selling_price'] > 0)
                                @php
                                    $fcColor = match(true) {
                                        $pa['food_cost_pct'] === null => 'text-gray-400',
                                        $pa['food_cost_pct'] <= 25    => 'text-green-600',
                                        $pa['food_cost_pct'] <= 35    => 'text-yellow-600',
                                        $pa['food_cost_pct'] <= 45    => 'text-orange-500',
                                        default                       => 'text-red-600',
                                    };
                                    $fcBg = match(true) {
                                        $pa['food_cost_pct'] === null => 'bg-gray-50',
                                        $pa['food_cost_pct'] <= 25    => 'bg-green-50',
                                        $pa['food_cost_pct'] <= 35    => 'bg-yellow-50',
                                        $pa['food_cost_pct'] <= 45    => 'bg-orange-50',
                                        default                       => 'bg-red-50',
                                    };
                                @endphp
                                <div class="rounded-lg {{ $fcBg }} px-3 py-3">
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-sm font-medium text-gray-700">
                                            {{ $pa['name'] }}
                                            @if ($pa['is_default'])
                                                <span class="text-xs text-indigo-600">(Default)</span>
                                            @endif
                                        </span>
                                        <span class="font-bold text-gray-900 tabular-nums">{{ number_format($pa['selling_price'], 2) }}</span>
                                    </div>
                                    <div class="flex justify-between text-xs">
                                        <span class="text-gray-500">Food Cost %</span>
                                        <span class="font-bold {{ $fcColor }} tabular-nums">{{ number_format($pa['food_cost_pct'], 1) }}%</span>
                                    </div>
                                    <div class="flex justify-between text-xs mt-0.5">
                                        <span class="text-gray-500">Gross Profit</span>
                                        <span class="{{ $pa['gross_profit'] >= 0 ? 'text-green-600' : 'text-red-600' }} font-medium tabular-nums">
                                            {{ number_format($pa['gross_profit'], 2) }}
                                            ({{ number_format($pa['gross_margin'], 1) }}%)
                                        </span>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @elseif ($legacyPrice > 0)
                    @php
                        $fcColor = match(true) {
                            $legacyFoodCostPct === null => 'text-gray-400',
                            $legacyFoodCostPct <= 25    => 'text-green-600',
                            $legacyFoodCostPct <= 35    => 'text-yellow-600',
                            $legacyFoodCostPct <= 45    => 'text-orange-500',
                            default                     => 'text-red-600',
                        };
                    @endphp
                    <div class="rounded-lg bg-gray-50 px-3 py-3">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-sm font-medium text-gray-700">Selling Price</span>
                            <span class="font-bold text-gray-900 tabular-nums">{{ number_format($legacyPrice, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-500">Food Cost %</span>
                            <span class="font-bold {{ $fcColor }} tabular-nums">{{ number_format($legacyFoodCostPct, 1) }}%</span>
                        </div>
                        <div class="flex justify-between text-xs mt-0.5">
                            <span class="text-gray-500">Gross Profit</span>
                            <span class="{{ ($legacyPrice - $grandCost) >= 0 ? 'text-green-600' : 'text-red-600' }} font-medium tabular-nums">
                                {{ number_format($legacyPrice - $grandCost, 2) }}
                            </span>
                        </div>
                    </div>
                @else
                    <p class="text-xs text-gray-400 italic">No selling prices set.</p>
                @endif

                {{-- Benchmark guide --}}
                <div class="text-xs text-gray-400 space-y-0.5 pt-3 mt-3 border-t border-gray-100">
                    <p class="font-medium text-gray-500 mb-1">Food cost guide:</p>
                    <p><span class="text-green-600">≤25%</span> Excellent &nbsp;
                       <span class="text-yellow-600">25–35%</span> Good</p>
                    <p><span class="text-orange-500">35–45%</span> High &nbsp;
                       <span class="text-red-600">&gt;45%</span> Review</p>
                </div>
            </div>

            {{-- Product Images --}}
            @php $dineInImages = $recipe->images->where('type', 'dine_in'); $takeawayImages = $recipe->images->where('type', 'takeaway'); @endphp
            @if ($dineInImages->count() || $takeawayImages->count())
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Product Images</h3>
                    @if ($dineInImages->count())
                        <p class="text-xs text-gray-500 font-medium mb-2">Dine-In</p>
                        <div class="grid grid-cols-2 gap-2 mb-3">
                            @foreach ($dineInImages as $img)
                                <img src="{{ $img->url() }}" alt="{{ $img->file_name }}" class="w-full h-24 object-cover rounded-lg border border-gray-200" />
                            @endforeach
                        </div>
                    @endif
                    @if ($takeawayImages->count())
                        <p class="text-xs text-gray-500 font-medium mb-2">Takeaway</p>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($takeawayImages as $img)
                                <img src="{{ $img->url() }}" alt="{{ $img->file_name }}" class="w-full h-24 object-cover rounded-lg border border-gray-200" />
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
