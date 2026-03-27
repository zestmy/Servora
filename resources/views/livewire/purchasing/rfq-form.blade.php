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
                / {{ $rfqId ? $rfqNumber : 'New RFQ' }}
            </p>
        </div>
        @if ($isEditable)
        <div class="flex gap-2 flex-shrink-0">
            <button wire:click="save('send')"
                    wire:confirm="This will email the RFQ to all selected suppliers. Continue?"
                    class="px-4 py-2 border border-blue-500 text-blue-600 text-sm font-medium rounded-lg hover:bg-blue-50 transition">
                Send to Suppliers
            </button>
            <button wire:click="save('draft')"
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
                <h3 class="text-sm font-semibold text-gray-700">RFQ Details</h3>

                {{-- RFQ Number (read-only) --}}
                <div>
                    <x-input-label value="RFQ Number" />
                    <p class="mt-1 px-3 py-2 bg-gray-50 rounded-md border border-gray-200 text-sm font-mono text-gray-700">
                        {{ $rfqNumber }}
                        @if ($rfqId)
                            <span class="ml-2 text-xs font-sans px-2 py-0.5 rounded-full
                                {{ match($status) {
                                    'draft'  => 'bg-gray-100 text-gray-600',
                                    'sent'   => 'bg-blue-100 text-blue-700',
                                    'closed' => 'bg-green-100 text-green-700',
                                    default  => 'bg-gray-100 text-gray-500',
                                } }}">
                                {{ ucfirst($status) }}
                            </span>
                        @endif
                    </p>
                </div>

                {{-- Title --}}
                <div>
                    <x-input-label for="rfq_title" value="Title *" />
                    <x-text-input id="rfq_title" wire:model="title" type="text" class="mt-1 block w-full"
                                  placeholder="e.g. Weekly Produce Quotation" :disabled="!$isEditable" />
                    <x-input-error :messages="$errors->get('title')" class="mt-1" />
                </div>

                {{-- Needed By Date --}}
                <div>
                    <x-input-label for="rfq_date" value="Needed By Date *" />
                    <x-text-input id="rfq_date" wire:model="needed_by_date" type="date" class="mt-1 block w-full"
                                  :disabled="!$isEditable" />
                    <x-input-error :messages="$errors->get('needed_by_date')" class="mt-1" />
                </div>

                {{-- Notes --}}
                <div>
                    <x-input-label for="rfq_notes" value="Notes" />
                    <textarea id="rfq_notes" wire:model="notes" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="Special requirements, delivery instructions, etc."
                              @if(!$isEditable) disabled @endif></textarea>
                </div>
            </div>
        </div>

        {{-- Supplier Selection (1/3) --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 lg:sticky lg:top-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Invite Suppliers *</h3>
                <x-input-error :messages="$errors->get('selectedSuppliers')" class="mb-2" />

                <div class="space-y-2 max-h-80 overflow-y-auto">
                    @forelse ($suppliers as $supplier)
                        <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer transition">
                            <input type="checkbox"
                                   wire:model="selectedSuppliers"
                                   value="{{ $supplier->id }}"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                   @if(!$isEditable) disabled @endif />
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-700 truncate">{{ $supplier->name }}</p>
                                @if ($supplier->email)
                                    <p class="text-xs text-gray-400 truncate">{{ $supplier->email }}</p>
                                @endif
                            </div>
                        </label>
                    @empty
                        <p class="text-xs text-gray-400">No active suppliers found.</p>
                    @endforelse
                </div>

                @if (count($selectedSuppliers) > 0)
                    <div class="mt-4 pt-3 border-t border-gray-100 text-xs text-gray-500">
                        {{ count($selectedSuppliers) }} supplier{{ count($selectedSuppliers) !== 1 ? 's' : '' }} selected
                    </div>
                @endif
            </div>
        </div>

    </div>

    {{-- Ingredient Lines --}}
    <div class="mt-4 bg-white rounded-xl shadow-sm border border-gray-100">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">Requested Items</h3>
                <p class="text-xs text-gray-400 mt-0.5">{{ count($lines) }} item{{ count($lines) !== 1 ? 's' : '' }}</p>
            </div>
        </div>

        {{-- Ingredient Search --}}
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
                       placeholder="Search ingredients to add... (type at least 2 characters)"
                       class="w-full pl-9 pr-4 py-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>

            @if ($searchResults->isNotEmpty())
                <div class="mt-2 border border-gray-200 rounded-lg overflow-hidden divide-y divide-gray-100 shadow-sm">
                    @foreach ($searchResults as $ingredient)
                        <button type="button"
                                wire:click="addIngredient({{ $ingredient->id }})"
                                class="w-full flex items-center justify-between px-4 py-2.5 text-left hover:bg-indigo-50 transition text-sm">
                            <div>
                                <span class="font-medium text-gray-800">{{ $ingredient->name }}</span>
                                @if ($ingredient->code)
                                    <span class="ml-2 text-xs text-gray-400">{{ $ingredient->code }}</span>
                                @endif
                            </div>
                            <span class="text-xs text-gray-400">{{ $ingredient->baseUom?->abbreviation }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
        @endif

        {{-- Lines Table --}}
        @if (count($lines) > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50/60">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ingredient</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-36">Quantity</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-36">UOM</th>
                        @if ($isEditable)
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16"></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($lines as $idx => $line)
                    <tr class="hover:bg-gray-50/40" wire:key="line-{{ $idx }}">
                        <td class="px-6 py-3 text-gray-400 tabular-nums">{{ $idx + 1 }}</td>
                        <td class="px-6 py-3 font-medium text-gray-800">{{ $line['ingredient_name'] }}</td>
                        <td class="px-6 py-3">
                            @if ($isEditable)
                                <input type="number" step="any" min="0"
                                       wire:model.lazy="lines.{{ $idx }}.quantity"
                                       class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            @else
                                <span class="tabular-nums">{{ $line['quantity'] }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3">
                            @if ($isEditable)
                                <select wire:model="lines.{{ $idx }}.uom_id"
                                        class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach ($uoms as $uom)
                                        <option value="{{ $uom->id }}">{{ $uom->abbreviation }}</option>
                                    @endforeach
                                </select>
                            @else
                                {{ $uoms->firstWhere('id', $line['uom_id'])?->abbreviation ?? '—' }}
                            @endif
                        </td>
                        @if ($isEditable)
                        <td class="px-6 py-3 text-center">
                            <button wire:click="removeLine({{ $idx }})"
                                    class="text-red-400 hover:text-red-600 transition" title="Remove">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
        <div class="px-6 py-12 text-center text-gray-400 text-sm">
            No items added yet. Use the search above to add ingredients.
        </div>
        @endif

    </div>
</div>
