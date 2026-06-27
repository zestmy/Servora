<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('settings.index') }}" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1 min-w-0">
            <h2 class="text-lg font-semibold text-gray-700">Par Levels</h2>
            <p class="text-xs text-gray-400">Set par levels per ingredient per outlet. Used for auto-calculating order quantities.</p>
        </div>
        <div class="flex items-center gap-2 text-xs">
            <span class="px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-600 font-medium">
                {{ $setCount }} / {{ $totalIngredients }} set
            </span>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Outlet</label>
                <select wire:model.live="outletId" class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Name or code..."
                       class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500" />
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Category</label>
                <select wire:model.live="categoryFilter" class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">All categories</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @foreach ($cat->children as $child)
                            <option value="{{ $child->id }}">&nbsp;&nbsp;— {{ $child->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                <select wire:model.live="statusFilter" class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">All ingredients</option>
                    <option value="set">Par level set</option>
                    <option value="unset">No par level</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Bulk tools --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
        <div class="flex flex-wrap items-end gap-x-6 gap-y-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Set all matching the filters</label>
                <div class="flex items-center gap-2">
                    <input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="bulkValue" placeholder="Qty"
                           class="w-28 rounded-lg border-gray-300 text-sm text-right focus:ring-indigo-500 focus:border-indigo-500" />
                    <button wire:click="applyToFiltered"
                            wire:confirm="Apply this value to every ingredient matching the current filters?"
                            class="px-3 py-2 bg-gray-700 text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition disabled:opacity-40"
                            @disabled($bulkValue === '')>
                        Apply
                    </button>
                </div>
            </div>

            <div class="h-9 w-px bg-gray-200 hidden sm:block"></div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Copy par levels from another outlet</label>
                <div class="flex items-center gap-2">
                    <select wire:model.live="copyFromOutletId" class="rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Select outlet…</option>
                        @foreach ($outlets as $outlet)
                            @if ($outlet->id !== $outletId)
                                <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                            @endif
                        @endforeach
                    </select>
                    <button wire:click="copyFromOutlet"
                            wire:confirm="Copy par levels from the selected outlet? Existing values for matching ingredients will be overwritten."
                            class="px-3 py-2 bg-gray-700 text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition disabled:opacity-40"
                            @disabled(!$copyFromOutletId)>
                        Copy
                    </button>
                </div>
            </div>

            <div class="ml-auto flex items-center gap-2 text-xs text-gray-400">
                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Changes save automatically as you type
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Ingredient</th>
                        <th class="px-4 py-3 text-left w-24">Code</th>
                        <th class="px-4 py-3 text-left w-48">Category</th>
                        <th class="px-4 py-3 text-left w-24">Base UOM</th>
                        <th class="px-4 py-3 text-right w-44">Par Level</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($ingredients as $ingredient)
                        <tr wire:key="par-row-{{ $ingredient->id }}" class="hover:bg-gray-50 transition">
                            <td class="px-4 py-2.5 font-medium text-gray-800">{{ $ingredient->name }}</td>
                            <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $ingredient->code ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $ingredient->ingredientCategory?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-gray-500">{{ $ingredient->baseUom?->abbreviation ?? '—' }}</td>
                            <td class="px-4 py-2.5"
                                x-data="{ saved: false }"
                                @par-saved.window="if ($event.detail.id == {{ $ingredient->id }}) { saved = true; setTimeout(() => saved = false, 1200) }">
                                <div class="flex items-center justify-end gap-2">
                                    <span x-show="saved" x-cloak x-transition.opacity class="text-green-600 text-xs font-medium whitespace-nowrap">Saved ✓</span>
                                    <input type="number" step="0.01" min="0"
                                           wire:model.blur="parLevels.{{ $ingredient->id }}"
                                           x-on:keydown.enter.prevent="$el.blur(); const a = [...document.querySelectorAll('.par-input')]; const i = a.indexOf($el); if (a[i + 1]) a[i + 1].focus()"
                                           placeholder="0"
                                           class="par-input w-28 text-right rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-gray-400">
                                No ingredients found for the current filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($ingredients->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $ingredients->links() }}
            </div>
        @endif
    </div>

    {{-- Footer save (optional explicit bulk save; edits already auto-save) --}}
    <div class="flex justify-end mt-4">
        <button wire:click="saveAll"
                class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            Save All
        </button>
    </div>
</div>
