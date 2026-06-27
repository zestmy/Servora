<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3500)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div wire:key="err-{{ microtime(true) }}" class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ session('error') }}
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
        <span class="px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-600 font-medium text-xs whitespace-nowrap">
            {{ $setCount }} / {{ $totalIngredients }} set
        </span>
        <button wire:click="exportCsv"
                class="px-3 py-2 border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Export
        </button>
    </div>

    {{-- Help tip --}}
    <div x-data="{ open: localStorage.getItem('parTipDismissed') !== '1' }" class="mb-4">
        <div x-show="open" x-collapse>
            <div class="relative bg-indigo-50/60 border border-indigo-100 rounded-xl p-4 pr-10 text-sm text-gray-600">
                <button type="button" @click="open = false; localStorage.setItem('parTipDismissed', '1')"
                        title="Dismiss" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <p class="font-semibold text-gray-700 mb-2 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    How to set up par levels
                </p>
                <ul class="space-y-1 list-disc list-inside marker:text-indigo-300">
                    <li><span class="font-medium text-gray-700">Pick the outlet</span> first — par levels are saved per outlet.</li>
                    <li><span class="font-medium text-gray-700">Type a quantity</span> in any row; it saves automatically. Press <kbd class="px-1 py-0.5 bg-white border border-gray-200 rounded text-xs">Enter</kbd> to jump to the next ingredient.</li>
                    <li><span class="font-medium text-gray-700">Need a starting point?</span> Tap <span class="text-indigo-600">≈ N ↑</span> on a row to use a value suggested from the last 3 months of purchases, or use <span class="font-medium text-gray-700">Fill blanks with suggested</span>.</li>
                    <li><span class="font-medium text-gray-700">Do many at once</span> with <span class="font-medium text-gray-700">Set all matching the filters</span> (optionally across all outlets), <span class="font-medium text-gray-700">Copy from another outlet</span>, or <span class="font-medium text-gray-700">Export → edit → Import</span> a spreadsheet.</li>
                    <li>The <span class="font-medium text-gray-700">On hand</span> column shows current stock; a <span class="text-red-600 font-medium">red value</span> means it's below par.</li>
                </ul>
            </div>
        </div>
        <button type="button" x-show="!open" @click="open = true; localStorage.removeItem('parTipDismissed')"
                class="text-xs text-indigo-500 hover:text-indigo-700 flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            How to set up par levels
        </button>
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
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Name or code..."
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
                           class="w-24 rounded-lg border-gray-300 text-sm text-right focus:ring-indigo-500 focus:border-indigo-500" />
                    <label class="inline-flex items-center gap-1.5 text-xs text-gray-500">
                        <input type="checkbox" wire:model="bulkAllOutlets" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        All outlets
                    </label>
                    <button wire:click="applyToFiltered"
                            wire:confirm="Apply this value to every ingredient matching the current filters?"
                            class="px-3 py-2 bg-gray-700 text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition disabled:opacity-40"
                            @disabled($bulkValue === '')>
                        Apply
                    </button>
                </div>
            </div>

            <div class="h-9 w-px bg-gray-200 hidden lg:block"></div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">From usage</label>
                <button wire:click="fillBlanksWithSuggested"
                        wire:confirm="Fill every blank par level (in the current filter) with a value suggested from the last 3 months of purchases?"
                        class="px-3 py-2 border border-indigo-200 text-indigo-600 text-sm font-medium rounded-lg hover:bg-indigo-50 transition">
                    Fill blanks with suggested
                </button>
            </div>

            <div class="h-9 w-px bg-gray-200 hidden lg:block"></div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Copy from another outlet</label>
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

            <div class="h-9 w-px bg-gray-200 hidden lg:block"></div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Import (CSV / Excel)</label>
                <div class="flex items-center gap-2">
                    <input type="file" wire:model="importFile" accept=".csv,.xlsx,.xls"
                           class="text-xs text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-gray-100 file:text-gray-600 hover:file:bg-gray-200" />
                    <button wire:click="importParLevels" wire:loading.attr="disabled"
                            class="px-3 py-2 bg-gray-700 text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition disabled:opacity-40"
                            @disabled(!$importFile)>
                        <span wire:loading.remove wire:target="importParLevels">Import</span>
                        <span wire:loading wire:target="importParLevels">Importing…</span>
                    </button>
                </div>
                @error('importFile') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="ml-auto flex items-center gap-2 text-xs text-gray-400">
                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Changes save automatically
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Product</th>
                        <th class="px-4 py-3 text-left w-24">Code</th>
                        <th class="px-4 py-3 text-left w-44">Category</th>
                        <th class="px-4 py-3 text-left w-20">UOM</th>
                        <th class="px-4 py-3 text-right w-28">On hand</th>
                        <th class="px-4 py-3 text-right w-48">Par Level</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($ingredients as $ingredient)
                        @php
                            $par = floatval($parLevels[$ingredient->id] ?? 0);
                            $oh  = $onHand[$ingredient->id] ?? null;
                            $belowPar = $par > 0 && $oh !== null && $oh < $par;
                            $sug = $suggested[$ingredient->id] ?? 0;
                        @endphp
                        <tr wire:key="par-row-{{ $ingredient->id }}" class="hover:bg-gray-50 transition">
                            <td class="px-4 py-2.5 font-medium text-gray-800">{{ $ingredient->name }}</td>
                            <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $ingredient->code ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $ingredient->ingredientCategory?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-gray-500">{{ $ingredient->baseUom?->abbreviation ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums {{ $belowPar ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                @if ($oh === null)
                                    —
                                @else
                                    {{ rtrim(rtrim(number_format($oh, 2), '0'), '.') }}
                                    @if ($belowPar)
                                        <span title="Below par level" class="inline-block w-1.5 h-1.5 rounded-full bg-red-500 ml-0.5 align-middle"></span>
                                    @endif
                                @endif
                            </td>
                            <td class="px-4 py-2.5"
                                x-data="{ saved: false }"
                                @par-saved.window="if ($event.detail.id == {{ $ingredient->id }}) { saved = true; setTimeout(() => saved = false, 1200) }">
                                <div class="flex items-center justify-end gap-2">
                                    <span x-show="saved" x-cloak x-transition.opacity class="text-green-600 text-xs font-medium whitespace-nowrap">Saved ✓</span>
                                    @if ($sug > 0)
                                        <button type="button" wire:click="applySuggested({{ $ingredient->id }})"
                                                title="Apply suggested ({{ rtrim(rtrim(number_format($sug, 2), '0'), '.') }} / mo from recent purchases)"
                                                class="text-xs text-indigo-500 hover:text-indigo-700 whitespace-nowrap">
                                            ≈ {{ rtrim(rtrim(number_format($sug, 2), '0'), '.') }} ↑
                                        </button>
                                    @endif
                                    <input type="number" step="0.01" min="0"
                                           wire:model.blur="parLevels.{{ $ingredient->id }}"
                                           x-on:keydown.enter.prevent="$el.blur(); const a = [...document.querySelectorAll('.par-input')]; const i = a.indexOf($el); if (a[i + 1]) a[i + 1].focus()"
                                           placeholder="0"
                                           class="par-input w-24 text-right rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-gray-400">
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

    {{-- Footer save (edits already auto-save; this is an explicit fallback) --}}
    <div class="flex justify-end mt-4">
        <button wire:click="saveAll"
                class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            Save All
        </button>
    </div>
</div>
