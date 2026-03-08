<div>
    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">{{ session('error') }}</div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-700">Convert PO to Delivery Order</h2>
            <p class="text-xs text-gray-400 mt-0.5">Review items and create DO for delivery arrangement</p>
        </div>
        <a href="{{ route('purchasing.index') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">Back to list</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: Form --}}
        <div class="lg:col-span-2 space-y-4">
            {{-- PO Info --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Purchase Order Details</h3>
                <div class="grid grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="text-gray-400 text-xs">PO Number</p>
                        <p class="font-mono font-medium text-gray-800">{{ $poNumber }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-xs">Outlet</p>
                        <p class="font-medium text-gray-800">{{ $outletName }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-xs">Supplier</p>
                        <p class="font-medium text-gray-800">{{ $supplierName }}</p>
                    </div>
                </div>
            </div>

            {{-- Delivery Info --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Delivery Information</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="delivery_date" value="Expected Delivery Date *" />
                        <x-text-input id="delivery_date" wire:model="delivery_date" type="date" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('delivery_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="notes" value="Notes" />
                        <x-text-input id="notes" wire:model="notes" type="text" class="mt-1 block w-full" />
                    </div>
                </div>
            </div>

            {{-- Add ingredient --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Items</h3>

                <div class="relative mb-4">
                    <x-text-input type="text" wire:model.live.debounce.300ms="ingredientSearch"
                                  placeholder="Search to add ingredients..."
                                  class="w-full" />
                    @if ($searchResults->count())
                        <div class="absolute z-20 mt-1 w-full bg-white rounded-lg shadow-lg border border-gray-200 max-h-48 overflow-y-auto">
                            @foreach ($searchResults as $ing)
                                <button type="button" wire:click="addIngredient({{ $ing->id }})"
                                        class="w-full text-left px-4 py-2 text-sm hover:bg-indigo-50 transition">
                                    <span class="font-medium text-gray-800">{{ $ing->name }}</span>
                                    <span class="text-gray-400 ml-2">{{ $ing->baseUom?->abbreviation }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Lines Table --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-gray-500 text-xs uppercase border-b">
                            <tr>
                                <th class="text-left py-2 px-2">Ingredient</th>
                                <th class="text-center py-2 px-2 w-20">PO Qty</th>
                                <th class="text-center py-2 px-2 w-24">DO Qty</th>
                                <th class="text-center py-2 px-2 w-20">UOM</th>
                                <th class="text-right py-2 px-2 w-28">Unit Cost</th>
                                <th class="text-right py-2 px-2 w-28">Total</th>
                                <th class="text-center py-2 px-2 w-12"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($lines as $idx => $line)
                                <tr class="border-b border-gray-50">
                                    <td class="py-2 px-2 text-gray-800">{{ $line['ingredient_name'] }}</td>
                                    <td class="py-2 px-2 text-center text-gray-400 text-xs">
                                        {{ $line['po_quantity'] > 0 ? floatval($line['po_quantity']) : '—' }}
                                    </td>
                                    <td class="py-2 px-2">
                                        <input type="number" wire:model.live.debounce.500ms="lines.{{ $idx }}.quantity"
                                               step="0.01" min="0"
                                               class="w-full text-center rounded border-gray-300 text-sm py-1 focus:border-indigo-500 focus:ring-indigo-500">
                                    </td>
                                    <td class="py-2 px-2 text-center">
                                        <select wire:model="lines.{{ $idx }}.uom_id" class="rounded border-gray-300 text-xs py-1 focus:border-indigo-500 focus:ring-indigo-500">
                                            @foreach ($uoms as $uom)
                                                <option value="{{ $uom->id }}">{{ $uom->abbreviation }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="py-2 px-2">
                                        <input type="number" wire:model.live.debounce.500ms="lines.{{ $idx }}.unit_cost"
                                               step="0.01" min="0"
                                               class="w-full text-right rounded border-gray-300 text-sm py-1 focus:border-indigo-500 focus:ring-indigo-500">
                                    </td>
                                    <td class="py-2 px-2 text-right tabular-nums text-gray-700 font-medium">
                                        {{ number_format($line['total_cost'] ?? 0, 2) }}
                                    </td>
                                    <td class="py-2 px-2 text-center">
                                        <button wire:click="removeLine({{ $idx }})" class="text-red-400 hover:text-red-600 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-gray-400 text-sm">No items added</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <x-input-error :messages="$errors->get('lines')" class="mt-2" />
            </div>
        </div>

        {{-- Right: Summary --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 sticky top-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">DO Summary</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">PO Number</dt>
                        <dd class="font-mono text-gray-800">{{ $poNumber }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Outlet</dt>
                        <dd class="text-gray-800">{{ $outletName }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Supplier</dt>
                        <dd class="text-gray-800">{{ $supplierName }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Items</dt>
                        <dd class="text-gray-800">{{ count($lines) }}</dd>
                    </div>
                    <hr class="my-2">
                    <div class="flex justify-between font-semibold text-base">
                        <dt class="text-gray-700">Grand Total</dt>
                        <dd class="text-gray-800 tabular-nums">RM {{ number_format($grandTotal, 2) }}</dd>
                    </div>
                </dl>

                <button wire:click="convert"
                        wire:confirm="Convert this PO to a Delivery Order? This will also generate a GRN for the outlet."
                        class="mt-6 w-full px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    Convert to Delivery Order
                </button>
            </div>
        </div>
    </div>
</div>
