<div>
    <x-page-header title="Training SOPs"
                   subtitle="Standard operating procedures for recipe preparation" />

    {{-- Filters --}}
    <div class="toolbar mb-6">
        <div class="min-w-0 flex-1">
            <label class="sr-only" for="sop-search">Search recipes</label>
            <input id="sop-search" type="search" wire:model.live.debounce.300ms="search"
                   placeholder="Search recipes…" class="input sm:max-w-sm" />
        </div>
        <div>
            <label class="sr-only" for="sop-category">Filter by category</label>
            <select id="sop-category" wire:model.live="categoryFilter" class="input">
                <option value="">All categories</option>
                <option value="prep">Prep items (all)</option>
                @foreach ($categories as $cat)
                    @if ($cat->children->isNotEmpty())
                        <optgroup label="{{ $cat->name }}">
                            <option value="{{ $cat->id }}">All {{ $cat->name }}</option>
                            @foreach ($cat->children as $sub)
                                <option value="{{ $sub->id }}">{{ $sub->name }}</option>
                            @endforeach
                        </optgroup>
                    @else
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endif
                @endforeach
            </select>
        </div>
    </div>

    {{-- SOP Cards by Category --}}
    @if ($grouped->count())
        @foreach ($grouped as $categoryName => $catRecipes)
            <div class="mb-8">
                <h2 class="page-eyebrow mb-3">{{ $categoryName }}</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($catRecipes as $recipe)
                        <a href="{{ route('lms.sop.show', array_filter(['id' => $recipe->id, 'search' => $search, 'categoryFilter' => $categoryFilter], fn ($v) => $v !== '' && $v !== null)) }}"
                           class="group card card-hover overflow-hidden">
                            @php $thumb = $recipe->images->whereIn('type', ['dine_in', 'presentation'])->first(); @endphp
                            @if ($thumb)
                                <div class="h-40 overflow-hidden bg-gray-100">
                                    <img src="{{ $thumb->url() }}" alt="{{ $recipe->name }}" loading="lazy"
                                         class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" />
                                </div>
                            @else
                                {{-- Flat brand tint, not the old brand-to-purple
                                     gradient. A second accent hue on a
                                     placeholder is decoration, and it read as a
                                     status the card did not have. --}}
                                <div class="flex h-40 items-center justify-center bg-brand-50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-brand-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                            @endif
                            <div class="p-4">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="text-sm font-semibold leading-snug text-gray-900 transition-colors group-hover:text-brand-700">{{ $recipe->name }}</h3>
                                    @if ($recipe->is_prep)
                                        <span class="badge-warning flex-shrink-0">Prep</span>
                                    @endif
                                </div>
                                @if ($recipe->description)
                                    <p class="mt-1 line-clamp-2 text-xs text-gray-600">{{ $recipe->description }}</p>
                                @endif
                                <div class="mt-3 flex items-center gap-2">
                                    <span class="text-xs tabular-nums text-gray-600">{{ $recipe->steps->count() }} step{{ $recipe->steps->count() !== 1 ? 's' : '' }}</span>
                                    @if ($recipe->video_url)
                                        {{-- brand-700, not brand-500: 500 is
                                             3.16:1 and large-text only. --}}
                                        <span class="text-xs font-medium text-brand-700">Video</span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    @else
        <div class="empty-state">
            <p class="empty-title">No training SOPs yet</p>
            <p class="empty-body">They appear here once your team adds preparation steps to a recipe.</p>
        </div>
    @endif
</div>
