{{-- Outlet selector (multi-outlet users) / read-only indicator (single outlet).
     Expects from the parent view: $canChooseOutlet, $outletOptions,
     $selectedOutletName, and the component's public $recordId. --}}
<div>
    <x-input-label value="Outlet *" />
    @if ($canChooseOutlet)
        <select wire:model="outlet_id"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            @foreach ($outletOptions as $o)
                <option value="{{ $o->id }}">{{ $o->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('outlet_id')" class="mt-1" />
    @else
        <div class="mt-1 flex items-center gap-2 px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-sm text-gray-700">
            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span class="font-medium">{{ $selectedOutletName ?? '—' }}</span>
            @unless ($recordId)
                <span class="text-xs text-gray-400">(your outlet)</span>
            @endunless
        </div>
    @endif
</div>
