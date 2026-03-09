<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Top bar --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('inventory.index') }}" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1 min-w-0">
            <p class="text-xs text-gray-400">
                <a href="{{ route('inventory.index') }}" class="hover:underline">Inventory</a>
                / Prep Items
                / {{ $recipeId ? ($name ?: 'Edit') : 'New Prep Item' }}
            </p>
        </div>
        <button wire:click="save"
                class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            Save
        </button>
    </div>

    {{-- Validation errors --}}
    @if ($errors->any())
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            <p class="font-medium mb-1">Please fix the following:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Details card --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-semibold text-gray-700">Prep Item Details</h3>
                    <span class="px-2 py-0.5 bg-amber-100 text-amber-700 text-xs font-semibold rounded-full">PREP</span>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <x-input-label for="p_name" value="Item Name *" />
                        <x-text-input id="p_name" wire:model.live="name" type="text"
                                      class="mt-1 block w-full" placeholder="e.g. White Steamed Rice, Sambal Belacan" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="p_code" value="Code" />
                        <x-text-input id="p_code" wire:model="code" type="text"
                                      class="mt-1 block w-full" placeholder="e.g. PREP-RICE-001" />
                    </div>
                    <div>
                        <label class="inline-flex items-center gap-2 cursor-pointer mt-6">
                            <input type="checkbox" wire:model="is_active"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700 font-medium">Active</span>
                        </label>
                    </div>
                </div>

                {{-- Category --}}
                <div>
                    <x-input-label for="p_category" value="Cost Center" />
                    <select id="p_category" wire:model="ingredient_category_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— No Category —</option>
                        @foreach ($categories as $main)
                                <option value="{{ $main->id }}">{{ $main->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-0.5 text-xs text-gray-400">Used for food cost % reporting.</p>
                </div>

                {{-- Yield --}}
                <div class="grid grid-cols-2 gap-4 pt-2 border-t border-gray-100">
                    <div>
                        <x-input-label for="p_yield" value="Yield Quantity *" />
                        <x-text-input id="p_yield" wire:model.live="yield_quantity" type="number"
                                      min="0.0001" step="0.01" class="mt-1 block w-full" />
                        <p class="mt-0.5 text-xs text-gray-400">How many portions / units this batch produces</p>
                        <x-input-error :messages="$errors->get('yield_quantity')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="p_uom" value="Yield UOM *" />
                        <select id="p_uom" wire:model.live="yield_uom_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— Select UOM —</option>
                            @foreach ($uoms as $uom)
                                <option value="{{ $uom->id }}">{{ $uom->name }} ({{ $uom->abbreviation }})</option>
                            @endforeach
                        </select>
                        <p class="mt-0.5 text-xs text-gray-400">Unit used when counting in stock take</p>
                        <x-input-error :messages="$errors->get('yield_uom_id')" class="mt-1" />
                    </div>
                </div>

                {{-- Notes --}}
                <div>
                    <x-input-label for="p_notes" value="Notes" />
                    <textarea id="p_notes" wire:model="notes" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="Optional preparation notes…"></textarea>
                </div>
            </div>
        </div>

        {{-- Cost summary card --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 lg:sticky lg:top-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Cost Summary</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Ingredients</dt>
                        <dd class="font-medium text-gray-800">{{ count($lines) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Batch Total Cost</dt>
                        <dd class="font-semibold text-gray-800 tabular-nums">RM {{ number_format($totalCost, 4) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Yield</dt>
                        <dd class="text-gray-700 tabular-nums">{{ $yield_quantity }} {{ collect($uoms)->firstWhere('id', $yield_uom_id)?->abbreviation ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-gray-100 pt-3">
                        <dt class="font-semibold text-gray-700">Cost per Unit</dt>
                        <dd class="font-bold text-lg text-indigo-600 tabular-nums">
                            RM {{ number_format($costPerYieldUnit, 4) }}
                        </dd>
                    </div>
                </dl>

                <div class="mt-4 pt-4 border-t border-gray-100 rounded-lg bg-amber-50 px-3 py-2.5">
                    <p class="text-xs text-amber-700 font-medium mb-1">How this works</p>
                    <p class="text-xs text-amber-600 leading-relaxed">
                        Saving this prep item will create/update a corresponding <strong>ingredient</strong> with cost = RM {{ number_format($costPerYieldUnit, 4) }} per unit.
                        This ingredient can then be used in sale recipes and counted in stock takes.
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Ingredients section --}}
    <div class="mt-4 bg-white rounded-xl shadow-sm border border-gray-100">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">Ingredients Used</h3>
                <p class="text-xs text-gray-400 mt-0.5">Raw ingredients that go into making this prep item</p>
            </div>
        </div>

        {{-- Ingredient search --}}
        <div class="px-6 py-4 border-b border-gray-100">
            <div class="relative">
                <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                    </svg>
                </div>
                <input type="text"
                       wire:model.live.debounce.300ms="ingredientSearch"
                       placeholder="Search raw ingredients…"
                       class="w-full pl-9 pr-4 py-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>

            @if ($searchResults->isNotEmpty())
                <div class="mt-2 border border-gray-200 rounded-lg overflow-hidden divide-y divide-gray-100 shadow-sm">
                    @foreach ($searchResults as $ingredient)
                        <button type="button" wire:click="addIngredient({{ $ingredient->id }})"
                                class="w-full flex items-center justify-between px-4 py-2.5 hover:bg-indigo-50 transition text-left">
                            <div>
                                <span class="font-medium text-gray-800 text-sm">{{ $ingredient->name }}</span>
                                @if ($ingredient->category)
                                    <span class="ml-2 text-xs text-gray-400">· {{ $ingredient->category }}</span>
                                @endif
                            </div>
                            <div class="text-right text-xs flex-shrink-0 ml-4">
                                <span class="text-gray-400">
                                    RM {{ number_format($ingredient->purchase_price, 4) }}
                                    / {{ $ingredient->baseUom?->abbreviation }}
                                </span>
                                <span class="ml-2 text-indigo-400">+ Add</span>
                            </div>
                        </button>
                    @endforeach
                </div>
            @elseif (strlen($ingredientSearch) >= 2)
                <p class="mt-2 text-sm text-gray-400 text-center py-2">No ingredients found.</p>
            @endif

            <x-input-error :messages="$errors->get('lines')" class="mt-2" />
        </div>

        {{-- Lines table --}}
        @if (count($lines))
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                        <tr>
                            <th class="px-4 py-2 text-left w-8">#</th>
                            <th class="px-4 py-2 text-left">Ingredient</th>
                            <th class="px-4 py-2 text-right w-28">Qty</th>
                            <th class="px-4 py-2 text-left w-44">UOM</th>
                            <th class="px-4 py-2 text-right w-24">Waste %</th>
                            <th class="px-4 py-2 text-right w-32">Line Cost (RM)</th>
                            <th class="px-4 py-2 w-10"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($lines as $idx => $line)
                            <tr class="hover:bg-gray-50 transition group">
                                <td class="px-4 py-2 text-gray-400 text-xs">{{ $idx + 1 }}</td>
                                <td class="px-4 py-2 font-medium text-gray-800">{{ $line['ingredient_name'] }}</td>
                                <td class="px-4 py-2">
                                    <input type="number" step="0.01" min="0.0001"
                                           wire:model.live.debounce.400ms="lines.{{ $idx }}.quantity"
                                           class="w-full text-right rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    <x-input-error :messages="$errors->get('lines.'.$idx.'.quantity')" class="mt-0.5" />
                                </td>
                                <td class="px-4 py-2">
                                    <select wire:model.live="lines.{{ $idx }}.uom_id"
                                            class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        @foreach ($uoms as $uom)
                                            <option value="{{ $uom->id }}">{{ $uom->name }} ({{ $uom->abbreviation }})</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('lines.'.$idx.'.uom_id')" class="mt-0.5" />
                                </td>
                                <td class="px-4 py-2">
                                    <div class="relative">
                                        <input type="number" step="0.1" min="0" max="100"
                                               wire:model.live.debounce.400ms="lines.{{ $idx }}.waste_percentage"
                                               class="w-full text-right rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 pr-6" />
                                        <span class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-gray-400">%</span>
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums font-medium text-gray-800">
                                    @if (isset($lineCosts[$idx]) && $lineCosts[$idx] !== null)
                                        {{ number_format($lineCosts[$idx], 4) }}
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-center opacity-0 group-hover:opacity-100 transition">
                                    <button type="button" wire:click="removeLine({{ $idx }})"
                                            class="text-red-400 hover:text-red-600 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200 text-sm font-semibold">
                        <tr>
                            <td colspan="5" class="px-4 py-3 text-right text-gray-600">Batch Total</td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-900">
                                {{ number_format($totalCost, 4) }}
                            </td>
                            <td></td>
                        </tr>
                        <tr class="bg-indigo-50">
                            <td colspan="5" class="px-4 py-3 text-right text-indigo-700 font-semibold">
                                Cost per {{ collect($uoms)->firstWhere('id', $yield_uom_id)?->abbreviation ?? 'unit' }}
                                (÷ {{ $yield_quantity }})
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-bold text-indigo-700">
                                {{ number_format($costPerYieldUnit, 4) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="px-6 py-12 text-center text-gray-400">
                <p class="text-3xl mb-2">🍳</p>
                <p class="font-medium">No ingredients added yet</p>
                <p class="text-xs mt-1">Search for the raw ingredients that make up this prep item.</p>
            </div>
        @endif

        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
            <a href="{{ route('inventory.index') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">Cancel</a>
            <button wire:click="save"
                    class="px-6 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                Save Prep Item
            </button>
        </div>
    </div>
</div>
