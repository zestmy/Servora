<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-3 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    @error('tray')
        <div class="mb-3 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl">{{ $message }}</div>
    @enderror

    <x-labels.guide class="mb-3" compact />

    {{-- Say so up front rather than letting someone queue ten items and only
         then discover there is nowhere to send them. --}}
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
    @endif

    {{-- Add-an-item block, pinned.

         .app-scroll is the scroll container, and the header sits outside it,
         so top-0 lands directly under the header. -mx-3 px-3 lets the opaque
         background span the full width of the scroll area, which is padded —
         without it the queue would show through at the edges as it passes
         behind. --}}
    <div class="sticky top-0 z-20 -mx-3 px-3 pb-3 bg-gray-50">
    {{-- Search --}}
    <div class="relative mb-3">
        <input type="search" wire:model.live.debounce.350ms="search"
               placeholder="Search item…" enterkeyhint="search"
               class="w-full rounded-xl border-gray-200 text-base py-3 pl-10">
        <svg class="w-5 h-5 text-gray-400 absolute left-3 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
    </div>

    {{-- Capped and scrolled in its own right: four groups of 25 would
         otherwise pin the whole screen and leave nothing to scroll to. --}}
    @if (count($results))
        <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-50 mb-3 overflow-y-auto max-h-[45vh]">
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
        <p class="text-center text-sm text-gray-400 py-4">Nothing found.</p>
    @endif

    {{-- Freeform --}}
    <div class="flex gap-2">
        <input type="text" wire:model="customName" placeholder="Or type any item name…"
               class="flex-1 rounded-xl border-gray-200 text-sm py-2.5">
        <button wire:click="addCustom" class="px-4 rounded-xl border border-gray-200 text-sm text-gray-600 active:bg-gray-50">Add</button>
    </div>
    </div>
    {{-- end pinned block --}}


    {{-- Queue --}}
    @if (count($tray))
        <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-semibold text-gray-700">To print ({{ count($tray) }})</p>
            <button wire:click="clearTray" class="text-xs text-gray-400 active:text-red-500">Clear</button>
        </div>

        <div class="space-y-2">
            @foreach ($tray as $index => $line)
                <div class="bg-white rounded-xl border border-gray-200 p-3" wire:key="tray-{{ $index }}-{{ $line['name'] }}">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-medium text-gray-800 flex-1">{{ $line['name'] }}</p>
                        <button wire:click="removeLine({{ $index }})" class="text-gray-300 active:text-red-500 text-lg leading-none px-1">&times;</button>
                    </div>

                    <div class="mt-2 flex items-center gap-2">
                        <select wire:model.live="tray.{{ $index }}.label_type" class="flex-1 rounded-lg border-gray-200 text-xs py-2">
                            @foreach ($labelTypes as $type => $caption)
                                <option value="{{ $type }}">{{ $caption ?: 'Custom' }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="tray.{{ $index }}.storage_state" class="flex-1 rounded-lg border-gray-200 text-xs py-2">
                            @foreach ($states as $state => $stateLabel)
                                <option value="{{ $state }}">{{ $stateLabel }}</option>
                            @endforeach
                        </select>

                        {{-- Stepper rather than a number field: easier to hit,
                             and no keyboard covering the screen. --}}
                        <div class="flex items-center border border-gray-200 rounded-lg">
                            <button wire:click="adjustCopies({{ $index }}, -1)" class="w-9 h-9 text-gray-500 active:bg-gray-50">&minus;</button>
                            <span class="w-7 text-center text-sm font-semibold">{{ $line['copies'] }}</span>
                            <button wire:click="adjustCopies({{ $index }}, 1)" class="w-9 h-9 text-gray-500 active:bg-gray-50">+</button>
                        </div>
                    </div>

                    <div class="mt-2">
                        @if ($previews[$index]['manual'] ?? true)
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] uppercase text-amber-600 shrink-0">Set use-by</span>
                                <input type="datetime-local" wire:model.live="tray.{{ $index }}.end_at"
                                       class="flex-1 rounded-lg border-amber-200 text-xs py-1.5">
                            </div>
                        @else
                            <p class="text-xs text-gray-500">
                                Use by <span class="font-semibold text-gray-700">{{ $previews[$index]['end_at']->format('d/m/Y H:i') }}</span>
                            </p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Sticky inside the scroll area rather than fixed to the viewport:
             it can never collide with the nav, because the scroll area ends
             where the nav begins. --}}
        <button wire:click="printTray" wire:loading.attr="disabled"
                class="sticky bottom-2 mt-3 w-full py-4 bg-indigo-600 text-white text-base font-semibold rounded-xl shadow-lg active:bg-indigo-700 disabled:opacity-50">
            <span wire:loading.remove wire:target="printTray">Print {{ count($tray) }} item{{ count($tray) === 1 ? '' : 's' }}</span>
            <span wire:loading wire:target="printTray">Printing…</span>
        </button>
    @else
        <div class="text-center py-12">
            <p class="text-sm text-gray-400">Search for something to label.</p>
        </div>
    @endif
</div>
