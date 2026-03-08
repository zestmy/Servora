<div>
    {{-- Top bar --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('purchasing.index') }}" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1 min-w-0">
            <p class="text-xs text-gray-400">
                <a href="{{ route('purchasing.index') }}" class="hover:underline">Purchasing</a>
                / {{ $orderId ? $poNumber : 'New Purchase Order' }}
            </p>
        </div>
        @if ($isEditable)
        <div class="flex gap-2 flex-shrink-0">
            <button wire:click="save('submit')"
                    class="px-4 py-2 border border-blue-500 text-blue-600 text-sm font-medium rounded-lg hover:bg-blue-50 transition">
                {{ $requirePoApproval ? 'Save & Submit' : 'Save & Approve' }}
            </button>
            <button wire:click="save"
                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                Save Draft
            </button>
        </div>
        @endif
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

        {{-- Details card (2/3) --}}
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <h3 class="text-sm font-semibold text-gray-700">Order Details</h3>

                {{-- PO Number (read-only) --}}
                <div>
                    <x-input-label value="PO Number" />
                    <p class="mt-1 px-3 py-2 bg-gray-50 rounded-md border border-gray-200 text-sm font-mono text-gray-700">
                        {{ $poNumber }}
                        @if ($orderId)
                            <span class="ml-2 text-xs font-sans px-2 py-0.5 rounded-full
                                {{ match($status) {
                                    'draft'     => 'bg-gray-100 text-gray-600',
                                    'sent'      => 'bg-blue-100 text-blue-700',
                                    'partial'   => 'bg-yellow-100 text-yellow-700',
                                    'received'  => 'bg-green-100 text-green-700',
                                    'cancelled' => 'bg-red-100 text-red-600',
                                    default     => 'bg-gray-100 text-gray-500',
                                } }}">
                                {{ ucfirst($status) }}
                            </span>
                        @endif
                    </p>
                </div>

                {{-- Supplier + Cost Center --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="po_supplier" value="Supplier *" />
                        <select id="po_supplier" wire:model.live="supplier_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— Select Supplier —</option>
                            @foreach ($suppliers as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('supplier_id')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="po_cost_center" value="Cost Center" />
                        <select id="po_cost_center" wire:model="ingredient_category_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— No Cost Center —</option>
                            @foreach ($costCenters as $cc)
                                <option value="{{ $cc->id }}">{{ $cc->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-0.5 text-xs text-gray-400">Used for food cost % reporting.</p>
                        <x-input-error :messages="$errors->get('ingredient_category_id')" class="mt-1" />
                    </div>
                </div>

                {{-- Order Date | Expected Delivery --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="po_date" value="Order Date *" />
                        <x-text-input id="po_date" wire:model="order_date" type="date" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('order_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="po_delivery" value="Expected Delivery" />
                        <x-text-input id="po_delivery" wire:model="expected_delivery_date" type="date" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('expected_delivery_date')" class="mt-1" />
                    </div>
                </div>

                {{-- Notes --}}
                <div>
                    <x-input-label for="po_notes" value="Notes" />
                    <textarea id="po_notes" wire:model="notes" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="Special instructions, delivery address, etc."></textarea>
                </div>
            </div>
        </div>

        {{-- Summary card (1/3, sticky) --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 lg:sticky lg:top-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Order Summary</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Items</dt>
                        <dd class="font-medium text-gray-800">{{ count($lines) }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-gray-100 pt-3">
                        <dt class="text-gray-600 font-semibold">Grand Total</dt>
                        <dd class="font-bold text-xl text-gray-900 tabular-nums">
                            {{ number_format($grandTotal, 2) }}
                        </dd>
                    </div>
                </dl>

                @if ($supplier_id)
                    @php $selectedSupplier = $suppliers->firstWhere('id', $supplier_id); @endphp
                    @if ($selectedSupplier)
                        <div class="mt-4 pt-4 border-t border-gray-100 text-xs text-gray-500 space-y-1">
                            <p class="font-medium text-gray-700">{{ $selectedSupplier->name }}</p>
                            @if ($selectedSupplier->contact_person)
                                <p>{{ $selectedSupplier->contact_person }}</p>
                            @endif
                            @if ($selectedSupplier->phone)
                                <p>{{ $selectedSupplier->phone }}</p>
                            @endif
                            @if ($selectedSupplier->payment_terms)
                                <p class="mt-1">Terms: <span class="font-medium">{{ $selectedSupplier->payment_terms }}</span></p>
                            @endif
                        </div>
                    @endif
                @endif
            </div>
        </div>

    </div>

    {{-- Ingredient Lines --}}
    <div class="mt-4 bg-white rounded-xl shadow-sm border border-gray-100">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">Order Lines</h3>
                <p class="text-xs text-gray-400 mt-0.5">{{ count($lines) }} item{{ count($lines) !== 1 ? 's' : '' }}</p>
            </div>
            @if ($isEditable && $availableTemplates->isNotEmpty())
                <select wire:model="selectedTemplateId" wire:change="loadTemplate"
                        class="text-xs border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5">
                    <option value="">Load Template…</option>
                    @foreach ($availableTemplates as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        {{-- Ingredient Search (editable only) --}}
        @if ($isEditable)
        <div class="px-6 py-4 border-b border-gray-100">
            <div class="relative">
                <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                    </svg>
                </div>
                <input type="text"
                       wire:model.live.debounce.300ms="ingredientSearch"
                       placeholder="Search ingredients to add… (type at least 2 characters)"
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
                                <span>{{ $ingredient->baseUom?->abbreviation }}</span>
                                @if (floatval($ingredient->purchase_price) > 0)
                                    <span class="ml-1">· RM {{ number_format($ingredient->purchase_price, 4) }}</span>
                                @endif
                                <span class="ml-2 text-indigo-400">+ Add</span>
                            </div>
                        </button>
                    @endforeach
                </div>
            @elseif (strlen($ingredientSearch) >= 2)
                <p class="mt-2 text-sm text-gray-400 text-center py-2">No ingredients found for "{{ $ingredientSearch }}".</p>
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
                            <th class="px-4 py-2 text-left w-32">UOM</th>
                            <th class="px-4 py-2 text-right w-32">Unit Cost (RM)</th>
                            <th class="px-4 py-2 text-right w-32">Total (RM)</th>
                            @if ($isEditable)
                            <th class="px-4 py-2 w-10"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($lines as $idx => $line)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-2 text-gray-400 text-xs">{{ $idx + 1 }}</td>
                                <td class="px-4 py-2">
                                    <div class="font-medium text-gray-800">{{ $line['ingredient_name'] }}</div>
                                    <x-input-error :messages="$errors->get('lines.'.$idx.'.ingredient_id')" class="mt-0.5" />
                                </td>
                                <td class="px-4 py-2">
                                    @if ($isEditable)
                                        <input type="number" step="0.001" min="0.001"
                                               wire:model.live.debounce.400ms="lines.{{ $idx }}.quantity"
                                               class="w-full text-right rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        <x-input-error :messages="$errors->get('lines.'.$idx.'.quantity')" class="mt-0.5" />
                                    @else
                                        <p class="text-right tabular-nums text-gray-700">{{ $line['quantity'] }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @if ($isEditable)
                                        <select wire:model.live="lines.{{ $idx }}.uom_id"
                                                class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            @foreach ($uoms as $uom)
                                                <option value="{{ $uom->id }}">{{ $uom->abbreviation }}</option>
                                            @endforeach
                                        </select>
                                        <x-input-error :messages="$errors->get('lines.'.$idx.'.uom_id')" class="mt-0.5" />
                                    @else
                                        @php $lineUom = $uoms->firstWhere('id', $line['uom_id']); @endphp
                                        <p class="text-gray-700">{{ $lineUom?->abbreviation ?? '—' }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @if ($isEditable)
                                        <input type="number" step="0.0001" min="0"
                                               wire:model.live.debounce.400ms="lines.{{ $idx }}.unit_cost"
                                               class="w-full text-right rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        <x-input-error :messages="$errors->get('lines.'.$idx.'.unit_cost')" class="mt-0.5" />
                                    @else
                                        <p class="text-right tabular-nums text-gray-700">{{ number_format($line['unit_cost'], 4) }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums font-medium text-gray-700">
                                    {{ number_format($line['total_cost'] ?? 0, 2) }}
                                </td>
                                @if ($isEditable)
                                <td class="px-4 py-2 text-center">
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
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                        <tr>
                            <td colspan="5" class="px-4 py-3 text-right text-sm font-semibold text-gray-600">Grand Total</td>
                            <td class="px-4 py-3 text-right font-bold text-gray-900 tabular-nums text-base">
                                {{ number_format($grandTotal, 2) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="px-6 py-12 text-center text-gray-400">
                <p class="text-3xl mb-2">📦</p>
                <p class="font-medium">No items added yet</p>
                <p class="text-xs mt-1">Use the search above to add ingredients to this order.</p>
            </div>
        @endif

        {{-- Footer --}}
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
            <a href="{{ route('purchasing.index') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">
                ← Back
            </a>
            @if ($isEditable)
                <div class="flex gap-2">
                    <button wire:click="save('send')"
                            class="px-4 py-2 border border-blue-500 text-blue-600 text-sm font-medium rounded-lg hover:bg-blue-50 transition">
                        Save &amp; Send to Supplier
                    </button>
                    <button wire:click="save"
                            class="px-6 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        Save Draft
                    </button>
                </div>
            @else
                <p class="text-xs text-gray-400 italic">This PO is read-only (status: {{ ucfirst($status) }}).</p>
            @endif
        </div>

    </div>
</div>
