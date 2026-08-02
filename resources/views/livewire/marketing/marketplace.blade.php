{{--
    Search and results, not a landing page. Livewire bindings are preserved
    exactly as they were: wire:model.live.debounce.400ms on search, and
    wire:model.live on each filter.

    What changed is state coverage. Live search previously gave no feedback
    at all between keystroke and re-render, so on a slow connection the page
    looked frozen with stale results on screen.
--}}
<div>
    {{-- ── Search ──────────────────────────────────────────────────────── --}}
    <section class="border-b border-gray-200 bg-gradient-to-b from-brand-50/70 to-white">
        <div class="mx-auto max-w-4xl px-4 py-14 text-center sm:px-6 lg:px-8">
            <h1 class="display-2 text-gray-950">F&amp;B product marketplace</h1>
            <p class="mx-auto mt-4 max-w-prose text-base leading-relaxed text-gray-600">
                Ingredients and supplies from verified suppliers across Malaysia. Compare what
                things actually cost before you commit.
            </p>

            <div class="relative mx-auto mt-8 max-w-2xl">
                <label for="marketplace-search" class="sr-only">Search products</label>
                <x-icon name="cart" size="h-5 w-5"
                        class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-gray-500" />

                <input id="marketplace-search" type="search"
                       wire:model.live.debounce.400ms="search"
                       placeholder="Prawn, flour, cooking oil"
                       autocomplete="off"
                       class="input py-4 pl-12 pr-12 text-base shadow-e2">

                {{-- Spinner sits inside the field, so feedback appears where
                     the user is already looking. --}}
                <span wire:loading wire:target="search"
                      class="absolute right-4 top-1/2 -translate-y-1/2" aria-hidden="true">
                    <svg class="h-5 w-5 animate-spin text-brand-600" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                        <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/>
                    </svg>
                </span>
            </div>

            <dl class="mt-8 flex flex-wrap items-center justify-center gap-x-10 gap-y-4">
                @foreach ([[number_format($totalProducts), 'Products'], [number_format($totalSuppliers), 'Verified suppliers'], [number_format($totalCategories), 'Categories']] as [$value, $label])
                    <div>
                        <dd class="tabular text-2xl font-bold tracking-tight text-gray-950">{{ $value }}</dd>
                        <dt class="mt-0.5 text-xs text-gray-600">{{ $label }}</dt>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- ── Filters ─────────────────────────────────────────────────────────
         top-16 clears the site header, which is sticky at that height. At
         top-0 this bar slid underneath it and the controls became
         unreachable while scrolling.
    --}}
    <section class="sticky top-16 z-sticky border-b border-gray-200 bg-white/95 backdrop-blur-md">
        <div class="mx-auto max-w-6xl px-4 py-3 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center gap-3">
                <label for="filter-category" class="sr-only">Category</label>
                <select id="filter-category" wire:model.live="categoryFilter" class="input w-auto py-2 text-sm">
                    <option value="">All categories</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat }}">{{ $cat }}</option>
                    @endforeach
                </select>

                <label for="filter-state" class="sr-only">Location</label>
                <select id="filter-state" wire:model.live="stateFilter" class="input w-auto py-2 text-sm">
                    <option value="">All locations</option>
                    @foreach ($states as $st)
                        <option value="{{ $st }}">{{ $st }}</option>
                    @endforeach
                </select>

                <label for="filter-sort" class="sr-only">Sort by</label>
                <select id="filter-sort" wire:model.live="sortBy" class="input w-auto py-2 text-sm">
                    <option value="relevance">Most relevant</option>
                    <option value="price_low">Price: low to high</option>
                    <option value="price_high">Price: high to low</option>
                </select>

                <p class="ml-auto text-sm text-gray-600" aria-live="polite">
                    <span wire:loading.remove wire:target="search,categoryFilter,stateFilter,sortBy">
                        {{ number_format($products->total()) }} {{ Str::plural('result', $products->total()) }}
                    </span>
                    <span wire:loading wire:target="search,categoryFilter,stateFilter,sortBy">Searching</span>
                </p>
            </div>
        </div>
    </section>

    {{-- ── Results ─────────────────────────────────────────────────────── --}}
    <section class="bg-gray-50 py-10">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">

            {{-- Loading. Skeletons match the real card shape so the grid does
                 not jump when results land. --}}
            <div wire:loading.grid wire:target="search,categoryFilter,stateFilter,sortBy"
                 class="grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @for ($i = 0; $i < 8; $i++)
                    <div class="card p-5">
                        <div class="skeleton h-4 w-20"></div>
                        <div class="skeleton mt-3 h-4 w-full"></div>
                        <div class="skeleton mt-2 h-4 w-2/3"></div>
                        <div class="skeleton mt-5 h-7 w-28"></div>
                        <div class="skeleton mt-4 h-9 w-full"></div>
                    </div>
                @endfor
            </div>

            <div wire:loading.remove wire:target="search,categoryFilter,stateFilter,sortBy">
                @if ($products->count() > 0)
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        @foreach ($products as $p)
                            @php
                                $stats = $productStats[$p->id] ?? ['supplier_count' => 1, 'min_price' => $p->unit_price, 'max_price' => $p->unit_price];
                                $uom   = $p->uom?->abbreviation ?? 'unit';
                                $trim  = fn ($n) => rtrim(rtrim(number_format($n, 2), '0'), '.');
                            @endphp

                            <article wire:key="product-{{ $p->id }}" class="card card-hover flex flex-col">
                                <div class="flex flex-1 flex-col p-5">
                                    @if ($p->category)
                                        <span class="badge-brand self-start">{{ $p->category }}</span>
                                    @endif

                                    <h2 class="mt-2 text-sm font-semibold leading-snug text-gray-950">{{ $p->name }}</h2>

                                    <p class="tabular mt-3 text-lg font-bold text-gray-950">
                                        RM {{ number_format($stats['min_price'], 2) }}@if ($stats['min_price'] != $stats['max_price'])<span class="font-normal text-gray-600"> to {{ number_format($stats['max_price'], 2) }}</span>@endif
                                    </p>
                                    <p class="mt-0.5 text-xs text-gray-600">per {{ $uom }}</p>

                                    {{-- Supplier count as a plain fact. The row of
                                         placeholder avatar circles it replaces stood
                                         in for people who were never shown. --}}
                                    <p class="mt-3 text-xs font-medium text-gray-700">
                                        {{ $stats['supplier_count'] }} verified
                                        {{ Str::plural('supplier', $stats['supplier_count']) }}
                                    </p>

                                    @if ($p->min_order_quantity > 1 || $p->pack_size > 1 || $p->lead_time_days)
                                        <div class="mt-3 flex flex-wrap gap-1.5">
                                            @if ($p->min_order_quantity > 1)
                                                <span class="badge-warning">Min {{ $trim($p->min_order_quantity) }} {{ $uom }}</span>
                                            @endif
                                            @if ($p->pack_size > 1)
                                                <span class="badge-neutral">Pack {{ $trim($p->pack_size) }}</span>
                                            @endif
                                            @if ($p->lead_time_days)
                                                <span class="badge-neutral">{{ $p->lead_time_days }} day lead</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                <div class="border-t border-gray-100 p-4">
                                    <a href="{{ route('saas.register') }}" class="btn-primary btn-sm w-full">
                                        Sign up to order
                                    </a>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    @if ($products->hasPages())
                        <div class="mt-10">{{ $products->links() }}</div>
                    @endif
                @else
                    <div class="empty-state">
                        <x-icon name="cart" size="h-8 w-8" class="text-gray-500" />
                        <p class="empty-title">No products match that search</p>
                        <p class="empty-body">
                            @if ($search || $categoryFilter || $stateFilter)
                                Try a broader term, or clear the filters to see everything listed.
                            @else
                                Nothing is listed yet. Suppliers are still being onboarded.
                            @endif
                        </p>
                        @if ($search || $categoryFilter || $stateFilter)
                            <button type="button"
                                    wire:click="$set('search', ''); $set('categoryFilter', ''); $set('stateFilter', '')"
                                    class="btn-secondary btn-sm mt-1">
                                Clear filters
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- ── Close ───────────────────────────────────────────────────────── --}}
    <section class="bg-gray-950">
        <div class="mx-auto max-w-3xl px-4 py-16 text-center sm:px-6 lg:px-8">
            <h2 class="display-3 text-white">See who sells it, and order from here</h2>
            <p class="mx-auto mt-4 max-w-prose leading-relaxed text-gray-300">
                Signing up unlocks supplier names, side-by-side price comparison, and purchase
                orders raised from the same screen.
            </p>
            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('saas.register') }}" class="btn-primary btn-lg">Start free trial</a>
                <a href="{{ route('for-suppliers') }}" class="btn-on-dark btn-lg">I sell to kitchens</a>
            </div>
        </div>
    </section>
</div>
