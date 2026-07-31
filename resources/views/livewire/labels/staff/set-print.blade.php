<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-3 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    @error('print')
        <div class="mb-3 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl">{{ $message }}</div>
    @enderror

    <div class="flex items-center justify-between mb-2 px-1">
        <a href="{{ route('labels.staff.sets') }}" wire:navigate class="text-xs text-gray-400 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            All sets
        </a>
        <div class="flex gap-3 text-xs">
            <button wire:click="selectAll(true)" class="text-indigo-600">All</button>
            <button wire:click="selectAll(false)" class="text-gray-500">None</button>
        </div>
    </div>

    {{-- Printer. Without this the screen offered no way to choose one and no
         explanation when there wasn't one — it simply couldn't print. --}}
    @if ($printers->isEmpty())
        <div class="mb-3 px-4 py-3 bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-xl">
            No label printer set up for {{ $this->outletName() ?: 'your outlet' }}.
            Ask your manager to add one in Servora under Labels &rarr; Label Printers.
        </div>
    @elseif ($printers->count() > 1)
        <select wire:model.live="printerId" class="w-full mb-3 rounded-xl border-gray-200 text-sm py-2.5">
            @foreach ($printers as $p)
                <option value="{{ $p->id }}">{{ $p->name }}</option>
            @endforeach
        </select>
    @else
        <p class="text-xs text-gray-400 mb-3 px-1">Printing to {{ $printers->first()->name }}</p>
    @endif

    <p class="text-xs text-gray-500 mb-3 px-1">Untick anything you haven't prepped today.</p>

    <div class="space-y-2">
        @forelse ($lines as $line)
            <label class="flex items-start gap-3 bg-white rounded-xl border border-gray-200 p-3 active:bg-gray-50"
                   wire:key="line-{{ $line->id }}">
                {{-- Deliberately oversized: tapped with gloves on. --}}
                <input type="checkbox" wire:model.live="selected.{{ $line->id }}"
                       class="mt-0.5 w-6 h-6 rounded border-gray-300 text-indigo-600">

                <span class="flex-1 min-w-0">
                    <span class="block text-sm font-medium text-gray-800">{{ $line->displayName() }}</span>
                    <span class="block text-xs text-gray-400 mt-0.5">
                        {{ \App\Models\LabelTemplate::LABEL_TYPES[$line->label_type] ?? $line->label_type }}
                        · {{ \App\Models\ShelfLifeRule::stateLabel($line->storage_state) }}
                    </span>

                    @if ($previews[$line->id]['manual'] ?? true)
                        <span class="mt-1.5 flex items-center gap-2">
                            <span class="text-[10px] uppercase text-amber-600 shrink-0">Set use-by</span>
                            <input type="datetime-local" wire:model.live="endAt.{{ $line->id }}"
                                   class="flex-1 rounded-lg border-amber-200 text-xs py-1.5">
                        </span>
                    @else
                        <span class="block text-xs text-gray-500 mt-0.5">
                            Use by <span class="font-semibold text-gray-700">{{ $previews[$line->id]['end_at']->format('d/m/Y H:i') }}</span>
                        </span>
                    @endif
                </span>

                <span class="text-xs text-gray-400 shrink-0 pt-1">&times;{{ $copies[$line->id] ?? $line->copies }}</span>
            </label>
        @empty
            <div class="text-center py-14">
                <p class="text-sm text-gray-400">This set is empty.</p>
            </div>
        @endforelse
    </div>

    @if ($lines->count() && $printers->isNotEmpty())
        <button wire:click="print" wire:loading.attr="disabled"
                class="fixed bottom-[4.5rem] inset-x-0 mx-auto max-w-2xl w-[calc(100%-1.5rem)] py-4 bg-indigo-600 text-white text-base font-semibold rounded-xl shadow-lg active:bg-indigo-700 disabled:opacity-50">
            <span wire:loading.remove wire:target="print">Print selected</span>
            <span wire:loading wire:target="print">Printing…</span>
        </button>
    @endif
</div>
