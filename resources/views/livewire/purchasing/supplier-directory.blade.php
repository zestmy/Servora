<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">{{ session('success') }}</div>
    @endif
    @if (session()->has('info'))
        <div wire:key="info-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-blue-50 border border-blue-200 text-blue-700 text-sm rounded-lg">{{ session('info') }}</div>
    @endif

    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('purchasing.index') }}" class="text-gray-400 hover:text-gray-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h2 class="text-lg font-semibold text-gray-700">Find Suppliers</h2>
            <p class="text-sm text-gray-400">Browse registered suppliers and their product catalogs</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
            <div class="flex-1 min-w-48">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search supplier name, product, or SKU..."
                       class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>
            <select wire:model.live="categoryFilter" class="rounded-lg border-gray-300 text-sm">
                <option value="">All Categories</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat }}">{{ $cat }}</option>
                @endforeach
            </select>
            <select wire:model.live="stateFilter" class="rounded-lg border-gray-300 text-sm">
                <option value="">All States</option>
                @foreach ($states as $st)
                    <option value="{{ $st }}">{{ $st }}</option>
                @endforeach
            </select>
            <input type="text" wire:model.live.debounce.300ms="cityFilter" placeholder="City..."
                   class="w-32 rounded-lg border-gray-300 text-sm" />
        </div>
    </div>

    {{-- Supplier Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($suppliers as $s)
            @php $alreadyAdded = in_array($s->email, $mySupplierEmails); @endphp
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-5">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-gray-800 truncate">{{ $s->name }}</h3>
                            <div class="flex flex-wrap gap-2 mt-1.5">
                                @if ($s->city || $s->state)
                                    <span class="text-xs text-gray-400">
                                        {{ collect([$s->city, $s->state])->filter()->implode(', ') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        @if ($alreadyAdded)
                            <span class="px-2 py-0.5 bg-green-50 text-green-600 text-xs rounded-full font-medium flex-shrink-0">Added</span>
                        @endif
                    </div>

                    {{-- Contact --}}
                    <div class="flex flex-wrap gap-3 mt-3 text-xs text-gray-400">
                        @if ($s->contact_person)
                            <span>{{ $s->contact_person }}</span>
                        @endif
                        @if ($s->phone)
                            <span>{{ $s->phone }}</span>
                        @endif
                    </div>

                    {{-- Product count + categories --}}
                    <div class="mt-3">
                        <span class="text-xs font-medium text-indigo-600">{{ $s->products_count }} {{ Str::plural('product', $s->products_count) }}</span>
                        @php
                            $cats = $s->products()->whereNotNull('category')->where('category', '!=', '')->distinct()->pluck('category')->take(3);
                        @endphp
                        @if ($cats->isNotEmpty())
                            <div class="flex flex-wrap gap-1 mt-1.5">
                                @foreach ($cats as $cat)
                                    <span class="px-2 py-0.5 bg-gray-100 text-gray-500 text-[11px] rounded">{{ $cat }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Actions --}}
                <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                    <button wire:click="viewProducts({{ $s->id }})"
                            class="text-sm text-indigo-600 hover:text-indigo-800 font-medium transition">
                        {{ $viewingSupplierId === $s->id ? 'Hide Products' : 'View Products' }}
                    </button>
                    @if (! $alreadyAdded)
                        <button wire:click="addSupplier({{ $s->id }})"
                                wire:confirm="Add {{ $s->name }} to your supplier list?"
                                class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 transition">
                            + Add Supplier
                        </button>
                    @endif
                </div>

                {{-- Expanded product list --}}
                @if ($viewingSupplierId === $s->id && $viewingProducts->isNotEmpty())
                    <div class="border-t border-gray-100 max-h-64 overflow-y-auto">
                        <table class="min-w-full text-xs">
                            <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider sticky top-0">
                                <tr>
                                    <th class="px-4 py-2 text-left">Product</th>
                                    <th class="px-4 py-2 text-left">SKU</th>
                                    <th class="px-4 py-2 text-right">Price</th>
                                    <th class="px-4 py-2 text-left">Category</th>
                                    <th class="px-4 py-2 w-10"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach ($viewingProducts as $p)
                                    @php $inCart = collect($rfqCart)->contains('product_id', $p->id); @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2 font-medium text-gray-700">{{ $p->name }}</td>
                                        <td class="px-4 py-2 font-mono text-gray-400">{{ $p->sku }}</td>
                                        <td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ number_format($p->unit_price, 2) }}</td>
                                        <td class="px-4 py-2 text-gray-400">{{ $p->category ?? '—' }}</td>
                                        <td class="px-4 py-2 text-center">
                                            @if ($inCart)
                                                <span class="text-green-600 text-xs font-medium">Added</span>
                                            @else
                                                <button wire:click="addToRfq({{ $p->id }})" title="Add to RFQ"
                                                        class="text-indigo-500 hover:text-indigo-700 transition">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @empty
            <div class="col-span-full bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <p class="text-gray-400 text-sm">No suppliers found matching your criteria.</p>
                <p class="text-gray-300 text-xs mt-1">Try adjusting your search or filters.</p>
            </div>
        @endforelse
    </div>

    @if ($suppliers->hasPages())
        <div class="mt-6">{{ $suppliers->links() }}</div>
    @endif

    {{-- RFQ Cart (sticky bottom bar) --}}
    @if (count($rfqCart) > 0)
        <div class="fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-gray-200 shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <span class="w-8 h-8 bg-indigo-600 text-white rounded-full flex items-center justify-center text-sm font-bold">{{ count($rfqCart) }}</span>
                            <span class="text-sm font-medium text-gray-700">{{ Str::plural('item', count($rfqCart)) }} selected for quotation</span>
                        </div>
                        <div class="hidden sm:flex flex-wrap gap-1 max-w-xl overflow-hidden">
                            @foreach (collect($rfqCart)->take(5) as $i => $item)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded">
                                    {{ Str::limit($item['product_name'], 20) }}
                                    <button wire:click="removeFromRfq({{ $i }})" class="text-gray-400 hover:text-red-500">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </span>
                            @endforeach
                            @if (count($rfqCart) > 5)
                                <span class="text-xs text-gray-400">+{{ count($rfqCart) - 5 }} more</span>
                            @endif
                        </div>
                    </div>
                    <button wire:click="sendToRfq"
                            class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition shadow-sm">
                        Request Quotation
                    </button>
                </div>
            </div>
        </div>
        <div class="h-16"></div> {{-- Spacer for sticky bar --}}
    @endif
</div>
