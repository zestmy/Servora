{{-- Alpine-powered searchable ingredient picker with inline create --}}
<div x-data="ingredientPicker({
        rIdx: {{ $rIdx }},
        lIdx: {{ $lIdx }},
        currentName: @js($currentName),
        rawName: @js($rawName),
    })"
     @ingredient-created.window="handleCreated($event.detail)"
     class="relative inline-block">

    <button type="button" @click="toggle()"
            class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] rounded border border-gray-200 bg-white hover:bg-indigo-50 hover:border-indigo-300 transition">
        <span x-text="currentName ? 'Change' : 'Select…'"></span>
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms
         @click.outside="open = false"
         @keydown.escape.window="open = false"
         class="absolute left-0 top-full mt-1 z-30 w-72 bg-white border border-gray-200 rounded-lg shadow-lg">

        <div class="p-2 border-b border-gray-100">
            <input type="text" x-model="query" x-ref="input"
                   @input="filter()"
                   @keydown.enter.prevent="onEnter()"
                   placeholder="Search ingredient or type new name…"
                   class="w-full text-xs border-gray-200 rounded px-2 py-1 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500" />
        </div>

        <ul class="max-h-60 overflow-y-auto py-1">
            <template x-for="ing in results" :key="ing.id">
                <li @click="pick(ing)"
                    class="px-3 py-1.5 text-xs hover:bg-indigo-50 cursor-pointer"
                    x-text="ing.name"></li>
            </template>

            <li x-show="results.length === 0 && query.length === 0"
                class="px-3 py-2 text-[11px] text-gray-400 italic">
                Type to search existing ingredients…
            </li>

            <li x-show="query.length > 0 && !exactMatch()"
                @click="requestCreate()"
                class="px-3 py-2 text-xs text-indigo-600 hover:bg-indigo-50 cursor-pointer border-t border-gray-100 font-medium">
                + Create "<span x-text="query" class="font-semibold"></span>" as new ingredient
            </li>
        </ul>
    </div>
</div>
