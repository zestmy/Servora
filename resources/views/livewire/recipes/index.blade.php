<div>
    @once
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    @endonce
    {{-- Flash --}}
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div wire:key="flash-error-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h2 class="text-lg font-semibold text-gray-700">{{ $isPrep ? 'Prep Items' : 'Recipes' }}</h2>
        <div class="flex flex-wrap items-center gap-2">
            @if (! $isPrep)
                {{-- PDF dropdown --}}
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="px-2.5 md:px-3 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        PDF
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" @click.away="open = false" x-transition
                         class="absolute right-0 mt-1 w-56 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                        @php
                            $pdfFilters = array_filter([
                                'search'   => $search,
                                'category' => $categoryFilter,
                                'status'   => $statusFilter !== 'all' ? $statusFilter : null,
                                'outlet'   => $outletFilter,
                                'cost'     => $costFilter,
                            ]);
                        @endphp
                        <a href="{{ route('recipes.cost-pdf-all', $pdfFilters) }}" target="_blank"
                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">All Recipe Costs</a>
                        <a href="{{ route('recipes.cost-pdf-summary', $pdfFilters) }}" target="_blank"
                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Recipe Cost Summary</a>
                    </div>
                </div>

                {{-- Export cost PDF by category --}}
                @include('livewire.recipes._cost-pdf-category-dropdown')
                @if ($this->locked)
                    <span class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-lg" title="The company admin has locked this list. Read-only mode.">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Locked
                    </span>
                @else
                    <a href="{{ route('recipes.import') }}"
                       title="Import"
                       class="px-2.5 md:px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M8 12l4 4 4-4M12 4v12" />
                        </svg>
                        <span class="hidden sm:inline">Import</span>
                    </a>
                    <a href="{{ route('recipes.create') }}"
                       class="px-3 md:px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        <span class="sm:hidden">+ New</span>
                        <span class="hidden sm:inline">+ New Recipe</span>
                    </a>
                @endif
            @else
                {{-- PDF dropdown for Prep Items --}}
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="px-2.5 md:px-3 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        PDF
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" @click.away="open = false" x-transition
                         class="absolute right-0 mt-1 w-56 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                        @php
                            $pdfPrepFilters = array_filter([
                                'search'   => $search,
                                'category' => $categoryFilter,
                                'status'   => $statusFilter !== 'all' ? $statusFilter : null,
                                'outlet'   => $outletFilter,
                            ]);
                        @endphp
                        <a href="{{ route('recipes.prep-cost-pdf-all', $pdfPrepFilters) }}" target="_blank"
                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">All Prep Item Costs</a>
                        <a href="{{ route('recipes.prep-cost-pdf-summary', $pdfPrepFilters) }}" target="_blank"
                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Prep Item Cost Summary</a>
                    </div>
                </div>

                {{-- Export cost PDF by category --}}
                @include('livewire.recipes._cost-pdf-category-dropdown')
                @if ($this->locked)
                    <span class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-lg" title="The company admin has locked this list. Read-only mode.">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Locked
                    </span>
                @else
                    <a href="{{ route('recipes.import', ['type' => 'prep']) }}"
                       title="Import"
                       class="px-2.5 md:px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M8 12l4 4 4-4M12 4v12" />
                        </svg>
                        <span class="hidden sm:inline">Import</span>
                    </a>
                    <a href="{{ route('inventory.prep-items.create') }}"
                       class="px-3 md:px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        <span class="sm:hidden">+ New</span>
                        <span class="hidden sm:inline">+ New Prep Item</span>
                    </a>
                @endif
            @endif
        </div>
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
            <div>
                <select wire:model.live="perPage"
                        class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach (\App\Livewire\Recipes\Index::PER_PAGE_OPTIONS as $n)
                        <option value="{{ $n }}">{{ $n }} / page</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Bulk Delete Bar --}}
    @if (count($selectedIds) > 0 && ! $this->locked)
        <div class="mb-4 px-4 py-3 bg-indigo-50 border border-indigo-200 rounded-lg flex items-center justify-between">
            <div class="text-sm text-indigo-700">
                <span class="font-semibold">{{ count($selectedIds) }}</span>
                {{ $isPrep ? 'prep item' : 'recipe' }}{{ count($selectedIds) > 1 ? 's' : '' }} selected
            </div>
            <div class="flex items-center gap-2">
                <button wire:click="$set('selectedIds', [])"
                        class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-800 transition">
                    Clear
                </button>
                <button wire:click="bulkDelete"
                        wire:confirm="Delete {{ count($selectedIds) }} selected {{ $isPrep ? 'prep item' : 'recipe' }}{{ count($selectedIds) > 1 ? 's' : '' }}? This cannot be undone."
                        class="px-3 py-1.5 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition">
                    Delete Selected
                </button>
            </div>
        </div>
    @endif

    {{-- Table — horizontally scrollable on mobile. --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-[960px] divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    @if (! $this->locked)
                        <th class="w-10 px-2 py-3 text-center">
                            <input type="checkbox"
                                   wire:model.live="selectAll"
                                   @checked($selectAll)
                                   x-data
                                   x-on:change="
                                       const checkboxes = document.querySelectorAll('input[name=\'recipe_ids[]\']');
                                       checkboxes.forEach(cb => {
                                           cb.checked = $event.target.checked;
                                           if ($event.target.checked) {
                                               if (!$wire.selectedIds.includes(parseInt(cb.value))) {
                                                   $wire.selectedIds.push(parseInt(cb.value));
                                               }
                                           }
                                       });
                                       if (!$event.target.checked) {
                                           $wire.selectedIds = [];
                                       }
                                   "
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        </th>
                    @endif
                    <th class="w-6 px-1 py-3"></th>
                    <th class="px-4 py-3 text-left">{{ $tab === 'prep-items' ? 'Prep Item' : 'Recipe' }}</th>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-center">Items</th>
                    <th class="px-4 py-3 text-right">Yield</th>
                    <th class="px-4 py-3 text-right">Total Cost</th>
                    @if ($tab !== 'prep-items')
                        @forelse ($priceClasses as $pc)
                            <th class="px-4 py-3 text-right">{{ $pc->name }}</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">FC %</th>
                        @empty
                            <th class="px-4 py-3 text-right">Selling Price</th>
                            <th class="px-4 py-3 text-right">Food Cost %</th>
                        @endforelse
                    @else
                        <th class="px-4 py-3 text-right">Cost / Unit</th>
                    @endif
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50"
                   x-data
                   x-init="new Sortable($el, {
                       handle: '.drag-handle',
                       animation: 150,
                       ghostClass: 'bg-indigo-50',
                       onEnd: () => {
                           const ids = Array.from($el.querySelectorAll('tr[data-id]')).map(tr => tr.dataset.id);
                           $wire.reorder(ids);
                       }
                   })">
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
                    <tr wire:key="recipe-row-{{ $recipe->id }}" data-id="{{ $recipe->id }}" class="hover:bg-gray-50 transition {{ in_array($recipe->id, $selectedIds) ? 'bg-indigo-50' : '' }}">
                        @if (! $this->locked)
                            <td class="px-2 py-3 text-center">
                                <input type="checkbox"
                                       name="recipe_ids[]"
                                       value="{{ $recipe->id }}"
                                       wire:model.live="selectedIds"
                                       @checked(in_array($recipe->id, $selectedIds))
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            </td>
                        @endif
                        <td class="{{ $this->locked ? 'px-1 py-3' : 'drag-handle px-1 py-3 text-center text-gray-300 hover:text-gray-500 cursor-grab select-none' }}" title="{{ $this->locked ? '' : 'Drag to reorder' }}">
                            @unless ($this->locked)
                                <svg class="w-4 h-4 inline" fill="currentColor" viewBox="0 0 20 20"><path d="M7 4a1 1 0 11-2 0 1 1 0 012 0zm0 4a1 1 0 11-2 0 1 1 0 012 0zm0 4a1 1 0 11-2 0 1 1 0 012 0zm0 4a1 1 0 11-2 0 1 1 0 012 0zm8-12a1 1 0 11-2 0 1 1 0 012 0zm0 4a1 1 0 11-2 0 1 1 0 012 0zm0 4a1 1 0 11-2 0 1 1 0 012 0zm0 4a1 1 0 11-2 0 1 1 0 012 0z"/></svg>
                            @endunless
                        </td>
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
                            @if ($isPrep)
                                @php
                                    $ic = $recipe->ingredientCategory;
                                    $icRoot = $ic?->parent ?? $ic;
                                    $icSub  = $ic?->parent ? $ic : null;
                                @endphp
                                @if ($ic)
                                    <div class="flex items-center gap-2">
                                        @if ($icRoot?->color)
                                            <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $icRoot->color }}"></div>
                                        @endif
                                        <span class="text-gray-600">
                                            {{ $icRoot?->name }}@if ($icSub) <span class="text-gray-400">/ {{ $icSub->name }}</span>@endif
                                        </span>
                                    </div>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            @elseif ($recipe->category)
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
                            @forelse ($priceClasses as $pc)
                                @php
                                    $pcPrice = floatval($recipe->prices->firstWhere('recipe_price_class_id', $pc->id)?->selling_price ?? 0);
                                    $pcPct   = $pcPrice > 0 ? ($totalCost / $pcPrice) * 100 : null;
                                    $pcColor = match(true) {
                                        $pcPct === null => 'text-gray-400',
                                        $pcPct <= 25   => 'text-green-600 font-semibold',
                                        $pcPct <= 35   => 'text-yellow-600 font-semibold',
                                        $pcPct <= 45   => 'text-orange-500 font-semibold',
                                        default        => 'text-red-600 font-semibold',
                                    };
                                @endphp
                                @include('livewire.recipes.partials.price-cell', [
                                    'recipe'       => $recipe,
                                    'priceClassId' => $pc->id,
                                    'value'        => $pcPrice,
                                    'locked'       => $this->locked,
                                ])
                                <td class="px-4 py-3 text-right tabular-nums" wire:key="fc-{{ $recipe->id }}-{{ $pc->id }}">
                                    @if ($pcPct !== null)
                                        <span class="{{ $pcColor }}">{{ number_format($pcPct, 1) }}%</span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                            @empty
                                @include('livewire.recipes.partials.price-cell', [
                                    'recipe'       => $recipe,
                                    'priceClassId' => 0,
                                    'value'        => $recipe->selling_price,
                                    'locked'       => $this->locked,
                                ])
                                <td class="px-4 py-3 text-right tabular-nums">
                                    @if ($foodCostPct !== null)
                                        <span class="{{ $fcColor }}">{{ number_format($foodCostPct, 1) }}%</span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                            @endforelse
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
                                @if (! $this->locked)
                                <button wire:click="toggleActive({{ $recipe->id }})"
                                        title="{{ $recipe->is_active ? 'Deactivate' : 'Activate' }}"
                                        class="{{ $recipe->is_active ? 'text-green-500 hover:text-green-700' : 'text-gray-400 hover:text-gray-600' }} transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </button>
                                @if (! $isPrep)
                                <button wire:click="duplicate({{ $recipe->id }})"
                                        wire:confirm="Duplicate '{{ $recipe->name }}'? A copy will be created that you can edit."
                                        title="Duplicate"
                                        class="text-gray-400 hover:text-indigo-600 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                                    </svg>
                                </button>
                                @endif
                                <button wire:click="delete({{ $recipe->id }})"
                                        wire:confirm="Delete '{{ $recipe->name }}'? This cannot be undone."
                                        title="Delete"
                                        class="text-red-400 hover:text-red-600 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ ($this->locked ? 10 : 11) + max($priceClasses->count() * 2 - 2, 0) }}" class="px-4 py-12 text-center text-gray-400">
                            <div class="text-3xl mb-2">📋</div>
                            <p class="font-medium">No {{ $isPrep ? 'prep items' : 'recipes' }} yet</p>
                            <p class="text-xs mt-1">
                                @if ($isPrep)
                                    <a href="{{ route('inventory.prep-items.create') }}" class="text-indigo-500 underline">Create your first prep item</a>
                                @else
                                    <a href="{{ route('recipes.create') }}" class="text-indigo-500 underline">Create your first recipe</a>
                                @endif
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
      </div>

        @if ($recipes->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $recipes->links() }}
            </div>
        @endif
    </div>
</div>
