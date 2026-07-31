<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-3 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    {{-- Restacks the same rows: by when they go off, or by the station they
         were printed for. Nothing is hidden either way. --}}
    <div class="grid grid-cols-2 gap-1 p-1 mb-4 bg-gray-100 rounded-xl">
        @foreach (['urgency' => 'By time', 'set' => 'By set'] as $mode => $label)
            <button type="button" wire:click="$set('groupBy', '{{ $mode }}')"
                    class="py-2 rounded-lg text-sm font-medium
                           {{ $groupBy === $mode ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($groupBy === 'set')
        @forelse ($groups as $group)
            <div class="mb-4" wire:key="grp-{{ $group['key'] }}">
                <div class="flex items-center gap-2 px-1 mb-2">
                    <span class="w-2 h-2 rounded-full {{ $group['expired'] ? 'bg-red-500' : 'bg-gray-300' }}"></span>
                    <p class="text-sm font-semibold text-gray-700">{{ $group['name'] }}</p>
                    <span class="text-xs text-gray-400">{{ $group['rows']->count() }}</span>
                    @if ($group['expired'])
                        <span class="text-xs text-red-600">· {{ $group['expired'] }} expired</span>
                    @endif
                </div>

                <div class="space-y-2">
                    @foreach ($group['rows'] as $print)
                        @include('livewire.labels.staff.partials.expiring-card', [
                            'print' => $print, 'showSet' => false, 'showDue' => true, 'now' => $now,
                        ])
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-center text-sm text-gray-400 py-10">Nothing expiring.</p>
        @endforelse
    @else
        @php
            $buckets = [
                ['label' => 'Expired',  'rows' => $expired,  'tone' => 'red'],
                ['label' => 'Today',    'rows' => $today,    'tone' => 'amber'],
                ['label' => 'Tomorrow', 'rows' => $tomorrow, 'tone' => 'gray'],
            ];
        @endphp

        @foreach ($buckets as $b)
            <div class="mb-4">
                <div class="flex items-center gap-2 px-1 mb-2">
                    <span class="w-2 h-2 rounded-full
                        {{ $b['tone'] === 'red' ? 'bg-red-500' : ($b['tone'] === 'amber' ? 'bg-amber-500' : 'bg-gray-300') }}"></span>
                    <p class="text-sm font-semibold text-gray-700">{{ $b['label'] }}</p>
                    <span class="text-xs text-gray-400">{{ $b['rows']->count() }}</span>
                </div>

                <div class="space-y-2">
                    @forelse ($b['rows'] as $print)
                        @include('livewire.labels.staff.partials.expiring-card', [
                            'print' => $print, 'showSet' => true, 'showDue' => false, 'now' => $now,
                        ])
                    @empty
                        <p class="text-center text-xs text-gray-400 py-4">Nothing here.</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    @endif

    {{-- Quantity sheet. A label carries no quantity, so wastage can't be
         costed without asking — inventing one corrupts the cost report. --}}
    <div x-data="{ open: @entangle('wastingId').live }">
        <template x-teleport="body">
            <div x-show="open" x-cloak class="fixed inset-0 z-50">
                <div class="absolute inset-0 bg-black/50" @click="$wire.closeWaste()"></div>

                {{-- Bottom sheet: reachable by thumb, familiar on mobile. --}}
                <div class="absolute bottom-0 inset-x-0 bg-white rounded-t-2xl p-5 safe-bottom max-w-2xl mx-auto"
                     @click.stop>
                    <div class="w-10 h-1 bg-gray-300 rounded-full mx-auto mb-4"></div>

                    @if ($wasting)
                        @php $c = $costable[$wasting->id] ?? null; @endphp
                        <p class="text-base font-semibold text-gray-800">{{ $wasting->printedName() }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">How much are you throwing away?</p>

                        <div class="mt-4">
                            <label class="text-xs font-medium text-gray-600">
                                Quantity {{ $c && $c['uom_abbr'] ? '(' . $c['uom_abbr'] . ')' : '' }}
                            </label>
                            <input type="number" step="0.001" min="0" inputmode="decimal"
                                   wire:model="wasteQuantity"
                                   class="mt-1 w-full rounded-xl border-gray-300 text-lg py-3">
                            @error('wasteQuantity')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <button wire:click="closeWaste"
                                    class="py-3 rounded-xl border border-gray-200 text-sm text-gray-600">Cancel</button>
                            <button wire:click="confirmWaste"
                                    class="py-3 rounded-xl bg-red-600 text-white text-sm font-semibold active:bg-red-700">
                                Record wastage
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </template>
    </div>
</div>
