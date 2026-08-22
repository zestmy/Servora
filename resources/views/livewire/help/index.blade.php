<div>
    <section class="border-b border-gray-200 bg-gradient-to-b from-brand-50/60 to-white">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-14 sm:py-20 text-center">
            <p class="page-eyebrow">Help Centre</p>
            <h1 class="display-3 mt-2">How to use Servora</h1>
            <p class="mt-3 text-base text-gray-600 max-w-2xl mx-auto">
                Step-by-step guides for everything in the product — from costing your first recipe
                to running payroll and printing food-safety labels.
            </p>

            <div class="mt-8 max-w-xl mx-auto">
                <label for="help-search" class="sr-only">Search the help centre</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                        <x-icon name="magnifier" size="h-5 w-5" />
                    </span>
                    <input id="help-search" type="search" wire:model.live.debounce.350ms="q"
                           class="input pl-10 py-3 text-base"
                           placeholder="Search — purchase order, wastage, shelf life, payroll…"
                           autocomplete="off" />
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-12">
        @if ($results !== null)
            {{-- Search results --}}
            <h2 class="text-sm font-semibold text-gray-800 mb-4">
                {{ $results->total() }} {{ Str::plural('result', $results->total()) }} for “{{ $q }}”
            </h2>

            @if ($results->isEmpty())
                <div class="empty-state">
                    <x-icon name="magnifier" size="h-8 w-8" class="text-gray-500" />
                    <p class="font-medium text-gray-700">Nothing matched that</p>
                    <p class="text-xs text-gray-600 max-w-sm">
                        Try a shorter phrase, or browse the sections below. If the answer really isn't
                        here, tell us — the manual is edited in-house and gaps get filled.
                    </p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($results as $article)
                        <a href="{{ $article->url() }}" wire:navigate
                           class="card card-hover block p-5">
                            <p class="page-eyebrow">{{ $article->category?->title }}</p>
                            <h3 class="mt-1 text-base font-semibold text-gray-900">{{ $article->title }}</h3>
                            <p class="mt-1 text-sm text-gray-600">{{ $article->teaser() }}</p>
                        </a>
                    @endforeach
                </div>

                @if ($results->hasPages())
                    <div class="mt-6">{{ $results->links() }}</div>
                @endif
            @endif
        @else
            {{-- Browse --}}
            <h2 class="text-sm font-semibold text-gray-800 mb-4">Browse by area</h2>

            @if ($categories->isEmpty())
                <div class="empty-state">
                    <x-icon name="book-open" size="h-8 w-8" class="text-gray-500" />
                    <p class="font-medium text-gray-700">The manual is being written</p>
                    <p class="text-xs text-gray-600">Check back shortly.</p>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($categories as $category)
                        <a href="{{ $category->url() }}" wire:navigate class="card card-hover p-5 flex gap-4">
                            <span class="flex h-10 w-10 flex-none items-center justify-center rounded-control bg-brand-50 text-brand-700">
                                <x-icon :name="$category->icon ?: 'book-open'" size="h-5 w-5" />
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-gray-900">{{ $category->title }}</span>
                                @if ($category->summary)
                                    <span class="mt-1 block text-xs leading-relaxed text-gray-600">{{ $category->summary }}</span>
                                @endif
                                <span class="mt-2 block text-[11px] font-medium uppercase tracking-wider text-gray-500">
                                    {{ $category->published_articles_count }}
                                    {{ Str::plural('guide', $category->published_articles_count) }}
                                </span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($popular->isNotEmpty())
                <h2 class="text-sm font-semibold text-gray-800 mt-12 mb-4">Start here</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($popular as $article)
                        <a href="{{ $article->url() }}" wire:navigate
                           class="list-row rounded-surface border border-gray-200 bg-white hover:bg-gray-50">
                            <x-icon name="document" size="h-4 w-4" class="flex-none text-gray-500" />
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-gray-900">{{ $article->title }}</span>
                                <span class="block truncate text-xs text-gray-600">{{ $article->category?->title }}</span>
                            </span>
                            <x-icon name="chevron-right" size="h-4 w-4" class="ml-auto flex-none text-gray-500" />
                        </a>
                    @endforeach
                </div>
            @endif
        @endif
    </section>
</div>
