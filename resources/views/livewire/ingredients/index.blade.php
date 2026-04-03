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
            <a href="{{ route('ingredients.export') }}"
               class="px-3 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center gap-1.5"
               title="Export to CSV">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M16 12l-4 4-4-4M12 4v12" />
                </svg>
                Export
            </a>
            <button wire:click="openImport"
                    class="px-3 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center gap-1.5"
                    title="Bulk update from CSV">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M8 12l4 4 4-4M12 4v12" />
                </svg>
                Bulk Update
            </button>
            <a href="{{ route('ingredients.import') }}"
               class="px-3 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
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
            <div class="flex items-center gap-1.5">
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
                <button wire:click="openCreateCategory" class="p-2 text-gray-400 hover:text-indigo-600 transition" title="Manage Categories">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </button>
            </div>
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
                    <option value="100">100 per page</option>
                    <option value="200">200 per page</option>
                    <option value="300">300 per page</option>
                    <option value="400">400 per page</option>
                    <option value="500">500 per page</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Bulk Action Bar --}}
    @if (count($selectedIds) > 0)
        <div class="mb-3 px-4 py-3 bg-indigo-50 border border-indigo-200 rounded-xl flex items-center justify-between">
            <span class="text-sm font-medium text-indigo-700">
                {{ count($selectedIds) }} ingredient{{ count($selectedIds) > 1 ? 's' : '' }} selected
            </span>
            <div class="flex items-center gap-2">
                <button wire:click="$set('selectedIds', [])"
                        class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Clear
                </button>
                <button wire:click="bulkDelete"
                        wire:confirm="Delete {{ count($selectedIds) }} selected ingredient(s)? This cannot be undone."
                        class="px-3 py-1.5 text-xs font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition">
                    Delete Selected
                </button>
            </div>
        </div>
    @endif

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 w-10">
                        <input type="checkbox" wire:model.live="selectAll"
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                    </th>
                    <th class="px-4 py-3 text-left">Name</th>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-left">UOM</th>
                    <th class="px-4 py-3 text-right">Price/Pack</th>
                    <th class="px-4 py-3 text-right">Pack Size</th>
                    <th class="px-4 py-3 text-right">Eff. Cost</th>
                    <th class="px-4 py-3 text-right">Recipe Cost</th>
                    <th class="px-4 py-3 text-center">Tax</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($ingredients as $ingredient)
                    <tr class="hover:bg-gray-50 transition {{ in_array($ingredient->id, $selectedIds) ? 'bg-indigo-50' : '' }}">
                        <td class="px-4 py-3">
                            <input type="checkbox" value="{{ $ingredient->id }}" wire:model.live="selectedIds"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-800">{{ $ingredient->name }}</div>
                            @if ($ingredient->code)
                                <div class="text-xs text-gray-400">{{ $ingredient->code }}</div>
                            @endif
                            @php
                                $defaultSupplier = $ingredient->suppliers
                                    ->sortByDesc(fn ($s) => $s->pivot->is_preferred)
                                    ->first();
                            @endphp
                            @if ($defaultSupplier)
                                <div class="text-xs text-indigo-500 mt-0.5">{{ $defaultSupplier->name }}</div>
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
                        <td class="px-4 py-3 text-gray-600">
                            <span>{{ $ingredient->baseUom->abbreviation }}</span>
                            @if ($ingredient->base_uom_id !== $ingredient->recipe_uom_id)
                                <span class="text-gray-300 mx-0.5">/</span>
                                <span class="text-indigo-500">{{ $ingredient->recipeUom->abbreviation }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">
                            {{ number_format($ingredient->purchase_price, 2) }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            @if (floatval($ingredient->pack_size) > 1)
                                <span class="text-blue-600 font-medium">{{ rtrim(rtrim(number_format(floatval($ingredient->pack_size), 4), '0'), '.') }}</span>
                                <span class="text-gray-400 text-xs">{{ $ingredient->baseUom->abbreviation }}</span>
                            @else
                                <span class="text-gray-400">1</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            <span class="{{ $ingredient->yield_percent < 100 ? 'text-red-600 font-medium' : 'text-gray-700' }}">
                                {{ number_format($ingredient->current_cost, 4) }}
                            </span>
                            <span class="text-gray-400 text-xs">/ {{ $ingredient->baseUom->abbreviation }}</span>
                            @if ($ingredient->yield_percent < 100)
                                <div class="text-xs text-amber-500">{{ number_format($ingredient->yield_percent, 0) }}% yield</div>
                            @endif
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
                            @if ($ingredient->taxRate)
                                <span class="px-2 py-0.5 rounded text-xs bg-blue-50 text-blue-600">{{ $ingredient->taxRate->name }} {{ $ingredient->taxRate->rate }}%</span>
                            @else
                                <span class="text-xs text-gray-300">Default</span>
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
                        <td colspan="11" class="px-4 py-12 text-center text-gray-400">
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
    @teleport('body')
    <div x-data="{}"
         x-show="$wire.showModal"
         x-cloak
         class="fixed inset-0 z-50">

        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-gray-900/50" @click="$wire.closeModal()"></div>

        {{-- Card --}}
        <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-2xl z-10">

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
                            <div class="flex items-center justify-between">
                                <x-input-label for="ingredient_category_id" value="Category" />
                                <button type="button" wire:click="openCreateCategory" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">+ Add</button>
                            </div>
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
                            <x-input-error :messages="$errors->get('ingredient_category_id')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="tax_rate_id" value="Tax Class" />
                            <select id="tax_rate_id" wire:model="tax_rate_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— Default (Company Rate) —</option>
                                @foreach ($taxRates as $tr)
                                    <option value="{{ $tr->id }}">{{ $tr->name }} {{ rtrim(rtrim(number_format($tr->rate, 2), '0'), '.') }}%{{ $tr->is_inclusive ? ' (Inclusive)' : '' }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('tax_rate_id')" class="mt-1" />
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
                            <p class="mt-0.5 text-xs text-gray-400">Base unit for costing (e.g. kg, L)</p>
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

                    {{-- Row 4: Purchase Price | Pack Size | Yield % --}}
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <x-input-label for="purchase_price" value="Purchase Price *" />
                            <x-text-input id="purchase_price" wire:model.live="purchase_price"
                                          type="number" step="0.0001" min="0"
                                          class="mt-1 block w-full" />
                            <p class="mt-0.5 text-xs text-gray-400">Price per pack/unit</p>
                            <x-input-error :messages="$errors->get('purchase_price')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="pack_size" value="Pack Size" />
                            <x-text-input id="pack_size" wire:model.live="pack_size"
                                          type="number" step="0.0001" min="0.0001"
                                          class="mt-1 block w-full" />
                            <p class="mt-0.5 text-xs text-gray-400">{{ $baseUomAbbr ?? 'base UOM' }} per pack (1 = no pack)</p>
                            <x-input-error :messages="$errors->get('pack_size')" class="mt-1" />
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

                    {{-- Remark + Supplier --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="remark" value="Remark" />
                            <x-text-input id="remark" wire:model="remark" type="text" class="mt-1 block w-full" placeholder="Optional note or remark" />
                        </div>
                        <div>
                            <x-input-label for="default_supplier" value="Default Supplier" />
                            <select id="default_supplier" wire:model="supplier_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— No Supplier —</option>
                                @foreach ($suppliers as $sup)
                                    <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-0.5 text-xs text-gray-400">Uses this ingredient's price, UOM &amp; pack size</p>
                        </div>
                    </div>

                    {{-- Outlet Visibility --}}
                    <div>
                        <x-input-label value="Outlet Visibility" />
                        <label class="flex items-center gap-2 mt-1 px-2 py-1.5 bg-indigo-50 rounded-lg cursor-pointer">
                            <input type="checkbox" wire:model.live="allOutletsVisible" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-sm font-medium text-indigo-700">Visible at all outlets</span>
                        </label>
                        @if (! $allOutletsVisible)
                            <div class="mt-2 max-h-28 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-1">
                                @foreach ($outlets as $o)
                                    <label class="flex items-center gap-2 px-2 py-1 rounded hover:bg-gray-50 cursor-pointer">
                                        <input type="checkbox" wire:model="ingredientOutletIds" value="{{ $o->id }}"
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                        <span class="text-sm text-gray-700">{{ $o->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Cost Chain Summary --}}
                    @if (floatval($purchase_price) > 0)
                        <div class="rounded-lg bg-indigo-50 border border-indigo-100 px-4 py-3">
                            <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wider mb-2">Cost Chain</p>
                            <div class="flex flex-wrap items-center gap-2 text-sm">

                                {{-- Purchase price (per pack) --}}
                                <div class="flex flex-col items-center">
                                    <span class="font-semibold text-gray-800">
                                        {{ number_format(floatval($purchase_price), 4) }}
                                        <span class="text-gray-500 font-normal text-xs">/ pack</span>
                                    </span>
                                    <span class="text-xs text-gray-500">purchase price</span>
                                </div>

                                {{-- Pack size step (only when pack_size > 1) --}}
                                @if (floatval($pack_size) > 1)
                                    <div class="flex flex-col items-center text-blue-500">
                                        <span class="font-medium">÷ {{ rtrim(rtrim(number_format(floatval($pack_size), 4), '0'), '.') }}</span>
                                        <span class="text-xs">{{ $baseUomAbbr ?? '?' }}/pack</span>
                                    </div>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-300 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                    <div class="flex flex-col items-center">
                                        <span class="font-semibold text-gray-700">
                                            {{ number_format($baseCost, 4) }}
                                            <span class="text-gray-500 font-normal text-xs">/ {{ $baseUomAbbr ?? '?' }}</span>
                                        </span>
                                        <span class="text-xs text-gray-500">cost per {{ $baseUomAbbr ?? 'unit' }}</span>
                                    </div>
                                @endif

                                @if (floatval($yield_percent) < 100)
                                    <div class="flex flex-col items-center text-amber-500">
                                        <span class="font-medium">÷ {{ number_format(floatval($yield_percent), 0) }}% yield</span>
                                        <span class="text-xs">loss applied</span>
                                    </div>

                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-300 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>

                                    <div class="flex flex-col items-center">
                                        <span class="font-semibold text-red-600">
                                            {{ number_format($effectiveCost, 4) }}
                                            <span class="text-gray-500 font-normal text-xs">/ {{ $baseUomAbbr ?? '?' }}</span>
                                        </span>
                                        <span class="text-xs text-gray-500">eff. cost</span>
                                    </div>
                                @else
                                    @if (floatval($pack_size) <= 1)
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
                                @elseif ($recipeCost === null && $base_uom_id && $recipe_uom_id && $base_uom_id != $recipe_uom_id && $editingId)
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-300 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                    <div class="flex flex-col items-center text-gray-400">
                                        <span class="text-xs italic">add {{ $baseUomAbbr }}→{{ $recipeUomAbbr }} custom conversion below</span>
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

                            <div class="mb-3 px-3 py-2 bg-green-50 border border-green-100 rounded-md text-xs text-green-700">
                                <strong>Built-in:</strong> kg&#8596;g&#8596;mg, L&#8596;mL&#8596;tsp&#8596;tbsp, lb&#8596;oz are auto-converted. Only add custom conversions for non-standard units (e.g. kg&#8594;pcs).
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
                                    No custom conversions. Standard conversions (same type) are automatic.
                                </p>
                            @endif
                        </div>
                    @endif

                    {{-- Additional Suppliers --}}
                    <div class="border-t border-gray-100 pt-4">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-700">Additional Suppliers</h4>
                                <p class="text-xs text-gray-400 mt-0.5">Add secondary suppliers with different pricing</p>
                            </div>
                            <button type="button" wire:click="addSupplierRow"
                                    class="text-xs px-2.5 py-1 bg-indigo-50 text-indigo-600 rounded-md hover:bg-indigo-100 transition">
                                + Add Supplier
                            </button>
                        </div>

                        @if (count($supplierLinks))
                                <div class="space-y-2">
                                    @foreach ($supplierLinks as $idx => $link)
                                        <div class="bg-gray-50 rounded-lg p-2 space-y-2">
                                            <div class="grid grid-cols-12 gap-2 items-start">
                                                {{-- Supplier select (spans 5 cols) --}}
                                                <div class="col-span-5">
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
                                                           placeholder="Cost/pack"
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
                                                {{-- Remove (spans 1 col) --}}
                                                <div class="col-span-1 flex items-center justify-center pt-1">
                                                    <button type="button" wire:click="removeSupplierRow({{ $idx }})"
                                                            class="text-red-400 hover:text-red-600 transition">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                            {{-- Pack Size row --}}
                                            <div class="flex items-center gap-2 pl-1">
                                                <span class="text-xs text-gray-400 whitespace-nowrap">Pack size:</span>
                                                <input type="number" step="0.0001" min="0.0001"
                                                       wire:model="supplierLinks.{{ $idx }}.pack_size"
                                                       placeholder="1"
                                                       class="w-24 rounded border-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500" />
                                                <span class="text-xs text-gray-400">base UOM per 1 supplier UOM (e.g. 1.2 for a 1.2kg pack)</span>
                                                <x-input-error :messages="$errors->get('supplierLinks.'.$idx.'.pack_size')" class="mt-0.5" />
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-gray-400 text-center py-3 bg-gray-50 rounded-lg">
                                    No additional suppliers. Use "+ Add Supplier" for secondary sources with different pricing.
                                </p>
                        @endif
                    </div>

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
    </div>
    @endteleport

    {{-- Import Modal --}}
    @teleport('body')
    <div x-data="{}" x-show="$wire.showImportModal" x-cloak class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-gray-900/50" @click="$wire.closeImport()"></div>
        <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg z-10">

            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-800">Bulk Update Ingredients</h3>
                <button @click="$wire.closeImport()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="px-6 py-5 space-y-4">
                <div class="bg-blue-50 border border-blue-100 rounded-lg px-4 py-3 text-sm text-blue-700">
                    <p class="font-medium mb-1">How to bulk update:</p>
                    <ol class="list-decimal list-inside space-y-0.5 text-xs">
                        <li>Click <strong>Export</strong> to download the current ingredients CSV</li>
                        <li>Open in Excel and edit Name, Code, Purchase Price, Yield %, Is Active, or Remark columns</li>
                        <li>Save as CSV and upload below</li>
                    </ol>
                    <p class="text-xs mt-2 text-blue-500">The <strong>ID</strong> column is used to match records. Do not change IDs.</p>
                    <p class="text-xs mt-2 font-medium text-blue-600">Accepted columns:</p>
                    <p class="text-xs text-blue-500">ID, Name, Code, Purchase Price, Pack Size, Yield %, Is Active, Remark</p>
                </div>

                @if (!empty($importResults))
                    <div class="rounded-lg border px-4 py-3 text-sm {{ ($importResults['updated'] ?? 0) > 0 ? 'bg-green-50 border-green-200 text-green-700' : 'bg-gray-50 border-gray-200 text-gray-600' }}">
                        <p><strong>{{ $importResults['updated'] ?? 0 }}</strong> updated, <strong>{{ $importResults['skipped'] ?? 0 }}</strong> skipped</p>
                        @if (!empty($importResults['errors']))
                            <ul class="mt-1 text-xs space-y-0.5">
                                @foreach (array_slice($importResults['errors'], 0, 5) as $err)
                                    <li class="text-red-600">{{ $err }}</li>
                                @endforeach
                                @if (count($importResults['errors']) > 5)
                                    <li class="text-gray-400">... and {{ count($importResults['errors']) - 5 }} more</li>
                                @endif
                            </ul>
                        @endif
                    </div>
                @endif

                <div>
                    <x-input-label value="Upload CSV File" />
                    <input type="file" wire:model="importFile" accept=".csv,.txt"
                           class="mt-1 block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100" />
                    <x-input-error :messages="$errors->get('importFile')" class="mt-1" />
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
                <button type="button" @click="$wire.closeImport()"
                        class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">Cancel</button>
                <button wire:click="processImport"
                        class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition"
                        {{ !$importFile ? 'disabled' : '' }}>
                    Process Update
                </button>
            </div>

        </div>
        </div>
        </div>
    </div>
    @endteleport

    {{-- Category Management Modal --}}
    @teleport('body')
    <div x-data="{}" x-show="$wire.showCategoryModal" x-cloak class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-gray-900/50" @click="$wire.closeCategoryModal()"></div>
        <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg z-10">

            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-800">
                    {{ $editingCategoryId ? 'Edit Category' : 'Add Category' }}
                </h3>
                <button @click="$wire.closeCategoryModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form wire:submit="saveCategory">
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <x-input-label for="catName" value="Name *" />
                        <x-text-input id="catName" wire:model="catName" type="text" class="mt-1 block w-full" placeholder="e.g. SEAFOOD" />
                        <x-input-error :messages="$errors->get('catName')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="catColor" value="Color" />
                            <div class="flex items-center gap-2 mt-1">
                                <input type="color" id="catColor" wire:model="catColor"
                                       class="h-9 w-12 rounded border border-gray-300 cursor-pointer" />
                                <x-text-input wire:model="catColor" type="text" class="block w-full font-mono text-xs" />
                            </div>
                            <x-input-error :messages="$errors->get('catColor')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="catSortOrder" value="Sort Order" />
                            <x-text-input id="catSortOrder" wire:model="catSortOrder" type="number" min="0" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('catSortOrder')" class="mt-1" />
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="catIsActive" wire:model="catIsActive"
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                        <label for="catIsActive" class="text-sm text-gray-600">Active</label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
                    <button type="button" @click="$wire.closeCategoryModal()"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">Cancel</button>
                    <button type="submit"
                            class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        {{ $editingCategoryId ? 'Update' : 'Create' }}
                    </button>
                </div>
            </form>

            {{-- Existing Categories List --}}
            @if ($categories->isNotEmpty())
                <div class="border-t border-gray-100 px-6 py-4">
                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Existing Categories</h4>
                    <div class="space-y-1.5 max-h-48 overflow-y-auto">
                        @foreach ($categories as $cat)
                            <div class="flex items-center justify-between py-1.5 px-2 rounded-lg hover:bg-gray-50 group">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $cat->color }}"></div>
                                    <span class="text-sm text-gray-700 {{ !$cat->is_active ? 'line-through text-gray-400' : '' }}">{{ $cat->name }}</span>
                                    <span class="text-xs text-gray-400">({{ $cat->ingredients_count ?? $cat->ingredients()->count() }})</span>
                                </div>
                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition">
                                    <button wire:click="openEditCategory({{ $cat->id }})"
                                            class="p-1 text-gray-400 hover:text-indigo-600 transition" title="Edit">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button wire:click="deleteCategory({{ $cat->id }})"
                                            wire:confirm="Delete &quot;{{ $cat->name }}&quot;? Ingredients in this category will become uncategorized."
                                            class="p-1 text-gray-400 hover:text-red-600 transition" title="Delete">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
        </div>
        </div>
    </div>
    @endteleport
</div>
