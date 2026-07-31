<div>
    <p class="text-xs text-gray-500 mb-3 px-1">
        Tap a set to review and print it. Sets are set up by your manager.
    </p>

    <div class="space-y-2">
        @forelse ($sets as $set)
            <a href="{{ route('labels.staff.sets.print', $set) }}" wire:navigate
               wire:key="set-{{ $set->id }}"
               class="flex items-center gap-3 bg-white rounded-xl border border-gray-200 px-4 py-4 active:bg-indigo-50">
                <span class="w-10 h-10 shrink-0 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                </span>
                <span class="flex-1 min-w-0">
                    <span class="block text-base font-medium text-gray-800 truncate">{{ $set->name }}</span>
                    <span class="block text-xs text-gray-400">
                        {{ $set->lines_count }} item{{ $set->lines_count === 1 ? '' : 's' }}
                        @if ($set->description) · {{ $set->description }} @endif
                    </span>
                </span>
                <svg class="w-5 h-5 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        @empty
            <div class="text-center py-14">
                <p class="text-sm text-gray-400">No print sets for this outlet yet.</p>
                <p class="text-xs text-gray-400 mt-1">Your manager can create them in Servora.</p>
            </div>
        @endforelse
    </div>
</div>
