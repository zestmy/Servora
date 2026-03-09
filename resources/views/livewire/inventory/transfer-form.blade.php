<div>
    {{-- Top bar --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('inventory.index', ['tab' => 'transfers']) }}" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1 min-w-0">
            <p class="text-xs text-gray-400">
                <a href="{{ route('inventory.index', ['tab' => 'transfers']) }}" class="hover:underline">Inventory</a>
                / {{ $transferId ? 'Transfer ' . $transfer_number : 'New Transfer' }}
            </p>
        </div>
        @if ($isDraft)
            <button wire:click="save"
                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                Save
            </button>
        @endif
    </div>

    {{-- Flash --}}
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

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
                <h3 class="text-sm font-semibold text-gray-700">Transfer Details</h3>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="t_number" value="Transfer #" />
                        <x-text-input id="t_number" :value="$transfer_number" type="text" class="mt-1 block w-full bg-gray-50" readonly />
                    </div>
                    <div>
                        <x-input-label for="t_date" value="Date *" />
                        <x-text-input id="t_date" wire:model="transfer_date" type="date" class="mt-1 block w-full" :disabled="!$isDraft" />
                        <x-input-error :messages="$errors->get('transfer_date')" class="mt-1" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="t_from" value="From Outlet *" />
                        <select id="t_from" wire:model.live="from_outlet_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                {{ !$isDraft ? 'disabled' : '' }}>
                            <option value="">Select source outlet…</option>
                            @foreach ($outlets as $outlet)
                                <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('from_outlet_id')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="t_to" value="To Outlet *" />
                        <select id="t_to" wire:model="to_outlet_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                {{ !$isDraft ? 'disabled' : '' }}>
                            <option value="">Select destination outlet…</option>
                            @foreach ($outlets as $outlet)
                                @if ((string) $outlet->id !== $from_outlet_id)
                                    <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                @endif
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('to_outlet_id')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="t_notes" value="Notes" />
                    <textarea id="t_notes" wire:model="notes" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="Optional notes…"
                              {{ !$isDraft ? 'disabled' : '' }}></textarea>
                </div>
            </div>
        </div>

        {{-- Summary card --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 lg:sticky lg:top-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Summary</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Items</dt>
                        <dd class="font-medium text-gray-800">{{ count($lines) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Status</dt>
                        <dd>
                            @switch($status)
                                @case('draft')
                                    <span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs font-semibold rounded-full">Draft</span>
                                    @break
                                @case('in_transit')
                                    <span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 text-xs font-semibold rounded-full">In Transit</span>
                                    @break
                                @case('received')
                                    <span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs font-semibold rounded-full">Received</span>
                                    @break
                                @case('cancelled')
                                    <span class="px-2 py-0.5 bg-red-100 text-red-600 text-xs font-semibold rounded-full">Cancelled</span>
                                    @break
                            @endswitch
                        </dd>
                    </div>
                    <div class="flex justify-between border-t border-gray-100 pt-3">
                        <dt class="font-semibold text-gray-600">Total Value</dt>
                        <dd class="font-bold text-lg text-teal-600 tabular-nums">
                            RM {{ number_format($totalCost, 2) }}
                        </dd>
                    </div>
                </dl>

                {{-- Action buttons --}}
                @if ($transferId)
                    <div class="mt-4 pt-4 border-t border-gray-100 space-y-2">
                        @if ($status === 'draft')
                            <button wire:click="send" wire:confirm="Send this transfer? Items will be marked as in transit."
                                    class="w-full px-4 py-2 bg-teal-600 text-white text-sm font-medium rounded-lg hover:bg-teal-700 transition">
                                Send Transfer
                            </button>
                            <button wire:click="cancel" wire:confirm="Cancel this transfer?"
                                    class="w-full px-4 py-2 bg-white border border-red-300 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50 transition">
                                Cancel Transfer
                            </button>
                        @elseif ($status === 'in_transit')
                            <button wire:click="receive" wire:confirm="Confirm receipt of this transfer?"
                                    class="w-full px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition">
                                Receive Transfer
                            </button>
                            <button wire:click="cancel" wire:confirm="Cancel this in-transit transfer?"
                                    class="w-full px-4 py-2 bg-white border border-red-300 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50 transition">
                                Cancel Transfer
                            </button>
                        @elseif ($status === 'received')
                            <p class="text-xs text-green-600 text-center font-medium">Transfer completed</p>
                        @elseif ($status === 'cancelled')
                            <p class="text-xs text-red-500 text-center font-medium">Transfer cancelled</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Items section --}}
    <div class="mt-4 bg-white rounded-xl shadow-sm border border-gray-100">

        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700">Transfer Items</h3>
            <p class="text-xs text-gray-400 mt-0.5">{{ count($lines) }} item{{ count($lines) !== 1 ? 's' : '' }}</p>
        </div>

        {{-- Search (draft only) --}}
        @if ($isDraft)
            <div class="px-6 py-4 border-b border-gray-100">
                <div class="relative">
                    <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                        </svg>
                    </div>
                    <input type="text"
                           wire:model.live.debounce.300ms="itemSearch"
                           placeholder="Search ingredients to add…"
                           class="w-full pl-9 pr-4 py-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>

                @if ($ingredientResults->isNotEmpty())
                    <div class="mt-2 border border-gray-200 rounded-lg overflow-hidden divide-y divide-gray-100 shadow-sm">
                        @foreach ($ingredientResults as $ingredient)
                            <button type="button" wire:click="addIngredient({{ $ingredient->id }})"
                                    class="w-full flex items-center justify-between px-4 py-2.5 hover:bg-indigo-50 transition text-left">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-gray-800 text-sm">{{ $ingredient->name }}</span>
                                    @if ($ingredient->is_prep)
                                        <span class="px-1.5 py-0.5 bg-amber-100 text-amber-700 text-xs font-semibold rounded">PREP</span>
                                    @endif
                                    @if ($ingredient->category)
                                        <span class="text-xs text-gray-400">· {{ $ingredient->category }}</span>
                                    @endif
                                </div>
                                <div class="text-right text-xs flex-shrink-0 ml-4">
                                    <span class="text-gray-400">
                                        RM {{ number_format($ingredient->is_prep ? $ingredient->current_cost : $ingredient->purchase_price, 4) }}
                                        / {{ $ingredient->baseUom?->abbreviation }}
                                    </span>
                                    <span class="ml-2 text-indigo-400">+ Add</span>
                                </div>
                            </button>
                        @endforeach
                    </div>
                @elseif (strlen($itemSearch) >= 2)
                    <p class="mt-2 text-sm text-gray-400 text-center py-2">No ingredients found.</p>
                @endif

                <x-input-error :messages="$errors->get('lines')" class="mt-2" />
            </div>
        @endif

        {{-- Lines table --}}
        @if (count($lines))
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                        <tr>
                            <th class="px-4 py-2 text-left w-8">#</th>
                            <th class="px-4 py-2 text-left">Ingredient</th>
                            <th class="px-4 py-2 text-right w-28">Qty</th>
                            <th class="px-4 py-2 text-left w-16">UOM</th>
                            <th class="px-4 py-2 text-right w-32">Unit Cost (RM)</th>
                            <th class="px-4 py-2 text-right w-32">Total Cost (RM)</th>
                            @if ($isDraft)
                                <th class="px-4 py-2 w-10"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($lines as $idx => $line)
                            <tr class="hover:bg-gray-50 transition group">
                                <td class="px-4 py-2 text-gray-400 text-xs">{{ $idx + 1 }}</td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-gray-800">{{ $line['item_name'] }}</span>
                                        @if ($line['is_prep'] ?? false)
                                            <span class="px-1.5 py-0.5 bg-amber-100 text-amber-700 text-xs font-semibold rounded">PREP</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-2">
                                    @if ($isDraft)
                                        <input type="number" step="0.01" min="0.0001"
                                               wire:model.live.debounce.400ms="lines.{{ $idx }}.quantity"
                                               class="w-full text-right rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    @else
                                        <span class="block text-right tabular-nums">{{ number_format(floatval($line['quantity']), 2) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-gray-500 text-xs">{{ $line['uom_abbr'] }}</td>
                                <td class="px-4 py-2">
                                    @if ($isDraft)
                                        <input type="number" step="0.0001" min="0"
                                               wire:model.live.debounce.400ms="lines.{{ $idx }}.unit_cost"
                                               class="w-full text-right rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    @else
                                        <span class="block text-right tabular-nums">{{ number_format(floatval($line['unit_cost']), 4) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums font-semibold text-teal-600">
                                    {{ number_format(floatval($line['total_cost']), 2) }}
                                </td>
                                @if ($isDraft)
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
                    </tbody>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200 text-sm font-semibold">
                        <tr>
                            <td colspan="5" class="px-4 py-3 text-right text-gray-600">Total</td>
                            <td class="px-4 py-3 text-right tabular-nums text-teal-600">
                                RM {{ number_format($totalCost, 2) }}
                            </td>
                            @if ($isDraft)
                                <td></td>
                            @endif
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="px-6 py-12 text-center text-gray-400">
                <p class="font-medium">No items added yet</p>
                <p class="text-xs mt-1">Search for ingredients above to add to this transfer.</p>
            </div>
        @endif

        @if ($isDraft)
            <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
                <a href="{{ route('inventory.index', ['tab' => 'transfers']) }}" class="text-sm text-gray-500 hover:text-gray-700 transition">Cancel</a>
                <button wire:click="save"
                        class="px-6 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    Save Transfer
                </button>
            </div>
        @endif

    </div>
</div>
