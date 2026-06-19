<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Top bar --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('inventory.index', ['tab' => 'purchases']) }}" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1 min-w-0">
            <p class="text-xs text-gray-400">
                <a href="{{ route('inventory.index', ['tab' => 'purchases']) }}" class="hover:underline">Inventory</a>
                / {{ $recordId ? 'Purchase #' . $recordId : 'Record Purchase' }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <button wire:click="save"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                {{ $recordId ? 'Update' : 'Save' }}
            </button>
        </div>
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

        {{-- Details card --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <h3 class="text-sm font-semibold text-gray-700">Purchase Details</h3>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="pc_date" value="Date *" />
                        <x-text-input id="pc_date" wire:model="purchase_date" type="date" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('purchase_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="pc_ref" value="Reference" />
                        <x-text-input id="pc_ref" wire:model="reference_number" type="text"
                                      class="mt-1 block w-full" placeholder="e.g. Invoice #1234" />
                    </div>
                </div>

                <div>
                    <x-input-label for="pc_dept" value="Department *" />
                    <select id="pc_dept" wire:model="department_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— Select Department —</option>
                        @foreach ($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('department_id')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="pc_supplier" value="Supplier" />
                    <select id="pc_supplier" wire:model.live="supplier_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— Select Supplier —</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                        <option value="other">Other (enter manually)</option>
                    </select>
                    <x-input-error :messages="$errors->get('supplier_id')" class="mt-1" />
                </div>

                @if ($supplier_id === 'other')
                    <div>
                        <x-input-label for="pc_supplier_name" value="Supplier Name *" />
                        <x-text-input id="pc_supplier_name" wire:model="supplier_name" type="text"
                                      class="mt-1 block w-full" placeholder="Type the supplier name" />
                        <x-input-error :messages="$errors->get('supplier_name')" class="mt-1" />
                    </div>
                @endif

                <div>
                    <x-input-label for="pc_amount" value="Total Purchase Value (RM) *" />
                    <x-text-input id="pc_amount" wire:model="amount" type="number" step="0.01" min="0"
                                  class="mt-1 block w-full text-lg font-semibold" placeholder="0.00" />
                    <p class="mt-1 text-xs text-gray-400">Enter the total purchase amount for this department.</p>
                    <x-input-error :messages="$errors->get('amount')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="pc_notes" value="Notes" />
                    <textarea id="pc_notes" wire:model="notes" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="Optional notes…"></textarea>
                </div>
            </div>
        </div>

        {{-- Summary card --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 lg:sticky lg:top-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Summary</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Department</dt>
                        <dd class="font-medium text-gray-800">
                            {{ optional($departments->firstWhere('id', $department_id))->name ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Supplier</dt>
                        <dd class="font-medium text-gray-800">
                            @if ($supplier_id === 'other')
                                {{ $supplier_name ?: '—' }}
                            @elseif ($supplier_id !== '')
                                {{ optional($suppliers->firstWhere('id', (int) $supplier_id))->name ?? '—' }}
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between border-t border-gray-100 pt-3">
                        <dt class="font-semibold text-gray-600">Total Purchase</dt>
                        <dd class="font-bold text-lg text-gray-800 tabular-nums">
                            RM {{ number_format(floatval($amount), 2) }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
