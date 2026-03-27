<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Top bar --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('purchasing.rfq.index') }}" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1 min-w-0">
            <p class="text-xs text-gray-400">
                <a href="{{ route('purchasing.rfq.index') }}" class="hover:underline">RFQ</a>
                / {{ $rfq->rfq_number }}
            </p>
        </div>
        @if ($rfq->status === 'draft')
            <a href="{{ route('purchasing.rfq.edit', $rfq->id) }}"
               class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                Edit RFQ
            </a>
        @endif
    </div>

    {{-- RFQ Info Card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">{{ $rfq->title }}</h2>
                <p class="text-sm font-mono text-gray-500 mt-1">{{ $rfq->rfq_number }}</p>
            </div>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                {{ match($rfq->status) {
                    'draft'  => 'bg-gray-100 text-gray-600',
                    'sent'   => 'bg-blue-100 text-blue-700',
                    'closed' => 'bg-green-100 text-green-700',
                    default  => 'bg-gray-100 text-gray-500',
                } }}">
                {{ ucfirst($rfq->status) }}
            </span>
        </div>

        <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-xs text-gray-400">Needed By</p>
                <p class="font-medium text-gray-700">{{ $rfq->needed_by_date?->format('d M Y') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Items</p>
                <p class="font-medium text-gray-700">{{ $rfq->lines->count() }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Suppliers Invited</p>
                <p class="font-medium text-gray-700">{{ $rfq->suppliers->count() }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Created By</p>
                <p class="font-medium text-gray-700">{{ $rfq->createdBy?->name ?? '—' }}</p>
            </div>
        </div>

        @if ($rfq->notes)
            <div class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-xs text-gray-400 mb-1">Notes</p>
                <p class="text-sm text-gray-600">{{ $rfq->notes }}</p>
            </div>
        @endif
    </div>

    {{-- Invited Suppliers --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Invited Suppliers</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
            @foreach ($rfq->suppliers as $rqs)
                <div class="flex items-center gap-3 p-3 rounded-lg border border-gray-100 bg-gray-50/50">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-700 truncate">{{ $rqs->supplier?->name ?? '—' }}</p>
                        <p class="text-xs text-gray-400">{{ $rqs->supplier?->email ?? '' }}</p>
                    </div>
                    @php
                        $supplierStatus = $rqs->quotation ? 'quoted' : ($rqs->status === 'declined' ? 'declined' : 'pending');
                    @endphp
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium flex-shrink-0
                        {{ match($supplierStatus) {
                            'quoted'   => 'bg-green-100 text-green-700',
                            'declined' => 'bg-red-100 text-red-600',
                            default    => 'bg-yellow-100 text-yellow-700',
                        } }}">
                        {{ ucfirst($supplierStatus) }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Comparison Table --}}
    @if ($quotations->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700">Quotation Comparison</h3>
            <p class="text-xs text-gray-400 mt-0.5">{{ $quotations->count() }} response{{ $quotations->count() !== 1 ? 's' : '' }} received</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50/60">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sticky left-0 bg-gray-50/60 z-10">Ingredient</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider sticky left-0 bg-gray-50/60">Qty</th>
                        @foreach ($quotations as $q)
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[160px]">
                                {{ $q->supplier?->name ?? 'Supplier' }}
                                <br>
                                <span class="text-[10px] font-normal text-gray-400">{{ $q->quotation_number }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($rfq->lines as $line)
                    <tr class="hover:bg-gray-50/40">
                        <td class="px-6 py-3 font-medium text-gray-800 sticky left-0 bg-white z-10">
                            {{ $line->ingredient?->name ?? '—' }}
                            <span class="text-xs text-gray-400 ml-1">{{ $line->uom?->abbreviation }}</span>
                        </td>
                        <td class="px-4 py-3 text-center tabular-nums text-gray-600">{{ floatval($line->quantity) }}</td>
                        @foreach ($quotations as $q)
                            @php
                                $qLine = $q->lines->firstWhere('ingredient_id', $line->ingredient_id);
                                $isLowest = isset($lowestPrices[$line->ingredient_id]) && in_array($q->id, $lowestPrices[$line->ingredient_id]);
                            @endphp
                            <td class="px-6 py-3 text-center {{ $isLowest ? 'bg-green-50' : '' }}">
                                @if ($qLine)
                                    <p class="font-medium tabular-nums {{ $isLowest ? 'text-green-700' : 'text-gray-800' }}">
                                        {{ number_format(floatval($qLine->unit_price), 2) }}
                                    </p>
                                    @if ($qLine->price_type && $qLine->price_type !== 'listed')
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium mt-0.5
                                            {{ match($qLine->price_type) {
                                                'discounted' => 'bg-orange-100 text-orange-700',
                                                'tender'     => 'bg-purple-100 text-purple-700',
                                                default      => 'bg-gray-100 text-gray-500',
                                            } }}">
                                            {{ ucfirst($qLine->price_type) }}
                                        </span>
                                    @endif
                                    @if ($qLine->discount_percent > 0)
                                        <p class="text-[10px] text-gray-400 mt-0.5">-{{ number_format(floatval($qLine->discount_percent), 1) }}%</p>
                                    @endif
                                @else
                                    <span class="text-xs text-gray-300">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-gray-200 bg-gray-50/60">
                    {{-- Subtotal row --}}
                    <tr>
                        <td class="px-6 py-3 text-sm font-medium text-gray-600 sticky left-0 bg-gray-50/60 z-10" colspan="2">Subtotal</td>
                        @foreach ($quotations as $q)
                            <td class="px-6 py-3 text-center font-medium tabular-nums text-gray-700">
                                {{ number_format(floatval($q->subtotal), 2) }}
                            </td>
                        @endforeach
                    </tr>
                    {{-- Tax row --}}
                    <tr>
                        <td class="px-6 py-2 text-xs text-gray-500 sticky left-0 bg-gray-50/60 z-10" colspan="2">Tax</td>
                        @foreach ($quotations as $q)
                            <td class="px-6 py-2 text-center text-xs tabular-nums text-gray-500">
                                {{ number_format(floatval($q->tax_amount), 2) }}
                            </td>
                        @endforeach
                    </tr>
                    {{-- Delivery row --}}
                    <tr>
                        <td class="px-6 py-2 text-xs text-gray-500 sticky left-0 bg-gray-50/60 z-10" colspan="2">Delivery</td>
                        @foreach ($quotations as $q)
                            <td class="px-6 py-2 text-center text-xs tabular-nums text-gray-500">
                                {{ number_format(floatval($q->delivery_charges), 2) }}
                            </td>
                        @endforeach
                    </tr>
                    {{-- Total row --}}
                    <tr class="border-t border-gray-200">
                        <td class="px-6 py-3 text-sm font-semibold text-gray-800 sticky left-0 bg-gray-50/60 z-10" colspan="2">Total</td>
                        @foreach ($quotations as $q)
                            <td class="px-6 py-3 text-center font-bold tabular-nums text-gray-900 text-base">
                                {{ number_format(floatval($q->total_amount), 2) }}
                            </td>
                        @endforeach
                    </tr>
                    {{-- Accept button row --}}
                    @if ($rfq->status === 'sent')
                    <tr>
                        <td class="px-6 py-4 sticky left-0 bg-gray-50/60 z-10" colspan="2"></td>
                        @foreach ($quotations as $q)
                            <td class="px-6 py-4 text-center">
                                @if ($q->status === 'submitted' || $q->status === 'pending')
                                    <div class="flex flex-col gap-2">
                                        <button wire:click="acceptQuotation({{ $q->id }})"
                                                wire:confirm="Accept this quotation from {{ $q->supplier?->name }} and create a Purchase Order?"
                                                class="px-4 py-2 bg-green-600 text-white text-xs font-medium rounded-lg hover:bg-green-700 transition">
                                            Accept & Create PO
                                        </button>
                                        <button wire:click="openAddToIngredients({{ $q->id }})"
                                                class="px-4 py-2 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 transition">
                                            Add to Ingredients
                                        </button>
                                    </div>
                                @elseif ($q->status === 'accepted')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                        Accepted
                                    </span>
                                @elseif ($q->status === 'rejected')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-600">
                                        Rejected
                                    </span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    @endif
                </tfoot>
            </table>
        </div>
    </div>
    @else
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
        <p class="text-gray-400 text-sm">No quotations received yet.</p>
        @if ($rfq->status === 'sent')
            <p class="text-gray-300 text-xs mt-1">Waiting for supplier responses.</p>
        @endif
    </div>
    @endif

    {{-- Add to Ingredients Modal --}}
    @if ($showAddModal)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-900/50" wire:click="$set('showAddModal', false)"></div>
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-2xl z-10 p-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">Add Quotation Items to Ingredients</h3>
                    <p class="text-sm text-gray-500 mb-4">Select items to add to your ingredient list and link to this supplier.</p>

                    {{-- Target selection --}}
                    <div class="flex gap-4 mb-4">
                        <label class="flex items-center gap-2">
                            <input type="radio" wire:model="addTarget" value="outlet" class="text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">For Outlet</span>
                        </label>
                        @if ($cpus->count() > 0)
                            <label class="flex items-center gap-2">
                                <input type="radio" wire:model="addTarget" value="cpu" class="text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700">For Central Kitchen</span>
                            </label>
                        @endif
                    </div>

                    @if ($addTarget === 'outlet')
                        <select wire:model="addTargetOutletId" class="w-full rounded-lg border-gray-300 text-sm mb-4">
                            @foreach ($outlets as $o)
                                <option value="{{ $o->id }}">{{ $o->name }}</option>
                            @endforeach
                        </select>
                    @endif

                    {{-- Items table --}}
                    <div class="overflow-x-auto border border-gray-200 rounded-lg mb-4">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-2 w-10"></th>
                                    <th class="px-4 py-2 text-left">Ingredient</th>
                                    <th class="px-4 py-2 text-right">Price</th>
                                    <th class="px-4 py-2 text-center">UOM</th>
                                    <th class="px-4 py-2 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach ($addLines as $i => $line)
                                    <tr class="{{ $line['mapped'] ? 'bg-gray-50/50' : '' }}">
                                        <td class="px-4 py-2 text-center">
                                            <input type="checkbox" wire:model="addLines.{{ $i }}.selected"
                                                   {{ $line['mapped'] ? 'disabled' : '' }}
                                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                        </td>
                                        <td class="px-4 py-2 font-medium text-gray-700">{{ $line['ingredient_name'] }}</td>
                                        <td class="px-4 py-2 text-right tabular-nums text-gray-800">{{ number_format($line['unit_price'], 4) }}</td>
                                        <td class="px-4 py-2 text-center text-gray-500">{{ $line['uom_name'] }}</td>
                                        <td class="px-4 py-2 text-center">
                                            @if ($line['mapped'])
                                                <span class="px-2 py-0.5 bg-green-50 text-green-600 text-xs rounded">Already linked</span>
                                            @elseif ($line['exists'])
                                                <span class="px-2 py-0.5 bg-blue-50 text-blue-600 text-xs rounded">Exists — will link</span>
                                            @else
                                                <span class="px-2 py-0.5 bg-amber-50 text-amber-600 text-xs rounded">New ingredient</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button wire:click="$set('showAddModal', false)" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</button>
                        <button wire:click="addToIngredients"
                                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                            Add Selected Items
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
