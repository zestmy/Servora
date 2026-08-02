<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="alert-success mb-3">
            {{ session('success') }}
        </div>
    @endif

    @error('print')
        <div class="alert-danger mb-3">{{ $message }}</div>
    @enderror

    <x-labels.guide class="mb-3" compact />

    {{-- These were 12px text links at roughly 16px tall, sitting in a row of
         four. -mx-2 keeps the visual line where it was while each control
         gets a real target. --}}
    <div class="-mx-2 mb-2 flex items-center justify-between">
        <a href="{{ route('labels.staff.sets') }}" wire:navigate
           class="flex min-h-[2.75rem] items-center gap-1 rounded-control px-2 text-xs font-medium text-gray-600 active:bg-gray-100">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            All sets
        </a>
        <div class="flex items-center">
            @unless ($editing)
                <button wire:click="selectAll(true)"
                        class="min-h-[2.75rem] rounded-control px-3 text-xs font-medium text-brand-700 active:bg-brand-50">All</button>
                <button wire:click="selectAll(false)"
                        class="min-h-[2.75rem] rounded-control px-3 text-xs font-medium text-gray-600 active:bg-gray-100">None</button>
            @endunless
            <button wire:click="toggleEditing"
                    class="min-h-[2.75rem] rounded-control px-3 text-xs active:bg-gray-100
                           {{ $editing ? 'font-semibold text-brand-700' : 'font-medium text-gray-600' }}">
                {{ $editing ? 'Done' : 'Edit items' }}
            </button>
        </div>
    </div>

    {{-- Printer. Without this the screen offered no way to choose one and no
         explanation when there wasn't one — it simply couldn't print. --}}
    @if ($printers->isEmpty())
        <div class="alert-warning mb-3">
            No label printer set up for {{ $this->outletName() ?: 'your outlet' }}.
            Ask your manager to add one in Servora under Labels &rarr; Label Printers.
        </div>
    @elseif ($printers->count() > 1)
        <select wire:model.live="printerId" class="input mb-3 py-3 text-base">
            @foreach ($printers as $p)
                <option value="{{ $p->id }}">{{ $p->name }}</option>
            @endforeach
        </select>
    @else
        <p class="text-xs text-gray-600 mb-3 px-1">Printing to {{ $printers->first()->name }}</p>
    @endif

    @if ($editing)
        {{-- Editing changes the set for everyone at this outlet, not just
             this shift. Say so before someone removes a colleague's line. --}}
        <div class="alert-warning mb-3 text-xs">
            Changes here apply to this set for everyone at {{ $this->outletName() ?: 'this outlet' }}, not just today.
        </div>

        {{-- Pinned while editing, so the set list scrolls under it and an
             item can be added without scrolling back up each time. Same
             -mx-3 px-3 trick as the print screen: the scroll area is padded
             and the background has to cover its full width. --}}
        <div class="sticky top-0 z-20 -mx-3 px-3 pb-3 bg-gray-50">
            <input type="search" wire:model.live.debounce.350ms="search"
                   placeholder="Search an item to add…" enterkeyhint="search"
                   class="input py-3 text-base">

            @if (count($results))
                <div class="card mt-2 divide-y divide-gray-100 overflow-y-auto max-h-[45vh]">
                    @foreach ($results as $group => $found)
                        <p class="sticky top-0 z-10 px-3 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-wider text-gray-600 bg-gray-50">
                            {{ $group }} <span class="normal-case tracking-normal">{{ $found['total'] }}</span>
                        </p>
                        @foreach ($found['items'] as $item)
                            <button type="button" wire:click="addItem('{{ $item['type'] }}', {{ $item['id'] }})"
                                    class="list-row text-sm font-medium text-gray-900">
                                {{ $item['name'] }}
                            </button>
                        @endforeach
                        @if ($found['truncated'])
                            <p class="px-4 py-2 text-xs font-medium text-warning-800 bg-warning-50">
                                Showing {{ count($found['items']) }} of {{ $found['total'] }} — type more to narrow it down.
                            </p>
                        @endif
                    @endforeach
                </div>
            @elseif (strlen(trim($search)) >= 2)
                <p class="text-center text-sm text-gray-600 py-3">Nothing found.</p>
            @endif

            <div class="flex gap-2 mt-2">
                <input type="text" wire:model="customName" wire:keydown.enter="addCustomItem"
                       placeholder="Or type any item name…"
                       class="input flex-1 py-2.5">
                <button wire:click="addCustomItem"
                        class="btn-secondary px-5">Add</button>
            </div>
        </div>
    @else
        <p class="mb-3 px-1 text-sm text-gray-600">Untick anything you haven't prepped today.</p>
    @endif

    <div class="space-y-2">
        @forelse ($lines as $line)
            <label class="card flex items-start gap-3 p-3.5 active:bg-gray-50"
                   wire:key="line-{{ $line->id }}">
                @if ($editing)
                    <button type="button" wire:click="removeItem({{ $line->id }})"
                            wire:confirm="Remove {{ $line->displayName() }} from this set?"
                            class="icon-btn icon-btn-danger relative mt-0.5 h-6 w-6 shrink-0 rounded-full bg-danger-50 text-danger-700 text-lg leading-none"
                            aria-label="Remove {{ $line->displayName() }}">&minus;</button>
                @else
                    {{-- Deliberately oversized: tapped with gloves on. --}}
                    <input type="checkbox" wire:model.live="selected.{{ $line->id }}"
                           class="mt-0.5 h-6 w-6 rounded-[0.375rem] border-gray-300 text-brand-600 focus:ring-brand-500/25">
                @endif

                <span class="flex-1 min-w-0">
                    <span class="block text-[15px] font-medium leading-snug text-gray-900">{{ $line->displayName() }}</span>
                    <span class="block text-xs text-gray-600 mt-0.5">
                        {{ \App\Models\LabelTemplate::LABEL_TYPES[$line->label_type] ?? $line->label_type }}
                        · {{ \App\Models\ShelfLifeRule::stateLabel($line->storage_state) }}
                    </span>

                    @if ($previews[$line->id]['manual'] ?? true)
                        <span class="mt-1.5 flex items-center gap-2">
                            <span class="shrink-0 text-[10px] font-semibold uppercase tracking-wider text-warning-700">Set use-by</span>
                            <input type="datetime-local" wire:model.live="endAt.{{ $line->id }}"
                                   class="input input-error flex-1 py-2 text-xs">
                        </span>
                    @else
                        <span class="mt-0.5 block text-xs text-gray-600">
                            Use by <span class="font-semibold tabular-nums text-gray-900">{{ $previews[$line->id]['end_at']->format('d/m H:i') }}</span>
                        </span>
                    @endif
                </span>

                <span class="shrink-0 pt-1 text-xs tabular-nums text-gray-600">&times;{{ $copies[$line->id] ?? $line->copies }}</span>
            </label>
        @empty
            <div class="text-center py-14">
                <p class="text-sm font-medium text-gray-900">This set is empty.</p>
                @unless ($editing)
                    <button wire:click="toggleEditing" class="btn-secondary mt-3">Add items</button>
                @endunless
            </div>
        @endforelse
    </div>

    @if ($lines->count() && $printers->isNotEmpty() && ! $editing)
        {{-- Sticky within the scroll area, so it stays reachable while the
             checklist scrolls and never overlaps the nav. --}}
        <button wire:click="print" wire:loading.attr="disabled"
                class="btn-primary sticky bottom-2 mt-3 w-full py-4 text-base shadow-e3">
            <span wire:loading.remove wire:target="print">Print selected</span>
            <span wire:loading wire:target="print">Printing…</span>
        </button>
    @endif
</div>
