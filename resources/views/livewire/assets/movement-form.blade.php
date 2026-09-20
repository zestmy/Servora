<div>
    <x-page-header :title="$isReceipt ? 'Receive Assets' : 'Dispose of Assets'" eyebrow="Assets"
                   :subtitle="$isReceipt
                        ? 'Book in what arrived. The register counts it at this outlet from the date below.'
                        : 'Record what was broken, lost or written off, so the register stops counting it.'">
        <x-slot:actions>
            <a href="{{ route('assets.records', ['tab' => $isReceipt ? 'receipts' : 'disposals']) }}"
               wire:navigate class="btn-secondary">Back</a>
            @canDo('assets.movements.record')
                <button wire:click="save" class="btn-primary">
                    {{ $recordId ? 'Save changes' : ($isReceipt ? 'Receive assets' : 'Record disposal') }}
                </button>
            @endcanDo
        </x-slot:actions>
    </x-page-header>

    @error('lines') <div class="alert alert-danger mb-4">{{ $message }}</div> @enderror

    @if ($purchaseRequest)
        <div class="alert alert-info mb-4">
            Received against purchase request <span class="font-medium">{{ $purchaseRequest->pr_number }}</span>.
            Quantities came from the request; prices did not — a request carries no prices, so check them
            against the invoice.
        </div>
    @endif

    {{-- Header --}}
    <div class="card p-5 mb-4">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="label" for="mv-outlet">Outlet</label>
                @if ($hasOutletChoice)
                    <select id="mv-outlet" wire:model.live="outlet_id" class="input">
                        <option value="">Choose…</option>
                        @foreach ($outlets as $outlet)
                            <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                        @endforeach
                    </select>
                @else
                    <p class="input bg-gray-50">{{ $outlets->firstWhere('id', $outlet_id)?->name ?? '—' }}</p>
                @endif
                @error('outlet_id') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="mv-date">Date</label>
                <input id="mv-date" type="date" wire:model="movement_date" class="input" />
                @error('movement_date') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="mv-ref">Reference</label>
                <input id="mv-ref" type="text" wire:model="reference_number" class="input"
                       placeholder="{{ $isReceipt ? 'Invoice or DO number' : 'Optional' }}" />
            </div>

            @if ($isReceipt)
                <div>
                    <label class="label" for="mv-supplier">Supplier</label>
                    <select id="mv-supplier" wire:model.live="supplier_id" class="input">
                        <option value="">None</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    @error('supplier_id') <p class="error-text">{{ $message }}</p> @enderror
                </div>
            @else
                <div>
                    <label class="label" for="mv-reason">Reason</label>
                    <select id="mv-reason" wire:model="reason" class="input">
                        <option value="">Choose…</option>
                        @foreach (\App\Models\AssetMovement::REASONS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('reason') <p class="error-text">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label class="label" for="mv-dept">Department</label>
                <select id="mv-dept" wire:model="department_id" class="input">
                    <option value="">Whole outlet</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sm:col-span-2 lg:col-span-3">
                <label class="label" for="mv-notes">Notes</label>
                <textarea id="mv-notes" wire:model="notes" rows="2" class="input"></textarea>
            </div>
        </div>
    </div>

    {{-- Add assets --}}
    <div class="card p-5 mb-4">
        <x-card-title>Add assets</x-card-title>
        <div class="relative">
            <input type="text" wire:model.live.debounce.300ms="assetSearch"
                   placeholder="Search by name, code or brand…" class="input" />
            @if (count($searchResults) > 0)
                <div class="absolute z-20 mt-1 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg max-h-60">
                    @foreach ($searchResults as $item)
                        <button type="button" wire:click="addAsset({{ $item->id }})"
                                class="flex w-full items-center justify-between border-b border-gray-50 px-4 py-2.5 text-left text-sm last:border-0 hover:bg-brand-50">
                            <div>
                                <span class="font-medium text-gray-700">{{ $item->name }}</span>
                                @if ($item->code)<span class="ml-1 text-gray-600">({{ $item->code }})</span>@endif
                                @if ($item->brand)
                                    <span class="block text-xs text-gray-600">{{ $item->brand }} {{ $item->model }}</span>
                                @endif
                            </div>
                            <span class="text-xs text-gray-600">{{ $item->uom?->abbreviation }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Lines --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-[780px]">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left w-10">#</th>
                        <th class="px-4 py-3 text-left">Asset</th>
                        <th class="px-4 py-3 text-right w-28">On hand now</th>
                        <th class="px-4 py-3 text-center w-28">Quantity</th>
                        <th class="px-4 py-3 text-center w-32">Unit cost</th>
                        <th class="px-4 py-3 text-right w-32">Total</th>
                        <th class="px-4 py-3 text-left w-44">Note</th>
                        <th class="px-4 py-3 w-12"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lines as $i => $line)
                        @php
                            $assetId  = (int) $line['asset_id'];
                            $held     = $onHand[$assetId] ?? 0;
                            $quantity = (float) $line['quantity'];
                            $after    = $isReceipt ? $held + $quantity : $held - $quantity;
                        @endphp
                        <tr wire:key="mv-line-{{ $assetId }}" class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-600">{{ $i + 1 }}</td>

                            <td class="px-4 py-3">
                                <span class="font-medium text-gray-700">{{ $line['asset_name'] }}</span>
                                <span class="ml-1 text-xs text-gray-600">{{ $line['uom'] }}</span>
                            </td>

                            <td class="px-4 py-3 text-right tabular-nums text-gray-600">
                                {{ number_format($held, 2) }}
                                {{-- Says where this line leaves the outlet, so somebody booking a
                                     disposal sees it would take the count below zero before it does. --}}
                                <span class="block text-xs {{ $after < 0 ? 'text-danger-600 font-medium' : 'text-gray-500' }}">
                                    → {{ number_format($after, 2) }}
                                </span>
                            </td>

                            <td class="px-4 py-3">
                                <input type="number" step="0.01" min="0"
                                       wire:model.live.debounce.500ms="lines.{{ $i }}.quantity"
                                       class="input text-center" />
                                @error("lines.$i.quantity") <p class="error-text">{{ $message }}</p> @enderror
                            </td>

                            <td class="px-4 py-3">
                                @if ($isReceipt)
                                    <input type="number" step="0.01" min="0"
                                           wire:model.live.debounce.500ms="lines.{{ $i }}.unit_cost"
                                           class="input text-center" />
                                    @error("lines.$i.unit_cost") <p class="error-text">{{ $message }}</p> @enderror
                                @else
                                    <div class="text-center tabular-nums text-gray-600">
                                        {{ number_format((float) $line['unit_cost'], 2) }}
                                    </div>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">
                                {{ number_format($quantity * (float) $line['unit_cost'], 2) }}
                            </td>

                            <td class="px-4 py-3">
                                <input type="text" wire:model="lines.{{ $i }}.notes" class="input" />
                            </td>

                            <td class="px-4 py-3 text-center">
                                <button type="button" wire:click="removeLine({{ $i }})"
                                        class="icon-btn icon-btn-danger" title="Remove">
                                    <x-icon name="close" size="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10">
                                <div class="empty-state">
                                    <p class="empty-title">Nothing on this {{ $isReceipt ? 'receipt' : 'disposal' }} yet</p>
                                    <p class="empty-body">Search for an asset above to add it.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($lines)
                    <tfoot>
                        <tr class="border-t-2 border-gray-200 bg-gray-50">
                            <td colspan="5" class="px-4 py-3 font-semibold text-gray-800">Total</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-gray-900">
                                {{ number_format($total, 2) }}
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <p class="help mt-4">
        @if ($isReceipt)
            The price you type here is the one on the invoice, and it becomes the asset's unit cost — which is
            what the register and the next count value it at. Needs “Set costs” to write back; without it the
            receipt still files at this price and the asset list is left alone.
        @else
            A disposal is valued at what the asset list already carries it at. There is no price to type: a
            figure entered here would move asset value with no document behind it.
        @endif
    </p>
</div>
