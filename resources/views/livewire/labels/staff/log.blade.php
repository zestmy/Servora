<div>
    {{-- Range picker as segmented control: three taps, no dropdown. --}}
    <div class="flex gap-1 p-1 bg-gray-100 rounded-xl mb-3">
        @foreach ([1 => 'Today', 3 => '3 days', 7 => 'Week'] as $value => $label)
            <button wire:click="setDays({{ $value }})"
                    class="flex-1 py-2 text-sm rounded-lg {{ $days === $value ? 'bg-white shadow-sm font-semibold text-gray-800' : 'text-gray-500' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Restacks the same runs: newest first, or under the set they came from. --}}
    <div class="grid grid-cols-2 gap-1 p-1 mb-3 bg-gray-100 rounded-xl">
        @foreach (['date' => 'By time', 'set' => 'By set'] as $mode => $label)
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
                    <p class="text-sm font-semibold text-gray-700">{{ $group['name'] }}</p>
                    <span class="text-xs text-gray-400">
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
                <p class="text-sm text-gray-400">Nothing printed in this period.</p>
            </div>
        @endforelse
    @else

    <div class="space-y-2">
        @forelse ($batches as $batch)
            @include('livewire.labels.staff.partials.log-batch', ['batch' => $batch, 'showSet' => true])
        @empty
            <div class="text-center py-14">
                <p class="text-sm text-gray-400">Nothing printed in this period.</p>
            </div>
        @endforelse
    </div>
    @endif

    <p class="text-center text-[11px] text-gray-400 mt-4">
        Shows what was printed at the time, not what the item says now.
    </p>
</div>
