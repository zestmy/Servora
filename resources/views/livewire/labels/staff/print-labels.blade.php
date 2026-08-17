<div>
    {{-- ── Greeting ──
         Face and FULL name, same as the Clock app's home screen — and full
         for the same hard-learned reason: names here are not reliably
         given-name-first, so cutting at the first space greeted MOHD AFFANDY
         BIN ZULKARNAIN as "MOHD". It matters more on this screen than most:
         whoever is greeted here is stamped as "Prepared by" on every label
         printed, so the greeting doubles as "you are printing as". --}}
    @php
        $greetHour = now()->hour;
        $greeting  = $greetHour < 12 ? 'Good morning' : ($greetHour < 18 ? 'Good afternoon' : 'Good evening');
    @endphp
    <div class="mb-3 flex items-center gap-3 px-1">
        <x-staff-avatar :name="$this->staff()->name" :employee="$this->staff()->id"
                        :photo="$this->staff()->photo_path" size="h-10 w-10 text-xs" />
        <div class="min-w-0">
            <p class="text-xs text-gray-600">{{ $greeting }}</p>
            <p class="truncate text-base font-semibold leading-tight text-gray-900">{{ $this->staff()->name }}</p>
        </div>
    </div>

    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="alert-success mb-3">
            {{ session('success') }}
        </div>
    @endif

    @error('tray')
        <div class="alert-danger mb-3">{{ $message }}</div>
    @enderror

    <x-labels.guide class="mb-3" compact />

    {{-- Say so up front rather than letting someone queue ten items and only
         then discover there is nowhere to send them. --}}
    @if ($printers->isEmpty())
        <div class="alert-warning mb-3">
            No label printer set up for {{ $this->outletName() ?: 'your outlet' }}.
            Ask your manager to add one in Servora under Labels &rarr; Label Printers.
        </div>
    @else
        @php $activePrinter = $printers->firstWhere('id', $printerId) ?? $printers->first(); @endphp
        <div class="mb-3 flex flex-wrap items-center gap-2">
            @if ($printers->count() > 1)
                <select wire:model.live="printerId" class="input flex-1 py-3 text-base">
                    @foreach ($printers as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
            @else
                <p class="flex-1 text-sm text-gray-600">Printing to
                    <span class="font-medium text-gray-900">{{ $activePrinter->name }}</span>
                </p>
            @endif
            {{-- Whether the thing at the other end is actually reachable.
                 Real for PrintNode, impossible for kiosk printing. --}}
            @if ($activePrinter)
                <x-labels.printer-status :printer="$activePrinter" class="shrink-0" />
            @endif
        </div>
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
               class="input py-3 pl-10 text-base">
        <svg class="w-5 h-5 text-gray-500 absolute left-3 top-3.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
    </div>

    {{-- Capped and scrolled in its own right: four groups of 25 would
         otherwise pin the whole screen and leave nothing to scroll to. --}}
    @if (count($results))
        <div class="card divide-y divide-gray-100 mb-3 overflow-y-auto max-h-[45vh]">
            @foreach ($results as $group => $found)
                {{-- Sticky so the group a row belongs to stays visible while
                     scrolling a long list — otherwise you lose track of
                     whether you are looking at a recipe or a prep item. --}}
                <p class="sticky top-0 z-10 px-3 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-wider text-gray-600 bg-gray-50">
                    {{ $group }} <span class="font-normal normal-case tracking-normal text-gray-500">{{ $found['total'] }}</span>
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
        <p class="text-center text-sm text-gray-600 py-4">Nothing found.</p>
    @endif

    {{-- Freeform --}}
    <div class="flex gap-2">
        <input type="text" wire:model="customName" placeholder="Or type any item name…"
               class="input flex-1 py-2.5">
        <button wire:click="addCustom" class="btn-secondary px-5">Add</button>
    </div>
    </div>
    {{-- end pinned block --}}


    {{-- Queue --}}
    @if (count($tray))
        <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-semibold text-gray-900">To print ({{ count($tray) }})</p>
            <button wire:click="clearTray" class="text-xs font-medium text-gray-600 active:text-danger-700">Clear</button>
        </div>

        <div class="space-y-2">
            @foreach ($tray as $index => $line)
                <div class="card p-3.5" wire:key="tray-{{ $index }}-{{ $line['name'] }}">
                    <div class="flex items-start justify-between gap-2">
                        <p class="flex-1 text-[15px] font-medium leading-snug text-gray-900">{{ $line['name'] }}</p>
                        {{-- Was a bare &times; glyph at roughly 24px. Same
                             visual weight, 44px of actual target. --}}
                        <button wire:click="removeLine({{ $index }})"
                                aria-label="Remove {{ $line['name'] }}"
                                class="icon-btn icon-btn-danger -mr-1 -mt-1 shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Full-width rather than squeezed beside the stepper:
                         these carry the two decisions that change what gets
                         printed, and at py-2 in a third of the row they were
                         the smallest targets on screen. --}}
                    <div class="mt-2.5 grid grid-cols-2 gap-2">
                        <select wire:model.live="tray.{{ $index }}.label_type" class="input py-2.5 text-sm">
                            @foreach ($labelTypes as $type => $caption)
                                <option value="{{ $type }}">{{ $caption ?: 'Custom' }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="tray.{{ $index }}.storage_state" class="input py-2.5 text-sm">
                            @foreach ($states as $state => $stateLabel)
                                <option value="{{ $state }}">{{ $stateLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-3 flex items-end justify-between gap-3 border-t border-gray-100 pt-3">
                        <div class="min-w-0">
                            @if ($previews[$index]['manual'] ?? true)
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-warning-700">No rule — set use-by</p>
                                <input type="datetime-local" wire:model.live="tray.{{ $index }}.end_at"
                                       class="input input-error mt-1 py-2 text-sm">
                            @else
                                @php
                                    // The span the rule is about to apply. Shown
                                    // because a wrong shelf-life rule is only
                                    // obvious as a duration — "3 days" reads
                                    // wrong on a cut garnish where the date
                                    // alone does not.
                                    $endsAt = $previews[$index]['end_at'];
                                    $secs   = (int) round(now()->diffInSeconds($endsAt, false));
                                    $dd     = intdiv(max(0, $secs), 86400);
                                    $hh     = intdiv(max(0, $secs) % 86400, 3600);
                                    $span   = $dd > 0
                                        ? $dd . ' day' . ($dd === 1 ? '' : 's') . ($hh > 0 ? ' ' . $hh . 'h' : '')
                                        : max(1, $hh) . ' hour' . ($hh === 1 ? '' : 's');
                                    $source = match ($previews[$index]['source'] ?? null) {
                                        'category' => 'from category',
                                        'legacy', 'legacy_prep_recipe' => 'from item shelf life',
                                        default => null,
                                    };
                                @endphp
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">Use by</p>
                                <p class="text-lg font-semibold tabular-nums leading-tight text-gray-900">
                                    {{ $endsAt->format('d/m H:i') }}
                                </p>
                                <p class="text-xs text-gray-600">{{ $span }}@if ($source) · {{ $source }} @endif</p>
                            @endif
                        </div>

                        {{-- Stepper rather than a number field: easier to hit,
                             and no keyboard covering the screen. --}}
                        <div class="stepper shrink-0">
                            <button wire:click="adjustCopies({{ $index }}, -1)"
                                    aria-label="One fewer copy" class="stepper-btn">&minus;</button>
                            <span class="stepper-val" aria-live="polite">{{ $line['copies'] }}</span>
                            <button wire:click="adjustCopies({{ $index }}, 1)"
                                    aria-label="One more copy" class="stepper-btn">+</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Sticky inside the scroll area rather than fixed to the viewport:
             it can never collide with the nav, because the scroll area ends
             where the nav begins. --}}
        <button wire:click="printTray" wire:loading.attr="disabled"
                class="btn-primary sticky bottom-2 mt-3 w-full py-4 text-base shadow-e3">
            <span wire:loading.remove wire:target="printTray">
                Print {{ count($tray) }} item{{ count($tray) === 1 ? '' : 's' }}
            </span>
            <span wire:loading wire:target="printTray" class="flex items-center gap-2">
                <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/>
                </svg>
                Printing…
            </span>
        </button>
    @else
        <div class="py-12 text-center">
            <p class="text-sm font-medium text-gray-900">Nothing queued</p>
            <p class="mt-1 text-sm text-gray-600">Search above for an ingredient, recipe or prep item.</p>
        </div>
    @endif
</div>
