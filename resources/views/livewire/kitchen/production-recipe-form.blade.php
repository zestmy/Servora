<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Top bar --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('kitchen.recipes.index') }}" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1 min-w-0">
            <p class="text-xs text-gray-400">
                <a href="{{ route('kitchen.index') }}" class="hover:underline">Kitchen</a>
                / <a href="{{ route('kitchen.recipes.index') }}" class="hover:underline">Recipes</a>
                / {{ $recipeId ? $name : 'New Production Recipe' }}
            </p>
        </div>
        <button wire:click="save"
                class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition flex-shrink-0">
            Save Recipe
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

    {{-- Main Layout: 2/3 left + 1/3 right --}}
    <div class="flex flex-col lg:flex-row gap-4">

        {{-- LEFT COLUMN --}}
        <div class="lg:w-2/3 space-y-4">

            {{-- Basic Details --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <h3 class="text-sm font-semibold text-gray-700">Basic Details</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Recipe Name *</label>
                        <input type="text" wire:model="name" class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. Nasi Lemak Sambal" />
                        @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Code</label>
                        <input type="text" wire:model="code" class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. NLS-001" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Category</label>
                        <input type="text" wire:model="category" class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. Sauce, Marinade, Base" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Kitchen *</label>
                        <select wire:model="kitchen_id" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">-- Select Kitchen --</option>
                            @foreach ($kitchens as $k)
                                <option value="{{ $k->id }}">{{ $k->name }}{{ $k->code ? " ({$k->code})" : '' }}</option>
                            @endforeach
                        </select>
                        @error('kitchen_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Description</label>
                    <textarea wire:model="description" rows="2" class="w-full rounded-lg border-gray-300 text-sm"
                              placeholder="Preparation notes, method summary..."></textarea>
                </div>
            </div>

            {{-- Yield --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <h3 class="text-sm font-semibold text-gray-700">Yield</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Yield Quantity *</label>
                        <input type="number" step="0.01" min="0.01" wire:model.live.debounce.500ms="yield_quantity"
                               class="w-full rounded-lg border-gray-300 text-sm" />
                        @error('yield_quantity') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Yield UOM *</label>
                        <select wire:model="yield_uom_id" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">-- Select UOM --</option>
                            @foreach ($uoms as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->abbreviation }})</option>
                            @endforeach
                        </select>
                        @error('yield_uom_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Packaging --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <h3 class="text-sm font-semibold text-gray-700">Packaging & Storage</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Packaging UOM</label>
                        <input type="text" wire:model="packaging_uom" class="w-full rounded-lg border-gray-300 text-sm"
                               placeholder="e.g. 500g pack, 1L bottle" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Per Carton Qty</label>
                        <input type="number" step="1" min="0" wire:model="per_carton_qty"
                               class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. 12" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Carton Weight (kg)</label>
                        <input type="number" step="0.01" min="0" wire:model="carton_weight"
                               class="w-full rounded-lg border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Shelf Life (days)</label>
                        <input type="number" step="1" min="0" wire:model="shelf_life_days"
                               class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. 30" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Storage Temperature</label>
                        <input type="text" wire:model="storage_temperature" class="w-full rounded-lg border-gray-300 text-sm"
                               placeholder="e.g. 2-8°C, Frozen -18°C" />
                    </div>
                </div>
            </div>

            {{-- Ingredient Lines --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700">Ingredient Lines</h3>
                        <p class="text-xs text-gray-400 mt-0.5">{{ count($lines) }} ingredient{{ count($lines) !== 1 ? 's' : '' }}</p>
                    </div>
                </div>

                {{-- Ingredient Search --}}
                <div class="px-6 py-4 border-b border-gray-100">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                            </svg>
                        </div>
                        <input type="text"
                               wire:model.live.debounce.300ms="ingredientSearch"
                               placeholder="Search ingredients to add... (type at least 2 characters)"
                               class="w-full pl-9 pr-4 py-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>

                    @if ($searchResults->isNotEmpty())
                        <div class="mt-2 border border-gray-200 rounded-lg overflow-hidden divide-y divide-gray-100 shadow-sm">
                            @foreach ($searchResults as $ingredient)
                                <button type="button"
                                        wire:click="addIngredient({{ $ingredient->id }})"
                                        class="w-full flex items-center justify-between px-4 py-2.5 hover:bg-indigo-50 transition text-left">
                                    <div>
                                        <span class="font-medium text-gray-800 text-sm">{{ $ingredient->name }}</span>
                                        @if ($ingredient->code)
                                            <span class="ml-2 text-xs text-gray-400">{{ $ingredient->code }}</span>
                                        @endif
                                    </div>
                                    <div class="text-right flex-shrink-0 ml-4 text-xs text-gray-400">
                                        <span>{{ $ingredient->baseUom?->abbreviation ?? '-' }}</span>
                                        @if (floatval($ingredient->purchase_price) > 0)
                                            <span class="ml-1">@ {{ number_format($ingredient->purchase_price, 4) }}</span>
                                        @endif
                                        <span class="ml-2 text-indigo-400">+ Add</span>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @elseif (strlen($ingredientSearch) >= 2)
                        <p class="mt-2 text-sm text-gray-400 text-center py-2">No ingredients found for "{{ $ingredientSearch }}".</p>
                    @endif

                    @error('lines') <p class="text-xs text-red-500 mt-2">{{ $message }}</p> @enderror
                </div>

                {{-- Lines table --}}
                @if (count($lines))
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                                <tr>
                                    <th class="px-4 py-2 text-left w-8">#</th>
                                    <th class="px-4 py-2 text-left">Ingredient</th>
                                    <th class="px-4 py-2 text-right w-28">Quantity</th>
                                    <th class="px-4 py-2 text-left w-28">UOM</th>
                                    <th class="px-4 py-2 text-right w-24">Waste %</th>
                                    <th class="px-4 py-2 text-right w-28">Unit Cost</th>
                                    <th class="px-4 py-2 text-right w-28">Line Cost</th>
                                    <th class="px-4 py-2 w-10"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach ($lines as $idx => $line)
                                    @php
                                        $qty   = floatval($line['quantity'] ?? 0);
                                        $cost  = floatval($line['unit_cost'] ?? 0);
                                        $waste = floatval($line['waste_percentage'] ?? 0);
                                        $lineCost = $cost * $qty * (1 + $waste / 100);
                                    @endphp
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="px-4 py-2 text-gray-400 text-xs">{{ $idx + 1 }}</td>
                                        <td class="px-4 py-2 font-medium text-gray-800">{{ $line['ingredient_name'] }}</td>
                                        <td class="px-4 py-2">
                                            <input type="number" step="0.01" min="0.01"
                                                   wire:model.live.debounce.500ms="lines.{{ $idx }}.quantity"
                                                   class="w-full text-right rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </td>
                                        <td class="px-4 py-2">
                                            <select wire:model="lines.{{ $idx }}.uom_id"
                                                    class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                @foreach ($uoms as $u)
                                                    <option value="{{ $u->id }}">{{ $u->abbreviation }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-4 py-2">
                                            <input type="number" step="0.1" min="0" max="100"
                                                   wire:model.live.debounce.500ms="lines.{{ $idx }}.waste_percentage"
                                                   class="w-full text-right rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </td>
                                        <td class="px-4 py-2 text-right tabular-nums text-gray-600">
                                            {{ number_format($cost, 4) }}
                                        </td>
                                        <td class="px-4 py-2 text-right tabular-nums text-gray-700 font-medium">
                                            {{ number_format($lineCost, 4) }}
                                        </td>
                                        <td class="px-4 py-2 text-center">
                                            <button type="button" wire:click="removeLine({{ $idx }})"
                                                    class="text-red-400 hover:text-red-600 transition">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
                    <div class="px-6 py-12 text-center text-gray-400">
                        <p class="font-medium">No ingredients added yet</p>
                        <p class="text-xs mt-1">Use the search above to add ingredients to this recipe.</p>
                    </div>
                @endif

                {{-- Footer --}}
                <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
                    <a href="{{ route('kitchen.recipes.index') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">
                        &larr; Back to Recipes
                    </a>
                    <button wire:click="save"
                            class="px-6 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        Save Recipe
                    </button>
                </div>
            </div>
        </div>

        {{-- RIGHT COLUMN: Costing Summary (sticky) --}}
        <div class="lg:w-1/3">
            <div class="lg:sticky lg:top-4 space-y-4">

                {{-- Costing Summary --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-700">Costing Summary</h3>

                    {{-- Raw Material Cost (auto) --}}
                    <div class="flex items-center justify-between py-2 border-b border-gray-100">
                        <span class="text-xs text-gray-500">Raw Material Cost</span>
                        <span class="text-sm font-medium text-gray-800 tabular-nums">{{ number_format($raw_material_cost, 4) }}</span>
                    </div>

                    {{-- Packaging Cost --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Packaging Cost per Unit</label>
                        <input type="number" step="0.01" min="0"
                               wire:model.live.debounce.500ms="packaging_cost_per_unit"
                               class="w-full rounded-lg border-gray-300 text-sm text-right" />
                    </div>

                    {{-- Label Cost --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Label Cost</label>
                        <input type="number" step="0.01" min="0"
                               wire:model.live.debounce.500ms="label_cost"
                               class="w-full rounded-lg border-gray-300 text-sm text-right" />
                    </div>

                    <div class="border-t border-gray-200 pt-3 space-y-3">
                        {{-- Total Cost per Unit --}}
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-500">Total Cost / Unit</span>
                            <span class="text-sm font-bold text-gray-900 tabular-nums">{{ number_format($total_cost_per_unit, 4) }}</span>
                        </div>

                        {{-- Selling Price --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Selling Price / Unit</label>
                            <input type="number" step="0.01" min="0"
                                   wire:model.live.debounce.500ms="selling_price_per_unit"
                                   class="w-full rounded-lg border-gray-300 text-sm text-right" />
                        </div>

                        {{-- Margin --}}
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-500">Margin</span>
                            <span class="text-sm font-bold tabular-nums {{ $margin >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ number_format($margin, 4) }}
                            </span>
                        </div>

                        {{-- Margin % --}}
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-500">Margin %</span>
                            <span class="text-sm font-bold tabular-nums {{ $margin_percent >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ number_format($margin_percent, 2) }}%
                            </span>
                        </div>
                    </div>

                    {{-- Formula hint --}}
                    <div class="text-[10px] text-gray-400 leading-relaxed border-t border-gray-100 pt-3">
                        <p>Cost/Unit = (Raw + Packaging + Label) / Yield Qty</p>
                        <p>Margin = Selling - Cost/Unit</p>
                        <p>Margin % = Margin / Selling x 100</p>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>
