<div>
    <p class="mb-3 px-1 text-sm text-gray-600">
        Tap a set to review and print it. Sets are set up by your manager.
    </p>

    <div class="space-y-2">
        @forelse ($sets as $set)
            <a href="{{ route('labels.staff.sets.print', $set) }}" wire:navigate
               wire:key="set-{{ $set->id }}"
               class="card flex items-center gap-3 px-4 py-4 active:bg-brand-50">
                <span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control bg-brand-50 text-brand-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-base font-medium text-gray-900">{{ $set->name }}</span>
                    <span class="block text-xs text-gray-600">
                        {{ $set->lines_count }} item{{ $set->lines_count === 1 ? '' : 's' }}
                        @if ($set->description) · {{ $set->description }} @endif
                    </span>
                </span>
                <svg class="w-5 h-5 shrink-0 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        @empty
            <div class="py-14 text-center">
                <p class="text-sm font-medium text-gray-900">No print sets yet</p>
                <p class="mt-1 text-sm text-gray-600">Your manager can create them in Servora.</p>
            </div>
        @endforelse
    </div>
</div>
