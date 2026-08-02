<div>
    {{-- Range picker as segmented control: three taps, no dropdown. --}}
    <div class="seg mb-3 grid grid-cols-3" role="tablist">
        @foreach ([1 => 'Today', 3 => '3 days', 7 => 'Week'] as $value => $label)
            <button wire:click="setDays({{ $value }})"
                    role="tab" aria-selected="{{ $days === $value ? 'true' : 'false' }}"
                    class="seg-item flex min-h-[2.75rem] items-center justify-center
                           {{ $days === $value ? 'seg-item-on' : '' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Restacks the same runs: newest first, or under the set they came from. --}}
    <div class="seg mb-3 grid grid-cols-2" role="tablist">
        @foreach (['date' => 'By time', 'set' => 'By set'] as $mode => $label)
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
                    <p class="text-sm font-semibold text-gray-900">{{ $group['name'] }}</p>
                    <span class="text-xs tabular-nums text-gray-600">
                        {{ $group['rows']->count() }} run{{ $group['rows']->count() === 1 ? '' : 's' }}
                        · {{ $group['labels'] }} label{{ $group['labels'] === 1 ? '' : 's' }}
                    </span>
                </div>

                <div class="space-y-2">
                    @foreach ($group['rows'] as $batch)
                        @include('livewire.labels.staff.partials.log-batch', [
                            'batch' => $batch, 'showSet' => false,
                        ])
                    @endforeach
                </div>
            </div>
        @empty
            <div class="text-center py-14">
                <p class="text-sm text-gray-600">Nothing printed in this period.</p>
            </div>
        @endforelse
    @else

    <div class="space-y-2">
        @forelse ($batches as $batch)
            @include('livewire.labels.staff.partials.log-batch', ['batch' => $batch, 'showSet' => true])
        @empty
            <div class="text-center py-14">
                <p class="text-sm text-gray-600">Nothing printed in this period.</p>
            </div>
        @endforelse
    </div>
    @endif

    <p class="mt-4 text-center text-[11px] text-gray-600">
        Shows what was printed at the time, not what the item says now.
    </p>
</div>
