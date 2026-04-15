<div>
    {{-- Hero --}}
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Training SOPs</h1>
        <p class="text-sm text-gray-500 mt-1">Standard Operating Procedures for recipe preparation</p>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col sm:flex-row gap-3 mb-6">
        <div class="flex-1">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search recipes..."
                   class="w-full sm:max-w-sm rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <div>
            <select wire:model.live="categoryFilter"
                    class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All Categories</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat }}">{{ $cat }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- SOP Cards by Category --}}
    @if ($grouped->count())
        @foreach ($grouped as $categoryName => $catRecipes)
            <div class="mb-8">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">{{ $categoryName }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @foreach ($catRecipes as $recipe)
                        <a href="{{ route('lms.sop.show', $recipe->id) }}"
                           class="group bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md hover:border-indigo-300 transition">
                            @php $thumb = $recipe->images->where('type', 'dine_in')->first(); @endphp
                            @if ($thumb)
                                <div class="h-40 bg-gray-100 overflow-hidden">
                                    <img src="{{ $thumb->url() }}" alt="{{ $recipe->name }}"
                                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                                </div>
                            @else
                                <div class="h-40 bg-gradient-to-br from-indigo-50 to-purple-50 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-indigo-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                            @endif
                            <div class="p-4">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="font-semibold text-gray-800 text-sm group-hover:text-indigo-600 transition">{{ $recipe->name }}</h3>
                                    @if ($recipe->is_prep)
                                        <span class="flex-shrink-0 px-2 py-0.5 bg-amber-100 text-amber-700 text-[10px] font-bold rounded-full tracking-wider">PREP</span>
                                    @endif
                                </div>
                                @if ($recipe->description)
                                    <p class="text-xs text-gray-500 mt-1 line-clamp-2">{{ $recipe->description }}</p>
                                @endif
                                <div class="flex items-center gap-2 mt-3">
                                    <span class="text-xs text-gray-400">{{ $recipe->steps->count() }} step{{ $recipe->steps->count() !== 1 ? 's' : '' }}</span>
                                    @if ($recipe->video_url)
                                        <span class="text-xs text-indigo-500 font-medium">Video</span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center text-gray-400">
            <p class="font-medium">No training SOPs available yet.</p>
            <p class="text-xs mt-1">SOPs will appear here once your team adds preparation steps to recipes.</p>
        </div>
    @endif
</div>
