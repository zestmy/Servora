<div>
    {{-- Top bar --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('settings.form-templates') }}" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1 min-w-0">
            <p class="text-xs text-gray-400">
                <a href="{{ route('settings.index') }}" class="hover:underline">Settings</a> /
                <a href="{{ route('settings.form-templates') }}" class="hover:underline">Form Templates</a> /
                {{ $name }}
            </p>
        </div>
    </div>

    {{-- Flash messages --}}
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Details card --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4 lg:sticky lg:top-6">
                <h3 class="text-sm font-semibold text-gray-700">Template Details</h3>

                <div>
                    <x-input-label value="Name *" />
                    <x-text-input wire:model="name" type="text" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="Type" />
                    <p class="mt-1 text-sm text-gray-600 font-medium px-3 py-2 bg-gray-50 rounded-md border border-gray-200">
                        @php
                            $typeLabels = ['stock_take' => 'Stock Take', 'purchase_order' => 'Purchase Order', 'wastage' => 'Wastage'];
                            $typeColors = ['stock_take' => 'text-teal-700', 'purchase_order' => 'text-blue-700', 'wastage' => 'text-red-700'];
                        @endphp
                        <span class="{{ $typeColors[$form_type] ?? 'text-gray-700' }}">
                            {{ $typeLabels[$form_type] ?? $form_type }}
                        </span>
                    </p>
                </div>

                <div>
                    <x-input-label value="Description" />
                    <textarea wire:model="description" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="Optional notes…"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label value="Sort Order" />
                        <x-text-input wire:model="sort_order" type="number" min="0" max="9999"
                                      class="mt-1 block w-full" />
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="is_active"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">Active</span>
                        </label>
                    </div>
                </div>

                <div class="pt-2">
                    <button wire:click="saveHeader"
                            class="w-full px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        Save Details
                    </button>
                </div>

                <div class="text-xs text-gray-400 pt-2 border-t border-gray-100">
                    <p>{{ count($lines) }} item{{ count($lines) !== 1 ? 's' : '' }} in this template.</p>
                    @if ($form_type === 'wastage')
                        <p class="mt-1">Wastage templates support both ingredients and recipes.</p>
                    @else
                        <p class="mt-1">Search for ingredients to add to this template.</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Items card --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">

                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Template Items</h3>
                    <p class="text-xs text-gray-400 mt-0.5">These items will be pre-loaded when this template is applied to a form.</p>
                </div>

                {{-- Search --}}
                <div class="px-6 py-4 border-b border-gray-100">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                            </svg>
                        </div>
                        <input type="text"
                               wire:model.live.debounce.300ms="itemSearch"
                               placeholder="{{ $form_type === 'wastage' ? 'Search ingredients or recipes…' : 'Search ingredients…' }}"
                               class="w-full pl-9 pr-4 py-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>

                    @if ($ingredientResults->isNotEmpty() || $recipeResults->isNotEmpty())
                        <div class="mt-2 border border-gray-200 rounded-lg overflow-hidden divide-y divide-gray-100 shadow-sm">

                            @if ($ingredientResults->isNotEmpty())
                                @if ($recipeResults->isNotEmpty())
                                    <div class="px-4 py-1.5 bg-gray-50 text-xs font-semibold text-gray-400 uppercase tracking-wider">Ingredients</div>
                                @endif
                                @foreach ($ingredientResults as $ingredient)
                                    <button type="button" wire:click="addIngredient({{ $ingredient->id }})"
                                            class="w-full flex items-center justify-between px-4 py-2.5 hover:bg-indigo-50 transition text-left">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-gray-800 text-sm">{{ $ingredient->name }}</span>
                                            @if ($ingredient->is_prep)
                                                <span class="px-1.5 py-0.5 bg-amber-100 text-amber-700 text-xs font-semibold rounded">PREP</span>
                                            @endif
                                        </div>
                                        <span class="text-xs text-gray-400 ml-4">{{ $ingredient->baseUom?->abbreviation }}
                                            <span class="text-indigo-400 ml-1">+ Add</span>
                                        </span>
                                    </button>
                                @endforeach
                            @endif

                            @if ($recipeResults->isNotEmpty())
                                <div class="px-4 py-1.5 bg-gray-50 text-xs font-semibold text-gray-400 uppercase tracking-wider">Recipes</div>
                                @foreach ($recipeResults as $recipe)
                                    <button type="button" wire:click="addRecipe({{ $recipe->id }})"
                                            class="w-full flex items-center justify-between px-4 py-2.5 hover:bg-indigo-50 transition text-left">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-gray-800 text-sm">{{ $recipe->name }}</span>
                                            <span class="px-1.5 py-0.5 bg-indigo-100 text-indigo-700 text-xs font-semibold rounded">RECIPE</span>
                                        </div>
                                        <span class="text-xs text-gray-400 ml-4">{{ $recipe->yieldUom?->abbreviation }}
                                            <span class="text-indigo-400 ml-1">+ Add</span>
                                        </span>
                                    </button>
                                @endforeach
                            @endif

                        </div>
                    @elseif (strlen($itemSearch) >= 2)
                        <p class="mt-2 text-sm text-gray-400 text-center py-2">No items found.</p>
                    @endif
                </div>

                {{-- Lines table --}}
                @if (count($lines))
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                                <tr>
                                    <th class="px-4 py-2 text-left w-8">#</th>
                                    <th class="px-4 py-2 text-left">Item</th>
                                    <th class="px-4 py-2 text-left w-16">UOM</th>
                                    <th class="px-4 py-2 text-right w-36">Default Qty</th>
                                    <th class="px-4 py-2 w-10"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach ($lines as $idx => $line)
                                    <tr class="hover:bg-gray-50 transition group">
                                        <td class="px-4 py-2 text-gray-400 text-xs">{{ $idx + 1 }}</td>
                                        <td class="px-4 py-2">
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium text-gray-800">{{ $line['item_name'] }}</span>
                                                @if ($line['item_type'] === 'recipe')
                                                    <span class="px-1.5 py-0.5 bg-indigo-100 text-indigo-700 text-xs font-semibold rounded">RECIPE</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-2 text-gray-500 text-xs">{{ $line['uom_abbr'] }}</td>
                                        <td class="px-4 py-2">
                                            <input type="number" step="0.0001" min="0.0001"
                                                   value="{{ $line['default_quantity'] }}"
                                                   wire:change="updateQty({{ $line['id'] }}, $event.target.value)"
                                                   class="w-full text-right rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </td>
                                        <td class="px-4 py-2 text-center opacity-0 group-hover:opacity-100 transition">
                                            <button type="button" wire:click="removeLine({{ $line['id'] }})"
                                                    wire:confirm="Remove '{{ addslashes($line['item_name']) }}' from this template?"
                                                    class="text-red-400 hover:text-red-600 transition">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
                    <div class="py-12 text-center text-gray-400">
                        <p class="text-3xl mb-2">📝</p>
                        <p class="font-medium">No items yet</p>
                        <p class="text-xs mt-1">Search above to add ingredients{{ $form_type === 'wastage' ? ' or recipes' : '' }} to this template.</p>
                    </div>
                @endif

            </div>
        </div>

    </div>
</div>
