<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3500)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="text-xs text-gray-400">Labels / Expiring</p>
            <h2 class="text-lg font-semibold text-gray-700 mt-1">Expiring</h2>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Outlet</label>
            <select wire:model.live="outletId" class="rounded-lg border-gray-300 text-sm">
                <option value="">All outlets</option>
                @foreach ($outlets as $outlet)
                    <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4">
        <p class="text-sm text-gray-600">
            Everything labelled and not yet accounted for, soonest first. Close each one off as you deal with it.
        </p>
        <p class="text-xs text-gray-500 mt-2">
            <strong>Used</strong> means consumed as normal. <strong>Wasted</strong> bins it and writes a costed line
            into today's wastage record for the outlet. <strong>Discarded</strong> is for items with nothing priceable
            behind them — they're binned but not costed, so the wastage figures stay honest.
        </p>
    </div>

    @php
        $buckets = [
            ['label' => 'Expired',   'rows' => $expired,  'tone' => 'red',   'note' => 'Past its use-by.'],
            ['label' => 'Today',     'rows' => $today,    'tone' => 'amber', 'note' => 'Use before end of day.'],
            ['label' => 'Tomorrow',  'rows' => $tomorrow, 'tone' => 'gray',  'note' => 'Coming up.'],
        ];
    @endphp

    <div class="space-y-4">
        @foreach ($buckets as $b)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100
                            {{ $b['tone'] === 'red' ? 'bg-red-50' : ($b['tone'] === 'amber' ? 'bg-amber-50' : 'bg-gray-50') }}">
                    <div>
                        <h3 class="text-sm font-semibold
                                   {{ $b['tone'] === 'red' ? 'text-red-700' : ($b['tone'] === 'amber' ? 'text-amber-700' : 'text-gray-700') }}">
                            {{ $b['label'] }} ({{ $b['rows']->count() }})
                        </h3>
                        <p class="text-xs text-gray-500">{{ $b['note'] }}</p>
                    </div>
                </div>

                <div class="divide-y divide-gray-50">
                    @forelse ($b['rows'] as $print)
                        <div class="px-4 py-3 flex flex-wrap items-center gap-3" wire:key="exp-{{ $print->id }}">
                            <div class="flex-1 min-w-[180px]">
                                <p class="text-sm font-medium text-gray-700">{{ $print->printedName() }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    Use by {{ $print->end_at->format('d/m/Y H:i') }}
                                    · {{ \App\Models\ShelfLifeRule::stateLabel($print->storage_state) }}
                                    @if ($print->copies > 1)
                                        · {{ $print->copies }} labels
                                    @endif
                                    @if ($print->batch?->employee)
                                        · {{ $print->batch->employee->name }}
                                    @endif
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <button wire:click="markUsed({{ $print->id }})"
                                        class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">
                                    Used
                                </button>

                                @if ($costable[$print->id] ?? null)
                                    <button wire:click="openWaste({{ $print->id }})"
                                            class="px-3 py-1.5 text-xs text-white bg-red-600 rounded-lg hover:bg-red-700">
                                        Wasted
                                    </button>
                                @else
                                    <button wire:click="markDiscarded({{ $print->id }})"
                                            wire:confirm="Discard without costing? This item has nothing priceable behind it."
                                            class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50"
                                            title="No linked item to cost against">
                                        Discard
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="px-4 py-6 text-center text-gray-400 text-sm">Nothing here.</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    {{-- Quantity prompt. A label carries no quantity, so wastage can't be
         costed without asking — inventing one would corrupt the cost report. --}}
    <div x-data="{ open: @entangle('wastingId').live }">
        <template x-teleport="body">
            <div x-show="open" x-cloak @keydown.escape.window="$wire.closeWaste()"
                 class="fixed inset-0 z-[100] overflow-y-auto">
                <div class="fixed inset-0 bg-black/50" @click="$wire.closeWaste()"></div>
                <div class="relative min-h-full flex items-start sm:items-center justify-center p-4">
                    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-sm" @click.stop>
                        <div class="px-5 py-3 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-800">Record wastage</h3>
                        </div>
                        <div class="p-5 space-y-3">
                            @if ($wasting)
                                <p class="text-sm text-gray-700 font-medium">{{ $wasting->printedName() }}</p>
                                @php $c = $costable[$wasting->id] ?? null; @endphp
                                <div>
                                    <label class="text-xs font-semibold text-gray-600">
                                        Quantity {{ $c && $c['uom_abbr'] ? '(' . $c['uom_abbr'] . ')' : '' }}
                                    </label>
                                    <input type="number" step="0.001" min="0" wire:model="wasteQuantity"
                                           class="mt-1 w-full text-sm rounded-lg border-gray-300">
                                    <x-input-error :messages="$errors->get('wasteQuantity')" class="mt-1" />
                                    @if ($c)
                                        <p class="mt-1 text-xs text-gray-400">
                                            At {{ number_format($c['unit_cost'], 4) }} per {{ $c['uom_abbr'] ?: 'unit' }}.
                                        </p>
                                    @endif
                                </div>
                                <p class="text-xs text-gray-500">
                                    Adds a line to today's wastage record for this outlet.
                                </p>
                            @endif
                        </div>
                        <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-gray-100">
                            <button wire:click="closeWaste" class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                            <button wire:click="confirmWaste" class="px-4 py-2 text-sm text-white bg-red-600 rounded-lg hover:bg-red-700">Record wastage</button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
