<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="text-xs text-gray-400">Labels / Shelf life</p>
            <h2 class="text-lg font-semibold text-gray-700 mt-1">Shelf life</h2>
        </div>
        <div class="text-right">
            <p class="text-xs text-gray-400">Categories with a chilled life</p>
            <p class="text-sm font-semibold {{ $coverage['covered'] < $coverage['total'] ? 'text-amber-600' : 'text-green-600' }}">
                {{ $coverage['covered'] }} / {{ $coverage['total'] }}
            </p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4">
        <p class="text-sm text-gray-600">
            How long each thing keeps, per storage state. Labels read these to work out a use-by date.
        </p>
        <p class="text-xs text-gray-500 mt-2">
            Work in <strong>Categories</strong> wherever you can — one row there covers every item in the category.
            Set an item's own value only where it genuinely differs. Anything left blank falls back to its category,
            and if nothing is set at all the chef has to type the date by hand.
        </p>
    </div>

    {{-- Scope + filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Showing</label>
                <select wire:model.live="scope" class="rounded-lg border-gray-300 text-sm">
                    @foreach ($scopes as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Name…"
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>
            <label class="flex items-center gap-2 pb-2 text-sm text-gray-600">
                <input type="checkbox" wire:model.live="gapsOnly" class="rounded border-gray-300 text-indigo-600">
                Only rows with nothing set
            </label>
        </div>

        @error('bulk')
            <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-[900px] divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left sticky left-0 bg-gray-50 z-10">Name</th>
                        @foreach ($states as $state => $stateLabel)
                            <th class="px-3 py-3 text-left w-40">{{ $stateLabel }}</th>
                        @endforeach
                    </tr>
                    {{-- Bulk apply: fills every row on this page --}}
                    <tr class="bg-gray-50/60 border-t border-gray-100">
                        <th class="px-4 py-2 text-left sticky left-0 bg-gray-50 z-10 normal-case text-gray-400 font-normal">
                            Apply to this page
                        </th>
                        @foreach ($states as $state => $stateLabel)
                            <th class="px-3 py-2">
                                <div class="flex items-center gap-1">
                                    <input type="number" min="0" step="0.5" wire:model="bulk.{{ $state }}.value"
                                           class="w-14 rounded border-gray-200 text-xs py-1">
                                    <select wire:model="bulk.{{ $state }}.unit" class="rounded border-gray-200 text-xs py-1">
                                        @foreach ($units as $u => $uLabel)
                                            <option value="{{ $u }}">{{ $uLabel }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" wire:click="bulkApply('{{ $state }}')"
                                            class="px-1.5 py-1 text-xs text-indigo-600 hover:text-indigo-800"
                                            title="Apply to every row on this page">→</button>
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-gray-50" wire:key="row-{{ $scope }}-{{ $row->id }}">
                            <td class="px-4 py-2 sticky left-0 bg-white z-10 font-medium text-gray-700">
                                {{ $row->name }}
                            </td>
                            @foreach ($states as $state => $stateLabel)
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-1">
                                        <input type="number" min="0" step="0.5"
                                               wire:model.live.debounce.600ms="cells.{{ $row->id }}.{{ $state }}.value"
                                               placeholder="{{ $inherited[$row->id][$state] ?? '—' }}"
                                               class="w-14 rounded border-gray-200 text-xs py-1">
                                        <select wire:model.live="cells.{{ $row->id }}.{{ $state }}.unit"
                                                class="rounded border-gray-200 text-xs py-1">
                                            @foreach ($units as $u => $uLabel)
                                                <option value="{{ $u }}">{{ $uLabel }}</option>
                                            @endforeach
                                        </select>
                                        @if (($cells[$row->id][$state]['value'] ?? '') !== '')
                                            <button type="button" wire:click="clearCell({{ $row->id }}, '{{ $state }}')"
                                                    class="text-gray-300 hover:text-red-500 text-xs" title="Clear">×</button>
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($states) + 1 }}" class="px-4 py-10 text-center text-gray-400 text-sm">
                                Nothing to show here.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $rows->links() }}
    </div>

    @if ($scope !== 'category')
        <p class="mt-3 text-xs text-gray-400">
            Greyed values are inherited from the item's category. Type a value to override it for this item only.
        </p>
    @endif
</div>
