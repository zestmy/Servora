<div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
    <nav class="mb-6 flex items-center gap-1.5 text-xs text-gray-600" aria-label="Breadcrumb">
        <a href="{{ route('help.index') }}" wire:navigate class="hover:text-gray-900">Help Centre</a>
        <x-icon name="chevron-right" size="h-3 w-3" class="text-gray-500" />
        <span class="text-gray-900 font-medium">{{ $category->title }}</span>
    </nav>

    <div class="grid gap-10 lg:grid-cols-[16rem_1fr]">
        {{-- Section list. Ordinary links, not a JS widget: this is the whole
             navigation of a public documentation site and it must work with
             no JavaScript at all. --}}
        <aside class="hidden lg:block">
            <p class="stat-label mb-3">Sections</p>
            <ul class="space-y-1">
                @foreach ($categories as $item)
                    <li>
                        <a href="{{ $item->url() }}" wire:navigate
                           class="flex items-center gap-2 rounded-control px-3 py-2 text-sm transition
                                  {{ $item->id === $category->id
                                      ? 'bg-brand-50 font-medium text-brand-800'
                                      : 'text-gray-700 hover:bg-gray-50' }}">
                            <x-icon :name="$item->icon ?: 'book-open'" size="h-4 w-4" />
                            {{ $item->title }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </aside>

        <div class="min-w-0">
            <div class="flex items-start gap-4">
                <span class="flex h-12 w-12 flex-none items-center justify-center rounded-surface bg-brand-50 text-brand-700">
                    <x-icon :name="$category->icon ?: 'book-open'" size="h-6 w-6" />
                </span>
                <div>
                    <h1 class="display-3">{{ $category->title }}</h1>
                    @if ($category->summary)
                        <p class="mt-2 text-base text-gray-600">{{ $category->summary }}</p>
                    @endif
                </div>
            </div>

            <div class="mt-8 space-y-3">
                @forelse ($articles as $article)
                    <a href="{{ $article->url() }}" wire:navigate class="card card-hover block p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <h2 class="text-base font-semibold text-gray-900">{{ $article->title }}</h2>
                                <p class="mt-1 text-sm text-gray-600">{{ $article->teaser(180) }}</p>
                            </div>
                            <span class="flex-none text-xs text-gray-500">{{ $article->readingMinutes() }} min</span>
                        </div>
                    </a>
                @empty
                    <div class="empty-state">
                        <x-icon name="book-open" size="h-8 w-8" class="text-gray-500" />
                        <p class="font-medium text-gray-700">Nothing published in this section yet</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
