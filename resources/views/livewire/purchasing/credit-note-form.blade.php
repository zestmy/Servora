<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Top bar --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('purchasing.credit-notes.index') }}" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1 min-w-0">
            <p class="text-xs text-gray-400">
                <a href="{{ route('purchasing.index') }}" class="hover:underline">Purchasing</a>
                / <a href="{{ route('purchasing.credit-notes.index') }}" class="hover:underline">Credit & Debit Notes</a>
                / {{ $creditNoteId ? $credit_note_number : 'New' }}
            </p>
        </div>
        @if ($isEditable)
        <div class="flex gap-2 flex-shrink-0">
            <button wire:click="save('issue')"
                    class="px-4 py-2 border border-blue-500 text-blue-600 text-sm font-medium rounded-lg hover:bg-blue-50 transition">
                Save & Issue
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
                <h3 class="text-sm font-semibold text-gray-700">Note Details</h3>

                {{-- Number (read-only) --}}
                <div>
                    <x-input-label value="Note Number" />
                    <p class="mt-1 px-3 py-2 bg-gray-50 rounded-md border border-gray-200 text-sm font-mono text-gray-700">
                        {{ $credit_note_number }}
                        @if ($creditNoteId)
                            <span class="ml-2 text-xs font-sans px-2 py-0.5 rounded-full
                                {{ match($status) {
                                    'draft'        => 'bg-gray-100 text-gray-600',
                                    'issued'       => 'bg-blue-100 text-blue-700',
                                    'applied'      => 'bg-green-100 text-green-700',
                                    'acknowledged' => 'bg-teal-100 text-teal-700',
                                    'cancelled'    => 'bg-red-100 text-red-600',
                                    default        => 'bg-gray-100 text-gray-500',
                                } }}">
                                {{ ucfirst($status) }}
                            </span>
                        @endif
                    </p>
                </div>

                {{-- Type & Direction --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="cn_type" value="Type *" />
                        <select id="cn_type" wire:model.live="type" {{ !$isEditable ? 'disabled' : '' }}
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="debit_note">Debit Note</option>
                            <option value="credit_note">Credit Note</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label for="cn_direction" value="Direction *" />
                        <select id="cn_direction" wire:model="direction" {{ !$isEditable ? 'disabled' : '' }}
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="issued">Issued (to supplier)</option>
                            <option value="received">Received (from supplier)</option>
                        </select>
                    </div>
                </div>

                {{-- Supplier --}}
                <div>
                    <x-input-label for="cn_supplier" value="Supplier *" />
                    <select id="cn_supplier" wire:model.live="supplier_id" {{ !$isEditable ? 'disabled' : '' }}
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— Select Supplier —</option>
                        @foreach ($suppliers as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('supplier_id')" class="mt-1" />
                </div>

                {{-- Date --}}
                <div>
                    <x-input-label for="cn_date" value="Issued Date *" />
                    <x-text-input id="cn_date" wire:model="issued_date" type="date" class="mt-1 block w-full" :disabled="!$isEditable" />
                    <x-input-error :messages="$errors->get('issued_date')" class="mt-1" />
                </div>

                {{-- Linked Invoice & GRN --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="cn_invoice" value="Linked Invoice" />
                        <select id="cn_invoice" wire:model="procurement_invoice_id" {{ !$isEditable ? 'disabled' : '' }}
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— None —</option>
                            @foreach ($invoices as $inv)
                                <option value="{{ $inv->id }}">{{ $inv->invoice_number }} ({{ number_format($inv->total_amount, 2) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="cn_grn" value="Linked GRN" />
                        <select id="cn_grn" wire:model.live="goods_received_note_id" {{ !$isEditable ? 'disabled' : '' }}
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— None —</option>
                            @foreach ($grns as $g)
                                <option value="{{ $g->id }}">{{ $g->grn_number }} ({{ $g->received_date?->format('d M Y') }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Reason --}}
                <div>
                    <x-input-label for="cn_reason" value="Reason" />
                    <x-text-input id="cn_reason" wire:model="reason" type="text" class="mt-1 block w-full" placeholder="e.g. Damaged goods on delivery" :disabled="!$isEditable" />
                </div>

                {{-- Notes --}}
                <div>
                    <x-input-label for="cn_notes" value="Notes" />
                    <textarea id="cn_notes" wire:model="notes" rows="2" {{ !$isEditable ? 'disabled' : '' }}
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                </div>
            </div>

            {{-- Lines --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <h3 class="text-sm font-semibold text-gray-700">Line Items</h3>

                {{-- Ingredient search --}}
                @if ($isEditable)
                <div class="relative" x-data="{ open: @entangle('ingredientSearch').live }">
                    <input type="text" wire:model.live.debounce.300ms="ingredientSearch"
                           placeholder="Search ingredients to add..."
                           class="w-full rounded-lg border-gray-300 text-sm" />

                    @if ($searchResults->count())
                        <div class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                            @foreach ($searchResults as $ing)
                                <button type="button" wire:click="addIngredient({{ $ing->id }})"
                                        class="w-full text-left px-4 py-2 hover:bg-indigo-50 text-sm flex justify-between items-center">
                                    <span>{{ $ing->name }}</span>
                                    <span class="text-xs text-gray-400">{{ $ing->baseUom?->abbreviation ?? '' }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
                @endif

                {{-- Lines table --}}
                @if (count($lines))
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-xs text-gray-500 uppercase bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left">Item</th>
                                    <th class="px-3 py-2 text-center w-24">Qty</th>
                                    <th class="px-3 py-2 text-center w-28">UOM</th>
                                    <th class="px-3 py-2 text-right w-28">Unit Price</th>
                                    <th class="px-3 py-2 text-right w-28">Total</th>
                                    <th class="px-3 py-2 text-center w-36">Reason</th>
                                    @if ($isEditable)
                                        <th class="px-3 py-2 w-10"></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($lines as $idx => $line)
                                    <tr wire:key="line-{{ $idx }}">
                                        <td class="px-3 py-2 text-gray-700 text-xs font-medium">
                                            {{ $line['ingredient_name'] }}
                                            @if (!empty($line['description']))
                                                <div class="text-xs text-gray-400 mt-0.5">{{ $line['description'] }}</div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" wire:model.live.debounce.500ms="lines.{{ $idx }}.quantity"
                                                   step="0.01" min="0" {{ !$isEditable ? 'disabled' : '' }}
                                                   class="w-full text-center rounded border-gray-300 text-sm" />
                                        </td>
                                        <td class="px-3 py-2">
                                            <select wire:model="lines.{{ $idx }}.uom_id" {{ !$isEditable ? 'disabled' : '' }}
                                                    class="w-full rounded border-gray-300 text-sm">
                                                @foreach ($uoms as $u)
                                                    <option value="{{ $u->id }}">{{ $u->abbreviation }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" wire:model.live.debounce.500ms="lines.{{ $idx }}.unit_price"
                                                   step="0.01" min="0" {{ !$isEditable ? 'disabled' : '' }}
                                                   class="w-full text-right rounded border-gray-300 text-sm" />
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums font-medium text-gray-800">
                                            {{ number_format($line['total_price'] ?? 0, 2) }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <select wire:model="lines.{{ $idx }}.reason_code" {{ !$isEditable ? 'disabled' : '' }}
                                                    class="w-full rounded border-gray-300 text-xs">
                                                <option value="damaged">Damaged</option>
                                                <option value="rejected">Rejected</option>
                                                <option value="short_delivery">Short Delivery</option>
                                                <option value="return">Return</option>
                                                <option value="overcharge">Overcharge</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </td>
                                        @if ($isEditable)
                                            <td class="px-3 py-2 text-center">
                                                <button wire:click="removeLine({{ $idx }})" class="text-red-400 hover:text-red-600 transition">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-center text-gray-400 py-6 text-sm">No line items added yet. Search above to add ingredients.</p>
                @endif

                <x-input-error :messages="$errors->get('lines')" class="mt-1" />
            </div>
        </div>

        {{-- Summary sidebar (1/3) --}}
        <div class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-3">
                <h3 class="text-sm font-semibold text-gray-700">Summary</h3>

                <div class="flex justify-between text-sm text-gray-600">
                    <span>Subtotal</span>
                    <span class="tabular-nums">{{ number_format($subtotal, 2) }}</span>
                </div>

                @if ($taxPct > 0)
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Tax ({{ number_format($taxPct, 0) }}%)</span>
                        <span class="tabular-nums">{{ number_format($taxAmount, 2) }}</span>
                    </div>
                @endif

                <div class="border-t border-gray-200 pt-2 flex justify-between text-base font-bold text-gray-800">
                    <span>Total</span>
                    <span class="tabular-nums">{{ number_format($grandTotal, 2) }}</span>
                </div>

                <div class="mt-2 text-xs text-gray-400">
                    {{ count($lines) }} line item(s)
                </div>
            </div>

            {{-- Quick info --}}
            @if ($creditNoteId)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-2">
                    <h3 class="text-sm font-semibold text-gray-700">Info</h3>
                    <div class="text-xs text-gray-500 space-y-1">
                        <p><span class="font-medium">Type:</span> {{ $type === 'debit_note' ? 'Debit Note' : 'Credit Note' }}</p>
                        <p><span class="font-medium">Direction:</span> {{ ucfirst($direction) }}</p>
                        <p><span class="font-medium">Status:</span> {{ ucfirst($status) }}</p>
                    </div>
                </div>
            @endif
        </div>

    </div>
</div>
