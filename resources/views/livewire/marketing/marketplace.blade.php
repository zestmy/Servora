<div>
    {{-- Hero --}}
    <section class="bg-gradient-to-r from-gray-900 to-gray-800 text-white py-12">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-3xl sm:text-4xl font-bold mb-3">F&B Product Marketplace</h1>
            <p class="text-gray-300 max-w-2xl mx-auto mb-8">Search thousands of ingredients and supplies from verified suppliers across Malaysia. Compare prices and find the best deals for your business.</p>

            {{-- Search bar --}}
            <div class="max-w-2xl mx-auto relative">
                <input type="text" wire:model.live.debounce.400ms="search"
                       placeholder="Search for products... e.g. Prawn, Flour, Cooking Oil"
                       class="w-full pl-12 pr-4 py-4 rounded-xl border-0 text-gray-800 text-lg shadow-lg focus:ring-2 focus:ring-indigo-500" />
                <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            {{-- Stats --}}
            <div class="flex justify-center gap-8 mt-8 text-sm">
                <div><span class="text-2xl font-bold text-white">{{ number_format($totalProducts) }}</span><br><span class="text-gray-400">Products</span></div>
                <div><span class="text-2xl font-bold text-white">{{ $totalSuppliers }}</span><br><span class="text-gray-400">Verified Suppliers</span></div>
                <div><span class="text-2xl font-bold text-white">{{ $totalCategories }}</span><br><span class="text-gray-400">Categories</span></div>
            </div>
        </div>
    </section>

    {{-- Filters --}}
    <section class="bg-white border-b border-gray-100 sticky top-0 z-20">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <div class="flex flex-wrap gap-3 items-center">
                <select wire:model.live="categoryFilter" class="rounded-lg border-gray-300 text-sm">
                    <option value="">All Categories</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat }}">{{ $cat }}</option>
                    @endforeach
                </select>
                <select wire:model.live="stateFilter" class="rounded-lg border-gray-300 text-sm">
                    <option value="">All Locations</option>
                    @foreach ($states as $st)
                        <option value="{{ $st }}">{{ $st }}</option>
                    @endforeach
                </select>
                <select wire:model.live="sortBy" class="rounded-lg border-gray-300 text-sm">
                    <option value="relevance">Most Relevant</option>
                    <option value="price_low">Price: Low to High</option>
                    <option value="price_high">Price: High to Low</option>
                </select>
                <span class="text-sm text-gray-400 ml-auto">{{ $products->total() }} {{ Str::plural('result', $products->total()) }}</span>
            </div>
        </div>
    </section>

    {{-- Product Grid --}}
    <section class="bg-gray-50 py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            @if ($products->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @foreach ($products as $p)
                        @php $stats = $productStats[$p->id] ?? ['supplier_count' => 1, 'min_price' => $p->unit_price, 'max_price' => $p->unit_price]; @endphp
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition group">
                            {{-- Product header --}}
                            <div class="p-5">
                                @if ($p->category)
                                    <span class="inline-block px-2 py-0.5 bg-indigo-50 text-indigo-600 text-[11px] font-medium rounded mb-2">{{ $p->category }}</span>
                                @endif
                                <h3 class="text-sm font-semibold text-gray-800 leading-snug mb-1">{{ $p->name }}</h3>

                                {{-- Price display --}}
                                <div class="mt-3">
                                    @if ($stats['min_price'] === $stats['max_price'])
                                        <p class="text-lg font-bold text-gray-900">RM {{ number_format($stats['min_price'], 2) }}</p>
                                    @else
                                        <p class="text-lg font-bold text-gray-900">RM {{ number_format($stats['min_price'], 2) }} <span class="text-sm font-normal text-gray-400">— {{ number_format($stats['max_price'], 2) }}</span></p>
                                    @endif
                                    <p class="text-xs text-gray-400 mt-0.5">per {{ $p->uom?->abbreviation ?? 'unit' }}</p>
                                </div>

                                {{-- Supplier count --}}
                                <div class="mt-3 flex items-center gap-2">
                                    <div class="flex -space-x-1.5">
                                        @for ($i = 0; $i < min($stats['supplier_count'], 3); $i++)
                                            <div class="w-5 h-5 rounded-full bg-gray-200 border-2 border-white flex items-center justify-center">
                                                <svg class="w-3 h-3 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
                                            </div>
                                        @endfor
                                    </div>
                                    <span class="text-xs text-gray-500">
                                        {{ $stats['supplier_count'] }} verified {{ Str::plural('supplier', $stats['supplier_count']) }}
                                    </span>
                                </div>

                                {{-- MOQ & details --}}
                                <div class="mt-3 flex flex-wrap gap-2 text-[11px]">
                                    @if ($p->min_order_quantity > 1)
                                        <span class="px-2 py-0.5 bg-amber-50 text-amber-600 rounded">MOQ: {{ rtrim(rtrim(number_format($p->min_order_quantity, 2), '0'), '.') }} {{ $p->uom?->abbreviation ?? '' }}</span>
                                    @endif
                                    @if ($p->pack_size > 1)
                                        <span class="px-2 py-0.5 bg-gray-100 text-gray-500 rounded">Pack: {{ rtrim(rtrim(number_format($p->pack_size, 2), '0'), '.') }}</span>
                                    @endif
                                    @if ($p->lead_time_days)
                                        <span class="px-2 py-0.5 bg-blue-50 text-blue-600 rounded">{{ $p->lead_time_days }}d lead time</span>
                                    @endif
                                </div>
                            </div>

                            {{-- CTA --}}
                            <div class="px-5 py-3 bg-gray-50 border-t border-gray-100">
                                <a href="{{ route('saas.register') }}"
                                   class="block w-full text-center px-4 py-2 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 transition">
                                    Get Best Price — Sign Up Free
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($products->hasPages())
                    <div class="mt-8">{{ $products->links() }}</div>
                @endif
            @else
                <div class="text-center py-16">
                    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <h3 class="text-lg font-semibold text-gray-600 mb-1">No products found</h3>
                    <p class="text-gray-400 text-sm">Try a different search term or adjust your filters.</p>
                </div>
            @endif
        </div>
    </section>

    {{-- CTA Banner --}}
    <section class="bg-indigo-700 py-12 text-white text-center">
        <div class="max-w-3xl mx-auto px-4">
            <h2 class="text-2xl font-bold mb-3">Want to See Supplier Details & Order Directly?</h2>
            <p class="text-indigo-200 mb-6">Sign up for Servora to unlock supplier names, send RFQs, compare quotes side-by-side, and place purchase orders — all from one platform.</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ route('saas.register') }}" class="px-8 py-3 bg-white text-indigo-700 font-semibold rounded-xl hover:bg-gray-100 transition">
                    Start Free Trial
                </a>
                <a href="{{ route('for-suppliers') }}" class="px-6 py-3 border border-white/30 text-white/80 hover:text-white rounded-xl transition font-medium">
                    I'm a Supplier
                </a>
            </div>
        </div>
    </section>
</div>
