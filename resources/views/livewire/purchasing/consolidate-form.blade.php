<div>
    {{-- Flash --}}
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('purchasing.index', ['tab' => 'pr']) }}" class="text-gray-400 hover:text-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="text-lg font-semibold text-gray-700">Consolidate Purchase Requests</h2>
        </div>
        <div class="flex gap-2">
            @if ($editMode)
                <button wire:click="exitEditMode"
                        class="px-4 py-2 border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                    Back to Preview
                </button>
                <button wire:click="consolidate" wire:loading.attr="disabled"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    <span wire:loading.remove wire:target="consolidate">Generate {{ count(collect($editablePreview)->filter(fn($g) => collect($g['lines'])->where('excluded', false)->isNotEmpty())) }} PO(s)</span>
                    <span wire:loading wire:target="consolidate">Creating...</span>
                </button>
            @elseif (count($selectedPrIds) > 0 && !$showPreview)
                <button wire:click="generatePreview"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    Preview Consolidation ({{ count($selectedPrIds) }})
                </button>
            @endif
        </div>
    </div>

    {{-- EDIT MODE: Full-width smart review --}}
    @if ($editMode)
        <div class="space-y-4">
            @foreach ($editablePreview as $gIdx => $group)
                @php
                    $activeLines = collect($group['lines'])->filter(fn($l) => !($l['excluded'] ?? false));
                @endphp
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden" wire:key="group-{{ $group['supplier_id'] }}">
                    {{-- Group Header --}}
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gray-50">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-700">{{ $group['supplier_name'] }}</h3>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $activeLines->count() }} active item(s) · {{ count($group['outlet_ids']) }} outlet(s)</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-400">PO Total</p>
                            <p class="text-lg font-bold text-gray-800 tabular-nums">RM {{ number_format($group['po_total'] ?? 0, 2) }}</p>
                            @if (($group['tax_total'] ?? 0) > 0)
                                <p class="text-xs text-gray-400">+ Tax: RM {{ number_format($group['tax_total'], 2) }}</p>
                            @endif
                        </div>
                    </div>

                    {{-- Lines Table --}}
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-2 w-10 text-center">Inc.</th>
                                    <th class="px-4 py-2 text-left">Ingredient</th>
                                    <th class="px-4 py-2 text-right w-28">Quantity</th>
                                    <th class="px-4 py-2 text-left w-20">UOM</th>
                                    <th class="px-4 py-2 text-left w-48">Supplier</th>
                                    <th class="px-4 py-2 text-right w-28">Unit Cost</th>
                                    <th class="px-4 py-2 text-center w-24">Tax</th>
                                    <th class="px-4 py-2 text-right w-28">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach ($group['lines'] as $lIdx => $line)
                                    <tr wire:key="line-{{ $gIdx }}-{{ $lIdx }}"
                                        class="{{ ($line['excluded'] ?? false) ? 'opacity-40 bg-gray-50' : 'hover:bg-gray-50' }} transition">
                                        <td class="px-4 py-2 text-center">
                                            <button wire:click="toggleLineExclusion({{ $gIdx }}, {{ $lIdx }})"
                                                    class="w-5 h-5 rounded border flex items-center justify-center
                                                           {{ ($line['excluded'] ?? false) ? 'border-gray-300' : 'bg-indigo-600 border-indigo-600' }}">
                                                @if (!($line['excluded'] ?? false))
                                                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                @endif
                                            </button>
                                        </td>
                                        <td class="px-4 py-2 font-medium text-gray-800">{{ $line['ingredient_name'] }}</td>
                                        <td class="px-4 py-2">
                                            <input type="number" step="0.01" min="0.01"
                                                   wire:model.blur="editablePreview.{{ $gIdx }}.lines.{{ $lIdx }}.quantity"
                                                   wire:change="$call('recalcLinePublic', {{ $gIdx }}, {{ $lIdx }})"
                                                   class="w-full text-right rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </td>
                                        <td class="px-4 py-2 text-gray-600">{{ $line['uom'] }}</td>
                                        <td class="px-4 py-2">
                                            <select wire:change="updateLineSupplier({{ $gIdx }}, {{ $lIdx }}, $event.target.value)"
                                                    class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                @foreach ($supplierOptions as $so)
                                                    <option value="{{ $so['id'] }}" {{ $so['id'] == $line['supplier_id'] ? 'selected' : '' }}>
                                                        {{ $so['name'] }}
                                                        @if (isset($costLookup[$line['ingredient_id']][$so['id']]))
                                                            (RM {{ number_format($costLookup[$line['ingredient_id']][$so['id']], 2) }})
                                                        @endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-4 py-2 text-right tabular-nums text-gray-700">
                                            {{ number_format($line['unit_cost'], 4) }}
                                        </td>
                                        <td class="px-4 py-2 text-center">
                                            @if (!empty($line['tax_label']))
                                                <span class="inline-block text-[10px] font-medium px-1.5 py-0.5 rounded
                                                    {{ floatval($line['tax_rate_pct'] ?? 0) > 0 ? 'bg-blue-50 text-blue-600' : 'bg-gray-100 text-gray-500' }}">
                                                    {{ $line['tax_label'] }}
                                                </span>
                                            @else
                                                <span class="text-gray-300 text-xs">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-right tabular-nums font-medium text-gray-700">
                                            {{ number_format($line['total_cost'] ?? 0, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach

            {{-- Summary Footer --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-500">
                        @php
                            $totalGroups = collect($editablePreview)->filter(fn($g) => collect($g['lines'])->where('excluded', false)->isNotEmpty())->count();
                            $totalItems = collect($editablePreview)->flatMap(fn($g) => $g['lines'])->where('excluded', false)->count();
                            $grandSubtotal = collect($editablePreview)->sum('po_total');
                            $grandTax = collect($editablePreview)->sum('tax_total');
                        @endphp
                        <span class="font-medium text-gray-700">{{ $totalGroups }} PO(s)</span> with {{ $totalItems }} items
                    </div>
                    <div class="text-right">
                        <div class="flex gap-6">
                            <div>
                                <p class="text-xs text-gray-400">Subtotal</p>
                                <p class="font-semibold text-gray-800 tabular-nums">RM {{ number_format($grandSubtotal, 2) }}</p>
                            </div>
                            @if ($grandTax > 0)
                            <div>
                                <p class="text-xs text-gray-400">Tax</p>
                                <p class="font-semibold text-gray-800 tabular-nums">RM {{ number_format($grandTax, 2) }}</p>
                            </div>
                            @endif
                            <div>
                                <p class="text-xs text-gray-400">Grand Total</p>
                                <p class="font-bold text-lg text-gray-900 tabular-nums">RM {{ number_format($grandSubtotal + $grandTax, 2) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    @else
    {{-- NORMAL MODE: PR selection + preview sidebar --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: Approved PRs --}}
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-gray-700">Approved Purchase Requests</h3>
                    <div class="flex gap-2">
                        @if ($approvedPrs->count() > 0)
                            <button wire:click="selectAll({{ json_encode($approvedPrs->pluck('id')->toArray()) }})"
                                    class="text-xs text-indigo-600 hover:underline">Select All</button>
                            <button wire:click="deselectAll"
                                    class="text-xs text-gray-400 hover:underline">Clear</button>
                        @endif
                    </div>
                </div>

                @if ($approvedPrs->count() > 0)
                    <div class="space-y-2">
                        @foreach ($approvedPrs as $pr)
                            <div wire:click="togglePr({{ $pr->id }})"
                                 class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition
                                        {{ in_array($pr->id, $selectedPrIds)
                                            ? 'border-indigo-300 bg-indigo-50'
                                            : 'border-gray-100 hover:border-gray-200 hover:bg-gray-50' }}">
                                <div class="pt-0.5">
                                    <div class="w-4 h-4 rounded border flex items-center justify-center
                                                {{ in_array($pr->id, $selectedPrIds) ? 'bg-indigo-600 border-indigo-600' : 'border-gray-300' }}">
                                        @if (in_array($pr->id, $selectedPrIds))
                                            <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-gray-700">{{ $pr->pr_number }}</span>
                                        <span class="text-xs text-gray-400">{{ $pr->requested_date->format('d M Y') }}</span>
                                    </div>
                                    <div class="flex items-center gap-3 mt-1 text-xs text-gray-500">
                                        <span>{{ $pr->outlet?->name }}</span>
                                        <span>{{ $pr->lines->count() }} items</span>
                                        @if ($pr->needed_by_date)
                                            <span class="text-amber-600">Need by {{ $pr->needed_by_date->format('d M') }}</span>
                                        @endif
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach ($pr->lines->take(5) as $line)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] bg-gray-100 text-gray-600">
                                                {{ $line->ingredient?->name }} ({{ number_format($line->quantity, 1) }})
                                            </span>
                                        @endforeach
                                        @if ($pr->lines->count() > 5)
                                            <span class="text-[11px] text-gray-400">+{{ $pr->lines->count() - 5 }} more</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8 text-gray-400 text-sm">
                        No approved purchase requests to consolidate.
                    </div>
                @endif
            </div>
        </div>

        {{-- Right: Preview / Actions --}}
        <div class="space-y-4">
            {{-- CPU Selection --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">CPU</h3>
                <select wire:model="cpuId" class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— Select CPU —</option>
                    @foreach ($cpus as $cpu)
                        <option value="{{ $cpu->id }}">{{ $cpu->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Preview --}}
            @if ($showPreview && count($preview) > 0)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">PO Preview</h3>
                    <p class="text-xs text-gray-400 mb-4">{{ count($preview) }} Purchase Order(s) will be created:</p>

                    <div class="space-y-4">
                        @foreach ($preview as $po)
                            <div class="border border-gray-100 rounded-lg p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-700">{{ $po['supplier_name'] }}</span>
                                    <span class="text-xs font-semibold text-gray-600">RM {{ number_format($po['po_total'] ?? 0, 2) }}</span>
                                </div>
                                <div class="space-y-1">
                                    @foreach ($po['lines'] as $line)
                                        <div class="flex justify-between text-xs text-gray-500">
                                            <span>{{ $line['ingredient_name'] }}</span>
                                            <span class="tabular-nums">{{ number_format($line['quantity'], 2) }} {{ $line['uom'] }} · RM {{ number_format($line['total_cost'] ?? 0, 2) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                                @if ($po['outlet_count'] > 1)
                                    <p class="text-[11px] text-amber-600 mt-2">From {{ $po['outlet_count'] }} outlets</p>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 space-y-2">
                        <button wire:click="enterEditMode"
                                class="w-full px-4 py-2.5 border border-indigo-300 text-indigo-600 text-sm font-medium rounded-lg hover:bg-indigo-50 transition">
                            Customize & Review
                        </button>
                        <button wire:click="consolidate" wire:loading.attr="disabled"
                                class="w-full px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                            <span wire:loading.remove wire:target="consolidate">Create {{ count($preview) }} PO(s)</span>
                            <span wire:loading wire:target="consolidate">Creating...</span>
                        </button>
                    </div>
                </div>
            @endif

            @if ($showPreview && count($preview) === 0)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div class="text-center py-4 text-amber-600 text-sm">
                        No POs to create — ensure PR lines have preferred suppliers assigned.
                    </div>
                </div>
            @endif

            {{-- Selection Summary --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Selection</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Selected PRs</span>
                        <span class="font-medium text-gray-700">{{ count($selectedPrIds) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Available PRs</span>
                        <span class="font-medium text-gray-700">{{ $approvedPrs->count() }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
