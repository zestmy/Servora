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
                / {{ $recordId ? 'Stock Take #' . $recordId : 'New Stock Take' }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if ($recordId)
                <a href="{{ route('inventory.stock-takes.count-sheet', $recordId) }}"
                   target="_blank"
                   class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                    Print Count Sheet
                </a>
            @endif
            @if (! $isCompleted)
                <button wire:click="save('save')"
                        class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                    Save Draft
                </button>
                <button wire:click="save('complete')"
                        wire:confirm="Mark this stock take as completed? This finalises the count."
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    Complete
                </button>
            @else
                <span class="px-3 py-1.5 bg-green-100 text-green-700 text-xs font-semibold rounded-full">Completed</span>
            @endif
        </div>
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

    @if (session('info'))
        <div class="mb-4 px-4 py-3 bg-amber-50 border border-amber-200 text-amber-700 text-sm rounded-lg">
            {{ session('info') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Details card --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <h3 class="text-sm font-semibold text-gray-700">Stock Take Details</h3>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="st_date" value="Date *" />
                        <x-text-input id="st_date" wire:model="stock_take_date" type="date"
                                      class="mt-1 block w-full" :disabled="$isCompleted" />
                        <x-input-error :messages="$errors->get('stock_take_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="st_ref" value="Reference" />
                        <x-text-input id="st_ref" wire:model="reference_number" type="text"
                                      class="mt-1 block w-full" placeholder="e.g. Weekly Count #1"
                                      :disabled="$isCompleted" />
                    </div>
                </div>

                <div>
                    <x-input-label for="st_dept" value="Department" />
                    <select id="st_dept" wire:model="department_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            {{ $isCompleted ? 'disabled' : '' }}>
                        <option value="">— All / No Department —</option>
                        @foreach ($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="st_notes" value="Notes" />
                    <textarea id="st_notes" wire:model="notes" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="Optional notes…"
                              {{ $isCompleted ? 'disabled' : '' }}></textarea>
                </div>
            </div>
        </div>

        {{-- Summary card --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 lg:sticky lg:top-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Summary</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Ingredients</dt>
                        <dd class="font-medium text-gray-800">{{ count($lines) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Over (+)</dt>
                        <dd class="font-medium text-green-600">{{ $positiveVariance }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Short (−)</dt>
                        <dd class="font-medium text-red-500">{{ $negativeVariance }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-gray-100 pt-3">
                        <dt class="font-semibold text-gray-600">Total Stock Value</dt>
                        <dd class="font-bold text-lg text-gray-800 tabular-nums">
                            RM {{ number_format($totalStockCost, 2) }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="font-semibold text-gray-600">Variance Cost</dt>
                        <dd class="font-bold text-base {{ $totalVarianceCost >= 0 ? 'text-green-600' : 'text-red-600' }} tabular-nums">
                            {{ $totalVarianceCost >= 0 ? '+' : '' }}{{ number_format($totalVarianceCost, 2) }}
                        </dd>
                    </div>
                </dl>

                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-xs text-gray-400 leading-relaxed">
                        <strong>Variance</strong> = Actual − System qty.<br>
                        Positive means more stock than expected.<br>
                        Negative means stock is missing.
                    </p>
                </div>

                @php $statusColors = ['draft' => 'bg-gray-100 text-gray-600', 'in_progress' => 'bg-yellow-100 text-yellow-700', 'completed' => 'bg-green-100 text-green-700']; @endphp
                <div class="mt-4">
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold {{ $statusColors[$status] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Items section --}}
    <div class="mt-4 bg-white rounded-xl shadow-sm border border-gray-100">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">Ingredients</h3>
                <p class="text-xs text-gray-400 mt-0.5">{{ count($lines) }} ingredient{{ count($lines) !== 1 ? 's' : '' }} · grouped by category</p>
            </div>
            @if (! $isCompleted)
                <div class="flex items-center gap-2 flex-wrap justify-end">
                    {{-- Template picker --}}
                    @if ($availableTemplates->isNotEmpty())
                        <select wire:model="selectedTemplateId" wire:change="loadTemplate"
                                class="text-xs border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5">
                            <option value="">Load Template…</option>
                            @foreach ($availableTemplates as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    <button type="button" wire:click="loadAll"
                            wire:confirm="Load all active ingredients? Items already added will be skipped."
                            class="text-xs text-indigo-600 hover:text-indigo-800 border border-indigo-200 px-3 py-1.5 rounded-lg hover:bg-indigo-50 transition">
                        Load All
                    </button>
                </div>
            @endif
        </div>

        {{-- Search --}}
        @if (! $isCompleted)
            <div class="px-6 py-4 border-b border-gray-100">
                <div class="relative">
                    <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                        </svg>
                    </div>
                    <input type="text"
                           wire:model.live.debounce.300ms="ingredientSearch"
                           placeholder="Search ingredients to add…"
                           class="w-full pl-9 pr-4 py-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>

                @if ($searchResults->isNotEmpty())
                    <div class="mt-2 border border-gray-200 rounded-lg overflow-hidden divide-y divide-gray-100 shadow-sm">
                        @foreach ($searchResults as $ingredient)
                            <button type="button" wire:click="addIngredient({{ $ingredient->id }})"
                                    class="w-full flex items-center justify-between px-4 py-2.5 hover:bg-indigo-50 transition text-left">
                                <div>
                                    <span class="font-medium text-gray-800 text-sm">{{ $ingredient->name }}</span>
                                </div>
                                <div class="text-right text-xs flex-shrink-0 ml-4">
                                    <span class="text-gray-500">{{ $ingredient->baseUom?->abbreviation }}</span>
                                    <span class="ml-2 text-indigo-400">+ Add</span>
                                </div>
                            </button>
                        @endforeach
                    </div>
                @elseif (strlen($ingredientSearch) >= 2)
                    <p class="mt-2 text-sm text-gray-400 text-center py-2">No ingredients found.</p>
                @endif

                <p class="mt-2 text-xs text-gray-400">
                    Can't find it?
                    <a href="{{ route('ingredients.index') }}" target="_blank" class="text-indigo-500 hover:underline">+ Add new ingredient</a>
                </p>

                <x-input-error :messages="$errors->get('lines')" class="mt-2" />
            </div>
        @endif

        {{-- Lines table — grouped by category --}}
        @if (count($lines))
            @php
                $colSpan = $isCompleted ? 8 : 9;

                // Build indexed + grouped lines
                $indexedLines = collect($lines)->map(fn($line, $idx) => array_merge($line, ['_idx' => $idx]));

                // Sort within group by category_sub_name then ingredient_name
                $grouped = $indexedLines->groupBy('category_group_name');

                // Uncategorized goes last
                $categorized   = $grouped->filter(fn($g, $k) => $k !== 'Uncategorized')->sortKeys();
                $uncategorized = $grouped->filter(fn($g, $k) => $k === 'Uncategorized');
                $grouped       = $categorized->merge($uncategorized);
            @endphp
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                        <tr>
                            <th class="px-4 py-2 text-left w-8">#</th>
                            <th class="px-4 py-2 text-left">Ingredient</th>
                            <th class="px-4 py-2 text-right w-28">System Qty</th>
                            <th class="px-4 py-2 text-right w-28">Actual Qty</th>
                            <th class="px-4 py-2 text-left w-16">UOM</th>
                            <th class="px-4 py-2 text-right w-28">Unit Cost</th>
                            <th class="px-4 py-2 text-right w-32">Stock Value</th>
                            <th class="px-4 py-2 text-right w-32">Variance Cost</th>
                            @if (! $isCompleted)
                                <th class="px-4 py-2 w-10"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @php $rowNum = 0; @endphp
                        @foreach ($grouped as $groupName => $groupLines)
                            @php
                                $groupColor      = $groupLines->first()['category_group_color'] ?? '#6b7280';
                                $groupStockValue = $groupLines->sum(fn($l) => floatval($l['actual_quantity']) * floatval($l['unit_cost']));
                                $groupVariance   = $groupLines->sum(fn($l) => floatval($l['variance_cost']));
                            @endphp

                            {{-- Category header row --}}
                            <tr class="bg-gray-50 border-t border-gray-200">
                                <td colspan="{{ $colSpan }}" class="px-4 py-2">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                                                  style="background-color: {{ $groupColor }}"></span>
                                            <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">
                                                {{ $groupName }}
                                            </span>
                                            <span class="text-xs text-gray-400">({{ $groupLines->count() }})</span>
                                        </div>
                                        <div class="flex items-center gap-6 text-xs tabular-nums">
                                            <span class="text-gray-500">
                                                Stock: <span class="font-semibold text-gray-700">RM {{ number_format($groupStockValue, 2) }}</span>
                                            </span>
                                            <span class="{{ $groupVariance >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                Var: <span class="font-semibold">{{ $groupVariance >= 0 ? '+' : '' }}{{ number_format($groupVariance, 2) }}</span>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            {{-- Lines for this group --}}
                            @foreach ($groupLines as $line)
                                @php
                                    $idx          = $line['_idx'];
                                    $variance     = floatval($line['variance_quantity']);
                                    $varianceCost = floatval($line['variance_cost']);
                                    $stockValue   = floatval($line['actual_quantity']) * floatval($line['unit_cost']);
                                    $varColor     = $variance > 0 ? 'text-green-600' : ($variance < 0 ? 'text-red-500' : 'text-gray-400');
                                    $rowNum++;
                                @endphp
                                <tr class="hover:bg-gray-50 transition group border-t border-gray-50">
                                    <td class="px-4 py-2 text-gray-400 text-xs">{{ $rowNum }}</td>
                                    <td class="px-4 py-2">
                                        <div class="flex items-center gap-1.5">
                                            @if ($line['category_sub_name'])
                                                <span class="text-xs text-gray-400">{{ $line['category_sub_name'] }} ·</span>
                                            @endif
                                            <span class="font-medium text-gray-800">{{ $line['ingredient_name'] }}</span>
                                            @if ($line['is_prep'] ?? false)
                                                <span class="px-1.5 py-0.5 bg-amber-100 text-amber-700 text-xs font-semibold rounded">PREP</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        @if ($isCompleted)
                                            <span class="block text-right tabular-nums text-gray-600">{{ number_format(floatval($line['system_quantity']), 2) }}</span>
                                        @else
                                            <input type="number" step="0.1" min="0"
                                                   wire:model.live.debounce.400ms="lines.{{ $idx }}.system_quantity"
                                                   class="w-full text-right rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">
                                        @if ($isCompleted)
                                            <span class="block text-right tabular-nums font-medium text-gray-800">{{ number_format(floatval($line['actual_quantity']), 2) }}</span>
                                        @else
                                            <input type="number" step="0.1" min="0"
                                                   wire:model.live.debounce.400ms="lines.{{ $idx }}.actual_quantity"
                                                   class="w-full text-right rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 font-medium" />
                                            <x-input-error :messages="$errors->get('lines.'.$idx.'.actual_quantity')" class="mt-0.5" />
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-gray-500 text-xs">{{ $line['uom_abbr'] }}</td>
                                    <td class="px-4 py-2">
                                        @if ($isCompleted)
                                            <span class="block text-right tabular-nums text-gray-600">{{ number_format(floatval($line['unit_cost']), 4) }}</span>
                                        @else
                                            <input type="number" step="0.0001" min="0"
                                                   wire:model.live.debounce.400ms="lines.{{ $idx }}.unit_cost"
                                                   class="w-full text-right rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right tabular-nums text-gray-700 font-medium">
                                        {{ number_format($stockValue, 2) }}
                                    </td>
                                    <td class="px-4 py-2 text-right tabular-nums font-semibold {{ $varColor }}">
                                        {{ $varianceCost > 0 ? '+' : '' }}{{ number_format($varianceCost, 2) }}
                                    </td>
                                    @if (! $isCompleted)
                                        <td class="px-4 py-2 text-center opacity-0 group-hover:opacity-100 transition">
                                            <button type="button" wire:click="removeLine({{ $idx }})"
                                                    class="text-red-400 hover:text-red-600 transition">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200 text-sm font-semibold">
                        <tr>
                            <td colspan="6" class="px-4 py-2.5 text-right text-gray-600">Totals</td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-gray-900">
                                RM {{ number_format($totalStockCost, 2) }}
                            </td>
                            <td class="px-4 py-2.5 text-right tabular-nums {{ $totalVarianceCost >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $totalVarianceCost >= 0 ? '+' : '' }}{{ number_format($totalVarianceCost, 2) }}
                            </td>
                            @if (! $isCompleted)<td></td>@endif
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="px-6 py-12 text-center text-gray-400">
                <p class="text-3xl mb-2">📋</p>
                <p class="font-medium">No ingredients added yet</p>
                <p class="text-xs mt-1">Search above, load a template, or click "Load All" to start counting.</p>
            </div>
        @endif

        @if (! $isCompleted)
            <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
                <a href="{{ route('inventory.index') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">Cancel</a>
                <div class="flex gap-2">
                    <button wire:click="save('save')"
                            class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                        Save Draft
                    </button>
                    <button wire:click="save('complete')"
                            wire:confirm="Mark this stock take as completed? This finalises the count."
                            class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        Complete Stock Take
                    </button>
                </div>
            </div>
        @endif

    </div>
</div>
