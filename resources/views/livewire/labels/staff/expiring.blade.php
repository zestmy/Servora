<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="alert-success mb-3">
            {{ session('success') }}
        </div>
    @endif

    {{-- Restacks the same rows: by when they go off, or by the station they
         were printed for. Nothing is hidden either way. --}}
    <div class="seg mb-4 grid grid-cols-2" role="tablist">
        @foreach (['urgency' => 'By time', 'set' => 'By set'] as $mode => $label)
            <button type="button" wire:click="$set('groupBy', '{{ $mode }}')"
                    role="tab" aria-selected="{{ $groupBy === $mode ? 'true' : 'false' }}"
                    class="seg-item flex min-h-[2.75rem] items-center justify-center
                           {{ $groupBy === $mode ? 'seg-item-on' : '' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($groupBy === 'set')
        @forelse ($groups as $group)
            <div class="mb-4" wire:key="grp-{{ $group['key'] }}">
                <div class="flex items-center gap-2 px-1 mb-2">
                    <span aria-hidden="true" class="w-2 h-2 rounded-full {{ $group['expired'] ? 'bg-danger-600' : 'bg-gray-300' }}"></span>
                    <p class="text-sm font-semibold text-gray-900">{{ $group['name'] }}</p>
                    <span class="text-xs tabular-nums text-gray-600">{{ $group['rows']->count() }}</span>
                    @if ($group['expired'])
                        <span class="text-xs font-medium text-danger-700">· {{ $group['expired'] }} expired</span>
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
            <div class="py-12 text-center">
                <p class="text-sm font-medium text-gray-900">Nothing expiring</p>
                <p class="mt-1 text-sm text-gray-600">Everything printed for this outlet is still in date.</p>
            </div>
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
                    <span aria-hidden="true" class="w-2 h-2 rounded-full
                        {{ $b['tone'] === 'red' ? 'bg-danger-600' : ($b['tone'] === 'amber' ? 'bg-warning-600' : 'bg-gray-300') }}"></span>
                    <p class="text-sm font-semibold text-gray-900">{{ $b['label'] }}</p>
                    <span class="text-xs tabular-nums text-gray-600">{{ $b['rows']->count() }}</span>
                </div>

                <div class="space-y-2">
                    @forelse ($b['rows'] as $print)
                        @include('livewire.labels.staff.partials.expiring-card', [
                            'print' => $print, 'showSet' => true, 'showDue' => false, 'now' => $now,
                        ])
                    @empty
                        <p class="py-4 text-center text-xs text-gray-600">Nothing here.</p>
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
                        <p class="text-base font-semibold text-gray-900">{{ $wasting->printedName() }}</p>
                        <p class="mt-0.5 text-sm text-gray-600">How much are you throwing away?</p>

                        <div class="field mt-4">
                            <label class="label" for="waste-qty">
                                Quantity {{ $c && $c['uom_abbr'] ? '(' . $c['uom_abbr'] . ')' : '' }}
                            </label>
                            <input id="waste-qty" type="number" step="0.001" min="0" inputmode="decimal"
                                   wire:model="wasteQuantity"
                                   class="input py-3 text-lg @error('wasteQuantity') input-error @enderror">
                            @error('wasteQuantity')
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <button wire:click="closeWaste" class="btn-secondary min-h-[2.75rem]">Cancel</button>
                            <button wire:click="confirmWaste" class="btn-danger min-h-[2.75rem]">
                                Record wastage
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </template>
    </div>
</div>
