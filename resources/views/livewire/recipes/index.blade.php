<div>
    {{-- Flash --}}
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-gray-700">{{ $isPrep ? 'Prep Items' : 'Recipes' }}</h2>
        <div class="flex items-center gap-2">
            @if (! $isPrep)
                {{-- PDF dropdown --}}
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="px-3 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        PDF
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" @click.away="open = false" x-transition
                         class="absolute right-0 mt-1 w-56 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                        <a href="{{ route('recipes.cost-pdf-all') }}" target="_blank"
                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">All Recipe Costs</a>
                        <a href="{{ route('recipes.cost-pdf-summary') }}" target="_blank"
                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Recipe Cost Summary</a>
                    </div>
                </div>
                <a href="{{ route('recipes.import') }}"
                   class="px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M8 12l4 4 4-4M12 4v12" />
                    </svg>
                    Import
                </a>
                <a href="{{ route('recipes.create') }}"
                   class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    + New Recipe
                </a>
            @else
                {{-- PDF dropdown for Prep Items --}}
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="px-3 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        PDF
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" @click.away="open = false" x-transition
                         class="absolute right-0 mt-1 w-56 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                        <a href="{{ route('recipes.prep-cost-pdf-all') }}" target="_blank"
                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">All Prep Item Costs</a>
                        <a href="{{ route('recipes.prep-cost-pdf-summary') }}" target="_blank"
                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Prep Item Cost Summary</a>
                    </div>
                </div>
                <a href="{{ route('recipes.import', ['type' => 'prep']) }}"
                   class="px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M8 12l4 4 4-4M12 4v12" />
                    </svg>
                    Import
                </a>
                <a href="{{ route('inventory.prep-items.create') }}"
                   class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    + New Prep Item
                </a>
            @endif
        </div>
    </div>

    {{-- Tabs --}}
    <div class="border-b border-gray-200 mb-4">
        <nav class="flex gap-6 -mb-px">
            <button wire:click="$set('tab', 'recipes')"
                    class="pb-3 px-1 text-sm font-medium border-b-2 transition {{ $tab === 'recipes' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Recipes
            </button>
            <button wire:click="$set('tab', 'prep-items')"
                    class="pb-3 px-1 text-sm font-medium border-b-2 transition {{ $tab === 'prep-items' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Prep Items
            </button>
        </nav>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search by name or code…"
                       class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>
            <div>
                <select wire:model.live="categoryFilter"
                        class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Categories</option>
                    @foreach ($recipeCategories as $cat)
                        @if ($cat->children && $cat->children->count())
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
            </div>
            @if ($outlets->count() > 1)
            <div>
                <select wire:model.live="outletFilter"
                        class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Outlets</option>
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            @if ($tab !== 'prep-items')
                <div>
                    <select wire:model.live="costFilter"
                            class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All Cost %</option>
                        <option value="under25">Under 25% (Low)</option>
                        <option value="25to35">25 – 35%</option>
                        <option value="35to45">35 – 45%</option>
                        <option value="over45">Over 45% (High)</option>
                        <option value="none">No Price Set</option>
                    </select>
                </div>
            @endif
            <div>
                <select wire:model.live="statusFilter"
                        class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="all">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">{{ $tab === 'prep-items' ? 'Prep Item' : 'Recipe' }}</th>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-center">Items</th>
                    <th class="px-4 py-3 text-right">Yield</th>
                    <th class="px-4 py-3 text-right">Total Cost</th>
                    @if ($tab !== 'prep-items')
                        <th class="px-4 py-3 text-right">Selling Price</th>
                        <th class="px-4 py-3 text-right">Food Cost %</th>
                    @else
                        <th class="px-4 py-3 text-right">Cost / Unit</th>
                    @endif
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($recipes as $recipe)
                    @php
                        $totalCost   = $recipe->total_cost;
                        $selling     = $recipe->effective_selling_price;
                        $foodCostPct = $selling > 0 ? ($totalCost / $selling) * 100 : null;
                        $fcColor     = match(true) {
                            $foodCostPct === null => 'text-gray-400',
                            $foodCostPct <= 25   => 'text-green-600 font-semibold',
                            $foodCostPct <= 35   => 'text-yellow-600 font-semibold',
                            $foodCostPct <= 45   => 'text-orange-500 font-semibold',
                            default              => 'text-red-600 font-semibold',
                        };
                    @endphp
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3">
                            <a href="{{ $tab === 'prep-items' ? route('inventory.prep-items.show', $recipe->id) : route('recipes.show', $recipe->id) }}" class="font-medium text-gray-800 hover:text-indigo-600 transition">{{ $recipe->name }}</a>
                            @if ($recipe->code)
                                <div class="text-xs text-gray-400">{{ $recipe->code }}</div>
                            @endif
                            @if ($recipe->outlets->isNotEmpty())
                                <div class="flex flex-wrap gap-1 mt-1">
                                    @foreach ($recipe->outlets as $o)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-600 border border-blue-100">
                                            {{ $o->code ?? $o->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($recipe->category)
                                @php $catColor = $recipeCategories->firstWhere('name', $recipe->category)?->color; @endphp
                                <div class="flex items-center gap-2">
                                    @if ($catColor)
                                        <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $catColor }}"></div>
                                    @endif
                                    <span class="text-gray-600">{{ $recipe->category }}</span>
                                </div>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center text-gray-600">{{ $recipe->lines_count }}</td>
                        <td class="px-4 py-3 text-right text-gray-600 tabular-nums">
                            {{ number_format($recipe->yield_quantity, 0) }}
                            <span class="text-gray-400 text-xs">{{ $recipe->yieldUom?->abbreviation }}</span>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700 font-medium">
                            {{ number_format($totalCost, 2) }}
                        </td>
                        @if ($tab !== 'prep-items')
                            <td class="px-4 py-3 text-right tabular-nums text-gray-700">
                                @if ($selling > 0)
                                    {{ number_format($selling, 2) }}
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                @if ($foodCostPct !== null)
                                    <span class="{{ $fcColor }}">{{ number_format($foodCostPct, 1) }}%</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                        @else
                            <td class="px-4 py-3 text-right tabular-nums text-gray-700">
                                @php
                                    $yieldQty = max(floatval($recipe->yield_quantity), 0.0001);
                                    $costPerUnit = $totalCost / $yieldQty;
                                @endphp
                                @if ($totalCost > 0)
                                    {{ number_format($costPerUnit, 4) }}
                                    <span class="text-gray-400 text-xs">/ {{ $recipe->yieldUom?->abbreviation }}</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                        @endif
                        <td class="px-4 py-3 text-center">
                            @if ($recipe->is_active)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ $tab === 'prep-items' ? route('inventory.prep-items.show', $recipe->id) : route('recipes.edit', $recipe->id) }}" title="Edit"
                                   class="text-indigo-500 hover:text-indigo-700 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </a>
                                <button wire:click="toggleActive({{ $recipe->id }})"
                                        title="{{ $recipe->is_active ? 'Deactivate' : 'Activate' }}"
                                        class="{{ $recipe->is_active ? 'text-green-500 hover:text-green-700' : 'text-gray-400 hover:text-gray-600' }} transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </button>
                                <button wire:click="delete({{ $recipe->id }})"
                                        wire:confirm="Delete '{{ $recipe->name }}'? This cannot be undone."
                                        title="Delete"
                                        class="text-red-400 hover:text-red-600 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-gray-400">
                            <div class="text-3xl mb-2">📋</div>
                            <p class="font-medium">No recipes yet</p>
                            <p class="text-xs mt-1">
                                <a href="{{ route('recipes.create') }}" class="text-indigo-500 underline">Create your first recipe</a>
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($recipes->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $recipes->links() }}
            </div>
        @endif
    </div>
</div>
