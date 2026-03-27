<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('purchasing.index', ['tab' => 'sto']) }}" class="text-gray-400 hover:text-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="text-lg font-semibold text-gray-700">New Stock Transfer Order</h2>
        </div>
        <div class="flex gap-2">
            <button wire:click="save('draft')" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition">Save Draft</button>
            <button wire:click="save('send')" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">Save & Send</button>
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg">
            <ul class="list-disc list-inside text-sm text-red-600 space-y-1">
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            {{-- Transfer Details --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Transfer Details</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">From CPU *</label>
                        <select wire:model="cpu_id" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">— Select —</option>
                            @foreach ($cpus as $cpu)
                                <option value="{{ $cpu->id }}">{{ $cpu->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">To Outlet *</label>
                        <select wire:model="to_outlet_id" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">— Select —</option>
                            @foreach ($outlets as $outlet)
                                <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Transfer Date *</label>
                        <input type="date" wire:model="transfer_date" class="w-full rounded-lg border-gray-300 text-sm" />
                    </div>
                </div>

                {{-- Chargeable toggle --}}
                <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model.live="is_chargeable" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span class="text-sm font-medium text-gray-700">Chargeable transfer</span>
                    </label>
                    <p class="text-xs text-gray-400 mt-1">If enabled, an invoice will be auto-generated for the outlet.</p>

                    @if ($is_chargeable)
                        <div class="grid grid-cols-2 gap-4 mt-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Tax Rate</label>
                                <select wire:model="tax_rate_id" class="w-full rounded-lg border-gray-300 text-sm">
                                    <option value="">No Tax</option>
                                    @foreach ($taxRates as $tr)
                                        <option value="{{ $tr->id }}">{{ $tr->name }} ({{ $tr->rate }}%{{ $tr->is_inclusive ? ' incl.' : '' }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Delivery Charges</label>
                                <input type="number" step="0.01" min="0" wire:model="delivery_charges" class="w-full rounded-lg border-gray-300 text-sm" />
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mt-4">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Notes</label>
                    <textarea wire:model="notes" rows="2" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Transfer notes..."></textarea>
                </div>
            </div>

            {{-- Add Ingredients --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Items</h3>
                <div class="relative mb-4">
                    <input type="text" wire:model.live.debounce.300ms="ingredientSearch" placeholder="Search ingredients..."
                           class="w-full rounded-lg border-gray-300 text-sm" />
                    @if (count($searchResults) > 0)
                        <div class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                            @foreach ($searchResults as $item)
                                <button wire:click="addIngredient({{ $item->id }})" type="button"
                                        class="w-full text-left px-4 py-2.5 hover:bg-indigo-50 text-sm flex justify-between border-b border-gray-50 last:border-0">
                                    <span class="font-medium text-gray-700">{{ $item->name }}</span>
                                    <span class="text-xs text-gray-400">{{ $item->baseUom?->abbreviation }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3 text-left">#</th>
                                <th class="px-4 py-3 text-left">Ingredient</th>
                                <th class="px-4 py-3 text-center w-28">Quantity</th>
                                <th class="px-4 py-3 text-left w-24">UOM</th>
                                @if ($is_chargeable)
                                    <th class="px-4 py-3 text-right w-28">Unit Cost</th>
                                    <th class="px-4 py-3 text-right w-28">Total</th>
                                @endif
                                <th class="px-4 py-3 w-12"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($lines as $i => $line)
                                <tr wire:key="line-{{ $i }}">
                                    <td class="px-4 py-3 text-gray-400">{{ $i + 1 }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-700">{{ $line['ingredient_name'] }}</td>
                                    <td class="px-4 py-3">
                                        <input type="number" step="0.01" min="0" wire:model.live.debounce.500ms="lines.{{ $i }}.quantity"
                                               class="w-full text-center rounded-lg border-gray-300 text-sm" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <select wire:model="lines.{{ $i }}.uom_id" class="w-full rounded-lg border-gray-300 text-sm">
                                            @foreach ($uoms as $uom)
                                                <option value="{{ $uom->id }}">{{ $uom->abbreviation ?? $uom->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    @if ($is_chargeable)
                                        <td class="px-4 py-3">
                                            <input type="number" step="0.01" min="0" wire:model.live.debounce.500ms="lines.{{ $i }}.unit_cost"
                                                   class="w-full text-right rounded-lg border-gray-300 text-sm" />
                                        </td>
                                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">
                                            {{ number_format(floatval($line['quantity'] ?? 0) * floatval($line['unit_cost'] ?? 0), 2) }}
                                        </td>
                                    @endif
                                    <td class="px-4 py-3 text-center">
                                        <button wire:click="removeLine({{ $i }})" class="text-red-400 hover:text-red-600 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No items added.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Summary --}}
        <div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 sticky top-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Summary</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Items</span>
                        <span class="font-medium text-gray-700">{{ count($lines) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Type</span>
                        <span class="font-medium {{ $is_chargeable ? 'text-amber-600' : 'text-green-600' }}">
                            {{ $is_chargeable ? 'Chargeable' : 'Free Transfer' }}
                        </span>
                    </div>
                    @if ($is_chargeable)
                        <div class="border-t border-gray-100 pt-3 space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Subtotal</span>
                                <span class="font-medium text-gray-700">{{ number_format($subtotal, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Delivery</span>
                                <span class="font-medium text-gray-700">{{ number_format(floatval($delivery_charges), 2) }}</span>
                            </div>
                            <div class="flex justify-between text-base font-bold border-t border-gray-100 pt-2">
                                <span class="text-gray-700">Total</span>
                                <span class="text-gray-900">{{ number_format($subtotal + floatval($delivery_charges), 2) }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
