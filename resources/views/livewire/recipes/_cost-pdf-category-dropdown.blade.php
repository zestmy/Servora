{{-- Export recipe/prep cost PDF by category. Expects $recipeCategories (tab-appropriate) and $isPrep in scope. --}}
@php
    $costPdfRoute = $isPrep ? 'recipes.prep-cost-pdf-all' : 'recipes.cost-pdf-all';
    $hasGroups    = $recipeCategories->contains(fn ($c) => $c->children && $c->children->count());
    $pdfIcon      = 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
@endphp
@if ($recipeCategories->count())
    <div x-data="{ open: false }" class="relative">
        <button @click="open = !open" type="button"
                class="px-2.5 md:px-3 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $pdfIcon }}" /></svg>
            <span class="hidden sm:inline">By Category</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
        </button>
        <div x-show="open" @click.away="open = false" x-transition style="display:none"
             class="absolute right-0 mt-1 w-64 max-h-80 overflow-y-auto bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
            @if ($hasGroups)
                <p class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100">Top-tier</p>
                @foreach ($recipeCategories as $cat)
                    @if ($cat->children && $cat->children->count())
                        <a href="{{ route($costPdfRoute, ['category' => $cat->id]) }}" target="_blank"
                           class="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 flex-shrink-0 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $pdfIcon }}" /></svg>
                            All {{ $cat->name }}
                        </a>
                    @endif
                @endforeach
            @endif
            <p class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100">Single category</p>
            @foreach ($recipeCategories as $cat)
                @if ($cat->children && $cat->children->count())
                    @foreach ($cat->children as $sub)
                        <a href="{{ route($costPdfRoute, ['category' => $sub->id]) }}" target="_blank"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 flex-shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $pdfIcon }}" /></svg>
                            {{ $sub->name }}
                        </a>
                    @endforeach
                @else
                    <a href="{{ route($costPdfRoute, ['category' => $cat->id]) }}" target="_blank"
                       class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 flex-shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $pdfIcon }}" /></svg>
                        {{ $cat->name }}
                    </a>
                @endif
            @endforeach
        </div>
    </div>
@endif
