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

    <div class="space-y-2">
        @forelse ($batches as $batch)
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden" wire:key="batch-{{ $batch->id }}">
                <button type="button" wire:click="toggle({{ $batch->id }})"
                        class="w-full text-left px-4 py-3 active:bg-gray-50 flex items-center gap-3">
                    <span class="flex-1 min-w-0">
                        <span class="block text-sm font-medium text-gray-800 truncate">
                            {{ $batch->labelSet?->name ?? 'Ad-hoc' }}
                        </span>
                        <span class="block text-xs text-gray-400 mt-0.5">
                            {{ $batch->printed_at->format('d/m H:i') }}
                            · {{ $batch->label_count }} label{{ $batch->label_count === 1 ? '' : 's' }}
                            @if ($batch->employee) · {{ $batch->employee->name }} @endif
                        </span>
                    </span>
                    <svg class="w-4 h-4 text-gray-300 shrink-0 transition-transform {{ $expandedId === $batch->id ? 'rotate-180' : '' }}"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                @if ($expandedId === $batch->id && $expanded)
                    <div class="border-t border-gray-100 divide-y divide-gray-50 bg-gray-50/60">
                        @foreach ($expanded->prints as $print)
                            <div class="px-4 py-2.5" wire:key="print-{{ $print->id }}">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-sm text-gray-800 flex-1">{{ $print->printedName() }}</p>
                                    @php
                                        $tone = match ($print->status) {
                                            'done'             => 'bg-green-50 text-green-700',
                                            'error', 'expired' => 'bg-red-50 text-red-700',
                                            'queued'           => 'bg-amber-50 text-amber-700',
                                            default            => 'bg-gray-100 text-gray-500',
                                        };
                                    @endphp
                                    <span class="px-1.5 py-0.5 rounded text-[10px] shrink-0 {{ $tone }}">
                                        {{ $print->statusLabel() }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    @if ($print->end_at)
                                        Use by {{ $print->end_at->format('d/m H:i') }}
                                    @else
                                        No use-by
                                    @endif
                                    @if ($print->copies > 1) · &times;{{ $print->copies }} @endif
                                    @if ($print->resolution)
                                        · {{ \App\Models\LabelPrint::RESOLUTIONS[$print->resolution] ?? $print->resolution }}
                                    @endif
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <div class="text-center py-14">
                <p class="text-sm text-gray-400">Nothing printed in this period.</p>
            </div>
        @endforelse
    </div>

    <p class="text-center text-[11px] text-gray-400 mt-4">
        Shows what was printed at the time, not what the item says now.
    </p>
</div>
