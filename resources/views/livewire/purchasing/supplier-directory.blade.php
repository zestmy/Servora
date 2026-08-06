<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">{{ session('success') }}</div>
    @endif
    @if (session()->has('info'))
        <div wire:key="info-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-blue-50 border border-blue-200 text-blue-700 text-sm rounded-lg">{{ session('info') }}</div>
    @endif

    <div class="flex items-center gap-4 mb-6">
        <a data-back href="{{ route('purchasing.index') }}" class="text-gray-600 hover:text-gray-900 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h2 class="page-title">Find Suppliers</h2>
            <p class="text-sm text-gray-600">Browse registered suppliers and their product catalogs</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card p-4 mb-6">
        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
            <div class="flex-1 min-w-48">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search supplier name, product, or SKU..."
                       class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" />
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
            <div class="card overflow-hidden">
                <div class="p-5">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-gray-800 truncate">{{ $s->name }}</h3>
                            <div class="flex flex-wrap gap-2 mt-1.5">
                                @if ($s->city || $s->state)
                                    <span class="text-xs text-gray-600">
                                        {{ collect([$s->city, $s->state])->filter()->implode(', ') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        @if ($alreadyAdded)
                            <span class="px-2 py-0.5 bg-success-50 text-success-600 text-xs rounded-full font-medium flex-shrink-0">Added</span>
                        @endif
                    </div>

                    {{-- Contact --}}
                    <div class="flex flex-wrap gap-3 mt-3 text-xs text-gray-600">
                        @if ($s->contact_person)
                            <span>{{ $s->contact_person }}</span>
                        @endif
                        @if ($s->phone)
                            <span>{{ $s->phone }}</span>
                        @endif
                    </div>

                    {{-- Product count + categories --}}
                    <div class="mt-3">
                        <span class="text-xs font-medium text-brand-600">{{ $s->products_count }} {{ Str::plural('product', $s->products_count) }}</span>
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
                            class="text-sm text-brand-600 hover:text-brand-800 font-medium transition">
                        {{ $viewingSupplierId === $s->id ? 'Hide Products' : 'View Products' }}
                    </button>
                    @if (! $alreadyAdded)
                        <button wire:click="addSupplier({{ $s->id }})"
                                wire:confirm="Add {{ $s->name }} to your supplier list?"
                                class="btn-primary btn-sm">
                            + Add Supplier
                        </button>
                    @endif
                </div>

                {{-- Expanded product list --}}
                @if ($viewingSupplierId === $s->id && $viewingProducts->isNotEmpty())
                    <div class="border-t border-gray-100 max-h-64 overflow-y-auto">
                        <table class="table-surface min-w-full text-xs">
                            <thead class="sticky top-0">
                                <tr>
                                    <th class="px-4 py-2 text-left">Product</th>
                                    <th class="px-4 py-2 text-left">SKU</th>
                                    <th class="px-4 py-2 text-right">Price</th>
                                    <th class="px-4 py-2 text-left">Category</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($viewingProducts as $p)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2 font-medium text-gray-700">{{ $p->name }}</td>
                                        <td class="px-4 py-2 font-mono text-gray-600">{{ $p->sku }}</td>
                                        <td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ number_format($p->unit_price, 2) }}</td>
                                        <td class="px-4 py-2 text-gray-600">{{ $p->category ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @empty
            <div class="col-span-full card p-12 text-center">
                <svg class="w-12 h-12 text-gray-500 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <p class="text-gray-600 text-sm">No suppliers found matching your criteria.</p>
                <p class="text-gray-500 text-xs mt-1">Try adjusting your search or filters.</p>
            </div>
        @endforelse
    </div>

    @if ($suppliers->hasPages())
        <div class="mt-6">{{ $suppliers->links() }}</div>
    @endif
</div>
