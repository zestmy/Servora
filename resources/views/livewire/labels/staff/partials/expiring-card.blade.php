{{-- One expiring label, phone-sized. Shared by both groupings so the buttons
     can never differ between the two views. --}}
@php
    $showSet ??= true;
    $showDue ??= false;
    $now     ??= now();
    $overdue   = $print->end_at && $print->end_at->lt($now);
@endphp

<div class="bg-white rounded-xl border border-gray-200 p-3" wire:key="exp-{{ $print->id }}">
    <div class="flex items-start justify-between gap-2">
        <p class="text-sm font-medium text-gray-800">{{ $print->printedName() }}</p>

        @if ($showDue && $overdue)
            <span class="shrink-0 px-2 py-0.5 rounded-full text-[11px] font-medium bg-red-50 text-red-700">
                Expired
            </span>
        @endif
    </div>

    <p class="text-xs text-gray-400 mt-0.5">
        Use by {{ $print->end_at->format('d/m H:i') }}
        · {{ \App\Models\ShelfLifeRule::stateLabel($print->storage_state) }}
        @if ($print->copies > 1) · {{ $print->copies }} labels @endif
    </p>

    @if ($showSet)
        {{-- Which station it came off, so nobody has to guess where to look. --}}
        <span class="inline-block mt-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium
                     {{ $print->batch?->labelSet
                        ? 'bg-indigo-50 text-indigo-700'
                        : 'bg-gray-100 text-gray-500' }}">
            {{ $print->batch?->labelSet?->name ?? 'No set' }}
        </span>
    @endif

    <div class="mt-2.5 grid grid-cols-2 gap-2">
        <button wire:click="markUsed({{ $print->id }})"
                class="py-2.5 rounded-lg border border-gray-200 text-sm text-gray-700 active:bg-gray-50">
            Used
        </button>

        @if ($costable[$print->id] ?? null)
            <button wire:click="openWaste({{ $print->id }})"
                    class="py-2.5 rounded-lg bg-red-600 text-white text-sm font-medium active:bg-red-700">
                Wasted
            </button>
        @else
            <button wire:click="markDiscarded({{ $print->id }})"
                    class="py-2.5 rounded-lg border border-gray-200 text-sm text-gray-700 active:bg-gray-50">
                Discard
            </button>
        @endif
    </div>
</div>
