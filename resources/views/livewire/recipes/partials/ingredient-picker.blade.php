{{-- Alpine searchable ingredient picker (popup teleported to body to avoid card overflow clipping) --}}
<div x-data="ingredientPicker({
        rIdx: {{ $rIdx }},
        lIdx: {{ $lIdx }},
        currentName: @js($currentName),
        rawName: @js($rawName),
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
                       placeholder="Search existing or type new name…"
                       class="w-full text-xs border-gray-200 rounded px-2 py-1.5 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500" />
            </div>

            <ul class="max-h-64 overflow-y-auto py-1" x-ref="list">
                <template x-for="(ing, idx) in results" :key="ing.id">
                    <li @click.stop="pick(ing)"
                        @mouseenter="highlightIdx = idx"
                        :class="idx === highlightIdx ? 'bg-indigo-100 text-indigo-900' : 'hover:bg-indigo-50'"
                        class="px-3 py-1.5 text-xs cursor-pointer"
                        x-text="ing.name"></li>
                </template>

                <li x-show="results.length === 0 && query.length === 0" x-cloak
                    class="px-3 py-2 text-[11px] text-gray-400 italic">
                    Start typing to search…
                </li>
                <li x-show="results.length === 0 && query.length > 0 && exactMatch()" x-cloak
                    class="px-3 py-2 text-[11px] text-gray-400 italic">
                    No close matches — existing "<span x-text="query"></span>" used.
                </li>
                <li x-show="results.length === 0 && query.length > 0 && !exactMatch()" x-cloak
                    class="px-3 py-2 text-[11px] text-gray-400 italic">
                    No matches found.
                </li>

                <li x-show="query.length > 0 && !exactMatch()" x-cloak
                    @click.stop="requestCreate()"
                    class="px-3 py-2 text-xs text-indigo-600 hover:bg-indigo-50 cursor-pointer border-t border-gray-100 font-medium">
                    + Create "<span x-text="query" class="font-semibold"></span>" as new ingredient
                </li>
            </ul>
        </div>
    </template>
</div>
