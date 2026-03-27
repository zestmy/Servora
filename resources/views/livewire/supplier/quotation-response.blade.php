<div>
    @php $rfq = $requestSupplier->quotationRequest; @endphp

    <div class="mb-6">
        <a href="{{ route('supplier.quotations') }}" class="text-sm text-indigo-600 hover:underline">&larr; Back to Quotations</a>
    </div>

    <h2 class="text-lg font-semibold text-gray-700 mb-1">Submit Quotation</h2>
    <p class="text-sm text-gray-500 mb-6">Respond to RFQ <span class="font-mono font-medium text-gray-700">{{ $rfq->rfq_number }}</span></p>

    {{-- RFQ Info --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
            <div>
                <span class="text-gray-500">RFQ Number</span>
                <p class="font-mono font-medium text-gray-800">{{ $rfq->rfq_number }}</p>
            </div>
            <div>
                <span class="text-gray-500">Title</span>
                <p class="font-medium text-gray-800">{{ $rfq->title }}</p>
            </div>
            <div>
                <span class="text-gray-500">Needed By</span>
                <p class="font-medium text-gray-800">{{ $rfq->needed_by_date?->format('d M Y') ?? '—' }}</p>
            </div>
        </div>
        @if ($rfq->notes)
            <div class="mt-3 text-sm">
                <span class="text-gray-500">Notes</span>
                <p class="text-gray-700">{{ $rfq->notes }}</p>
            </div>
        @endif
    </div>

    <form wire:submit="submit">
        {{-- Lines Table --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
            <div class="px-5 py-3 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">Line Items</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-3 text-left">Ingredient</th>
                            <th class="px-4 py-3 text-center">Qty</th>
                            <th class="px-4 py-3 text-center">UOM</th>
                            <th class="px-4 py-3 text-right">Unit Price</th>
                            <th class="px-4 py-3 text-center">Price Type</th>
                            <th class="px-4 py-3 text-right">Discount %</th>
                            <th class="px-4 py-3 text-left">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($lines as $idx => $line)
                            <tr>
                                <td class="px-4 py-3 text-gray-800 font-medium">{{ $line['ingredient_name'] }}</td>
                                <td class="px-4 py-3 text-center text-gray-600 tabular-nums">{{ number_format($line['quantity'], 2) }}</td>
                                <td class="px-4 py-3 text-center text-gray-600">{{ $line['uom_name'] }}</td>
                                <td class="px-4 py-3">
                                    <input type="number" step="0.0001" min="0"
                                           wire:model="lines.{{ $idx }}.unit_price"
                                           class="w-28 ml-auto block text-right rounded-lg border-gray-300 text-sm tabular-nums"
                                           placeholder="0.00" required>
                                    @error("lines.{$idx}.unit_price") <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                </td>
                                <td class="px-4 py-3">
                                    <select wire:model.live="lines.{{ $idx }}.price_type"
                                            class="w-full rounded-lg border-gray-300 text-sm">
                                        <option value="listed">Listed</option>
                                        <option value="discounted">Discounted</option>
                                        <option value="tender">Tender</option>
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($lines[$idx]['price_type'] === 'discounted')
                                        <input type="number" step="0.01" min="0" max="100"
                                               wire:model="lines.{{ $idx }}.discount_percent"
                                               class="w-20 ml-auto block text-right rounded-lg border-gray-300 text-sm tabular-nums"
                                               placeholder="0">
                                    @else
                                        <span class="text-gray-400 text-xs block text-right">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <input type="text"
                                           wire:model="lines.{{ $idx }}.notes"
                                           class="w-full rounded-lg border-gray-300 text-sm"
                                           placeholder="Optional">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Header Fields --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Quotation Details</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Valid Until</label>
                    <input type="date" wire:model="valid_until" class="w-full rounded-lg border-gray-300 text-sm" required>
                    @error('valid_until') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Delivery Charges</label>
                    <input type="number" step="0.01" min="0" wire:model="delivery_charges" class="w-full rounded-lg border-gray-300 text-sm tabular-nums" placeholder="0.00">
                    @error('delivery_charges') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Notes</label>
                    <input type="text" wire:model="notes" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Optional remarks">
                </div>
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex justify-end">
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center px-5 py-2.5 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 transition">
                <span wire:loading.remove wire:target="submit">Submit Quotation</span>
                <span wire:loading wire:target="submit">Submitting...</span>
            </button>
        </div>
    </form>
</div>
