<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Top bar --}}
    <div class="flex items-center gap-3 mb-6">
        <a data-back href="{{ route('kitchen.index') }}" class="text-gray-600 hover:text-gray-900 transition flex-shrink-0">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1 min-w-0">
            <p class="text-xs text-gray-600">
                <a href="{{ route('kitchen.index') }}" class="hover:underline">Kitchen</a>
                / {{ $orderId ? $orderNumber : 'New Production Order' }}
            </p>
        </div>
        @if ($isEditable)
        <div class="flex gap-2 flex-shrink-0">
            <button wire:click="save('schedule')"
                    class="px-4 py-2 border border-blue-500 text-blue-600 text-sm font-medium rounded-lg hover:bg-blue-50 transition">
                Save & Schedule
            </button>
            <button wire:click="save"
                    class="btn-primary">
                Save Draft
            </button>
        </div>
        @endif
    </div>

    {{-- Validation errors --}}
    @if ($errors->any())
        <div class="mb-4 px-4 py-3 bg-danger-50 border border-danger-200 text-danger-700 text-sm rounded-lg">
            <p class="font-medium mb-1">Please fix the following:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Header Details --}}
    <div class="card p-6 space-y-4 mb-4">
        <h3 class="text-sm font-semibold text-gray-700">Order Details</h3>

        {{-- Order Number (read-only) --}}
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Order Number</label>
            <p class="px-3 py-2 bg-gray-50 rounded-md border border-gray-200 text-sm font-mono text-gray-700">
                {{ $orderNumber }}
                @if ($orderId)
                    <span class="ml-2 text-xs font-sans px-2 py-0.5 rounded-full
                        {{ match($status) {
                            'draft'       => 'bg-gray-100 text-gray-600',
                            'scheduled'   => 'bg-blue-100 text-blue-700',
                            'in_progress' => 'bg-yellow-100 text-yellow-700',
                            'completed'   => 'bg-success-100 text-success-700',
                            'cancelled'   => 'bg-danger-100 text-danger-600',
                            default       => 'bg-gray-100 text-gray-500',
                        } }}">
                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                    </span>
                @endif
            </p>
        </div>

        {{-- Kitchen --}}
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Kitchen *</label>
            <select wire:model="kitchen_id" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">-- Select Kitchen --</option>
                @foreach ($kitchens as $k)
                    <option value="{{ $k->id }}">{{ $k->name }}{{ $k->code ? " ({$k->code})" : '' }}</option>
                @endforeach
            </select>
            @error('kitchen_id') <p class="text-xs text-danger-500 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Dates --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Production Date *</label>
                <input type="date" wire:model="production_date" class="w-full rounded-lg border-gray-300 text-sm" />
                @error('production_date') <p class="text-xs text-danger-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Needed By</label>
                <input type="date" wire:model="needed_by_date" class="w-full rounded-lg border-gray-300 text-sm" />
                @error('needed_by_date') <p class="text-xs text-danger-500 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Notes --}}
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Notes</label>
            <textarea wire:model="notes" rows="2" class="w-full rounded-lg border-gray-300 text-sm"
                      placeholder="Production instructions, special notes..."></textarea>
        </div>
    </div>

    {{-- Recipe Lines --}}
    <div class="card">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">Production Lines</h3>
                <p class="text-xs text-gray-600 mt-0.5">{{ count($lines) }} item{{ count($lines) !== 1 ? 's' : '' }}</p>
            </div>
        </div>

        {{-- Recipe Search --}}
        @if ($isEditable)
        <div class="px-6 py-4 border-b border-gray-100">
            <div class="relative">
                <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                    <svg class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                    </svg>
                </div>
                <input type="text"
                       wire:model.live.debounce.300ms="recipeSearch"
                       placeholder="Search production recipes or prep items to add... (type at least 2 characters)"
                       class="w-full pl-9 pr-4 py-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" />
            </div>

            {{-- The kitchen's own products come first; prep items are the
                 outlet-side recipes the kitchen also produces. --}}
            @if ($productionResults->isNotEmpty())
                <p class="mt-3 text-[11px] font-semibold uppercase tracking-wider text-purple-600">Production Recipes</p>
                <div class="mt-1 border border-purple-200 rounded-lg overflow-hidden divide-y divide-purple-100 shadow-sm">
                    @foreach ($productionResults as $pr)
                        <button type="button"
                                wire:click="addProductionRecipe({{ $pr->id }})"
                                class="w-full flex items-center justify-between px-4 py-2.5 hover:bg-purple-50 transition text-left">
                            <div>
                                <span class="font-medium text-gray-800 text-sm">{{ $pr->name }}</span>
                                @if ($pr->code)
                                    <span class="ml-2 text-xs text-gray-600">{{ $pr->code }}</span>
                                @endif
                                @if ($pr->category)
                                    <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] bg-purple-100 text-purple-700">{{ $pr->category }}</span>
                                @endif
                            </div>
                            <div class="text-right flex-shrink-0 ml-4 text-xs text-gray-600">
                                <span>Batch {{ rtrim(rtrim(number_format((float) $pr->yield_quantity, 2, '.', ''), '0'), '.') }} {{ $pr->yieldUom?->abbreviation ?? '' }}</span>
                                @if (floatval($pr->total_cost_per_unit) > 0)
                                    <span class="ml-1">· Cost {{ number_format($pr->total_cost_per_unit, 4) }}</span>
                                @endif
                            </div>
                        </button>
                    @endforeach
                </div>
            @endif

            @if ($searchResults->isNotEmpty())
                <p class="mt-3 text-[11px] font-semibold uppercase tracking-wider text-gray-500">Prep Items (outlet recipes)</p>
                <div class="mt-1 border border-gray-200 rounded-lg overflow-hidden divide-y divide-gray-100 shadow-sm">
                    @foreach ($searchResults as $recipe)
                        <button type="button"
                                wire:click="addRecipe({{ $recipe->id }})"
                                class="w-full flex items-center justify-between px-4 py-2.5 hover:bg-brand-50 transition text-left">
                            <div>
                                <span class="font-medium text-gray-800 text-sm">{{ $recipe->name }}</span>
                                @if ($recipe->code)
                                    <span class="ml-2 text-xs text-gray-600">{{ $recipe->code }}</span>
                                @endif
                            </div>
                            <div class="text-right flex-shrink-0 ml-4 text-xs text-gray-600">
                                <span>{{ $recipe->yieldUom?->abbreviation ?? '-' }}</span>
                                @if (floatval($recipe->cost_per_yield_unit) > 0)
                                    <span class="ml-1">Cost: {{ number_format($recipe->cost_per_yield_unit, 4) }}</span>
                                @endif
                                <span class="ml-2 text-brand-400">+ Add</span>
                            </div>
                        </button>
                    @endforeach
                </div>
            @endif

            @if (strlen($recipeSearch) >= 2 && $searchResults->isEmpty() && $productionResults->isEmpty())
                <p class="mt-2 text-sm text-gray-600 text-center py-2">
                    Nothing found for "{{ $recipeSearch }}".
                    <a href="{{ route('kitchen.recipes.create') }}" class="text-brand-500 hover:underline">Create a production recipe</a>.
                </p>
            @endif

            @error('lines') <p class="text-xs text-danger-500 mt-2">{{ $message }}</p> @enderror
        </div>
        @endif

        {{-- Lines table --}}
        @if (count($lines))
            <div class="overflow-x-auto">
                <table class="table-surface min-w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left w-8">#</th>
                            <th class="px-4 py-2 text-left">Item</th>
                            <th class="px-4 py-2 text-right w-28">Planned Qty</th>
                            <th class="px-4 py-2 text-left w-20">UOM</th>
                            <th class="px-4 py-2 text-right w-28">Unit Cost</th>
                            <th class="px-4 py-2 text-left w-44">Destination Outlet</th>
                            @if ($isEditable)
                            <th class="px-4 py-2 w-10"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $idx => $line)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-2 text-gray-600 text-xs">{{ $idx + 1 }}</td>
                                <td class="px-4 py-2 font-medium text-gray-800">
                                    {{ $line['recipe_name'] }}
                                    {{-- Say which list the line came from; the two produce
                                         the same way but are managed in different places. --}}
                                    @if (($line['source'] ?? 'prep') === 'production')
                                        <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-purple-100 text-purple-700">Production recipe</span>
                                    @else
                                        <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600">Prep item</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @if ($isEditable)
                                        <input type="number" step="0.01" min="0.01"
                                               wire:model.lazy="lines.{{ $idx }}.planned_quantity"
                                               class="w-full text-right rounded border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500" />
                                    @else
                                        <p class="text-right tabular-nums text-gray-700">{{ rtrim(rtrim(number_format(floatval($line['planned_quantity']), 4), '0'), '.') }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-sm font-medium text-gray-600">{{ $line['uom_name'] }}</td>
                                <td class="px-4 py-2">
                                    @if ($isEditable)
                                        <input type="number" step="0.0001" min="0"
                                               wire:model.lazy="lines.{{ $idx }}.unit_cost"
                                               class="w-full text-right rounded border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500" />
                                    @else
                                        <p class="text-right tabular-nums text-gray-700">{{ number_format(floatval($line['unit_cost']), 4) }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @if ($isEditable)
                                        <select wire:model="lines.{{ $idx }}.to_outlet_id"
                                                class="w-full rounded border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                                            <option value="">-- None --</option>
                                            @foreach ($outlets as $outlet)
                                                <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        @php $destOutlet = $outlets->firstWhere('id', $line['to_outlet_id']); @endphp
                                        <span class="text-gray-600">{{ $destOutlet?->name ?? '-' }}</span>
                                    @endif
                                </td>
                                @if ($isEditable)
                                <td class="px-4 py-2 text-center">
                                    <button type="button" wire:click="removeLine({{ $idx }})"
                                            class="text-danger-400 hover:text-danger-600 transition">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-6 py-12 text-center text-gray-600">
                <p class="font-medium">No lines added yet</p>
                <p class="text-xs mt-1">Use the search above to add prep recipes to this production order.</p>
            </div>
        @endif

        {{-- Footer --}}
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
            <a href="{{ route('kitchen.index') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">
                &larr; Back
            </a>
            @if ($isEditable)
                <div class="flex gap-2">
                    <button wire:click="save('schedule')"
                            class="px-4 py-2 border border-blue-500 text-blue-600 text-sm font-medium rounded-lg hover:bg-blue-50 transition">
                        Save & Schedule
                    </button>
                    <button wire:click="save"
                            class="btn-primary">
                        Save Draft
                    </button>
                </div>
            @else
                <p class="text-xs text-gray-600 italic">This order is read-only (status: {{ ucfirst(str_replace('_', ' ', $status)) }}).</p>
            @endif
        </div>
    </div>
</div>
