<div>
    <x-page-header title="Asset Register" eyebrow="Assets"
                   subtitle="What is held here and what it is worth — the last completed count, plus every receipt and disposal since.">
        <x-slot:actions>
            <a href="{{ route('assets.index') }}" wire:navigate class="btn-secondary">Asset list</a>
            @canDo('assets.counts.record')
                <a href="{{ route('assets.counts.create') }}" wire:navigate class="btn-primary">
                    <x-icon name="clipboard" size="h-4 w-4" />
                    <span class="hidden sm:inline">New count</span>
                </a>
            @endcanDo
        </x-slot:actions>
    </x-page-header>

    {{-- Headline figures --}}
    <div class="grid gap-4 sm:grid-cols-3 mb-4">
        <div class="stat">
            <p class="stat-label">Total asset value · {{ $scopeLabel }}</p>
            <p class="stat-value">{{ number_format($totalValue, 2) }}</p>
            <p class="stat-meta">{{ $lineCount }} asset{{ $lineCount === 1 ? '' : 's' }} held</p>
        </div>
        <div class="stat">
            <p class="stat-label">Units on hand</p>
            <p class="stat-value">{{ number_format($totalUnits, 2) }}</p>
            <p class="stat-meta">across every category shown</p>
        </div>
        <div class="stat">
            <p class="stat-label">Last completed count</p>
            <p class="stat-value">
                {{ $lastCount ? $lastCount->count_date->format('d M Y') : '—' }}
            </p>
            <p class="stat-meta">
                @if ($lastCount)
                    {{ $lastCount->outlet?->name }} · {{ number_format((float) $lastCount->total_asset_value, 2) }}
                @else
                    Nothing counted yet — these figures are receipts alone
                @endif
            </p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="toolbar mb-4">
        <div class="flex w-full flex-col flex-wrap gap-3 sm:flex-row sm:items-center">
            <div class="flex-1 min-w-[200px]">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search by name, code or brand…" class="input" />
            </div>

            @if ($outlets->isNotEmpty())
                <select wire:model.live="outletFilter" class="input sm:w-52">
                    <option value="">All outlets</option>
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                    @endforeach
                </select>
            @endif

            <select wire:model.live="categoryFilter" class="input sm:w-52">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">
                        {{ $category->parent ? $category->parent->name . ' ▸ ' : '' }}{{ $category->name }}
                    </option>
                @endforeach
            </select>

            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" wire:model.live="includeZero"
                       class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                Show assets with none here
            </label>

            <button wire:click="resetFilters" class="btn-ghost">Reset</button>
        </div>
    </div>

    @if ($neverSeen > 0 && ! $includeZero)
        <div class="alert alert-info mb-4">
            {{ $neverSeen }} asset{{ $neverSeen === 1 ? ' in the catalogue has' : 's in the catalogue have' }}
            nothing on hand at {{ $scopeLabel }} — never counted here and never received here. Tick
            “Show assets with none here” to see which.
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-4">
        {{-- Value by category --}}
        <div class="card p-5 lg:col-span-1">
            <x-card-title>Value by category</x-card-title>
            @forelse ($byCategory as $categoryName => $value)
                @php $share = $totalValue > 0 ? ($value / $totalValue) * 100 : 0; @endphp
                <div class="mb-3" wire:key="cat-bar-{{ Str::slug($categoryName) }}">
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="text-sm text-gray-700 truncate">{{ $categoryName }}</span>
                        <span class="text-sm tabular-nums text-gray-700">{{ number_format($value, 2) }}</span>
                    </div>
                    <div class="progress mt-1">
                        <div class="progress-fill" style="width: {{ number_format($share, 2) }}%"></div>
                    </div>
                    <p class="text-xs text-gray-600 mt-0.5">{{ number_format($share, 1) }}% of value</p>
                </div>
            @empty
                <p class="text-sm text-gray-600">Nothing held yet.</p>
            @endforelse
        </div>

        {{-- The register itself --}}
        <div class="card overflow-hidden lg:col-span-3">
            <div class="overflow-x-auto">
                {{-- +60px over the old 720: the thumbnail and its gap come out of the Asset
                     column, and without this the value columns start squeezing first. --}}
                <table class="table-surface min-w-[780px]">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left">Asset</th>
                            <th class="px-4 py-3 text-left w-40">Category</th>
                            <th class="px-4 py-3 text-right w-28">On hand</th>
                            <th class="px-4 py-3 text-right w-28">Unit cost</th>
                            <th class="px-4 py-3 text-right w-32">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr wire:key="reg-{{ $row['id'] }}" class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    {{-- Tap to enlarge, as on the asset list and the count
                                         sheet. A thumbnail on its own is a dead end for
                                         anyone wondering which mixing bowl the RM 1,224
                                         belongs to. --}}
                                    <div class="flex items-center gap-3" x-data="{ zoom: false }">
                                        @if ($row['image'])
                                            <button type="button" @click="zoom = true" title="View larger"
                                                    class="h-10 w-10 flex-shrink-0 overflow-hidden rounded-control border border-gray-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                                                <img src="{{ $row['image'] }}" alt="" loading="lazy" class="h-full w-full object-cover" />
                                            </button>

                                            <template x-teleport="body">
                                                <div x-show="zoom" x-cloak @keydown.escape.window="zoom = false"
                                                     class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                                                    <div class="fixed inset-0 bg-gray-900/70" @click="zoom = false"></div>
                                                    <div class="relative max-h-full" @click.stop>
                                                        <img src="{{ $row['image'] }}" alt="{{ $row['name'] }}"
                                                             class="max-h-[80vh] max-w-[90vw] rounded-panel bg-white object-contain shadow-e4" />
                                                        <p class="mt-3 text-center text-sm text-white/90">{{ $row['name'] }}</p>
                                                    </div>
                                                </div>
                                            </template>
                                        @else
                                            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-control border border-gray-200 bg-gray-100">
                                                <x-icon name="building" size="h-4 w-4" class="text-gray-400" />
                                            </div>
                                        @endif

                                        <div class="min-w-0">
                                            <span class="font-medium text-gray-700">{{ $row['name'] }}</span>
                                            @if ($row['code'])
                                                <span class="text-gray-600 ml-1">({{ $row['code'] }})</span>
                                            @endif
                                            @if ($row['brand'])
                                                <span class="block text-xs text-gray-600">{{ $row['brand'] }}</span>
                                            @endif
                                            @unless ($row['is_active'])
                                                <span class="badge-neutral ml-1">Inactive</span>
                                            @endunless
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    <span class="inline-flex items-center gap-1.5">
                                        @if ($row['color'])
                                            <span class="h-2 w-2 rounded-full" style="background-color: {{ $row['color'] }}"></span>
                                        @endif
                                        {{ $row['category'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums {{ $row['quantity'] < 0 ? 'text-danger-600 font-medium' : 'text-gray-700' }}">
                                    {{ number_format($row['quantity'], 2) }}
                                    <span class="text-xs text-gray-600">{{ $row['uom'] }}</span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-600">
                                    {{ number_format($row['unit_cost'], 2) }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">
                                    {{ number_format($row['value'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10">
                                    <div class="empty-state">
                                        <p class="empty-title">Nothing on hand here yet</p>
                                        <p class="empty-body">
                                            Book in what this outlet already has with an asset receipt, or take a
                                            first count — either one gives the register something to value.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        <tfoot>
                            <tr class="border-t-2 border-gray-200 bg-gray-50">
                                <td class="px-4 py-3 font-semibold text-gray-800" colspan="4">
                                    Total · {{ $scopeLabel }}
                                    @if ($rows->hasPages())
                                        <span class="ml-1 text-xs font-normal text-gray-600">
                                            (every asset, not just this page)
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums font-semibold text-gray-900">
                                    {{ number_format($totalValue, 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            @if ($rows->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">{{ $rows->links() }}</div>
            @endif
        </div>
    </div>

    <p class="help mt-4">
        A negative figure means more has been disposed of than the register knows arrived — usually an
        opening balance that was never booked in. Take a count; a completed count replaces the figure
        outright rather than adjusting it.
    </p>
</div>
