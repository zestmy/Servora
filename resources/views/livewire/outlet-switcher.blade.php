<div>
    @if ($hidden ?? false)
        <span class="text-xs text-gray-400 truncate">All Outlets</span>
    @elseif ($outlets->count() > 1 || $canViewAll)
        <select wire:model="activeOutletId" wire:change="switchOutlet"
                class="w-full text-xs bg-gray-800 text-gray-300 border-gray-600 rounded-md
                       focus:border-indigo-500 focus:ring-indigo-500 py-1.5 pl-2 pr-7 truncate">
            @if ($canViewAll)
                <option value="">All Outlets</option>
            @endif
            @foreach ($outlets as $outlet)
                <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
            @endforeach
        </select>
    @else
        <span class="text-xs text-gray-400 truncate">
            {{ $activeOutlet?->name ?? ($outlets->first()?->name ?? 'No branch') }}
        </span>
    @endif
</div>
