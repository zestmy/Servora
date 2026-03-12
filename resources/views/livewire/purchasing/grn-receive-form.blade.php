<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">{{ session('error') }}</div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-700">Receive Goods — {{ $grnNumber }}</h2>
            <p class="text-xs text-gray-400 mt-0.5">Confirm received quantities and conditions</p>
        </div>
        <a href="{{ route('purchasing.index', ['tab' => 'grn']) }}" class="text-sm text-gray-500 hover:text-gray-700 transition">Back to list</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: Form --}}
        <div class="lg:col-span-2 space-y-4">
            {{-- Reference Info --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div>
                        <p class="text-gray-400 text-xs">GRN Number</p>
                        <p class="font-mono font-medium text-gray-800">{{ $grnNumber }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-xs">DO Number</p>
                        <p class="font-mono text-gray-600">{{ $doNumber }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-xs">PO Number</p>
                        <p class="font-mono text-gray-600">{{ $poNumber }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-xs">Supplier</p>
                        <p class="font-medium text-gray-800">{{ $supplierName }}</p>
                    </div>
                </div>
            </div>

            {{-- Receiving Details --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Receiving Details</h3>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="received_date" value="Received Date *" />
                        <x-text-input id="received_date" wire:model="received_date" type="date" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('received_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="reference_number" value="Invoice / Ref #" />
                        <x-text-input id="reference_number" wire:model="reference_number" type="text" class="mt-1 block w-full" placeholder="e.g. INV-12345" />
                    </div>
                    <div>
                        <x-input-label for="notes" value="Notes" />
                        <x-text-input id="notes" wire:model="notes" type="text" class="mt-1 block w-full" />
                    </div>
                </div>
            </div>

            {{-- Lines --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Items</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-gray-500 text-xs uppercase border-b">
                            <tr>
                                <th class="text-left py-2 px-2">Ingredient</th>
                                <th class="text-center py-2 px-2 w-20">Expected</th>
                                <th class="text-center py-2 px-2 w-24">Received</th>
                                <th class="text-center py-2 px-2 w-16">UOM</th>
                                @if ($showPrice)
                                    <th class="text-right py-2 px-2 w-28">Unit Cost</th>
                                @endif
                                <th class="text-center py-2 px-2 w-28">Condition</th>
                                @if ($showPrice)
                                    <th class="text-right py-2 px-2 w-24">Total</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lines as $idx => $line)
                                @php
                                    $isRejected = $line['condition'] === 'rejected';
                                    $isDamaged  = $line['condition'] === 'damaged';
                                    $rowClass   = $isRejected ? 'bg-red-50' : ($isDamaged ? 'bg-orange-50' : '');
                                @endphp
                                <tr class="border-b border-gray-50 {{ $rowClass }}">
                                    <td class="py-2 px-2">
                                        <div class="font-medium text-gray-800">
                                            {{ $line['ingredient_name'] }}
                                            @if (! empty($line['pack_info']))
                                                <span class="text-indigo-600 font-semibold">{{ $line['pack_info'] }}</span>
                                            @endif
                                        </div>
                                        @if ($supplierName && $supplierName !== '—')
                                            <p class="text-xs text-gray-400 mt-0.5">[{{ $supplierName }}]</p>
                                        @endif
                                    </td>
                                    <td class="py-2 px-2 text-center text-gray-400 text-xs">{{ floatval($line['expected_qty']) }}</td>
                                    <td class="py-2 px-2">
                                        <input type="number" wire:model.lazy="lines.{{ $idx }}.received_qty"
                                               step="0.01" min="0"
                                               class="w-full text-center rounded border-gray-300 text-sm py-1 focus:border-indigo-500 focus:ring-indigo-500">
                                    </td>
                                    <td class="py-2 px-2 text-center text-gray-500 text-xs">{{ $line['uom_abbr'] }}</td>
                                    @if ($showPrice)
                                        <td class="py-2 px-2">
                                            <input type="number" wire:model.lazy="lines.{{ $idx }}.unit_cost"
                                                   step="0.01" min="0"
                                                   class="w-full text-right rounded border-gray-300 text-sm py-1 focus:border-indigo-500 focus:ring-indigo-500">
                                        </td>
                                    @endif
                                    <td class="py-2 px-2">
                                        <select wire:model.live="lines.{{ $idx }}.condition"
                                                class="w-full rounded border-gray-300 text-xs py-1 focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="good">Good</option>
                                            <option value="damaged">Damaged</option>
                                            <option value="rejected">Rejected</option>
                                        </select>
                                    </td>
                                    @if ($showPrice)
                                        <td class="py-2 px-2 text-right tabular-nums font-medium {{ $isRejected ? 'text-red-400 line-through' : 'text-gray-700' }}">
                                            {{ number_format(floatval($line['received_qty']) * floatval($line['unit_cost']), 2) }}
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Right: Summary --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 sticky top-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Receipt Summary</h3>

                @php
                    $goodCount = collect($lines)->where('condition', 'good')->count();
                    $damagedCount = collect($lines)->where('condition', 'damaged')->count();
                    $rejectedCount = collect($lines)->where('condition', 'rejected')->count();
                @endphp

                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Outlet</dt>
                        <dd class="text-gray-800">{{ $outletName }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Supplier</dt>
                        <dd class="text-gray-800">{{ $supplierName }}</dd>
                    </div>
                    <hr class="my-2">
                    <div class="flex justify-between">
                        <dt class="text-green-600">Good</dt>
                        <dd class="text-green-700 font-medium">{{ $goodCount }} items</dd>
                    </div>
                    @if ($damagedCount)
                        <div class="flex justify-between">
                            <dt class="text-orange-600">Damaged</dt>
                            <dd class="text-orange-700 font-medium">{{ $damagedCount }} items</dd>
                        </div>
                    @endif
                    @if ($rejectedCount)
                        <div class="flex justify-between">
                            <dt class="text-red-600">Rejected</dt>
                            <dd class="text-red-700 font-medium">{{ $rejectedCount }} items</dd>
                        </div>
                    @endif
                    @if ($showPrice)
                        <hr class="my-2">
                        <div class="flex justify-between font-semibold text-base">
                            <dt class="text-gray-700">Accepted Total</dt>
                            <dd class="text-gray-800 tabular-nums">RM {{ number_format($grandTotal, 2) }}</dd>
                        </div>
                    @endif
                </dl>

                <button wire:click="confirm"
                        wire:confirm="Confirm receipt of goods? This will update inventory costs and create a purchase record."
                        class="mt-6 w-full px-4 py-2.5 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition">
                    Confirm Receipt
                </button>
            </div>
        </div>
    </div>
</div>
