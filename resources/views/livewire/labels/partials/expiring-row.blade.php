{{-- One expiring label. Shared by both groupings so the two views can never
     drift apart on what a row offers. $showSet is off inside a set section,
     where repeating the heading on every row is noise; $showDue is off inside
     an urgency bucket, where the bucket already says when. --}}
@php
    $showSet ??= true;
    $showDue ??= false;
    $now     ??= now();
    $overdue   = $print->end_at && $print->end_at->lt($now);
@endphp

<div class="px-4 py-3 flex flex-wrap items-center gap-3" wire:key="exp-{{ $print->id }}">
    <div class="flex-1 min-w-[180px]">
        <div class="flex flex-wrap items-center gap-2">
            <p class="text-sm font-medium text-gray-700">{{ $print->printedName() }}</p>

            @if ($showSet)
                {{-- Where it was printed, so you know which station to walk to. --}}
                <span class="px-2 py-0.5 rounded-full text-[11px] font-medium
                             {{ $print->batch?->labelSet
                                ? 'bg-brand-50 text-brand-700'
                                : 'bg-gray-100 text-gray-500' }}">
                    {{ $print->batch?->labelSet?->name ?? 'No set' }}
                </span>
            @endif

            @if ($showDue && $overdue)
                <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-danger-50 text-danger-700">Expired</span>
            @endif
        </div>

        <p class="text-xs text-gray-600 mt-0.5">
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
                class="btn-secondary btn-sm">
            Used
        </button>

        @if ($costable[$print->id] ?? null)
            <button wire:click="openWaste({{ $print->id }})"
                    class="btn-danger btn-sm">
                Wasted
            </button>
        @else
            <button wire:click="markDiscarded({{ $print->id }})"
                    wire:confirm="Discard without costing? This item has nothing priceable behind it."
                    class="btn-secondary btn-sm"
                    title="No linked item to cost against">
                Discard
            </button>
        @endif
    </div>
</div>
