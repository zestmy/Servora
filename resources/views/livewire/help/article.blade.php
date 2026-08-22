<div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
    <nav class="mb-6 flex flex-wrap items-center gap-1.5 text-xs text-gray-600" aria-label="Breadcrumb">
        <a href="{{ route('help.index') }}" wire:navigate class="hover:text-gray-900">Help Centre</a>
        <x-icon name="chevron-right" size="h-3 w-3" class="text-gray-500" />
        <a href="{{ $category->url() }}" wire:navigate class="hover:text-gray-900">{{ $category->title }}</a>
        <x-icon name="chevron-right" size="h-3 w-3" class="text-gray-500" />
        <span class="text-gray-900 font-medium">{{ $article->title }}</span>
    </nav>

    <div class="grid gap-10 lg:grid-cols-[16rem_1fr]">
        {{-- In-section contents, so the reader can see where this article sits
             in the sequence rather than having to go back to find out. --}}
        <aside class="hidden lg:block">
            <p class="stat-label mb-3">{{ $category->title }}</p>
            <ul class="space-y-1">
                @foreach ($siblings as $sibling)
                    <li>
                        <a href="{{ $sibling->url() }}" wire:navigate
                           class="block rounded-control px-3 py-2 text-sm leading-snug transition
                                  {{ $sibling->id === $article->id
                                      ? 'bg-brand-50 font-medium text-brand-800'
                                      : 'text-gray-700 hover:bg-gray-50' }}">
                            {{ $sibling->title }}
                        </a>
                    </li>
                @endforeach
            </ul>

            <a href="{{ route('help.index') }}" wire:navigate
               class="mt-6 inline-flex items-center gap-1.5 text-xs font-medium text-brand-700 hover:text-brand-800">
                <x-icon name="chevron-left" size="h-3 w-3" />
                All sections
            </a>
        </aside>

        <article class="min-w-0">
            <header>
                <p class="page-eyebrow">{{ $category->title }}</p>
                <h1 class="display-3 mt-1">{{ $article->title }}</h1>
                @if ($article->excerpt)
                    <p class="mt-3 text-base text-gray-600">{{ $article->excerpt }}</p>
                @endif
                <p class="mt-4 text-xs text-gray-500">
                    {{ $article->readingMinutes() }} min read
                    · Last reviewed {{ $article->updated_at?->format('d M Y') }}
                </p>
            </header>

            @if ($article->hero_image)
                <img src="{{ $article->hero_image }}" alt=""
                     class="mt-8 w-full rounded-surface border border-gray-200 shadow-e1" />
            @endif

            {{--
                The body is HTML written by a system admin in /admin/docs —
                the same trust model as the marketing CMS pages, and the same
                reason it is rendered raw: headings, lists, tables and figures
                are the whole point of a manual.

                prose-img and prose-figure are styled here rather than in the
                editor's markup so a figure looks the same whether it was
                inserted by the upload button or typed by hand.
            --}}
            <div class="doc-prose prose prose-sm sm:prose max-w-none mt-8
                        prose-headings:text-gray-900 prose-headings:font-semibold
                        prose-p:text-gray-700 prose-li:text-gray-700 prose-strong:text-gray-900
                        prose-a:text-brand-700 prose-a:font-medium
                        prose-code:text-brand-800 prose-code:bg-brand-50 prose-code:px-1 prose-code:py-0.5
                        prose-code:rounded prose-code:before:content-none prose-code:after:content-none
                        prose-img:rounded-surface prose-img:border prose-img:border-gray-200 prose-img:shadow-e1
                        prose-figcaption:text-xs prose-figcaption:text-gray-600 prose-figcaption:text-center
                        prose-th:text-gray-900 prose-td:text-gray-700
                        prose-blockquote:border-brand-300 prose-blockquote:text-gray-700">
                {!! $article->body !!}
            </div>

            {{-- Prev / next. A manual is read in order more often than it is
                 searched, and the sidebar is hidden below lg. --}}
            @if ($previous || $next)
                <nav class="mt-12 grid gap-3 sm:grid-cols-2 border-t border-gray-200 pt-6"
                     aria-label="Article navigation">
                    @if ($previous)
                        <a href="{{ $previous->url() }}" wire:navigate class="card card-hover p-4">
                            <span class="stat-label">Previous</span>
                            <span class="mt-1 block text-sm font-medium text-gray-900">{{ $previous->title }}</span>
                        </a>
                    @else
                        <span></span>
                    @endif

                    @if ($next)
                        <a href="{{ $next->url() }}" wire:navigate class="card card-hover p-4 sm:text-right">
                            <span class="stat-label">Next</span>
                            <span class="mt-1 block text-sm font-medium text-gray-900">{{ $next->title }}</span>
                        </a>
                    @endif
                </nav>
            @endif

            <div class="mt-8 rounded-surface border border-brand-200 bg-brand-50 p-5">
                <p class="text-sm font-medium text-brand-900">Still stuck?</p>
                <p class="mt-1 text-sm text-brand-800">
                    Every screen in Servora has the same shape — a filter strip, a table, and the
                    actions on the right. If a guide is missing or out of date, tell your account
                    manager and it gets fixed in the manual, not just in an email.
                </p>
            </div>
        </article>
    </div>
</div>
