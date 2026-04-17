{{-- Picker trigger button.
     A single shared Alpine picker at the bottom of smart-import.blade.php
     handles the actual search UI — one per-line instance was too heavy for
     large imports (hundreds of Alpine components + hundreds of teleported
     popups in <body> caused noticeable lag on every morph). --}}
<button type="button"
        @click.stop="$dispatch('open-ingredient-picker', {
            rIdx: {{ $rIdx }},
            lIdx: {{ $lIdx }},
            currentName: @js($currentName),
            rawName: @js($rawName),
            triggerEl: $event.currentTarget,
        })"
        class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] rounded border border-gray-200 bg-white hover:bg-indigo-50 hover:border-indigo-300 transition">
    <span>{{ $currentName ? 'Change' : 'Select ingredient…' }}</span>
    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
    </svg>
</button>
