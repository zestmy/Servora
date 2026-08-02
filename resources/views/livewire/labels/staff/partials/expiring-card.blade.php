{{-- One expiring label, phone-sized. Shared by both groupings so the buttons
     can never differ between the two views. --}}
@php
    $showSet ??= true;
    $showDue ??= false;
    $now     ??= now();
    $overdue   = $print->end_at && $print->end_at->lt($now);
@endphp

<div class="card p-3.5 {{ $overdue ? 'border-danger-200' : '' }}" wire:key="exp-{{ $print->id }}">
    <div class="flex items-start justify-between gap-2">
        <p class="flex-1 text-[15px] font-medium leading-snug text-gray-900">{{ $print->printedName() }}</p>

        @if ($showSet)
            {{-- Which station it came off, so nobody has to guess where to look. --}}
            <span class="shrink-0 {{ $print->batch?->labelSet ? 'badge-brand' : 'badge-neutral' }}">
                {{ $print->batch?->labelSet?->name ?? 'No set' }}
            </span>
        @endif
    </div>

    <p class="mt-0.5 text-xs text-gray-600">
        {{ \App\Models\ShelfLifeRule::stateLabel($print->storage_state) }}
        @if ($print->copies > 1) · {{ $print->copies }} labels @endif
    </p>

    {{-- The meter replaces the old "Expired" pill: it carries the same state
         in the same place for every row, expired or not, instead of a badge
         that only appears in one of the two groupings. The absolute date
         stays beside it — the meter answers "how urgent", the date answers
         "which batch is this". --}}
    <x-meter :start="$print->start_at" :end="$print->end_at" :time="false" class="mt-3" />

    <div class="mt-1.5 flex items-baseline justify-between gap-2">
        <x-meter :start="$print->start_at" :end="$print->end_at" :bar="false" />
        <p class="text-xs tabular-nums text-gray-600">
            {{ $overdue ? 'Was due' : 'Use by' }} {{ $print->end_at->format('d/m H:i') }}
        </p>
    </div>

    <div class="mt-3 grid grid-cols-2 gap-2">
        <button wire:click="markUsed({{ $print->id }})" class="btn-secondary min-h-[2.75rem]">
            Used
        </button>

        @if ($costable[$print->id] ?? null)
            <button wire:click="openWaste({{ $print->id }})" class="btn-danger min-h-[2.75rem]">
                Wasted
            </button>
        @else
            <button wire:click="markDiscarded({{ $print->id }})" class="btn-secondary min-h-[2.75rem]">
                Discard
            </button>
        @endif
    </div>
</div>
