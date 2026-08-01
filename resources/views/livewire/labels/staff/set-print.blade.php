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

    <x-labels.guide class="mb-3" compact />

    <div class="flex items-center justify-between mb-2 px-1">
        <a href="{{ route('labels.staff.sets') }}" wire:navigate class="text-xs text-gray-400 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            All sets
        </a>
        <div class="flex gap-3 text-xs">
            @unless ($editing)
                <button wire:click="selectAll(true)" class="text-indigo-600">All</button>
                <button wire:click="selectAll(false)" class="text-gray-500">None</button>
            @endunless
            <button wire:click="toggleEditing" class="{{ $editing ? 'text-indigo-600 font-semibold' : 'text-gray-500' }}">
                {{ $editing ? 'Done' : 'Edit items' }}
            </button>
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

    @if ($editing)
        {{-- Editing changes the set for everyone at this outlet, not just
             this shift. Say so before someone removes a colleague's line. --}}
        <div class="mb-3 px-4 py-3 bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-xl">
            Changes here apply to this set for everyone at {{ $this->outletName() ?: 'this outlet' }}, not just today.
        </div>

        <div class="mb-3">
            <input type="search" wire:model.live.debounce.350ms="search"
                   placeholder="Search an item to add…" enterkeyhint="search"
                   class="w-full rounded-xl border-gray-200 text-base py-3">

            @if (count($results))
                <div class="mt-2 bg-white rounded-xl border border-gray-200 divide-y divide-gray-50 overflow-hidden">
                    @foreach ($results as $group => $found)
                        <p class="px-3 pt-2 pb-1 text-[10px] uppercase tracking-wider text-gray-400 bg-gray-50">
                            {{ $group }} <span class="normal-case tracking-normal">{{ $found['total'] }}</span>
                        </p>
                        @foreach ($found['items'] as $item)
                            <button type="button" wire:click="addItem('{{ $item['type'] }}', {{ $item['id'] }})"
                                    class="w-full text-left px-4 py-3.5 text-sm text-gray-800 active:bg-indigo-50">
                                {{ $item['name'] }}
                            </button>
                        @endforeach
                        @if ($found['truncated'])
                            <p class="px-4 py-2 text-xs text-amber-600 bg-amber-50">
                                Showing {{ count($found['items']) }} of {{ $found['total'] }} — type more to narrow it down.
                            </p>
                        @endif
                    @endforeach
                </div>
            @elseif (strlen(trim($search)) >= 2)
                <p class="text-center text-sm text-gray-400 py-3">Nothing found.</p>
            @endif

            <div class="flex gap-2 mt-2">
                <input type="text" wire:model="customName" wire:keydown.enter="addCustomItem"
                       placeholder="Or type any item name…"
                       class="flex-1 rounded-xl border-gray-200 text-sm py-2.5">
                <button wire:click="addCustomItem"
                        class="px-4 rounded-xl border border-gray-200 text-sm text-gray-600 active:bg-gray-50">Add</button>
            </div>
        </div>
    @else
        <p class="text-xs text-gray-500 mb-3 px-1">Untick anything you haven't prepped today.</p>
    @endif

    <div class="space-y-2">
        @forelse ($lines as $line)
            <label class="flex items-start gap-3 bg-white rounded-xl border border-gray-200 p-3 active:bg-gray-50"
                   wire:key="line-{{ $line->id }}">
                @if ($editing)
                    <button type="button" wire:click="removeItem({{ $line->id }})"
                            wire:confirm="Remove {{ $line->displayName() }} from this set?"
                            class="mt-0.5 w-6 h-6 shrink-0 rounded-full bg-red-50 text-red-600 flex items-center justify-center text-lg leading-none"
                            aria-label="Remove {{ $line->displayName() }}">&minus;</button>
                @else
                    {{-- Deliberately oversized: tapped with gloves on. --}}
                    <input type="checkbox" wire:model.live="selected.{{ $line->id }}"
                           class="mt-0.5 w-6 h-6 rounded border-gray-300 text-indigo-600">
                @endif

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
                @unless ($editing)
                    <button wire:click="toggleEditing" class="mt-2 text-sm text-indigo-600">Add items</button>
                @endunless
            </div>
        @endforelse
    </div>

    @if ($lines->count() && $printers->isNotEmpty() && ! $editing)
        {{-- Sticky within the scroll area, so it stays reachable while the
             checklist scrolls and never overlaps the nav. --}}
        <button wire:click="print" wire:loading.attr="disabled"
                class="sticky bottom-2 mt-3 w-full py-4 bg-indigo-600 text-white text-base font-semibold rounded-xl shadow-lg active:bg-indigo-700 disabled:opacity-50">
            <span wire:loading.remove wire:target="print">Print selected</span>
            <span wire:loading wire:target="print">Printing…</span>
        </button>
    @endif
</div>
