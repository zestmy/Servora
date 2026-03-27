<div>
    {{-- Flash --}}
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('purchasing.index', ['tab' => 'pr']) }}" class="text-gray-400 hover:text-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-lg font-semibold text-gray-700">{{ $requestId ? 'Edit' : 'New' }} Purchase Request</h2>
                <p class="text-sm text-gray-400">{{ $prNumber }}</p>
            </div>
        </div>

        @if ($isEditable)
            <div class="flex gap-2">
                <button wire:click="save('save')" wire:loading.attr="disabled"
                        class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition">
                    Save Draft
                </button>
                <button wire:click="save('submit')" wire:loading.attr="disabled"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    Save & Submit
                </button>
            </div>
        @endif
    </div>

    {{-- Status Badge --}}
    @if ($requestId)
        <div class="mb-4">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                {{ match($status) {
                    'draft' => 'bg-gray-100 text-gray-700',
                    'submitted' => 'bg-yellow-100 text-yellow-700',
                    'approved' => 'bg-green-100 text-green-700',
                    'rejected' => 'bg-red-100 text-red-700',
                    'converted' => 'bg-indigo-100 text-indigo-700',
                    'cancelled' => 'bg-gray-100 text-gray-500',
                    default => 'bg-gray-100 text-gray-700',
                } }}">
                {{ ucfirst($status) }}
            </span>
        </div>
    @endif

    {{-- Validation Errors --}}
    @if ($errors->any())
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg">
            <ul class="list-disc list-inside text-sm text-red-600 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: Details --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Order Info --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Request Details</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Request Date *</label>
                        <input type="date" wire:model="requested_date" {{ !$isEditable ? 'disabled' : '' }}
                               class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-50" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Needed By</label>
                        <input type="date" wire:model="needed_by_date" {{ !$isEditable ? 'disabled' : '' }}
                               class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-50" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Department</label>
                        <select wire:model="department_id" {{ !$isEditable ? 'disabled' : '' }}
                                class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-50">
                            <option value="">— Select —</option>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Notes</label>
                    <textarea wire:model="notes" rows="2" {{ !$isEditable ? 'disabled' : '' }}
                              class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-50"
                              placeholder="Any special instructions..."></textarea>
                </div>
            </div>

            {{-- Search + Add Ingredients --}}
            @if ($isEditable)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Add Ingredients</h3>
                    <div class="relative">
                        <input type="text" wire:model.live.debounce.300ms="ingredientSearch"
                               placeholder="Search ingredients by name or code..."
                               class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @if (count($searchResults) > 0)
                            <div class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                @foreach ($searchResults as $item)
                                    <button wire:click="addIngredient({{ $item->id }})" type="button"
                                            class="w-full text-left px-4 py-2.5 hover:bg-indigo-50 text-sm flex items-center justify-between border-b border-gray-50 last:border-0">
                                        <div>
                                            <span class="font-medium text-gray-700">{{ $item->name }}</span>
                                            @if ($item->code)
                                                <span class="text-gray-400 ml-1">({{ $item->code }})</span>
                                            @endif
                                        </div>
                                        <span class="text-xs text-gray-400">{{ $item->baseUom?->abbreviation ?? $item->baseUom?->name }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Lines Table --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3 text-left">#</th>
                                <th class="px-4 py-3 text-left">Ingredient</th>
                                <th class="px-4 py-3 text-center w-20">Par Level</th>
                                <th class="px-4 py-3 text-center w-28">Quantity</th>
                                <th class="px-4 py-3 text-left w-24">UOM</th>
                                <th class="px-4 py-3 text-left w-40">Preferred Supplier</th>
                                @if ($isEditable)
                                    <th class="px-4 py-3 w-12"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($lines as $i => $line)
                                <tr wire:key="line-{{ $i }}">
                                    <td class="px-4 py-3 text-gray-400">{{ $i + 1 }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-700">{{ $line['ingredient_name'] }}</td>
                                    <td class="px-4 py-3 text-center text-gray-400">
                                        {{ $line['par_level'] > 0 ? number_format($line['par_level'], 2) : '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($isEditable)
                                            <input type="number" step="0.01" min="0"
                                                   wire:model.live.debounce.500ms="lines.{{ $i }}.quantity"
                                                   class="w-full text-center rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        @else
                                            <div class="text-center">{{ number_format($line['quantity'], 2) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($isEditable)
                                            <select wire:model="lines.{{ $i }}.uom_id"
                                                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                @foreach ($uoms as $uom)
                                                    <option value="{{ $uom->id }}">{{ $uom->abbreviation ?? $uom->name }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            {{ $uoms->firstWhere('id', $line['uom_id'])?->abbreviation ?? '' }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($isEditable)
                                            <select wire:model="lines.{{ $i }}.preferred_supplier_id"
                                                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="">— None —</option>
                                                @foreach ($suppliers as $s)
                                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            {{ $line['supplier_name'] ?? '—' }}
                                        @endif
                                    </td>
                                    @if ($isEditable)
                                        <td class="px-4 py-3 text-center">
                                            <button wire:click="removeLine({{ $i }})" class="text-red-400 hover:text-red-600 transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                                        No ingredients added. Use the search above to add items.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Right: Summary --}}
        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 sticky top-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Summary</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">PR Number</span>
                        <span class="font-medium text-gray-700">{{ $prNumber }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Total Items</span>
                        <span class="font-medium text-gray-700">{{ count($lines) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Total Quantity</span>
                        <span class="font-medium text-gray-700">{{ number_format(collect($lines)->sum('quantity'), 2) }}</span>
                    </div>
                    @php
                        $supplierCount = collect($lines)->pluck('preferred_supplier_id')->filter()->unique()->count();
                    @endphp
                    <div class="flex justify-between">
                        <span class="text-gray-500">Suppliers</span>
                        <span class="font-medium text-gray-700">{{ $supplierCount }}</span>
                    </div>
                </div>

                @if (!$isEditable)
                    <div class="mt-6 pt-4 border-t border-gray-100">
                        <p class="text-xs text-gray-400">This request is {{ $status }} and cannot be edited.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
