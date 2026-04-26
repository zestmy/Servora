<div>
    @once
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    @endonce
    <x-desktop-hint storageKey="desktop-hint-recipe-form" message="Editing recipes and ingredient lines is easier on a desktop. Mobile works, but a wider screen helps." />
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Top bar --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('recipes.index') }}" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1 min-w-0">
            <p class="text-xs text-gray-400"><a href="{{ route('recipes.index') }}" class="hover:underline">Recipes</a> / {{ $recipeId ? $name : 'New Recipe' }}</p>
        </div>
        <button wire:click="save"
                class="flex-shrink-0 px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            Save Recipe
        </button>
    </div>

    {{-- Validation errors summary --}}
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

        {{-- ── Details card (2/3) ── --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Recipe Details</h3>

                {{-- Name --}}
                <div>
                    <x-input-label for="r_name" value="Recipe Name *" />
                    <x-text-input id="r_name" wire:model="name" type="text"
                                  class="mt-1 block w-full text-base font-medium"
                                  placeholder="e.g. Garlic Butter Prawn" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                {{-- Code | Category --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="r_code" value="Code" />
                        <x-text-input id="r_code" wire:model="code" type="text"
                                      class="mt-1 block w-full" placeholder="e.g. RCP-001" />
                        <x-input-error :messages="$errors->get('code')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="r_category" value="Menu Category" />
                        <select id="r_category" wire:model="category"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— No Category —</option>
                            @foreach ($recipeCategories as $cat)
                                @if ($cat->children && $cat->children->count())
                                    <optgroup label="{{ $cat->name }}">
                                        <option value="{{ $cat->name }}">{{ $cat->name }} (All)</option>
                                        @foreach ($cat->children as $sub)
                                            <option value="{{ $sub->name }}">{{ $sub->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @else
                                    <option value="{{ $cat->name }}">{{ $cat->name }}</option>
                                @endif
                            @endforeach
                        </select>
                        @if ($recipeCategories->isEmpty())
                            <p class="mt-0.5 text-xs text-gray-400">
                                <a href="{{ route('settings.recipe-categories') }}" class="text-indigo-500 hover:underline" target="_blank">Add recipe categories</a> in Settings.
                            </p>
                        @endif
                        <x-input-error :messages="$errors->get('category')" class="mt-1" />
                    </div>
                </div>

                {{-- Department --}}
                <div>
                    <x-input-label for="r_department" value="Department" />
                    <select id="r_department" wire:model="department_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— No Department —</option>
                        @foreach ($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-0.5 text-xs text-gray-400">Assigns cost to a department.</p>
                    <x-input-error :messages="$errors->get('department_id')" class="mt-1" />
                </div>

                {{-- Description --}}
                <div>
                    <x-input-label for="r_desc" value="Description" />
                    <textarea id="r_desc" wire:model="description" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="Optional notes or preparation instructions…"></textarea>
                </div>

                {{-- Yield | UOM | Selling Price --}}
                <div class="grid grid-cols-{{ $priceClasses->isEmpty() ? '3' : '2' }} gap-4">
                    <div>
                        <x-input-label for="r_yield" value="Yield Qty *" />
                        <x-text-input id="r_yield" wire:model.live="yield_quantity"
                                      type="number" step="0.01" min="0.01"
                                      class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('yield_quantity')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="r_uom" value="Yield UOM *" />
                        <select id="r_uom" wire:model.live="yield_uom_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— select —</option>
                            @foreach ($uoms as $uom)
                                <option value="{{ $uom->id }}">{{ $uom->name }} ({{ $uom->abbreviation }})</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('yield_uom_id')" class="mt-1" />
                    </div>
                    @if ($priceClasses->isEmpty())
                        <div>
                            <x-input-label for="r_price" value="Selling Price" />
                            <x-text-input id="r_price" wire:model.live="selling_price"
                                          type="number" step="0.01" min="0"
                                          class="mt-1 block w-full" />
                            <p class="mt-0.5 text-xs text-gray-400">Per {{ $yield_quantity ?: '1' }} {{ collect($uoms)->firstWhere('id', $yield_uom_id)?->abbreviation ?? 'serving' }}</p>
                            <x-input-error :messages="$errors->get('selling_price')" class="mt-1" />
                        </div>
                    @endif
                </div>

                {{-- Multi-price classes --}}
                @if ($priceClasses->isNotEmpty())
                    <div class="border-t border-gray-100 pt-4">
                        <x-input-label value="Selling Prices" />
                        <p class="text-xs text-gray-400 mt-0.5 mb-3">Set different selling prices per price class. <a href="{{ route('settings.price-classes') }}" target="_blank" class="text-indigo-500 hover:underline">Manage classes</a></p>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            @foreach ($priceClasses as $pc)
                                <div>
                                    <label class="text-xs font-medium text-gray-600 flex items-center gap-1">
                                        {{ $pc->name }}
                                        @if ($pc->is_default)
                                            <span class="px-1 py-0.5 bg-indigo-100 text-indigo-600 text-xs rounded">Default</span>
                                        @endif
                                    </label>
                                    <x-text-input wire:model.live="classPrices.{{ $pc->id }}"
                                                  type="number" step="0.01" min="0"
                                                  class="mt-1 block w-full" />
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Is Active + LMS toggle --}}
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="is_active"
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                        <span class="text-sm text-gray-700 font-medium">Active</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer" title="Hide this recipe from the LMS (training) portal">
                        <input type="checkbox" wire:model="exclude_from_lms"
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                        <span class="text-sm text-gray-700 font-medium">Exclude from LMS</span>
                    </label>
                </div>

                {{-- Outlet Tagging --}}
                @if ($outlets->count() > 1)
                    <div class="border-t border-gray-100 pt-4">
                        <x-input-label value="Available At" />
                        <p class="text-xs text-gray-400 mt-0.5 mb-3">Tag this recipe to specific outlets, or leave as "All Outlets" to make it available everywhere.</p>

                        <div class="space-y-2">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="radio" wire:model.live="allOutlets" value="1"
                                       class="text-indigo-600 border-gray-300 focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700 font-medium">All Outlets</span>
                            </label>

                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="radio" wire:model.live="allOutlets" value=""
                                       class="text-indigo-600 border-gray-300 focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700 font-medium">Selected Outlets</span>
                            </label>
                        </div>

                        @if (! $allOutlets)
                            @if ($outletGroups->count() > 0)
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <span class="text-xs font-medium text-gray-500">Apply Group:</span>
                                    @foreach ($outletGroups as $group)
                                        <button type="button"
                                                wire:click="applyGroup({{ $group->id }})"
                                                class="inline-flex items-center gap-1 px-3 py-1 rounded-full border border-indigo-200 bg-white text-indigo-600 text-xs font-medium hover:bg-indigo-50 transition">
                                            + {{ $group->name }}
                                            <span class="text-[10px] text-gray-400">({{ count($group->outlet_ids) }})</span>
                                        </button>
                                    @endforeach
                                    @if (! empty($outletIds))
                                        <button type="button" wire:click="clearOutletSelection"
                                                class="text-xs text-gray-400 hover:text-gray-600 underline">Clear</button>
                                    @endif
                                </div>
                            @endif

                            <div class="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-2">
                                @foreach ($outlets as $outlet)
                                    <label class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition
                                        {{ in_array($outlet->id, $outletIds) ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200 hover:border-gray-300' }}">
                                        <input type="checkbox"
                                               value="{{ $outlet->id }}"
                                               wire:model.live="outletIds"
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                        <div class="min-w-0">
                                            <span class="text-sm font-medium text-gray-700 block truncate">{{ $outlet->name }}</span>
                                            @if ($outlet->code)
                                                <span class="text-xs text-gray-400">{{ $outlet->code }}</span>
                                            @endif
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                            @if (empty($outletIds))
                                <p class="mt-1 text-xs text-amber-500">Select at least one outlet, or switch to "All Outlets".</p>
                            @endif
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- ── Cost Summary card (1/3, sticky) ── --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 lg:sticky lg:top-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Cost Summary</h3>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Ingredient Cost</dt>
                        <dd class="text-gray-700 tabular-nums">{{ number_format($totalCost, 2) }}</dd>
                    </div>

                    @if ($packagingCost > 0)
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Packaging Cost</dt>
                            <dd class="text-gray-700 tabular-nums">{{ number_format($packagingCost, 2) }}</dd>
                        </div>
                    @endif

                    @if ($extraCostTotal > 0)
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Extra Costs</dt>
                            <dd class="text-gray-700 tabular-nums">{{ number_format($extraCostTotal, 2) }}</dd>
                        </div>
                    @endif

                    <div class="flex justify-between border-t border-gray-100 pt-2">
                        <dt class="text-gray-500 font-medium">Cost (excl. tax)</dt>
                        <dd class="font-semibold text-gray-800 tabular-nums">
                            {{ number_format($grandCost, 2) }}
                        </dd>
                    </div>

                    @if ($totalTaxWithPackaging > 0)
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Tax</dt>
                            <dd class="text-gray-700 tabular-nums">{{ number_format($totalTaxWithPackaging, 2) }}</dd>
                        </div>

                        <div class="flex justify-between">
                            <dt class="text-gray-500 font-medium">Cost (incl. tax)</dt>
                            <dd class="font-semibold text-indigo-700 tabular-nums">
                                {{ number_format($grandCostWithTax, 2) }}
                            </dd>
                        </div>
                    @endif

                    @php $yieldUomAbbr = collect($uoms)->firstWhere('id', $yield_uom_id)?->abbreviation ?? 'serving'; @endphp
                    <div class="flex justify-between border-t border-gray-100 pt-2">
                        <dt class="text-gray-500">
                            Cost / {{ $yieldUomAbbr }}
                        </dt>
                        <dd class="text-gray-700 tabular-nums">{{ number_format($costPerServing, 4) }}</dd>
                    </div>
                    @if ($totalTaxWithPackaging > 0)
                        <div class="flex justify-between">
                            <dt class="text-gray-500">
                                Cost / {{ $yieldUomAbbr }} <span class="text-xs text-gray-400">(w/ tax)</span>
                            </dt>
                            <dd class="text-indigo-700 tabular-nums">{{ number_format($costPerServingWithTax, 4) }}</dd>
                        </div>
                    @endif

                    @if ($priceClasses->isNotEmpty())
                        {{-- Multi-class pricing breakdown --}}
                        <div class="border-t border-gray-100 pt-3 space-y-2">
                            <dt class="text-gray-600 font-medium text-xs uppercase tracking-wider">Pricing by Class</dt>
                            @foreach ($priceClasses as $pc)
                                @php
                                    $cd = $classCostData[$pc->id] ?? [];
                                    $sp = $cd['selling_price'] ?? 0;
                                    $fcp = $cd['food_cost_pct'] ?? null;
                                    $gp = $cd['gross_profit'] ?? null;
                                    $pcColor = match(true) {
                                        $fcp === null => 'text-gray-400',
                                        $fcp <= 25    => 'text-green-600',
                                        $fcp <= 35    => 'text-yellow-600',
                                        $fcp <= 45    => 'text-orange-500',
                                        default       => 'text-red-600',
                                    };
                                @endphp
                                @if ($sp > 0)
                                    <div class="rounded-lg bg-gray-50 px-3 py-2">
                                        <div class="flex justify-between items-center">
                                            <span class="text-xs font-medium text-gray-600">{{ $pc->name }}</span>
                                            <span class="text-sm font-semibold text-gray-800 tabular-nums">{{ number_format($sp, 2) }}</span>
                                        </div>
                                        <div class="flex justify-between mt-1 text-xs">
                                            <span class="text-gray-500">Food Cost %</span>
                                            <span class="font-bold {{ $pcColor }} tabular-nums">{{ number_format($fcp, 1) }}%</span>
                                        </div>
                                        <div class="flex justify-between mt-0.5 text-xs">
                                            <span class="text-gray-500">Gross Profit</span>
                                            <span class="{{ $gp >= 0 ? 'text-green-600' : 'text-red-600' }} font-medium tabular-nums">{{ number_format($gp, 2) }}</span>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                            @if (collect($classCostData)->every(fn ($cd) => ($cd['selling_price'] ?? 0) <= 0))
                                <div class="text-xs text-gray-400 italic">Enter selling prices above to see food cost %.</div>
                            @endif
                        </div>

                        {{-- Benchmark guide --}}
                        <div class="text-xs text-gray-400 space-y-0.5 pt-1">
                            <p class="font-medium text-gray-500 mb-1">Food cost guide:</p>
                            <p><span class="text-green-600">≤25%</span> Excellent &nbsp;
                               <span class="text-yellow-600">25–35%</span> Good</p>
                            <p><span class="text-orange-500">35–45%</span> High &nbsp;
                               <span class="text-red-600">&gt;45%</span> Review</p>
                        </div>
                    @elseif (floatval($selling_price) > 0)
                        <div class="flex justify-between border-t border-gray-100 pt-3">
                            <dt class="text-gray-500">Selling Price</dt>
                            <dd class="text-gray-700 tabular-nums">{{ number_format(floatval($selling_price), 2) }}</dd>
                        </div>

                        @php
                            $fcColor = match(true) {
                                $foodCostPct === null => 'text-gray-400',
                                $foodCostPct <= 25   => 'text-green-600',
                                $foodCostPct <= 35   => 'text-yellow-600',
                                $foodCostPct <= 45   => 'text-orange-500',
                                default              => 'text-red-600',
                            };
                            $fcBg = match(true) {
                                $foodCostPct === null => 'bg-gray-50',
                                $foodCostPct <= 25   => 'bg-green-50',
                                $foodCostPct <= 35   => 'bg-yellow-50',
                                $foodCostPct <= 45   => 'bg-orange-50',
                                default              => 'bg-red-50',
                            };
                        @endphp

                        <div class="rounded-lg {{ $fcBg }} px-3 py-2">
                            <div class="flex justify-between">
                                <dt class="text-gray-600 font-medium">Food Cost %</dt>
                                <dd class="font-bold text-lg {{ $fcColor }} tabular-nums">
                                    {{ number_format($foodCostPct, 1) }}%
                                </dd>
                            </div>
                            @if ($totalTaxWithPackaging > 0 && $foodCostPctWithTax !== null)
                                <div class="flex justify-between mt-1 text-xs">
                                    <span class="text-gray-500">Food Cost % (w/ tax)</span>
                                    <span class="font-medium text-indigo-600 tabular-nums">{{ number_format($foodCostPctWithTax, 1) }}%</span>
                                </div>
                            @endif
                            <div class="flex justify-between mt-1 text-xs">
                                <span class="text-gray-500">Gross Profit</span>
                                <span class="{{ $grossProfit >= 0 ? 'text-green-600' : 'text-red-600' }} font-medium tabular-nums">
                                    {{ number_format($grossProfit, 2) }}
                                    ({{ number_format($grossProfitPct, 1) }}%)
                                </span>
                            </div>
                        </div>

                        {{-- Benchmark guide --}}
                        <div class="text-xs text-gray-400 space-y-0.5 pt-1">
                            <p class="font-medium text-gray-500 mb-1">Food cost guide:</p>
                            <p><span class="text-green-600">≤25%</span> Excellent &nbsp;
                               <span class="text-yellow-600">25–35%</span> Good</p>
                            <p><span class="text-orange-500">35–45%</span> High &nbsp;
                               <span class="text-red-600">&gt;45%</span> Review</p>
                        </div>
                    @else
                        <div class="text-xs text-gray-400 mt-2 italic">
                            Enter a selling price to see food cost %.
                        </div>
                    @endif
                </dl>
            </div>
        </div>

    </div>

    {{-- ── Product Images ── --}}
    <div class="mt-4 bg-white rounded-xl shadow-sm border border-gray-100 p-6" x-data="{ imageTab: 'dine_in' }">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">Product Images</h3>
                <p class="text-xs text-gray-400 mt-0.5">Upload final product photos for plating reference. Max 5MB per image.</p>
            </div>
            <div class="flex rounded-lg border border-gray-200 overflow-hidden text-sm">
                <button type="button" @click="imageTab = 'dine_in'"
                        :class="imageTab === 'dine_in' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                        class="px-4 py-1.5 font-medium transition">
                    Dine-In
                </button>
                <button type="button" @click="imageTab = 'takeaway'"
                        :class="imageTab === 'takeaway' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                        class="px-4 py-1.5 font-medium transition border-l border-gray-200">
                    Takeaway
                </button>
            </div>
        </div>

        {{-- Dine-In Images --}}
        <div x-show="imageTab === 'dine_in'" x-cloak>
            @if (count($existingDineInImages))
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 mb-4">
                    @foreach ($existingDineInImages as $img)
                        <div class="relative group rounded-lg overflow-hidden border border-gray-200 bg-gray-50">
                            <img src="{{ $img['url'] }}" alt="{{ $img['file_name'] }}"
                                 class="w-full h-32 object-cover" />
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                                <button type="button" wire:click="removeExistingImage({{ $img['id'] }})"
                                        wire:confirm="Remove this image?"
                                        class="px-3 py-1.5 bg-red-600 text-white text-xs font-medium rounded-lg hover:bg-red-700 transition">
                                    Remove
                                </button>
                            </div>
                            <div class="px-2 py-1.5 text-xs text-gray-500 truncate">{{ $img['file_name'] }}</div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div>
                <label class="block">
                    <div class="flex items-center justify-center w-full h-24 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-indigo-400 hover:bg-indigo-50/30 transition">
                        <div class="text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-7 w-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <p class="mt-1 text-xs text-gray-500">Upload dine-in plating photos</p>
                        </div>
                    </div>
                    <input type="file" wire:model="newDineInImages" multiple accept="image/*" class="hidden" />
                </label>
                <x-input-error :messages="$errors->get('newDineInImages.*')" class="mt-1" />
            </div>

            @if (count($newDineInImages))
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 mt-3">
                    @foreach ($newDineInImages as $idx => $file)
                        <div class="relative group rounded-lg overflow-hidden border border-indigo-200 bg-indigo-50">
                            <img src="{{ $file->temporaryUrl() }}" alt="New upload"
                                 class="w-full h-32 object-cover" />
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                                <button type="button" wire:click="removeNewDineInImage({{ $idx }})"
                                        class="px-3 py-1.5 bg-red-600 text-white text-xs font-medium rounded-lg hover:bg-red-700 transition">
                                    Remove
                                </button>
                            </div>
                            <div class="absolute top-1 right-1 px-1.5 py-0.5 bg-indigo-600 text-white text-xs rounded font-medium">New</div>
                            <div class="px-2 py-1.5 text-xs text-gray-500 truncate">{{ $file->getClientOriginalName() }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Takeaway Images --}}
        <div x-show="imageTab === 'takeaway'" x-cloak>
            @if (count($existingTakeawayImages))
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 mb-4">
                    @foreach ($existingTakeawayImages as $img)
                        <div class="relative group rounded-lg overflow-hidden border border-gray-200 bg-gray-50">
                            <img src="{{ $img['url'] }}" alt="{{ $img['file_name'] }}"
                                 class="w-full h-32 object-cover" />
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                                <button type="button" wire:click="removeExistingImage({{ $img['id'] }})"
                                        wire:confirm="Remove this image?"
                                        class="px-3 py-1.5 bg-red-600 text-white text-xs font-medium rounded-lg hover:bg-red-700 transition">
                                    Remove
                                </button>
                            </div>
                            <div class="px-2 py-1.5 text-xs text-gray-500 truncate">{{ $img['file_name'] }}</div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div>
                <label class="block">
                    <div class="flex items-center justify-center w-full h-24 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-amber-400 hover:bg-amber-50/30 transition">
                        <div class="text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-7 w-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <p class="mt-1 text-xs text-gray-500">Upload takeaway presentation photos</p>
                        </div>
                    </div>
                    <input type="file" wire:model="newTakeawayImages" multiple accept="image/*" class="hidden" />
                </label>
                <x-input-error :messages="$errors->get('newTakeawayImages.*')" class="mt-1" />
            </div>

            @if (count($newTakeawayImages))
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 mt-3">
                    @foreach ($newTakeawayImages as $idx => $file)
                        <div class="relative group rounded-lg overflow-hidden border border-amber-200 bg-amber-50">
                            <img src="{{ $file->temporaryUrl() }}" alt="New upload"
                                 class="w-full h-32 object-cover" />
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                                <button type="button" wire:click="removeNewTakeawayImage({{ $idx }})"
                                        class="px-3 py-1.5 bg-red-600 text-white text-xs font-medium rounded-lg hover:bg-red-700 transition">
                                    Remove
                                </button>
                            </div>
                            <div class="absolute top-1 right-1 px-1.5 py-0.5 bg-amber-600 text-white text-xs rounded font-medium">New</div>
                            <div class="px-2 py-1.5 text-xs text-gray-500 truncate">{{ $file->getClientOriginalName() }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- ── Training / SOP ── --}}
    <div class="mt-4 bg-white rounded-xl shadow-sm border border-gray-100" x-data="{ sopOpen: {{ count($steps) || $video_url ? 'true' : 'false' }} }">
        <button type="button" @click="sopOpen = !sopOpen"
                class="w-full flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition">
            <div class="text-left">
                <h3 class="text-sm font-semibold text-gray-700">Training / SOP</h3>
                <p class="text-xs text-gray-400 mt-0.5">Preparation steps, video & training content</p>
            </div>
            <svg :class="sopOpen && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div x-show="sopOpen" x-cloak class="px-6 pb-6 space-y-4 border-t border-gray-100">
            {{-- Video URL --}}
            <div class="pt-4">
                <x-input-label for="video_url" value="Video URL" />
                <x-text-input id="video_url" wire:model="video_url" type="url"
                              class="mt-1 block w-full text-sm"
                              placeholder="https://www.youtube.com/watch?v=..." />
                <p class="text-xs text-gray-400 mt-1">YouTube or Vimeo link for the training video</p>
                <x-input-error :messages="$errors->get('video_url')" class="mt-1" />
            </div>

            {{-- Preparation Steps --}}
            <div>
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700">Preparation Steps</h4>
                        <p class="text-xs text-gray-400 mt-0.5">{{ count($steps) }} step{{ count($steps) !== 1 ? 's' : '' }}</p>
                    </div>
                    <button type="button" wire:click="addStep"
                            class="px-3 py-1.5 text-xs font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">
                        + Add Step
                    </button>
                </div>

                @if (count($steps))
                    <div class="space-y-3">
                        @foreach ($steps as $idx => $step)
                            <div class="relative bg-gray-50 rounded-lg p-4 border border-gray-200" wire:key="step-{{ $idx }}">
                                <div class="flex items-start gap-3">
                                    <span class="flex-shrink-0 w-7 h-7 bg-indigo-600 text-white rounded-full flex items-center justify-center text-xs font-bold mt-1">{{ $idx + 1 }}</span>
                                    <div class="flex-1 space-y-2">
                                        <input type="text"
                                               wire:model.blur="steps.{{ $idx }}.title"
                                               placeholder="Step title (optional, e.g. Preparation)"
                                               class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        <textarea wire:model.blur="steps.{{ $idx }}.instruction"
                                                  rows="3"
                                                  placeholder="Describe the preparation step..."
                                                  class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                        <x-input-error :messages="$errors->get('steps.'.$idx.'.instruction')" class="mt-0.5" />

                                        {{-- Image upload --}}
                                        <div class="flex items-start gap-3 pt-1">
                                            <div class="flex-shrink-0">
                                                @if (!empty($step['new_image']))
                                                    {{-- Newly uploaded (pending save) --}}
                                                    <div class="relative">
                                                        <img src="{{ $step['new_image']->temporaryUrl() }}" class="w-20 h-20 object-cover rounded border border-indigo-300" />
                                                        <button type="button" wire:click="clearStepNewImage({{ $idx }})"
                                                                class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs hover:bg-red-600">×</button>
                                                    </div>
                                                @elseif (!empty($step['image_url']) && empty($step['remove_image']))
                                                    {{-- Existing saved image --}}
                                                    <div class="relative">
                                                        <img src="{{ $step['image_url'] }}" class="w-20 h-20 object-cover rounded border border-gray-300" />
                                                        <button type="button" wire:click="removeStepImage({{ $idx }})"
                                                                class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs hover:bg-red-600" title="Remove image">×</button>
                                                    </div>
                                                @else
                                                    <label class="cursor-pointer inline-flex flex-col items-center justify-center w-20 h-20 rounded border-2 border-dashed border-gray-300 bg-white hover:border-indigo-400 hover:bg-indigo-50 transition">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                        <span class="text-[10px] text-gray-500 mt-1">Add photo</span>
                                                        <input type="file" wire:model="steps.{{ $idx }}.new_image" accept="image/*" class="hidden" />
                                                    </label>
                                                @endif
                                            </div>
                                            <div class="flex-1">
                                                <p class="text-xs text-gray-500 pt-1">Optional step photo (max 5MB). Images enhance training visuals in the LMS and SOP PDFs.</p>
                                                <x-input-error :messages="$errors->get('steps.'.$idx.'.new_image')" class="mt-1" />
                                                <div wire:loading wire:target="steps.{{ $idx }}.new_image" class="text-xs text-indigo-500 mt-1">Uploading…</div>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" wire:click="removeStep({{ $idx }})"
                                            class="flex-shrink-0 text-red-400 hover:text-red-600 transition mt-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-6 text-gray-400">
                        <p class="text-sm">No preparation steps added yet.</p>
                        <p class="text-xs mt-1">Add steps to create training content for this recipe.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Ingredient Lines ── --}}
    <div class="mt-4 bg-white rounded-xl shadow-sm border border-gray-100">

        {{-- Section header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">Ingredients</h3>
                <p class="text-xs text-gray-400 mt-0.5">{{ count($lines) }} item{{ count($lines) !== 1 ? 's' : '' }}</p>
            </div>
        </div>

        {{-- Search / Add --}}
        <div class="px-6 py-4 border-b border-gray-100" x-data="{ focused: false }">
            <div class="relative">
                <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                    </svg>
                </div>
                <input type="text"
                       wire:model.live.debounce.300ms="ingredientSearch"
                       @focus="focused = true" @click.away="focused = false"
                       placeholder="Search ingredients to add… (type at least 2 characters)"
                       class="w-full pl-9 pr-4 py-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>

            {{-- Search results --}}
            @if ($searchResults->isNotEmpty())
                <div class="mt-2 border border-gray-200 rounded-lg overflow-hidden divide-y divide-gray-100 shadow-sm">
                    @foreach ($searchResults as $ingredient)
                        <button type="button"
                                wire:click="addIngredient({{ $ingredient->id }})"
                                class="w-full flex items-center justify-between px-4 py-2.5 hover:bg-indigo-50 transition text-left">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-medium text-gray-800 text-sm">{{ $ingredient->name }}</span>
                                @if ($ingredient->is_prep)
                                    <span class="px-1.5 py-0.5 bg-amber-100 text-amber-700 text-xs font-semibold rounded">PREP</span>
                                @endif
                                @if ($ingredient->code)
                                    <span class="text-xs text-gray-400">{{ $ingredient->code }}</span>
                                @endif
                                @if ($ingredient->category)
                                    <span class="text-xs text-gray-400">· {{ $ingredient->category }}</span>
                                @endif
                            </div>
                            <div class="text-right flex-shrink-0 ml-4">
                                <span class="text-xs text-indigo-600 font-medium">
                                    {{ $ingredient->recipeUom->abbreviation }}
                                    @if ($ingredient->secondaryRecipeUom)
                                        <span class="text-purple-500">· {{ $ingredient->secondaryRecipeUom->abbreviation }}</span>
                                    @endif
                                </span>
                                <span class="text-xs text-gray-400 ml-1">
                                    @php $rc = $ingredient->recipeCost(); @endphp
                                    @if ($rc !== null)
                                        RM {{ number_format($rc, 4) }}/{{ $ingredient->recipeUom->abbreviation }}
                                    @else
                                        RM {{ number_format($ingredient->current_cost, 4) }}/{{ $ingredient->baseUom->abbreviation }}
                                    @endif
                                </span>
                                <span class="ml-2 text-xs text-indigo-400">+ Add</span>
                            </div>
                        </button>
                    @endforeach
                </div>
            @elseif (strlen($ingredientSearch) >= 2)
                <p class="mt-2 text-sm text-gray-400 text-center py-2">No ingredients found for "{{ $ingredientSearch }}".</p>
            @endif

            <p class="mt-2 text-xs text-gray-400">
                Can't find it?
                <a href="{{ route('ingredients.index') }}" target="_blank" class="text-indigo-500 hover:underline">+ Add new ingredient</a>
            </p>
        </div>

        {{-- Lines table --}}
        @if (count($lines))
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                        <tr>
                            <th class="px-2 py-2 text-left w-6"></th>
                            <th class="px-4 py-2 text-left w-8">#</th>
                            <th class="px-4 py-2 text-left">Ingredient</th>
                            <th class="px-4 py-2 text-right w-28">Qty</th>
                            <th class="px-4 py-2 text-left w-36">UOM</th>
                            <th class="px-4 py-2 text-right w-24">Waste %</th>
                            <th class="px-4 py-2 text-right w-32">Line Cost</th>
                            <th class="px-4 py-2 w-10"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50"
                           x-data
                           x-init="new Sortable($el, {
                               handle: '.line-drag-handle',
                               animation: 150,
                               ghostClass: 'bg-indigo-50',
                               onEnd: () => {
                                   const idxs = Array.from($el.querySelectorAll('tr[data-idx]')).map(tr => tr.dataset.idx);
                                   $wire.reorderLines(idxs);
                               }
                           })">
                        @foreach ($lines as $idx => $line)
                            <tr wire:key="line-{{ $line['ingredient_id'] ?? $idx }}" data-idx="{{ $idx }}" class="hover:bg-gray-50 transition group">
                                <td class="line-drag-handle px-2 py-2 text-center text-gray-300 hover:text-gray-500 cursor-grab select-none" title="Drag to reorder">
                                    <svg class="w-4 h-4 inline" fill="currentColor" viewBox="0 0 20 20"><path d="M7 4a1 1 0 11-2 0 1 1 0 012 0zm0 4a1 1 0 11-2 0 1 1 0 012 0zm0 4a1 1 0 11-2 0 1 1 0 012 0zm0 4a1 1 0 11-2 0 1 1 0 012 0zm8-12a1 1 0 11-2 0 1 1 0 012 0zm0 4a1 1 0 11-2 0 1 1 0 012 0zm0 4a1 1 0 11-2 0 1 1 0 012 0zm0 4a1 1 0 11-2 0 1 1 0 012 0z"/></svg>
                                </td>
                                <td class="px-4 py-2 text-gray-400 text-xs">{{ $idx + 1 }}</td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-gray-800">{{ $line['ingredient_name'] }}</span>
                                        @if ($line['is_prep'] ?? false)
                                            <span class="px-1.5 py-0.5 bg-amber-100 text-amber-700 text-xs font-semibold rounded">PREP</span>
                                        @endif
                                    </div>
                                    <x-input-error :messages="$errors->get('lines.'.$idx.'.ingredient_id')" class="mt-0.5" />
                                </td>
                                <td class="px-4 py-2">
                                    <input type="number" step="0.0001" min="0.0001"
                                           wire:model.live.debounce.400ms="lines.{{ $idx }}.quantity"
                                           class="w-full text-right rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    <x-input-error :messages="$errors->get('lines.'.$idx.'.quantity')" class="mt-0.5" />
                                </td>
                                <td class="px-4 py-2">
                                    @php
                                        $lineRecipeUomIds = array_filter([
                                            $line['recipe_uom_id'] ?? null,
                                            $line['secondary_recipe_uom_id'] ?? null,
                                        ]);
                                        $lineValidUoms = count($lineRecipeUomIds)
                                            ? $uoms->whereIn('id', $lineRecipeUomIds)->values()
                                            : $uoms;
                                        $lineOtherUoms = count($lineRecipeUomIds)
                                            ? $uoms->whereNotIn('id', $lineRecipeUomIds)->values()
                                            : collect();
                                    @endphp
                                    <select wire:model.live="lines.{{ $idx }}.uom_id"
                                            class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        @if (count($lineRecipeUomIds))
                                            <optgroup label="Recipe UOMs">
                                                @foreach ($lineValidUoms as $uom)
                                                    <option value="{{ $uom->id }}">{{ $uom->abbreviation }}</option>
                                                @endforeach
                                            </optgroup>
                                            <optgroup label="Other UOMs">
                                                @foreach ($lineOtherUoms as $uom)
                                                    <option value="{{ $uom->id }}">{{ $uom->abbreviation }}</option>
                                                @endforeach
                                            </optgroup>
                                        @else
                                            @foreach ($uoms as $uom)
                                                <option value="{{ $uom->id }}">{{ $uom->abbreviation }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                    <x-input-error :messages="$errors->get('lines.'.$idx.'.uom_id')" class="mt-0.5" />
                                </td>
                                <td class="px-4 py-2">
                                    <div class="relative">
                                        <input type="number" step="0.1" min="0" max="100"
                                               wire:model.live.debounce.400ms="lines.{{ $idx }}.waste_percentage"
                                               class="w-full text-right pr-6 rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        <span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none">%</span>
                                    </div>
                                    <x-input-error :messages="$errors->get('lines.'.$idx.'.waste_percentage')" class="mt-0.5" />
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">
                                    @if ($lineCosts[$idx] !== null)
                                        <span class="font-medium text-gray-800">{{ number_format($lineCosts[$idx], 4) }}</span>
                                    @else
                                        <span class="text-gray-300 text-xs italic">—</span>
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
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                        <tr>
                            <td colspan="6" class="px-4 py-3 text-right text-sm font-semibold text-gray-600">Ingredient Cost</td>
                            <td class="px-4 py-3 text-right font-bold text-gray-900 tabular-nums text-base">
                                {{ number_format($totalCost, 2) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="px-6 py-12 text-center text-gray-400">
                <p class="text-3xl mb-2">🔍</p>
                <p class="font-medium">No ingredients added yet</p>
                <p class="text-xs mt-1">Use the search above to find and add ingredients.</p>
            </div>
        @endif

        {{-- ── Packaging ── --}}
        <div class="border-t border-gray-100">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div>
                    <h3 class="text-sm font-semibold text-gray-700">Packaging</h3>
                    <p class="text-xs text-gray-400 mt-0.5">{{ count($packagingLines) }} item{{ count($packagingLines) !== 1 ? 's' : '' }} · counted separately so packaging cost appears as its own line</p>
                </div>
            </div>

            <div class="px-6 py-4 border-b border-gray-100">
                <div class="relative">
                    <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                        </svg>
                    </div>
                    <input type="text"
                           wire:model.live.debounce.300ms="packagingSearch"
                           placeholder="Search packaging items to add… (type at least 2 characters)"
                           class="w-full pl-9 pr-4 py-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>

                @if ($packagingSearchResults->isNotEmpty())
                    <div class="mt-2 border border-gray-200 rounded-lg overflow-hidden divide-y divide-gray-100 shadow-sm">
                        @foreach ($packagingSearchResults as $ingredient)
                            <button type="button"
                                    wire:click="addPackaging({{ $ingredient->id }})"
                                    class="w-full flex items-center justify-between px-4 py-2.5 hover:bg-indigo-50 transition text-left">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-medium text-gray-800 text-sm">{{ $ingredient->name }}</span>
                                    @if ($ingredient->code)
                                        <span class="text-xs text-gray-400">{{ $ingredient->code }}</span>
                                    @endif
                                </div>
                                <div class="text-right flex-shrink-0 ml-4">
                                    <span class="text-xs text-indigo-600 font-medium">
                                        {{ $ingredient->recipeUom?->abbreviation ?? $ingredient->baseUom?->abbreviation }}
                                    </span>
                                    <span class="text-xs text-gray-400 ml-1">
                                        RM {{ number_format($ingredient->current_cost, 4) }}/{{ $ingredient->baseUom?->abbreviation }}
                                    </span>
                                    <span class="ml-2 text-xs text-indigo-400">+ Add</span>
                                </div>
                            </button>
                        @endforeach
                    </div>
                @elseif (strlen($packagingSearch) >= 2)
                    <p class="mt-2 text-sm text-gray-400 text-center py-2">No items found for "{{ $packagingSearch }}".</p>
                @endif
            </div>

            @if (count($packagingLines))
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                            <tr>
                                <th class="px-2 py-2 text-left w-6"></th>
                                <th class="px-4 py-2 text-left w-8">#</th>
                                <th class="px-4 py-2 text-left">Packaging</th>
                                <th class="px-4 py-2 text-right w-28">Qty</th>
                                <th class="px-4 py-2 text-left w-36">UOM</th>
                                <th class="px-4 py-2 text-right w-24">Waste %</th>
                                <th class="px-4 py-2 text-right w-32">Line Cost</th>
                                <th class="px-4 py-2 w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50"
                               x-data
                               x-init="new Sortable($el, {
                                   handle: '.pack-drag-handle',
                                   animation: 150,
                                   ghostClass: 'bg-indigo-50',
                                   onEnd: () => {
                                       const idxs = Array.from($el.querySelectorAll('tr[data-pack-idx]')).map(tr => tr.dataset.packIdx);
                                       $wire.reorderPackagingLines(idxs);
                                   }
                               })">
                            @foreach ($packagingLines as $idx => $line)
                                <tr wire:key="pack-line-{{ $line['ingredient_id'] ?? $idx }}" data-pack-idx="{{ $idx }}" class="hover:bg-gray-50 transition group">
                                    <td class="pack-drag-handle px-2 py-2 text-center text-gray-300 hover:text-gray-500 cursor-grab select-none" title="Drag to reorder">
                                        <svg class="w-4 h-4 inline" fill="currentColor" viewBox="0 0 20 20"><path d="M7 4a1 1 0 11-2 0 1 1 0 012 0zm0 4a1 1 0 11-2 0 1 1 0 012 0zm0 4a1 1 0 11-2 0 1 1 0 012 0zm0 4a1 1 0 11-2 0 1 1 0 012 0zm8-12a1 1 0 11-2 0 1 1 0 012 0zm0 4a1 1 0 11-2 0 1 1 0 012 0zm0 4a1 1 0 11-2 0 1 1 0 012 0zm0 4a1 1 0 11-2 0 1 1 0 012 0z"/></svg>
                                    </td>
                                    <td class="px-4 py-2 text-gray-400 text-xs">{{ $idx + 1 }}</td>
                                    <td class="px-4 py-2">
                                        <span class="font-medium text-gray-800">{{ $line['ingredient_name'] }}</span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="number" step="0.0001" min="0.0001"
                                               wire:model.live.debounce.400ms="packagingLines.{{ $idx }}.quantity"
                                               class="w-full text-right rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    </td>
                                    <td class="px-4 py-2">
                                        @php
                                            $pkgUomIds = array_filter([
                                                $line['recipe_uom_id'] ?? null,
                                                $line['secondary_recipe_uom_id'] ?? null,
                                            ]);
                                            $pkgValidUoms = count($pkgUomIds)
                                                ? $uoms->whereIn('id', $pkgUomIds)->values()
                                                : $uoms;
                                            $pkgOtherUoms = count($pkgUomIds)
                                                ? $uoms->whereNotIn('id', $pkgUomIds)->values()
                                                : collect();
                                        @endphp
                                        <select wire:model.live="packagingLines.{{ $idx }}.uom_id"
                                                class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            @if (count($pkgUomIds))
                                                <optgroup label="Recipe UOMs">
                                                    @foreach ($pkgValidUoms as $uom)
                                                        <option value="{{ $uom->id }}">{{ $uom->abbreviation }}</option>
                                                    @endforeach
                                                </optgroup>
                                                <optgroup label="Other UOMs">
                                                    @foreach ($pkgOtherUoms as $uom)
                                                        <option value="{{ $uom->id }}">{{ $uom->abbreviation }}</option>
                                                    @endforeach
                                                </optgroup>
                                            @else
                                                @foreach ($uoms as $uom)
                                                    <option value="{{ $uom->id }}">{{ $uom->abbreviation }}</option>
                                                @endforeach
                                            @endif
                                        </select>
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="number" step="0.1" min="0" max="100"
                                               wire:model.live.debounce.400ms="packagingLines.{{ $idx }}.waste_percentage"
                                               class="w-full text-right rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    </td>
                                    <td class="px-4 py-2 text-right tabular-nums">
                                        @if (($packagingLineCosts[$idx] ?? null) !== null)
                                            <span class="font-medium text-gray-800">{{ number_format($packagingLineCosts[$idx], 4) }}</span>
                                        @else
                                            <span class="text-gray-300 text-xs italic">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-center opacity-0 group-hover:opacity-100 transition">
                                        <button type="button" wire:click="removePackagingLine({{ $idx }})"
                                                class="text-red-400 hover:text-red-600 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                            <tr>
                                <td colspan="6" class="px-4 py-3 text-right text-sm font-semibold text-gray-600">Packaging Cost</td>
                                <td class="px-4 py-3 text-right font-bold text-gray-900 tabular-nums text-base">
                                    {{ number_format($packagingCost, 2) }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>

        {{-- Extra Costs --}}
        <div class="px-6 py-4 border-t border-gray-100">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h4 class="text-sm font-semibold text-gray-700">Extra Costs</h4>
                    <p class="text-xs text-gray-400 mt-0.5">Add overhead costs like packaging, electricity, logistics, labour.</p>
                </div>
                <button type="button" wire:click="addExtraCostRow"
                        class="px-3 py-1.5 text-xs font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">
                    + Add Cost
                </button>
            </div>

            @if (count($extraCosts))
                <div class="space-y-2">
                    @foreach ($extraCosts as $idx => $cost)
                        <div class="flex items-center gap-2" wire:key="extra-cost-{{ $idx }}">
                            <div class="flex-1">
                                <input type="text"
                                       wire:model.live.debounce.400ms="extraCosts.{{ $idx }}.label"
                                       placeholder="e.g. Packaging Cost"
                                       class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                <x-input-error :messages="$errors->get('extraCosts.'.$idx.'.label')" class="mt-0.5" />
                            </div>
                            <div class="w-24">
                                <select wire:model.live="extraCosts.{{ $idx }}.type"
                                        class="w-full rounded border-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="value">RM</option>
                                    <option value="percent">%</option>
                                </select>
                            </div>
                            <div class="w-28">
                                <input type="number" step="0.01" min="0"
                                       wire:model.live.debounce.400ms="extraCosts.{{ $idx }}.amount"
                                       placeholder="{{ ($cost['type'] ?? 'value') === 'percent' ? '0 %' : '0.00' }}"
                                       class="w-full text-right rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                <x-input-error :messages="$errors->get('extraCosts.'.$idx.'.amount')" class="mt-0.5" />
                            </div>
                            @if (($cost['type'] ?? 'value') === 'percent' && floatval($cost['amount'] ?? 0) > 0 && $totalCost > 0)
                                <span class="text-xs text-gray-400 tabular-nums w-20 text-right">= {{ number_format($totalCost * floatval($cost['amount']) / 100, 2) }}</span>
                            @else
                                <span class="w-20"></span>
                            @endif
                            <button type="button" wire:click="removeExtraCostRow({{ $idx }})"
                                    class="text-red-400 hover:text-red-600 transition flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    @endforeach
                </div>

                @if ($extraCostTotal > 0)
                    <div class="mt-3 flex justify-end text-sm">
                        <span class="text-gray-500 mr-4">Extra Costs Subtotal:</span>
                        <span class="font-semibold text-gray-800 tabular-nums">{{ number_format($extraCostTotal, 2) }}</span>
                    </div>
                @endif
            @endif

            @if (count($lines) || count($extraCosts))
                <div class="mt-4 pt-3 border-t-2 border-gray-200 flex justify-end text-sm">
                    <span class="text-gray-600 font-semibold mr-4">Grand Total Cost:</span>
                    <span class="font-bold text-gray-900 tabular-nums text-base">{{ number_format($grandCost, 2) }}</span>
                </div>
            @endif
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
            <a href="{{ route('recipes.index') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">
                Cancel
            </a>
            <button wire:click="save"
                    class="px-6 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                Save Recipe
            </button>
        </div>

    </div>
</div>
