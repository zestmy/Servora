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
                / {{ $poId ? 'Receive: ' . $poNumber : 'Record Direct Purchase' }}
            </p>
        </div>
        <button wire:click="confirm"
                class="flex-shrink-0 px-5 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition">
            Confirm Receipt
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

        {{-- Header card (2/3) --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <h3 class="text-sm font-semibold text-gray-700">Delivery Details</h3>

                {{-- DO Number (read-only) --}}
                <div>
                    <x-input-label value="DO Number" />
                    <p class="mt-1 px-3 py-2 bg-gray-50 rounded-md border border-gray-200 text-sm font-mono text-gray-700">
                        {{ $doNumber }}
                    </p>
                </div>

                {{-- Linked PO info (if from PO) --}}
                @if ($poId)
                    <div class="p-3 bg-blue-50 rounded-lg text-sm text-blue-700 border border-blue-100">
                        Receiving against PO <span class="font-mono font-semibold">{{ $poNumber }}</span>
                        · {{ $poSupplier }}
                    </div>
                @endif

                {{-- Supplier (standalone mode) --}}
                @if (! $poId)
                    <div>
                        <x-input-label for="do_supplier" value="Supplier *" />
                        <select id="do_supplier" wire:model="supplier_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— Select Supplier —</option>
                            @foreach ($suppliers as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                {{-- Delivery Date | Invoice Ref --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="do_date" value="Delivery Date *" />
                        <x-text-input id="do_date" wire:model="delivery_date" type="date" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('delivery_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="do_ref" value="Invoice / DO Reference" />
                        <x-text-input id="do_ref" wire:model="reference_number" type="text"
                                      class="mt-1 block w-full" placeholder="e.g. INV-2024-001" />
                    </div>
                </div>

                {{-- Notes --}}
                <div>
                    <x-input-label for="do_notes" value="Notes" />
                    <textarea id="do_notes" wire:model="notes" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="Delivery notes, discrepancies, etc."></textarea>
                </div>
            </div>
        </div>

        {{-- Summary card (1/3, sticky) --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 lg:sticky lg:top-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Receipt Summary</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Lines</dt>
                        <dd class="font-medium text-gray-800">{{ count($lines) }}</dd>
                    </div>
                    @php
                        $rejectedCount = collect($lines)->where('condition', 'rejected')->count();
                        $damagedCount  = collect($lines)->where('condition', 'damaged')->count();
                    @endphp
                    @if ($rejectedCount > 0)
                        <div class="flex justify-between text-red-600">
                            <dt>Rejected lines</dt>
                            <dd class="font-medium">{{ $rejectedCount }}</dd>
                        </div>
                    @endif
                    @if ($damagedCount > 0)
                        <div class="flex justify-between text-orange-500">
                            <dt>Damaged lines</dt>
                            <dd class="font-medium">{{ $damagedCount }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between border-t border-gray-100 pt-3">
                        <dt class="text-gray-600 font-semibold">Total (excl. rejected)</dt>
                        <dd class="font-bold text-xl text-gray-900 tabular-nums">
                            {{ number_format($grandTotal, 2) }}
                        </dd>
                    </div>
                </dl>

                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-xs text-gray-400 leading-relaxed">
                        On confirmation:
                        <br>· Delivery order &amp; purchase record created
                        <br>· PO status updated automatically
                        <br>· Ingredient costs updated for "Good" lines with new pricing
                    </p>
                </div>
            </div>
        </div>

    </div>

    {{-- Lines --}}
    <div class="mt-4 bg-white rounded-xl shadow-sm border border-gray-100">

        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700">Items to Receive</h3>
            <p class="text-xs text-gray-400 mt-0.5">Adjust quantities and mark condition for each item.</p>
        </div>

        {{-- Ingredient search (standalone mode only) --}}
        @if (! $poId)
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
                            <button type="button"
                                    wire:click="addIngredient({{ $ingredient->id }})"
                                    class="w-full flex items-center justify-between px-4 py-2.5 hover:bg-indigo-50 transition text-left">
                                <span class="font-medium text-gray-800 text-sm">{{ $ingredient->name }}</span>
                                <span class="text-xs text-gray-400 ml-4 flex-shrink-0">
                                    {{ $ingredient->baseUom?->abbreviation }}
                                    @if (floatval($ingredient->purchase_price) > 0)
                                        · RM {{ number_format($ingredient->purchase_price, 4) }}
                                    @endif
                                    <span class="ml-1 text-indigo-400">+ Add</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                @elseif (strlen($ingredientSearch) >= 2)
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
                            <th class="px-4 py-2 text-left">#</th>
                            <th class="px-4 py-2 text-left">Ingredient</th>
                            @if ($poId)
                                <th class="px-4 py-2 text-right w-24">Ordered</th>
                            @endif
                            <th class="px-4 py-2 text-right w-28">Received Qty</th>
                            <th class="px-4 py-2 text-left w-24">UOM</th>
                            <th class="px-4 py-2 text-right w-32">Unit Cost (RM)</th>
                            <th class="px-4 py-2 text-center w-32">Condition</th>
                            <th class="px-4 py-2 text-right w-28">Total (RM)</th>
                            @if (! $poId)
                                <th class="px-4 py-2 w-10"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($lines as $idx => $line)
                            @php
                                $received = floatval($line['received_qty'] ?? 0);
                                $cost     = floatval($line['unit_cost'] ?? 0);
                                $lineTotal = $line['condition'] !== 'rejected' ? $received * $cost : 0;
                                $rowBg = match($line['condition']) {
                                    'damaged'  => 'bg-orange-50',
                                    'rejected' => 'bg-red-50',
                                    default    => '',
                                };
                            @endphp
                            <tr class="transition {{ $rowBg }} hover:bg-gray-50">
                                <td class="px-4 py-2 text-gray-400 text-xs">{{ $idx + 1 }}</td>
                                <td class="px-4 py-2 font-medium text-gray-800">{{ $line['ingredient_name'] }}</td>
                                @if ($poId)
                                    <td class="px-4 py-2 text-right text-gray-500 tabular-nums">
                                        {{ number_format($line['ordered_qty'], 3) }}
                                        <span class="text-gray-300 text-xs">{{ $line['uom_abbr'] }}</span>
                                    </td>
                                @endif
                                <td class="px-4 py-2">
                                    <input type="number" step="0.001" min="0"
                                           wire:model.live.debounce.300ms="lines.{{ $idx }}.received_qty"
                                           class="w-full text-right rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    <x-input-error :messages="$errors->get('lines.'.$idx.'.received_qty')" class="mt-0.5" />
                                </td>
                                <td class="px-4 py-2">
                                    @if ($poId)
                                        <span class="text-gray-500 text-xs">{{ $line['uom_abbr'] }}</span>
                                    @else
                                        <select wire:model.live="lines.{{ $idx }}.uom_id"
                                                class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            @foreach ($uoms as $uom)
                                                <option value="{{ $uom->id }}">{{ $uom->abbreviation }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    <input type="number" step="0.0001" min="0"
                                           wire:model.live.debounce.300ms="lines.{{ $idx }}.unit_cost"
                                           class="w-full text-right rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    <x-input-error :messages="$errors->get('lines.'.$idx.'.unit_cost')" class="mt-0.5" />
                                </td>
                                <td class="px-4 py-2">
                                    <select wire:model.live="lines.{{ $idx }}.condition"
                                            class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500
                                                {{ match($line['condition']) {
                                                    'good'     => 'text-green-700',
                                                    'damaged'  => 'text-orange-600',
                                                    'rejected' => 'text-red-600',
                                                    default    => '',
                                                } }}">
                                        <option value="good">Good</option>
                                        <option value="damaged">Damaged</option>
                                        <option value="rejected">Rejected</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('lines.'.$idx.'.condition')" class="mt-0.5" />
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums font-medium
                                    {{ $line['condition'] === 'rejected' ? 'text-red-400 line-through' : 'text-gray-700' }}">
                                    {{ number_format($lineTotal, 2) }}
                                </td>
                                @if (! $poId)
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
                            <td colspan="{{ $poId ? 7 : 7 }}" class="px-4 py-3 text-right text-sm font-semibold text-gray-600">
                                Total (excl. rejected)
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-gray-900 tabular-nums text-base">
                                {{ number_format($grandTotal, 2) }}
                            </td>
                            @if (! $poId)<td></td>@endif
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="px-6 py-12 text-center text-gray-400">
                <p class="text-3xl mb-2">📥</p>
                <p class="font-medium">No items to receive</p>
                @if (! $poId)
                    <p class="text-xs mt-1">Use the search above to add ingredients.</p>
                @endif
            </div>
        @endif

        {{-- Footer --}}
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
            <a href="{{ route('purchasing.index') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">
                Cancel
            </a>
            <button wire:click="confirm"
                    class="px-6 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition">
                Confirm Receipt
            </button>
        </div>

    </div>
</div>
