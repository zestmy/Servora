<div>
    {{-- Page Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-700">Price History</h2>
            <p class="text-xs text-gray-400 mt-0.5">Track ingredient purchase price changes over time</p>
        </div>
        <div class="flex items-center gap-3">
            <button wire:click="exportPdf" wire:loading.attr="disabled"
                    class="px-3 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center gap-1.5 disabled:opacity-50">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                <span wire:loading.remove wire:target="exportPdf">Export PDF</span>
                <span wire:loading wire:target="exportPdf">Generating…</span>
            </button>
            <a href="{{ route('reports.index') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">Back to Reports</a>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex flex-col lg:flex-row gap-3">
            {{-- Period Presets --}}
            <div class="flex rounded-lg border border-gray-200 overflow-hidden text-sm">
                @foreach (['weekly' => 'Week', 'monthly' => 'Month', 'yearly' => 'Year', 'custom' => 'Custom'] as $val => $label)
                    <button type="button" wire:click="$set('period', '{{ $val }}')"
                            class="px-3 py-1.5 font-medium transition {{ $period === $val ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50' }} {{ !$loop->first ? 'border-l border-gray-200' : '' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- Date Range --}}
            <div class="flex items-center gap-2">
                <input type="date" wire:model.live="dateFrom"
                       class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                <span class="text-gray-400 text-xs">to</span>
                <input type="date" wire:model.live="dateTo"
                       class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>

            {{-- Search --}}
            <div class="flex-1">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search ingredient..."
                       class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>

            {{-- Supplier --}}
            <select wire:model.live="supplierFilter"
                    class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All Suppliers</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                @endforeach
            </select>

            {{-- Category --}}
            <select wire:model.live="categoryFilter"
                    class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All Categories</option>
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

            {{-- Per page --}}
            <select wire:model.live="perPage"
                    title="Rows per page"
                    class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="100">100 rows</option>
                <option value="200">200 rows</option>
                <option value="300">300 rows</option>
                <option value="400">400 rows</option>
                <option value="500">500 rows</option>
                <option value="all">All records</option>
            </select>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Price Records</p>
            <p class="text-2xl font-bold text-gray-800 mt-1 tabular-nums">{{ number_format($stats['totalRecords']) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ $stats['uniqueIngredients'] }} ingredients</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Avg Change</p>
            <p class="text-2xl font-bold {{ $stats['avgChangePct'] > 0 ? 'text-red-600' : ($stats['avgChangePct'] < 0 ? 'text-green-600' : 'text-gray-800') }} mt-1 tabular-nums">
                {{ $stats['avgChangePct'] > 0 ? '+' : '' }}{{ $stats['avgChangePct'] }}%
            </p>
            <p class="text-xs text-gray-400 mt-0.5">across period</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Increases</p>
            <p class="text-2xl font-bold text-red-600 mt-1 tabular-nums">{{ $stats['increases'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">ingredients went up</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Decreases</p>
            <p class="text-2xl font-bold text-green-600 mt-1 tabular-nums">{{ $stats['decreases'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">ingredients went down</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Biggest Mover</p>
            @if ($stats['biggestIncreaseName'])
                <p class="text-sm font-bold text-red-600 mt-1 truncate">{{ $stats['biggestIncreaseName'] }}</p>
                <p class="text-xs text-red-500">+{{ $stats['biggestIncreasePct'] }}%</p>
            @else
                <p class="text-sm text-gray-400 mt-1">No data</p>
            @endif
        </div>
    </div>

    {{-- Detail Modal --}}
    @if ($detailData)
        <div class="mb-4 bg-white rounded-xl shadow-sm border border-indigo-200 overflow-hidden">
            <div class="px-6 py-4 bg-indigo-50 border-b border-indigo-200 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-indigo-800">{{ $detailData['ingredient']->name }}</h3>
                    <p class="text-xs text-indigo-500 mt-0.5">
                        {{ $detailData['ingredient']->code ?? '' }}
                        &middot; Base UOM: {{ $detailData['ingredient']->baseUom->abbreviation }}
                        &middot; {{ $detailData['totalRecords'] }} record{{ $detailData['totalRecords'] > 1 ? 's' : '' }}
                    </p>
                </div>
                <button wire:click="closeDetail" class="text-indigo-400 hover:text-indigo-600 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Detail Stats --}}
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 px-6 py-4 border-b border-gray-100">
                <div>
                    <p class="text-xs text-gray-400">First Price</p>
                    <p class="font-semibold text-gray-800 tabular-nums">{{ number_format($detailData['firstCost'], 4) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Latest Price</p>
                    <p class="font-semibold text-gray-800 tabular-nums">{{ number_format($detailData['lastCost'], 4) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Overall Change</p>
                    @if ($detailData['overallChangePct'] !== null)
                        <p class="font-semibold tabular-nums {{ $detailData['overallChangePct'] > 0 ? 'text-red-600' : ($detailData['overallChangePct'] < 0 ? 'text-green-600' : 'text-gray-800') }}">
                            {{ $detailData['overallChangePct'] > 0 ? '+' : '' }}{{ $detailData['overallChangePct'] }}%
                        </p>
                    @else
                        <p class="text-gray-400">—</p>
                    @endif
                </div>
                <div>
                    <p class="text-xs text-gray-400">Min / Max</p>
                    <p class="font-semibold text-gray-800 tabular-nums text-sm">
                        {{ number_format($detailData['minCost'], 4) }} – {{ number_format($detailData['maxCost'], 4) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Average</p>
                    <p class="font-semibold text-gray-800 tabular-nums">{{ number_format($detailData['avgCost'], 4) }}</p>
                </div>
            </div>

            {{-- History Table --}}
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-right">Price</th>
                            <th class="px-4 py-2 text-right">Change</th>
                            <th class="px-4 py-2 text-right">%</th>
                            <th class="px-4 py-2 text-left">Supplier</th>
                            <th class="px-4 py-2 text-left">Source</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($detailData['history'] as $h)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-gray-700">{{ $h['date'] }}</td>
                                <td class="px-4 py-2 text-right font-medium text-gray-800 tabular-nums">{{ number_format($h['cost'], 4) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">
                                    @if ($h['change_amt'] !== null)
                                        <span class="{{ $h['change_amt'] > 0 ? 'text-red-600' : ($h['change_amt'] < 0 ? 'text-green-600' : 'text-gray-400') }}">
                                            {{ $h['change_amt'] > 0 ? '+' : '' }}{{ number_format($h['change_amt'], 4) }}
                                        </span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">
                                    @if ($h['change_pct'] !== null)
                                        <span class="inline-flex items-center gap-0.5 {{ $h['change_pct'] > 0 ? 'text-red-600' : ($h['change_pct'] < 0 ? 'text-green-600' : 'text-gray-400') }}">
                                            @if ($h['change_pct'] > 0)
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
                                            @elseif ($h['change_pct'] < 0)
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                                            @endif
                                            {{ $h['change_pct'] > 0 ? '+' : '' }}{{ $h['change_pct'] }}%
                                        </span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-gray-600">{{ $h['supplier'] }}</td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium
                                        {{ $h['source'] === 'grn_receive' ? 'bg-green-100 text-green-700' : ($h['source'] === 'purchase_record' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500') }}">
                                        {{ str_replace('_', ' ', ucfirst($h['source'])) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Summary Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-700">Price Changes Summary</h3>
            <select wire:model.live="sortBy" class="rounded-lg border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="latest">Latest First</option>
                <option value="name">By Name</option>
                <option value="count">Most Records</option>
            </select>
        </div>

        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Ingredient</th>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-center">Records</th>
                    <th class="px-4 py-3 text-right">First Price</th>
                    <th class="px-4 py-3 text-right">Latest Price</th>
                    <th class="px-4 py-3 text-right">Change</th>
                    <th class="px-4 py-3 text-right">Min / Max</th>
                    <th class="px-4 py-3 text-center">Last Updated</th>
                    <th class="px-4 py-3 text-center">Detail</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($changes as $row)
                    @php
                        $changePct = ($row->first_cost && $row->first_cost > 0)
                            ? (($row->last_cost - $row->first_cost) / $row->first_cost) * 100
                            : null;
                        $changeAmt = $row->last_cost - $row->first_cost;
                    @endphp
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-800">{{ $row->ingredient_name }}</div>
                            @if ($row->ingredient_code)
                                <div class="text-xs text-gray-400">{{ $row->ingredient_code }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $row->category_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-center text-gray-600">{{ $row->record_count }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">
                            {{ $row->first_cost !== null ? number_format($row->first_cost, 4) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">
                            {{ $row->last_cost !== null ? number_format($row->last_cost, 4) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            @if ($changePct !== null && $row->record_count >= 2)
                                <div class="flex items-center justify-end gap-1 {{ $changePct > 0 ? 'text-red-600' : ($changePct < 0 ? 'text-green-600' : 'text-gray-400') }}">
                                    @if ($changePct > 0)
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
                                    @elseif ($changePct < 0)
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                                    @endif
                                    <span class="font-medium">{{ $changePct > 0 ? '+' : '' }}{{ number_format($changePct, 1) }}%</span>
                                </div>
                                <div class="text-xs {{ $changeAmt > 0 ? 'text-red-400' : ($changeAmt < 0 ? 'text-green-400' : 'text-gray-300') }}">
                                    {{ $changeAmt > 0 ? '+' : '' }}{{ number_format($changeAmt, 4) }}
                                </div>
                            @else
                                <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-xs text-gray-500">
                            {{ number_format($row->min_cost, 2) }} – {{ number_format($row->max_cost, 2) }}
                        </td>
                        <td class="px-4 py-3 text-center text-gray-500 text-xs">
                            {{ \Carbon\Carbon::parse($row->latest_date)->format('d M Y') }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button wire:click="showDetail({{ $row->ingredient_id }})"
                                    class="text-indigo-500 hover:text-indigo-700 transition text-xs font-medium">
                                View
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-gray-400">
                            <p class="font-medium">No price history records found</p>
                            <p class="text-xs mt-1">Price history is recorded when goods are received via GRN or purchase receive.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($changes instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
            @if ($changes->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $changes->links() }}
                </div>
            @endif
        @else
            <div class="px-4 py-2 border-t border-gray-100 text-[11px] text-gray-500">
                Showing all {{ $changes->count() }} record{{ $changes->count() === 1 ? '' : 's' }} for the selected filter.
            </div>
        @endif
    </div>
</div>
