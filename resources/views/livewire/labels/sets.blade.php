<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Here as well as on the print screen: a set's label types are chosen
         once and then printed unread for months, so this is the moment the
         choice actually matters. --}}
    <x-labels.guide class="mb-4" />

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
            <a data-back href="{{ route('labels.print') }}" class="text-gray-600 hover:text-gray-900 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <p class="page-eyebrow">Labels / Print sets</p>
                <h1 class="page-title mt-1">Print sets</h1>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if ($sets->count())
                @canDo('labels.manage')
                <a href="{{ route('labels.sets.qr-sheet', ['outlet' => $outletId]) }}" target="_blank"
                   class="px-3 py-2 text-sm text-brand-600 border border-brand-200 rounded-lg hover:bg-brand-50">
                    Print QR sheet
                </a>
                @endcanDo
            @endif
            <button wire:click="openCreate" class="btn-primary">
                + New set
            </button>
        </div>
    </div>

    <div class="card p-4 mb-4">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Outlet</label>
                <select wire:model.live="outletId" class="rounded-lg border-gray-300 text-sm">
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                    @endforeach
                </select>
            </div>
            <p class="text-xs text-gray-500 pb-2">
                Sets belong to one outlet — group whatever gets relabelled together, like a chiller or a station.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- Sets --}}
        <div class="card overflow-hidden">
            <div class="divide-y divide-gray-50">
                @forelse ($sets as $row)
                    <div class="px-4 py-3 {{ $editingSetId === $row->id ? 'bg-brand-50/60' : 'hover:bg-gray-50' }}"
                         wire:key="set-{{ $row->id }}">
                        <div class="flex items-start justify-between gap-2">
                            <button wire:click="editLines({{ $row->id }})" class="text-left flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-700">{{ $row->name }}</p>
                                <p class="text-xs text-gray-600">{{ $row->lines_count }} item{{ $row->lines_count === 1 ? '' : 's' }}</p>
                            </button>
                            <div class="flex flex-col items-end gap-1">
                                <a href="{{ route('labels.sets.print', $row) }}"
                                   class="btn-primary btn-sm">Print</a>
                                <div class="flex gap-1 text-xs">
                                    <button wire:click="showQr({{ $row->id }})" class="text-brand-600 hover:underline">QR</button>
                                    <button wire:click="openRename({{ $row->id }})" class="text-gray-600 hover:text-gray-900">Edit</button>
                                    <button wire:click="deleteSet({{ $row->id }})" wire:confirm="Delete this set?"
                                            class="text-gray-600 hover:text-danger-500">Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-10 text-center text-gray-600 text-sm">
                        No sets for this outlet yet.
                    </p>
                @endforelse
            </div>
        </div>

        {{-- Line editor.

             On a wide screen this column is its own scroll container: the add
             field stays put while the list of what is already in the set
             scrolls under it. Building a twenty-item set otherwise meant
             scrolling back to the top after every addition to reach the
             search box. Below lg the height cap is dropped and it flows
             normally — freezing a field on a phone just eats the screen. --}}
        <div class="lg:col-span-2 flex flex-col gap-4 lg:h-[calc(100vh-9rem)]">
            @if (! $set)
                <div class="card p-10 text-center">
                    <p class="text-sm text-gray-600">Pick a set on the left to edit what's in it.</p>
                </div>
            @else
                <div class="card p-4 flex-shrink-0">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Add to “{{ $set->name }}”</label>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search items…"
                           class="w-full rounded-lg border-gray-300 text-sm">

                    @if (count($results))
                        <div class="mt-3 space-y-3 max-h-60 overflow-y-auto">
                            @foreach ($results as $group => $found)
                                @if (count($found['items']))
                                    <div>
                                        <p class="text-xs uppercase tracking-wider text-gray-600 mb-1">
                                            {{ $group }}
                                            <span class="normal-case tracking-normal text-gray-500">{{ $found['total'] }}</span>
                                        </p>
                                        @foreach ($found['items'] as $item)
                                            <button type="button" wire:click="addLine('{{ $item['type'] }}', {{ $item['id'] }})"
                                                    class="w-full text-left px-3 py-2 rounded-lg border border-gray-100 hover:bg-brand-50 text-sm text-gray-700 mb-1">
                                                {{ $item['name'] }}
                                            </button>
                                        @endforeach
                                        @if ($found['truncated'])
                                            <p class="text-xs text-warning-600">
                                                Showing {{ count($found['items']) }} of {{ $found['total'] }} — type more to narrow it down.
                                            </p>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-3 flex gap-2">
                        <input type="text" wire:model="customName" wire:keydown.enter="addCustomLine"
                               placeholder="Or add a freeform item…"
                               class="flex-1 rounded-lg border-gray-300 text-sm">
                        <button wire:click="addCustomLine" class="btn-secondary">Add</button>
                    </div>
                </div>

                <div class="card overflow-hidden flex flex-col lg:flex-1 lg:min-h-0">
                    <div class="px-4 py-2 bg-gray-50 border-b border-gray-100 flex items-center justify-between gap-3 flex-shrink-0">
                        <p class="text-xs text-gray-500">
                            Order matters — labels come off the roll in this order, so match how you walk the shelf.
                        </p>
                        <span class="text-xs text-gray-600 whitespace-nowrap">{{ $lines->count() }} item{{ $lines->count() === 1 ? '' : 's' }}</span>
                    </div>
                    <div class="divide-y divide-gray-50 lg:flex-1 lg:min-h-0 lg:overflow-y-auto">
                        @forelse ($lines as $i => $line)
                            <div class="px-4 py-3" wire:key="setline-{{ $line->id }}">
                                <div class="flex items-start gap-3">
                                    <div class="flex flex-col gap-0.5 pt-1">
                                        <button wire:click="moveLine({{ $line->id }}, -1)" @disabled($i === 0)
                                                class="text-gray-500 hover:text-gray-600 disabled:opacity-30 text-xs leading-none">▲</button>
                                        <button wire:click="moveLine({{ $line->id }}, 1)" @disabled($i === $lines->count() - 1)
                                                class="text-gray-500 hover:text-gray-600 disabled:opacity-30 text-xs leading-none">▼</button>
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        @if ($line->labelable_type)
                                            <p class="text-sm font-medium text-gray-700">{{ $line->displayName() }}</p>
                                        @else
                                            {{-- Freeform lines are editable in place: a typo here is
                                                 printed onto every label from this set until someone
                                                 notices. Linked lines take their name from the item
                                                 and stay read-only. --}}
                                            <input type="text" value="{{ $line->custom_name }}"
                                                   wire:change="updateLine({{ $line->id }}, 'custom_name', $event.target.value)"
                                                   title="Freeform item — edit to fix a typo"
                                                   class="w-full text-sm font-medium text-gray-700 border-0 border-b border-dashed border-gray-200 px-0 py-0.5 focus:ring-0 focus:border-brand-400 bg-transparent">
                                        @endif
                                        <div class="mt-2 grid grid-cols-3 gap-2">
                                            <select wire:change="updateLine({{ $line->id }}, 'label_type', $event.target.value)"
                                                    class="rounded border-gray-200 text-xs py-1">
                                                @foreach ($labelTypes as $type => $caption)
                                                    <option value="{{ $type }}" @selected($line->label_type === $type)>{{ $caption ?: 'Custom' }}</option>
                                                @endforeach
                                            </select>
                                            <select wire:change="updateLine({{ $line->id }}, 'storage_state', $event.target.value)"
                                                    class="rounded border-gray-200 text-xs py-1">
                                                @foreach ($states as $state => $stateLabel)
                                                    <option value="{{ $state }}" @selected($line->storage_state === $state)>{{ $stateLabel }}</option>
                                                @endforeach
                                            </select>
                                            <input type="number" min="1" max="99" value="{{ $line->copies }}"
                                                   wire:change="updateLine({{ $line->id }}, 'copies', $event.target.value)"
                                                   class="rounded border-gray-200 text-xs py-1">
                                        </div>
                                    </div>

                                    <button wire:click="removeLine({{ $line->id }})"
                                            class="text-gray-500 hover:text-danger-500 text-sm leading-none pt-1">×</button>
                                </div>
                            </div>
                        @empty
                            <p class="px-4 py-10 text-center text-gray-600 text-sm">Nothing in this set yet.</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- One set's QR --}}
    <div x-data="{ open: @entangle('qrSetId').live }">
        <template x-teleport="body">
            <div x-show="open" x-cloak @keydown.escape.window="$wire.closeQr()"
                 class="fixed inset-0 z-[100] overflow-y-auto">
                <div class="fixed inset-0 bg-black/50" @click="$wire.closeQr()"></div>
                <div class="relative min-h-full flex items-start sm:items-center justify-center p-4">
                    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-sm" @click.stop>
                        <div class="px-5 py-3 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-800">{{ $qrSet?->name }}</h3>
                        </div>
                        <div class="p-5 text-center">
                            @if ($qrImage)
                                <img src="{{ $qrImage }}" alt="QR for {{ $qrSet?->name }}"
                                     class="w-52 h-52 mx-auto border border-gray-100 rounded-lg">
                                <p class="mt-3 text-xs text-gray-500">
                                    Staff scan this to open the set on their phone, ready to print.
                                    They'll be asked for their PIN first if they aren't signed in.
                                </p>
                                <p class="mt-2 text-[11px] text-gray-600 break-all font-mono">{{ $qrUrl }}</p>
                            @endif
                        </div>
                        <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-gray-100">
                            <button wire:click="closeQr" class="btn-secondary">Close</button>
                            @canDo('labels.manage')
                            <a href="{{ route('labels.sets.qr-sheet', ['outlet' => $outletId, 'set' => $qrSet?->id]) }}"
                               target="_blank"
                               class="btn-primary">Print</a>
                            @endcanDo
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Create / rename --}}
    <div x-data="{ open: @entangle('showModal') }">
        <template x-teleport="body">
            <div x-show="open" x-cloak @keydown.escape.window="open = false" class="fixed inset-0 z-[100] overflow-y-auto">
                <div class="fixed inset-0 bg-black/50" @click="open = false"></div>
                <div class="relative min-h-full flex items-start sm:items-center justify-center p-4">
                    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md" @click.stop>
                        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-800">{{ $modalSetId ? 'Edit set' : 'New print set' }}</h3>
                            <button @click="open = false" class="text-gray-600 hover:text-gray-900 p-1">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <form wire:submit.prevent="saveSet" class="p-5 space-y-3">
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Name <span class="text-danger-500">*</span></label>
                                <input type="text" wire:model="name" class="mt-1 w-full text-sm rounded-lg border-gray-300"
                                       placeholder="e.g. Chiller 1, Grill Station" />
                                <x-input-error :messages="$errors->get('name')" class="mt-1" />
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Description</label>
                                <input type="text" wire:model="description" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                                <x-input-error :messages="$errors->get('description')" class="mt-1" />
                            </div>

                            {{-- What the QR label prints on the unit door. --}}
                            <div class="pt-3 border-t border-gray-100">
                                <label class="inline-flex items-start gap-2">
                                    <input type="checkbox" wire:model.live="showStorage"
                                           class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                    <span>
                                        <span class="text-sm text-gray-700">Show storage temperature on the QR label</span>
                                        <span class="block text-xs text-gray-600">
                                            Turn this off for sets where a temperature doesn't apply.
                                        </span>
                                    </span>
                                </label>

                                @if ($showStorage)
                                    <div class="mt-3 pl-6">
                                        <p class="text-xs font-semibold text-gray-600">Which temperature?</p>
                                        <p class="text-xs text-gray-600 mt-0.5 mb-2">
                                            Leave all unticked to follow whatever the items in this set use.
                                            Tick one to state it outright — a chiller door should say chilled
                                            even if the set holds the odd frozen item.
                                        </p>

                                        <div class="space-y-1.5">
                                            @foreach ($states as $state => $stateLabel)
                                                <label class="flex items-center gap-2">
                                                    <input type="checkbox" value="{{ $state }}" wire:model="storageStates"
                                                           class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                                    <span class="text-sm text-gray-700">{{ $stateLabel }}</span>
                                                    @if ($temperatures[$state] ?? null)
                                                        <span class="text-xs text-gray-600">{{ $temperatures[$state] }}</span>
                                                    @else
                                                        <span class="text-xs text-gray-500">no fixed range</span>
                                                    @endif
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                                <button type="button" @click="open = false" class="btn-secondary">Cancel</button>
                                <button type="submit" class="btn-primary">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
