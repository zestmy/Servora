<div>
    {{-- Flash message --}}
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-gray-700">Ingredients</h2>
        <div class="flex items-center gap-2">
            <a href="{{ route('ingredients.import') }}"
               class="px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M8 12l4 4 4-4M12 4v12" />
                </svg>
                Import
            </a>
            <button wire:click="openCreate"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                + Add Ingredient
            </button>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Search by name or code…"
                       class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>
            <div>
                <select wire:model.live="categoryFilter"
                        class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Categories</option>
                    @foreach ($categories as $main)
                        @if ($main->children->isNotEmpty())
                            <optgroup label="{{ $main->name }}">
                                <option value="{{ $main->id }}">All {{ $main->name }}</option>
                                @foreach ($main->children as $sub)
                                    <option value="{{ $sub->id }}">{{ $sub->name }}</option>
                                @endforeach
                            </optgroup>
                        @else
                            <option value="{{ $main->id }}">{{ $main->name }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
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
                    <th class="px-4 py-3 text-left">Name</th>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-left">Base UOM</th>
                    <th class="px-4 py-3 text-left">Recipe UOM</th>
                    <th class="px-4 py-3 text-right">Purchase Price</th>
                    <th class="px-4 py-3 text-right">Yield %</th>
                    <th class="px-4 py-3 text-right">Eff. Cost</th>
                    <th class="px-4 py-3 text-right">Recipe Cost</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($ingredients as $ingredient)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-800">{{ $ingredient->name }}</div>
                            @if ($ingredient->code)
                                <div class="text-xs text-gray-400">{{ $ingredient->code }}</div>
                            @endif
                            @php
                                $preferredSupplier = $ingredient->suppliers
                                    ->sortByDesc(fn ($s) => $s->pivot->is_preferred)
                                    ->first();
                            @endphp
                            @if ($preferredSupplier)
                                <div class="text-xs text-indigo-500 mt-0.5">
                                    🏭 {{ $preferredSupplier->name }}
                                    @if ($preferredSupplier->pivot->is_preferred)
                                        <span class="text-gray-400">(preferred)</span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($ingredient->ingredientCategory)
                                @php $ic = $ingredient->ingredientCategory; @endphp
                                <div class="flex items-center gap-1.5">
                                    <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $ic->color }}"></div>
                                    <span class="text-gray-600 text-sm">
                                        @if ($ic->parent)
                                            <span class="text-gray-400">{{ $ic->parent->name }} /</span>
                                            {{ $ic->name }}
                                        @else
                                            {{ $ic->name }}
                                        @endif
                                    </span>
                                </div>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $ingredient->baseUom->abbreviation }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $ingredient->recipeUom->abbreviation }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">
                            {{ number_format($ingredient->purchase_price, 2) }}
                            <span class="text-gray-400 text-xs">/ {{ $ingredient->baseUom->abbreviation }}</span>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            @if ($ingredient->yield_percent < 100)
                                <span class="text-amber-600 font-medium">{{ number_format($ingredient->yield_percent, 0) }}%</span>
                            @else
                                <span class="text-gray-400">100%</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            @if ($ingredient->yield_percent < 100)
                                <span class="text-red-600 font-medium">{{ number_format($ingredient->current_cost, 2) }}</span>
                            @else
                                <span class="text-gray-700">{{ number_format($ingredient->current_cost, 2) }}</span>
                            @endif
                            <span class="text-gray-400 text-xs">/ {{ $ingredient->baseUom->abbreviation }}</span>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            @php $rc = $ingredient->recipeCost(); @endphp
                            @if ($rc !== null)
                                <span class="font-semibold text-indigo-700">{{ number_format($rc, 4) }}</span>
                                <span class="text-gray-400 text-xs">/ {{ $ingredient->recipeUom->abbreviation }}</span>
                            @else
                                <span class="text-xs text-gray-300 italic">no conversion</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if ($ingredient->is_active)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                    Active
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                                    Inactive
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <button wire:click="openEdit({{ $ingredient->id }})" title="Edit"
                                        class="text-indigo-500 hover:text-indigo-700 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button wire:click="toggleActive({{ $ingredient->id }})"
                                        title="{{ $ingredient->is_active ? 'Deactivate' : 'Activate' }}"
                                        class="{{ $ingredient->is_active ? 'text-green-500 hover:text-green-700' : 'text-gray-400 hover:text-gray-600' }} transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </button>
                                <button wire:click="delete({{ $ingredient->id }})"
                                        wire:confirm="Delete '{{ $ingredient->name }}'? This cannot be undone."
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
                        <td colspan="10" class="px-4 py-12 text-center text-gray-400">
                            <div class="text-3xl mb-2">🥕</div>
                            <p class="font-medium">No ingredients found</p>
                            <p class="text-xs mt-1">Try adjusting your filters or add your first ingredient.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($ingredients->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $ingredients->links() }}
            </div>
        @endif
    </div>

    {{-- Modal --}}
    <div x-data="{}"
         x-show="$wire.showModal"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto">

        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-gray-900/50" @click="$wire.closeModal()"></div>

        {{-- Card --}}
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-2xl mx-4 my-6 z-10">

            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-800">
                    @if ($editingId) Edit: {{ $name }} @else New Ingredient @endif
                </h3>
                <button @click="$wire.closeModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Form --}}
            <form wire:submit="save">
                <div class="px-6 py-5 space-y-4 max-h-[75vh] overflow-y-auto">

                    {{-- Row 1: Name --}}
                    <div>
                        <x-input-label for="name" value="Name *" />
                        <x-text-input id="name" wire:model="name" type="text" class="mt-1 block w-full" placeholder="e.g. Tiger Prawn" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    {{-- Row 2: Code | Category --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="code" value="Code" />
                            <x-text-input id="code" wire:model="code" type="text" class="mt-1 block w-full" placeholder="e.g. PRAWN-TGR" />
                            <x-input-error :messages="$errors->get('code')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="ingredient_category_id" value="Category" />
                            <select id="ingredient_category_id" wire:model="ingredient_category_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— No Category —</option>
                                @foreach ($categories as $main)
                                    @if ($main->children->isNotEmpty())
                                        <optgroup label="{{ $main->name }}">
                                            @foreach ($main->children as $sub)
                                                <option value="{{ $sub->id }}">{{ $sub->name }}</option>
                                            @endforeach
                                        </optgroup>
                                    @else
                                        <option value="{{ $main->id }}">{{ $main->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @if ($categories->isEmpty())
                                <p class="mt-0.5 text-xs text-amber-500">
                                    <a href="{{ route('settings.categories') }}" target="_blank" class="underline">Create categories in Settings</a> first.
                                </p>
                            @endif
                            <x-input-error :messages="$errors->get('ingredient_category_id')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Row 3: Base UOM | Recipe UOM --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="base_uom_id" value="Base UOM *" />
                            <select id="base_uom_id" wire:model.live="base_uom_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— select —</option>
                                @foreach ($uoms as $uom)
                                    <option value="{{ $uom->id }}">{{ $uom->name }} ({{ $uom->abbreviation }})</option>
                                @endforeach
                            </select>
                            <p class="mt-0.5 text-xs text-gray-400">Unit you purchase in</p>
                            <x-input-error :messages="$errors->get('base_uom_id')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="recipe_uom_id" value="Recipe UOM *" />
                            <select id="recipe_uom_id" wire:model.live="recipe_uom_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— select —</option>
                                @foreach ($uoms as $uom)
                                    <option value="{{ $uom->id }}">{{ $uom->name }} ({{ $uom->abbreviation }})</option>
                                @endforeach
                            </select>
                            <p class="mt-0.5 text-xs text-gray-400">Unit used in recipes</p>
                            <x-input-error :messages="$errors->get('recipe_uom_id')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Row 4: Purchase Price | Yield % --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="purchase_price" value="Purchase Price *" />
                            <x-text-input id="purchase_price" wire:model.live="purchase_price"
                                          type="number" step="0.0001" min="0"
                                          class="mt-1 block w-full" />
                            <p class="mt-0.5 text-xs text-gray-400">Per {{ $baseUomAbbr ?? 'base UOM' }}</p>
                            <x-input-error :messages="$errors->get('purchase_price')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="yield_percent" value="Yield %" />
                            <div class="mt-1 relative">
                                <x-text-input id="yield_percent" wire:model.live="yield_percent"
                                              type="number" step="0.01" min="0.01" max="100"
                                              class="block w-full pr-8" />
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">%</span>
                            </div>
                            <p class="mt-0.5 text-xs text-gray-400">Usable after prep (100 = no loss)</p>
                            <x-input-error :messages="$errors->get('yield_percent')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Cost Chain Summary --}}
                    @if (floatval($purchase_price) > 0)
                        <div class="rounded-lg bg-indigo-50 border border-indigo-100 px-4 py-3">
                            <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wider mb-2">Cost Chain</p>
                            <div class="flex flex-wrap items-center gap-2 text-sm">

                                {{-- Purchase price --}}
                                <div class="flex flex-col items-center">
                                    <span class="font-semibold text-gray-800">
                                        {{ number_format(floatval($purchase_price), 4) }}
                                        <span class="text-gray-500 font-normal text-xs">/ {{ $baseUomAbbr ?? '?' }}</span>
                                    </span>
                                    <span class="text-xs text-gray-500">purchase price</span>
                                </div>

                                @if (floatval($yield_percent) < 100)
                                    <div class="flex flex-col items-center text-amber-500">
                                        <span class="font-medium">÷ {{ number_format(floatval($yield_percent), 0) }}% yield</span>
                                        <span class="text-xs">loss applied</span>
                                    </div>

                                    {{-- Arrow --}}
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-300 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>

                                    {{-- Effective cost --}}
                                    <div class="flex flex-col items-center">
                                        <span class="font-semibold text-red-600">
                                            {{ number_format($effectiveCost, 4) }}
                                            <span class="text-gray-500 font-normal text-xs">/ {{ $baseUomAbbr ?? '?' }}</span>
                                        </span>
                                        <span class="text-xs text-gray-500">eff. cost</span>
                                    </div>
                                @else
                                    <div class="flex flex-col items-center text-gray-400">
                                        <span class="text-xs">no yield loss</span>
                                    </div>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-300 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                    <div class="flex flex-col items-center">
                                        <span class="font-semibold text-gray-700">
                                            {{ number_format($effectiveCost, 4) }}
                                            <span class="text-gray-500 font-normal text-xs">/ {{ $baseUomAbbr ?? '?' }}</span>
                                        </span>
                                        <span class="text-xs text-gray-500">eff. cost</span>
                                    </div>
                                @endif

                                {{-- Recipe cost segment (only when conversion resolves) --}}
                                @if ($recipeCost !== null && $recipeUomAbbr !== null && $recipeUomAbbr !== $baseUomAbbr)
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-300 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                    <div class="flex flex-col items-center">
                                        <span class="font-semibold text-indigo-700">
                                            {{ number_format($recipeCost, 4) }}
                                            <span class="text-gray-500 font-normal text-xs">/ {{ $recipeUomAbbr }}</span>
                                        </span>
                                        <span class="text-xs text-gray-500">recipe cost</span>
                                    </div>
                                @elseif ($base_uom_id && $recipe_uom_id && $base_uom_id != $recipe_uom_id && $editingId)
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-300 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                    <div class="flex flex-col items-center text-gray-400">
                                        <span class="text-xs italic">add {{ $baseUomAbbr }}→{{ $recipeUomAbbr }} conversion below</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Is Active --}}
                    <div>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="is_active"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700 font-medium">Active</span>
                        </label>
                    </div>

                    {{-- UOM Conversions (edit mode only) --}}
                    @if ($editingId)
                        <div class="border-t border-gray-100 pt-4">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-700">UOM Conversions</h4>
                                    <p class="text-xs text-gray-400 mt-0.5">
                                        e.g. 1 {{ $baseUomAbbr ?? 'base' }} = 30 {{ $recipeUomAbbr ?? 'recipe' }}
                                        → factor: 30
                                    </p>
                                </div>
                                <button type="button" wire:click="addConversionRow"
                                        class="text-xs px-2.5 py-1 bg-indigo-50 text-indigo-600 rounded-md hover:bg-indigo-100 transition">
                                    + Add Conversion
                                </button>
                            </div>

                            @if (count($conversions))
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-xs">
                                        <thead class="text-gray-400 uppercase tracking-wider">
                                            <tr>
                                                <th class="pb-1 text-left font-medium">1 unit of</th>
                                                <th class="pb-1 text-center font-medium">=</th>
                                                <th class="pb-1 text-left font-medium">Factor</th>
                                                <th class="pb-1 text-left font-medium">units of</th>
                                                <th class="pb-1"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50">
                                            @foreach ($conversions as $idx => $row)
                                                <tr>
                                                    <td class="py-1 pr-2">
                                                        <select wire:model.live="conversions.{{ $idx }}.from_uom_id"
                                                                class="w-full rounded border-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                                                            <option value="">— from —</option>
                                                            @foreach ($uoms as $uom)
                                                                <option value="{{ $uom->id }}">{{ $uom->abbreviation }}</option>
                                                            @endforeach
                                                        </select>
                                                        <x-input-error :messages="$errors->get('conversions.'.$idx.'.from_uom_id')" class="mt-0.5" />
                                                    </td>
                                                    <td class="py-1 text-center text-gray-400 px-1">=</td>
                                                    <td class="py-1 pr-2">
                                                        <input type="number" step="0.000001" min="0.000001"
                                                               wire:model.live="conversions.{{ $idx }}.factor"
                                                               placeholder="e.g. 30"
                                                               class="w-full rounded border-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500" />
                                                        <x-input-error :messages="$errors->get('conversions.'.$idx.'.factor')" class="mt-0.5" />
                                                    </td>
                                                    <td class="py-1 pr-2">
                                                        <select wire:model.live="conversions.{{ $idx }}.to_uom_id"
                                                                class="w-full rounded border-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                                                            <option value="">— to —</option>
                                                            @foreach ($uoms as $uom)
                                                                <option value="{{ $uom->id }}">{{ $uom->abbreviation }}</option>
                                                            @endforeach
                                                        </select>
                                                        <x-input-error :messages="$errors->get('conversions.'.$idx.'.to_uom_id')" class="mt-0.5" />
                                                    </td>
                                                    <td class="py-1 text-center">
                                                        <button type="button" wire:click="removeConversionRow({{ $idx }})"
                                                                class="text-red-400 hover:text-red-600 transition">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-xs text-gray-400 text-center py-3 bg-gray-50 rounded-lg">
                                    No conversions yet. Add one above to enable recipe cost calculation.
                                </p>
                            @endif
                        </div>

                        {{-- Supplier Links (edit mode only) --}}
                        <div class="border-t border-gray-100 pt-4">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-700">Suppliers</h4>
                                    <p class="text-xs text-gray-400 mt-0.5">Link suppliers and their quoted prices</p>
                                </div>
                                <button type="button" wire:click="addSupplierRow"
                                        class="text-xs px-2.5 py-1 bg-indigo-50 text-indigo-600 rounded-md hover:bg-indigo-100 transition">
                                    + Add Supplier
                                </button>
                            </div>

                            @if (count($supplierLinks))
                                <div class="space-y-2">
                                    @foreach ($supplierLinks as $idx => $link)
                                        <div class="grid grid-cols-12 gap-2 items-start bg-gray-50 rounded-lg p-2">
                                            {{-- Supplier select (spans 4 cols) --}}
                                            <div class="col-span-4">
                                                <select wire:model="supplierLinks.{{ $idx }}.supplier_id"
                                                        class="w-full rounded border-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option value="">— supplier —</option>
                                                    @foreach ($suppliers as $sup)
                                                        <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                                                    @endforeach
                                                </select>
                                                <x-input-error :messages="$errors->get('supplierLinks.'.$idx.'.supplier_id')" class="mt-0.5" />
                                            </div>
                                            {{-- SKU (spans 2 cols) --}}
                                            <div class="col-span-2">
                                                <input type="text"
                                                       wire:model="supplierLinks.{{ $idx }}.supplier_sku"
                                                       placeholder="SKU"
                                                       class="w-full rounded border-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500" />
                                            </div>
                                            {{-- Last Cost (spans 2 cols) --}}
                                            <div class="col-span-2">
                                                <input type="number" step="0.0001" min="0"
                                                       wire:model="supplierLinks.{{ $idx }}.last_cost"
                                                       placeholder="Cost"
                                                       class="w-full rounded border-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500" />
                                                <x-input-error :messages="$errors->get('supplierLinks.'.$idx.'.last_cost')" class="mt-0.5" />
                                            </div>
                                            {{-- UOM (spans 2 cols) --}}
                                            <div class="col-span-2">
                                                <select wire:model="supplierLinks.{{ $idx }}.uom_id"
                                                        class="w-full rounded border-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option value="">— UOM —</option>
                                                    @foreach ($uoms as $uom)
                                                        <option value="{{ $uom->id }}">{{ $uom->abbreviation }}</option>
                                                    @endforeach
                                                </select>
                                                <x-input-error :messages="$errors->get('supplierLinks.'.$idx.'.uom_id')" class="mt-0.5" />
                                            </div>
                                            {{-- Preferred + Remove (spans 2 cols) --}}
                                            <div class="col-span-2 flex items-center justify-between pt-1">
                                                <label class="flex items-center gap-1 cursor-pointer" title="Preferred supplier">
                                                    <input type="checkbox"
                                                           wire:model="supplierLinks.{{ $idx }}.is_preferred"
                                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                                    <span class="text-xs text-gray-500">Pref.</span>
                                                </label>
                                                <button type="button" wire:click="removeSupplierRow({{ $idx }})"
                                                        class="text-red-400 hover:text-red-600 transition">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <p class="text-xs text-gray-400 mt-2">Mark one supplier as <strong>Pref.</strong> to show it in the ingredient list.</p>
                            @else
                                <p class="text-xs text-gray-400 text-center py-3 bg-gray-50 rounded-lg">
                                    No suppliers linked yet.
                                </p>
                            @endif
                        </div>
                    @endif

                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
                    <button type="button" @click="$wire.closeModal()"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        Save Ingredient
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>
