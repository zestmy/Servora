{{-- One print run. Shared by both groupings so the expanded detail can't
     differ between them. $showSet is off inside a set section, where the
     heading already says which set this is. --}}
@php $showSet ??= true; @endphp

<div class="card overflow-hidden" wire:key="batch-{{ $batch->id }}">
    <button type="button" wire:click="toggle({{ $batch->id }})"
            aria-expanded="{{ $expandedId === $batch->id ? 'true' : 'false' }}"
            class="flex min-h-[3.5rem] w-full items-center gap-3 px-4 py-3 text-left active:bg-gray-50">
        <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium text-gray-900">
                @if ($showSet)
                    {{ $batch->labelSet?->name ?? 'Ad-hoc' }}
                @else
                    {{ $batch->printed_at->format('D d/m H:i') }}
                @endif
            </span>
            <span class="mt-0.5 block text-xs text-gray-600">
                @if ($showSet)
                    {{ $batch->printed_at->format('D d/m H:i') }}
                    ·
                @endif
                {{ $batch->label_count }} label{{ $batch->label_count === 1 ? '' : 's' }}
                @if ($batch->employee) · {{ $batch->employee->name }} @endif
            </span>
        </span>
        <svg class="w-4 h-4 shrink-0 text-gray-500 transition-transform {{ $expandedId === $batch->id ? 'rotate-180' : '' }}"
             fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    @if ($expandedId === $batch->id && $expanded)
        <div class="border-t border-gray-100 divide-y divide-gray-50 bg-gray-50/60">
            @foreach ($expanded->prints as $print)
                <div class="px-4 py-2.5" wire:key="print-{{ $print->id }}">
                    <div class="flex items-start justify-between gap-2">
                        <p class="flex-1 text-sm text-gray-900">{{ $print->printedName() }}</p>
                        @php
                            $tone = match ($print->status) {
                                'done'             => 'badge-success',
                                'error', 'expired' => 'badge-danger',
                                'queued'           => 'badge-warning',
                                default            => 'badge-neutral',
                            };
                        @endphp
                        <span class="{{ $tone }} shrink-0">{{ $print->statusLabel() }}</span>
                    </div>
                    <p class="mt-0.5 text-xs text-gray-600">
                        @if ($print->end_at)
                            Use by {{ $print->end_at->format('D d/m H:i') }}
                        @else
                            No use-by
                        @endif
                        @if ($print->copies > 1) · &times;{{ $print->copies }} @endif
                        @if ($print->resolution)
                            · {{ \App\Models\LabelPrint::RESOLUTIONS[$print->resolution] ?? $print->resolution }}
                        @endif
                    </p>

                    {{-- Only where it still means something. A resolved line
                         has already been used or binned, so time-remaining is
                         history — the resolution above is the outcome. --}}
                    @if (! $print->resolution && $print->end_at)
                        <x-meter :start="$print->start_at" :end="$print->end_at" :bar="false" class="mt-1" />
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
