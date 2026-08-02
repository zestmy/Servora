<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3500)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="page-eyebrow">Labels / Expiring</p>
            <h1 class="page-title mt-1">Expiring</h1>
        </div>
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Outlet</label>
                <select wire:model.live="outletId" class="rounded-lg border-gray-300 text-sm">
                    <option value="">All outlets</option>
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Print set</label>
                <select wire:model.live="setFilter" class="rounded-lg border-gray-300 text-sm">
                    <option value="">All sets</option>
                    @foreach ($sets as $set)
                        <option value="{{ $set->id }}">{{ $set->name }}</option>
                    @endforeach
                    {{-- Ad-hoc prints belong to no set, and they are exactly the
                         ones most likely to be forgotten. --}}
                    <option value="none">Not from a set</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Group by</label>
                <div class="seg">
                    @foreach (['urgency' => 'Urgency', 'set' => 'Print set'] as $mode => $label)
                        <button type="button" wire:click="$set('groupBy', '{{ $mode }}')"
                                class="seg-item {{ $groupBy === $mode ? 'seg-item-on' : '' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="card p-5 mb-4">
        <p class="text-sm text-gray-600">
            Everything labelled and not yet accounted for, soonest first. Close each one off as you deal with it.
            Group by <strong>Print set</strong> to walk one station at a time — the set shown is the run the label
            was printed on, so anything printed ad-hoc appears under <em>Not from a set</em>.
        </p>
        <p class="text-xs text-gray-500 mt-2">
            <strong>Used</strong> means consumed as normal. <strong>Wasted</strong> bins it and writes a costed line
            into today's wastage record for the outlet. <strong>Discarded</strong> is for items with nothing priceable
            behind them — they're binned but not costed, so the wastage figures stay honest.
        </p>
    </div>

    @if ($groupBy === 'set')
        {{-- By print set: the station you'd walk to. Ordered by whichever set
             holds the most urgent item, so an expired one can't hide behind a
             quiet station. --}}
        <div class="space-y-4">
            @forelse ($groups as $group)
                <div class="card overflow-hidden"
                     wire:key="grp-{{ $group['key'] }}">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100
                                {{ $group['expired'] ? 'bg-danger-50' : 'bg-gray-50' }}">
                        <div>
                            <h3 class="text-sm font-semibold {{ $group['expired'] ? 'text-danger-700' : 'text-gray-700' }}">
                                {{ $group['name'] }} ({{ $group['rows']->count() }})
                            </h3>
                            <p class="text-xs text-gray-500">
                                @if ($group['expired'])
                                    {{ $group['expired'] }} already past use-by.
                                @else
                                    Nothing expired yet.
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="divide-y divide-gray-50">
                        @foreach ($group['rows'] as $print)
                            @include('livewire.labels.partials.expiring-row', [
                                'print' => $print, 'showSet' => false, 'showDue' => true, 'now' => $now,
                            ])
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="card px-4 py-10 text-center text-gray-600 text-sm">
                    Nothing expiring.
                </div>
            @endforelse
        </div>
    @else
        @php
            $buckets = [
                ['label' => 'Expired',   'rows' => $expired,  'tone' => 'red',   'note' => 'Past its use-by.'],
                ['label' => 'Today',     'rows' => $today,    'tone' => 'amber', 'note' => 'Use before end of day.'],
                ['label' => 'Tomorrow',  'rows' => $tomorrow, 'tone' => 'gray',  'note' => 'Coming up.'],
            ];
        @endphp

        <div class="space-y-4">
            @foreach ($buckets as $b)
                <div class="card overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100
                                {{ $b['tone'] === 'red' ? 'bg-danger-50' : ($b['tone'] === 'amber' ? 'bg-warning-50' : 'bg-gray-50') }}">
                        <div>
                            <h3 class="text-sm font-semibold
                                       {{ $b['tone'] === 'red' ? 'text-danger-700' : ($b['tone'] === 'amber' ? 'text-warning-700' : 'text-gray-700') }}">
                                {{ $b['label'] }} ({{ $b['rows']->count() }})
                            </h3>
                            <p class="text-xs text-gray-500">{{ $b['note'] }}</p>
                        </div>
                    </div>

                    <div class="divide-y divide-gray-50">
                        @forelse ($b['rows'] as $print)
                            @include('livewire.labels.partials.expiring-row', [
                                'print' => $print, 'showSet' => true, 'showDue' => false, 'now' => $now,
                            ])
                        @empty
                            <p class="px-4 py-6 text-center text-gray-600 text-sm">Nothing here.</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @endif

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
                                        <p class="mt-1 text-xs text-gray-600">
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
                            <button wire:click="closeWaste" class="btn-secondary">Cancel</button>
                            <button wire:click="confirmWaste" class="btn-danger">Record wastage</button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
