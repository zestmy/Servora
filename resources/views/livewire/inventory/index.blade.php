<div>
    {{-- Flash --}}
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-gray-700">Inventory</h2>
        <div class="flex items-center gap-2">
            @if ($tab === 'prep-items')
                <a href="{{ route('inventory.prep-items.create') }}"
                   class="px-4 py-2 bg-amber-500 text-white text-sm font-medium rounded-lg hover:bg-amber-600 transition">
                    + New Prep Item
                </a>
            @elseif ($tab === 'stock-takes')
                <a href="{{ route('inventory.stock-takes.create') }}"
                   class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    + New Stock Take
                </a>
            @elseif ($tab === 'wastage')
                <a href="{{ route('inventory.wastage.create') }}"
                   class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    + Record Wastage
                </a>
            @elseif ($tab === 'staff-meals')
                <a href="{{ route('inventory.staff-meals.create') }}"
                   class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    + Record Staff Meal
                </a>
            @elseif ($tab === 'transfers')
                <a href="{{ route('inventory.transfers.create') }}"
                   class="px-4 py-2 bg-teal-600 text-white text-sm font-medium rounded-lg hover:bg-teal-700 transition">
                    + New Transfer
                </a>
            @endif
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Prep Items</p>
            <p class="text-2xl font-bold text-amber-600 mt-1">{{ $prepItemCount }}</p>
            <p class="text-xs text-gray-400 mt-1">Semi-finished goods</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Stock Takes This Month</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $monthStockTakes }}</p>
            <p class="text-xs {{ $draftStockTakes > 0 ? 'text-yellow-600 font-medium' : 'text-gray-400' }} mt-1">
                {{ $draftStockTakes }} draft{{ $draftStockTakes !== 1 ? 's' : '' }} pending
            </p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Wastage This Month</p>
            <p class="text-2xl font-bold text-red-600 mt-1 tabular-nums">RM {{ number_format($monthWastageCost, 2) }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ now()->format('M Y') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Staff Meals This Month</p>
            <p class="text-2xl font-bold text-purple-600 mt-1 tabular-nums">RM {{ number_format($monthStaffMealCost, 2) }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ now()->format('M Y') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Latest Stock Value</p>
            @if ($latestStockTake)
                <p class="text-2xl font-bold text-indigo-600 mt-1 tabular-nums">RM {{ number_format($latestStockTake->total_stock_cost, 2) }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ $latestStockTake->stock_take_date->format('d M Y') }}</p>
            @else
                <p class="text-2xl font-bold text-gray-300 mt-1">—</p>
                <p class="text-xs text-gray-400 mt-1">No completed stock takes</p>
            @endif
        </div>
    </div>

    {{-- Cost by Cost Center Panel --}}
    @if ($categoryBreakdown)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6" x-data="{}">
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                <div>
                    <h3 class="text-sm font-semibold text-gray-700">Cost by Cost Center</h3>
                    <p class="text-xs text-gray-400 mt-0.5">
                        Based on stock take: {{ $categoryBreakdown['date']->format('d M Y') }}
                    </p>
                </div>
                <span class="text-sm font-bold text-indigo-700 tabular-nums">
                    RM {{ number_format($categoryBreakdown['total'], 2) }} total
                </span>
            </div>
            <table class="min-w-full text-sm divide-y divide-gray-50">
                <thead class="bg-gray-50 text-gray-400 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-5 py-2 text-left">Cost Center</th>
                        <th class="px-5 py-2 text-right">Stock Value (RM)</th>
                        <th class="px-5 py-2 text-right w-24">% of Total</th>
                        <th class="px-3 py-2 w-8"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categoryBreakdown['groups'] as $group)
                        @php
                            $pct = $categoryBreakdown['total'] > 0
                                ? ($group['total_cost'] / $categoryBreakdown['total']) * 100
                                : 0;
                            $hasSubs = ! empty($group['sub_breakdown']);
                        @endphp
                        <tr x-data="{ open: false }" class="hover:bg-gray-50 transition border-b border-gray-50">
                            <td class="px-5 py-2.5 font-medium text-gray-800">
                                <div class="flex items-center gap-2">
                                    @if ($hasSubs)
                                        <button @click="open = !open"
                                                class="text-gray-400 hover:text-gray-600 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 transition-transform" :class="open ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </button>
                                    @else
                                        <span class="w-3.5"></span>
                                    @endif
                                    {{ $group['main_name'] }}
                                </div>
                            </td>
                            <td class="px-5 py-2.5 text-right tabular-nums font-semibold text-gray-800">
                                {{ number_format($group['total_cost'], 2) }}
                            </td>
                            <td class="px-5 py-2.5 text-right tabular-nums text-gray-500 text-xs">
                                {{ number_format($pct, 1) }}%
                            </td>
                            <td></td>
                        </tr>
                        @if ($hasSubs)
                            @foreach ($group['sub_breakdown'] as $sub)
                                <tr x-show="open" x-cloak class="bg-gray-50/60 border-b border-gray-50">
                                    <td class="pl-12 pr-5 py-2 text-gray-600">↳ {{ $sub['name'] }}</td>
                                    <td class="px-5 py-2 text-right tabular-nums text-gray-600">
                                        {{ number_format($sub['cost'], 2) }}
                                    </td>
                                    <td class="px-5 py-2 text-right tabular-nums text-gray-400 text-xs">
                                        @php $subPct = $categoryBreakdown['total'] > 0 ? ($sub['cost'] / $categoryBreakdown['total']) * 100 : 0; @endphp
                                        {{ number_format($subPct, 1) }}%
                                    </td>
                                    <td></td>
                                </tr>
                            @endforeach
                        @endif
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                    <tr>
                        <td class="px-5 py-2.5 text-sm font-semibold text-gray-700">Total</td>
                        <td class="px-5 py-2.5 text-right tabular-nums font-bold text-gray-900">
                            {{ number_format($categoryBreakdown['total'], 2) }}
                        </td>
                        <td class="px-5 py-2.5 text-right text-xs text-gray-400">100.0%</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    {{-- Tabs --}}
    <div class="flex border-b border-gray-200 mb-4">
        <button wire:click="$set('tab', 'prep-items')"
                class="px-5 py-3 text-sm font-medium border-b-2 transition -mb-px
                       {{ $tab === 'prep-items' ? 'border-amber-500 text-amber-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
            🍳 Prep Items
        </button>
        <button wire:click="$set('tab', 'stock-takes')"
                class="px-5 py-3 text-sm font-medium border-b-2 transition -mb-px
                       {{ $tab === 'stock-takes' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
            📋 Stock Takes
        </button>
        <button wire:click="$set('tab', 'wastage')"
                class="px-5 py-3 text-sm font-medium border-b-2 transition -mb-px
                       {{ $tab === 'wastage' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
            🗑️ Wastage
        </button>
        <button wire:click="$set('tab', 'staff-meals')"
                class="px-5 py-3 text-sm font-medium border-b-2 transition -mb-px
                       {{ $tab === 'staff-meals' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
            🍽️ Staff Meals
        </button>
        <button wire:click="$set('tab', 'transfers')"
                class="px-5 py-3 text-sm font-medium border-b-2 transition -mb-px
                       {{ $tab === 'transfers' ? 'border-teal-600 text-teal-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
            🔄 Transfers
            @if ($inTransitCount > 0)
                <span class="ml-1 px-1.5 py-0.5 bg-yellow-100 text-yellow-700 text-xs rounded-full">{{ $inTransitCount }}</span>
            @endif
        </button>
    </div>

    {{-- Filter bar --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="{{ $tab === 'prep-items' ? 'Search prep items…' : ($tab === 'transfers' ? 'Search transfer number…' : 'Search reference number…') }}"
                       class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>
            @if ($tab !== 'prep-items')
                @if ($tab === 'transfers')
                    <select wire:model.live="statusFilter"
                            class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All Statuses</option>
                        <option value="draft">Draft</option>
                        <option value="in_transit">In Transit</option>
                        <option value="received">Received</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                @endif
                <div class="flex items-center gap-1">
                    <input type="date" wire:model.live="dateFrom"
                           class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <span class="text-gray-400 text-xs">to</span>
                    <input type="date" wire:model.live="dateTo"
                           class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>
            @endif
        </div>
    </div>

    {{-- ── Prep Items Tab ─────────────────────────────────────────────────── --}}
    @if ($tab === 'prep-items')
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">Code</th>
                        <th class="px-4 py-3 text-right">Yield</th>
                        <th class="px-4 py-3 text-right">Cost / Unit (RM)</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($prepItems as $item)
                        @php
                            $costPerUnit = $item->ingredient
                                ? floatval($item->ingredient->current_cost)
                                : 0;
                        @endphp
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="px-1.5 py-0.5 bg-amber-100 text-amber-700 text-xs font-semibold rounded">PREP</span>
                                    <span class="font-medium text-gray-800">{{ $item->name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-400 text-xs">{{ $item->code ?: '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-600">
                                {{ floatval($item->yield_quantity) }} {{ $item->yieldUom?->abbreviation }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-indigo-600">
                                {{ number_format($costPerUnit, 4) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($item->is_active)
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">Active</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">Inactive</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('inventory.prep-items.show', $item->id) }}" title="Edit"
                                       class="text-indigo-500 hover:text-indigo-700 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    <button wire:click="deletePrepItem({{ $item->id }})"
                                            wire:confirm="Delete '{{ $item->name }}'? This also removes the linked ingredient."
                                            title="Delete"
                                            class="text-red-400 hover:text-red-600 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-gray-400">
                                <div class="text-3xl mb-2">🍳</div>
                                <p class="font-medium">No prep items yet</p>
                                <p class="text-xs mt-1">
                                    <a href="{{ route('inventory.prep-items.create') }}" class="text-amber-600 underline">Create your first prep item</a>
                                    — e.g. White Steamed Rice, Sambal Belacan
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if (method_exists($prepItems, 'hasPages') && $prepItems->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $prepItems->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- ── Stock Takes Tab ───────────────────────────────────────────────── --}}
    @if ($tab === 'stock-takes')
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Reference</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Items</th>
                        <th class="px-4 py-3 text-right">Stock Value (RM)</th>
                        <th class="px-4 py-3 text-right">Variance Cost (RM)</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($stockTakes as $record)
                        @php
                            $statusColor = match($record->status) {
                                'draft'       => 'bg-gray-100 text-gray-600',
                                'in_progress' => 'bg-yellow-100 text-yellow-700',
                                'completed'   => 'bg-green-100 text-green-700',
                                default       => 'bg-gray-100 text-gray-500',
                            };
                            $varianceCost = floatval($record->total_variance_cost);
                            $stockCost    = floatval($record->total_stock_cost);
                        @endphp
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 font-medium text-gray-700">
                                {{ $record->stock_take_date->format('d M Y') }}
                                @if ($record->stock_take_date->isToday())
                                    <span class="ml-1 text-xs text-indigo-400">Today</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $record->reference_number ?: '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ $statusColor }}">
                                    {{ ucfirst(str_replace('_', ' ', $record->status)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ $record->lines_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">
                                {{ $stockCost > 0 ? number_format($stockCost, 2) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium {{ $varianceCost >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $varianceCost >= 0 ? '+' : '' }}{{ number_format($varianceCost, 2) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('inventory.stock-takes.show', $record->id) }}" title="View / Edit"
                                       class="text-indigo-500 hover:text-indigo-700 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    @if ($record->status === 'draft')
                                        <button wire:click="deleteStockTake({{ $record->id }})"
                                                wire:confirm="Delete this stock take? This cannot be undone."
                                                title="Delete"
                                                class="text-red-400 hover:text-red-600 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                                <div class="text-3xl mb-2">📋</div>
                                <p class="font-medium">No stock takes yet</p>
                                <p class="text-xs mt-1">
                                    <a href="{{ route('inventory.stock-takes.create') }}" class="text-indigo-500 underline">Start a new stock take</a>
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if (method_exists($stockTakes, 'hasPages') && $stockTakes->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $stockTakes->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- ── Staff Meals Tab ──────────────────────────────────────────────── --}}
    @if ($tab === 'staff-meals')
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Reference</th>
                        <th class="px-4 py-3 text-center">Items</th>
                        <th class="px-4 py-3 text-right">Total Cost (RM)</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($staffMealRecords as $record)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 font-medium text-gray-700">
                                {{ $record->meal_date->format('d M Y') }}
                                @if ($record->meal_date->isToday())
                                    <span class="ml-1 text-xs text-indigo-400">Today</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $record->reference_number ?: '—' }}</td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ $record->lines_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-purple-600">
                                {{ number_format($record->total_cost, 2) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('inventory.staff-meals.show', $record->id) }}" title="Edit"
                                       class="text-indigo-500 hover:text-indigo-700 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    <button wire:click="deleteStaffMeal({{ $record->id }})"
                                            wire:confirm="Delete this staff meal record? This cannot be undone."
                                            title="Delete"
                                            class="text-red-400 hover:text-red-600 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-gray-400">
                                <div class="text-3xl mb-2">🍽️</div>
                                <p class="font-medium">No staff meal records yet</p>
                                <p class="text-xs mt-1">
                                    <a href="{{ route('inventory.staff-meals.create') }}" class="text-indigo-500 underline">Record today's staff meals</a>
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if ($staffMealRecords->count() > 0)
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200 text-sm font-semibold text-gray-700">
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-right text-xs text-gray-500 font-normal">
                                Page total ({{ $staffMealRecords->count() }} records)
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-purple-600">
                                {{ number_format($staffMealRecords->sum('total_cost'), 2) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>

            @if (method_exists($staffMealRecords, 'hasPages') && $staffMealRecords->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $staffMealRecords->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- ── Transfers Tab ─────────────────────────────────────────────────── --}}
    @if ($tab === 'transfers')
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Transfer #</th>
                        <th class="px-4 py-3 text-left">From</th>
                        <th class="px-4 py-3 text-left">To</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Items</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($transfers as $transfer)
                        @php
                            $statusColor = match($transfer->status) {
                                'draft'      => 'bg-gray-100 text-gray-600',
                                'in_transit' => 'bg-yellow-100 text-yellow-700',
                                'received'   => 'bg-green-100 text-green-700',
                                'cancelled'  => 'bg-red-100 text-red-600',
                                default      => 'bg-gray-100 text-gray-500',
                            };
                        @endphp
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 font-medium text-gray-700">
                                {{ $transfer->transfer_date->format('d M Y') }}
                                @if ($transfer->transfer_date->isToday())
                                    <span class="ml-1 text-xs text-indigo-400">Today</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $transfer->transfer_number }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $transfer->fromOutlet?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $transfer->toOutlet?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ $statusColor }}">
                                    {{ ucfirst(str_replace('_', ' ', $transfer->status)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ $transfer->lines_count }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('inventory.transfers.show', $transfer->id) }}" title="View / Edit"
                                       class="text-indigo-500 hover:text-indigo-700 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    @if ($transfer->status === 'draft')
                                        <button wire:click="deleteTransfer({{ $transfer->id }})"
                                                wire:confirm="Delete this transfer? This cannot be undone."
                                                title="Delete"
                                                class="text-red-400 hover:text-red-600 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                                <div class="text-3xl mb-2">🔄</div>
                                <p class="font-medium">No transfers yet</p>
                                <p class="text-xs mt-1">
                                    <a href="{{ route('inventory.transfers.create') }}" class="text-teal-600 underline">Create your first transfer</a>
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if (method_exists($transfers, 'hasPages') && $transfers->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $transfers->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- ── Wastage Tab ───────────────────────────────────────────────────── --}}
    @if ($tab === 'wastage')
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Reference</th>
                        <th class="px-4 py-3 text-center">Items</th>
                        <th class="px-4 py-3 text-right">Total Cost (RM)</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($wastageRecords as $record)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 font-medium text-gray-700">
                                {{ $record->wastage_date->format('d M Y') }}
                                @if ($record->wastage_date->isToday())
                                    <span class="ml-1 text-xs text-indigo-400">Today</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $record->reference_number ?: '—' }}</td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ $record->lines_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-red-600">
                                {{ number_format($record->total_cost, 2) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('inventory.wastage.show', $record->id) }}" title="Edit"
                                       class="text-indigo-500 hover:text-indigo-700 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    <button wire:click="deleteWastage({{ $record->id }})"
                                            wire:confirm="Delete this wastage record? This cannot be undone."
                                            title="Delete"
                                            class="text-red-400 hover:text-red-600 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-gray-400">
                                <div class="text-3xl mb-2">🗑️</div>
                                <p class="font-medium">No wastage records yet</p>
                                <p class="text-xs mt-1">
                                    <a href="{{ route('inventory.wastage.create') }}" class="text-indigo-500 underline">Record today's wastage</a>
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if ($wastageRecords->count() > 0)
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200 text-sm font-semibold text-gray-700">
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-right text-xs text-gray-500 font-normal">
                                Page total ({{ $wastageRecords->count() }} records)
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-red-600">
                                {{ number_format($wastageRecords->sum('total_cost'), 2) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>

            @if (method_exists($wastageRecords, 'hasPages') && $wastageRecords->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $wastageRecords->links() }}
                </div>
            @endif
        </div>
    @endif

</div>
