<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="alert-success mb-3">
            {{ session('success') }}
        </div>
    @endif

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
                <p class="mt-1 text-sm text-gray-600">
                    Your manager can create them in Servora
                    @if ($importOutlets->isNotEmpty())
                        — or bring one over from another outlet below.
                    @else
                        .
                    @endif
                </p>
            </div>
        @endforelse
    </div>

    {{-- Import a set from another outlet.

         The one write this screen allows, and it is a COPY: it cannot touch
         the outlet it came from, and importing the same set again tops the
         copy up rather than doubling it. Only offered when there is somewhere
         to import FROM — an outlet with no sets is a dead end three taps
         deep, so it never appears in the list. --}}
    @if ($importOutlets->isNotEmpty())
        <div class="mt-4">
            @if (! $showImport)
                <button wire:click="openImport"
                        class="card flex w-full items-center gap-3 px-4 py-4 text-left active:bg-brand-50">
                    <span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control bg-gray-100 text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                        </svg>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-base font-medium text-gray-900">Import from another outlet</span>
                        <span class="block text-xs text-gray-600">Copy one of their sets into this outlet</span>
                    </span>
                </button>
            @else
                <div class="card px-4 py-4">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold text-gray-900">Import from another outlet</h2>
                        <button wire:click="closeImport" aria-label="Close import"
                                class="icon-btn text-gray-500">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="mt-3 space-y-3">
                        <div class="field">
                            <label class="label" for="import-outlet">Outlet</label>
                            <select id="import-outlet" wire:model.live="importOutletId"
                                    class="input py-3 @error('importOutletId') input-error @enderror">
                                <option value="">Choose an outlet…</option>
                                @foreach ($importOutlets as $outlet)
                                    <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                @endforeach
                            </select>
                            @error('importOutletId') <p class="error-text">{{ $message }}</p> @enderror
                        </div>

                        @if ($importOutletId)
                            <div class="field">
                                <label class="label" for="import-set">Set</label>
                                <select id="import-set" wire:model.live="importSetId"
                                        class="input py-3 @error('importSetId') input-error @enderror">
                                    <option value="">Choose a set…</option>
                                    @foreach ($importSets as $s)
                                        <option value="{{ $s->id }}">
                                            {{ $s->name }} — {{ $s->lines_count }} item{{ $s->lines_count === 1 ? '' : 's' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('importSetId') <p class="error-text">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </div>

                    <button wire:click="importSet" class="btn-primary mt-4 w-full py-3.5"
                            wire:loading.attr="disabled" wire:target="importSet">
                        <span wire:loading.remove wire:target="importSet">Import</span>
                        <span wire:loading wire:target="importSet">Importing…</span>
                    </button>

                    <p class="help mt-3">
                        Copies the set into your outlet — the other outlet's set is not
                        changed. Importing the same set again only adds what's new.
                    </p>
                </div>
            @endif
        </div>
    @endif
</div>
