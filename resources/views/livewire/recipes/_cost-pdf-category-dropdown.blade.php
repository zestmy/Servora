{{-- Export recipe/prep cost PDF by category. Expects $recipeCategories (tab-appropriate) and $isPrep in scope. --}}
@php
    $costPdfRoute = $isPrep ? 'recipes.prep-cost-pdf-all' : 'recipes.cost-pdf-all';
    $hasGroups    = $recipeCategories->contains(fn ($c) => $c->children && $c->children->count());
    $pdfIcon      = 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
@endphp
@if ($recipeCategories->count())
    <div x-data="{ open: false }" class="relative">
        <button @click="open = !open" type="button"
                class="btn-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $pdfIcon }}" /></svg>
            <span class="hidden sm:inline">By Category</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
        </button>
        <div x-show="open" @click.away="open = false" x-transition style="display:none"
             class="absolute right-0 mt-1 w-64 max-h-80 overflow-y-auto bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
            @if ($hasGroups)
                <p class="px-4 py-2 text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-100">Top-tier</p>
                @foreach ($recipeCategories as $cat)
                    @if ($cat->children && $cat->children->count())
                        <x-download-link href="{{ route($costPdfRoute, ['category' => $cat->id]) }}"
                           class="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-brand-50 hover:text-brand-700 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 flex-shrink-0 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $pdfIcon }}" /></svg>
                            All {{ $cat->name }}
                        </x-download-link>
                    @endif
                @endforeach
            @endif
            <p class="px-4 py-2 text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-100">Single category</p>
            @foreach ($recipeCategories as $cat)
                @if ($cat->children && $cat->children->count())
                    @foreach ($cat->children as $sub)
                        <x-download-link href="{{ route($costPdfRoute, ['category' => $sub->id]) }}"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-brand-50 hover:text-brand-700 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 flex-shrink-0 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $pdfIcon }}" /></svg>
                            {{ $sub->name }}
                        </x-download-link>
                    @endforeach
                @else
                    <x-download-link href="{{ route($costPdfRoute, ['category' => $cat->id]) }}"
                       class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-brand-50 hover:text-brand-700 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 flex-shrink-0 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $pdfIcon }}" /></svg>
                        {{ $cat->name }}
                    </x-download-link>
                @endif
            @endforeach
        </div>
    </div>
@endif
