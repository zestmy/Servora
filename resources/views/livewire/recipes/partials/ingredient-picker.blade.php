{{-- Alpine searchable ingredient picker (popup teleported to body to avoid card overflow clipping) --}}
<div x-data="ingredientPicker({
        rIdx: {{ $rIdx }},
        lIdx: {{ $lIdx }},
        currentName: @js($currentName),
        rawName: @js($rawName),
        isUnmatched: {{ empty($currentName) ? 'true' : 'false' }},
    })"
     @ingredient-created.window="handleCreated($event.detail)"
     class="relative inline-block">

    <button x-ref="trigger" type="button" @click.stop="toggle()"
            class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] rounded border border-gray-200 bg-white hover:bg-indigo-50 hover:border-indigo-300 transition">
        <span x-text="currentName ? 'Change' : 'Select ingredient…'"></span>
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <template x-teleport="body">
        <div x-show="open" x-cloak
             @click.outside="handleOutsideClick($event)"
             @keydown.escape.window="open = false"
             :style="popupStyle"
             class="fixed z-[80] w-80 bg-white border border-gray-200 rounded-lg shadow-xl text-gray-800">

            <div class="p-2 border-b border-gray-100">
                <input type="text" x-model="query" x-ref="input"
                       @keydown.arrow-down.prevent="moveHighlight(1)"
                       @keydown.arrow-up.prevent="moveHighlight(-1)"
                       @keydown.enter.prevent="onEnter()"
                       placeholder="Search existing ingredient…"
                       class="w-full text-xs border-gray-200 rounded px-2 py-1.5 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500" />
            </div>

            {{-- Search results --}}
            <ul x-show="results.length > 0" class="max-h-64 overflow-y-auto py-1" x-ref="list">
                <template x-for="(ing, idx) in results" :key="ing.id">
                    <li @click.stop="pick(ing)"
                        @mouseenter="highlightIdx = idx"
                        :class="idx === highlightIdx ? 'bg-indigo-100 text-indigo-900' : 'hover:bg-indigo-50'"
                        class="px-3 py-1.5 text-xs cursor-pointer"
                        x-text="ing.name"></li>
                </template>
            </ul>

            {{-- Empty state (no query yet) --}}
            <div x-show="results.length === 0 && query.trim().length === 0" x-cloak
                 class="px-3 py-3 text-[11px] text-gray-400 italic text-center">
                Start typing to search ingredients…
            </div>

            {{-- Zero-results state: offer create + optional remove-line --}}
            <div x-show="results.length === 0 && query.trim().length > 0" x-cloak
                 class="divide-y divide-gray-100">
                <div class="px-3 py-2 text-[11px] text-gray-500 bg-gray-50">
                    No matching ingredient found for "<span x-text="query.trim()" class="font-semibold text-gray-700"></span>".
                </div>

                <button type="button" @click.stop="requestCreate()"
                        class="w-full flex items-center gap-2 px-3 py-2.5 text-xs text-indigo-600 hover:bg-indigo-50 transition font-medium text-left">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Create "<span x-text="query.trim()" class="font-semibold"></span>" as new ingredient</span>
                </button>

                <button type="button" x-show="isUnmatched" @click.stop="removeLine()"
                        class="w-full flex items-center gap-2 px-3 py-2.5 text-xs text-red-600 hover:bg-red-50 transition text-left">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3" />
                    </svg>
                    <span>Remove this ingredient line</span>
                </button>
            </div>
        </div>
    </template>
</div>
