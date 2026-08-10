<div>
    {{-- Flash --}}
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Header --}}
    <x-page-header title="Stocks Management"
                   eyebrow="Inventory"
                   subtitle="Counts, wastage, staff meals, transfers and captured purchases.">
        <x-slot:actions>
            @if ($tab === 'stock-takes')
                @canDo('inventory.stock_takes.record')
                    <a href="{{ route('inventory.stock-takes.create') }}" class="btn-primary">+ New Stock Take</a>
                @endcanDo
            @elseif ($tab === 'wastage')
                @canDo('inventory.wastage.record')
                    <a href="{{ route('inventory.wastage.create') }}" class="btn-primary">+ Record Wastage</a>
                @endcanDo
            @elseif ($tab === 'staff-meals')
                @canDo('inventory.staff_meals.record')
                    <a href="{{ route('inventory.staff-meals.create') }}" class="btn-primary">+ Record Staff Meal</a>
                @endcanDo
            @elseif ($tab === 'transfers')
                @canDo('inventory.transfers.record')
                    {{-- btn-primary, not a hardcoded teal: the accent is a token
                         so that changing it is one edit in tailwind.config.js. --}}
                    <a href="{{ route('inventory.transfers.create') }}" class="btn-primary">+ New Transfer</a>
                @endcanDo
            @elseif ($tab === 'purchases')
                @canDo('inventory.purchases.record')
                    <a href="{{ route('inventory.purchases.create') }}" class="btn-primary">+ Record Purchase</a>
                @endcanDo
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Tabs. A segmented control rather than five underlined buttons in five
         different accent colours — the colour used to change with the tab,
         which made the accent mean "which tab" instead of "this is the
         action". --}}
    <div class="seg mb-4 overflow-x-auto">
        @foreach ($tabs as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')"
                    class="seg-item whitespace-nowrap {{ $tab === $key ? 'seg-item-on' : '' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Stats: about the tab you are on, under the filters you have set.

         Five fixed cards used to describe five different tabs at once, three of
         them about tabs a company may never have used — so most of the row read
         as zeros and dashes no matter what you were doing. They were also
         hardcoded to this month and ignored every filter, so narrowing the
         table to last week left the cards answering about the month. --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        <div class="stat">
            <p class="stat-label">{{ $stats['label'] }} {{ $stats['rangeLabel'] }}</p>
            <p class="stat-value">{{ number_format($stats['count']) }}</p>
            @if ($stats['previousCount'] !== null)
                @php $delta = $stats['count'] - $stats['previousCount']; @endphp
                {{-- Deliberately uncoloured. More wastage and more purchases are
                     the same arithmetic and opposite news, so a green "up" would
                     be wrong half the time. The label says what is counted; the
                     reader knows which way is good. --}}
                <p class="text-xs text-gray-600 mt-1 tabular-nums">
                    {{ $delta > 0 ? '▲' : ($delta < 0 ? '▼' : '—') }}
                    {{ $delta === 0 ? 'no change' : abs($delta) . ' vs the period before' }}
                </p>
            @endif
        </div>

        @if ($stats['moneyLabel'])
            <div class="stat">
                <p class="stat-label">{{ $stats['moneyLabel'] }} {{ $stats['rangeLabel'] }}</p>
                <p class="stat-value tabular-nums">RM {{ number_format($stats['value'] ?? 0, 2) }}</p>
                @if ($stats['previousValue'] !== null)
                    <p class="text-xs text-gray-600 mt-1 tabular-nums">
                        Previous period: RM {{ number_format($stats['previousValue'], 2) }}
                    </p>
                @endif
            </div>
        @endif

        @if ($highlight)
            <div class="stat">
                <p class="stat-label">{{ $highlight['label'] }}</p>
                <p class="stat-value {{ $highlight['tone'] === 'warning' ? 'text-warning-600' : '' }}">
                    {{ $highlight['value'] }}
                </p>
            </div>
        @endif
    </div>

    {{-- Filters. One strip for every tab: a filter is written once and every
         tab that can answer it offers it. Department lives on four of the five
         tables and every form fills it, but only Wastage ever filtered by it;
         supplier is stored and used on Purchases and filtered nowhere. --}}
    <div class="toolbar mb-4">
        <div class="flex flex-wrap items-center gap-2">
            @foreach (\App\Livewire\Inventory\Index::quickRangeOptions() as $key => $label)
                <button wire:click="setQuickRange('{{ $key }}')"
                        class="px-3 py-1.5 text-xs font-medium rounded-full border transition
                               {{ $quickRange === $key
                                    ? 'bg-brand-600 text-white border-brand-600'
                                    : 'bg-white text-gray-600 border-gray-200 hover:border-brand-300 hover:text-brand-700' }}">
                    {{ $label }}
                </button>
            @endforeach

            @if ($quickRange === '' && ($dateFrom || $dateTo))
                <span class="badge-neutral">Custom range</span>
            @endif
        </div>

        <div class="mt-3 flex flex-col lg:flex-row gap-3">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="{{ $tab === 'transfers' ? 'Search transfer number…' : ($tab === 'purchases' ? 'Search reference or supplier…' : 'Search reference number…') }}"
                   class="input flex-1" />

            @if ($filterOutlets->isNotEmpty())
                <select wire:model.live="outletFilter" class="input lg:w-44">
                    <option value="">All branches</option>
                    @foreach ($filterOutlets as $o)
                        <option value="{{ $o->id }}">{{ $o->name }}</option>
                    @endforeach
                </select>
            @endif

            @if ($tabConfig['dept'] && $departments->isNotEmpty())
                <select wire:model.live="departmentFilter" class="input lg:w-44">
                    <option value="">All departments</option>
                    @foreach ($departments as $d)
                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                    @endforeach
                    {{-- Offered explicitly: records that predate the column
                         would otherwise be unreachable by any filter value. --}}
                    <option value="none">— No department —</option>
                </select>
            @endif

            @if ($tabConfig['supplier'] && $suppliers->isNotEmpty())
                <select wire:model.live="supplierFilter" class="input lg:w-44">
                    <option value="">All suppliers</option>
                    @foreach ($suppliers as $sup)
                        <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                    @endforeach
                </select>
            @endif

            @if ($tabConfig['status'])
                <select wire:model.live="statusFilter" class="input lg:w-40">
                    <option value="">All statuses</option>
                    <option value="draft">Draft</option>
                    <option value="in_transit">In transit</option>
                    <option value="received">Received</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            @endif

            <div class="flex items-center gap-1">
                <input type="date" wire:model.live="dateFrom" class="input" aria-label="From date" />
                <span class="text-xs text-gray-600">to</span>
                <input type="date" wire:model.live="dateTo" class="input" aria-label="To date" />
            </div>

            <button wire:click="resetFilters" class="btn-ghost whitespace-nowrap">Reset</button>
        </div>
    </div>

    {{-- Prep Items tab removed — now under Recipes --}}

    {{-- ── Stock Takes Tab ───────────────────────────────────────────────── --}}
    @if ($tab === 'stock-takes')
        <div class="card overflow-hidden">
            <table class="table-surface min-w-full">
                <thead>
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
                <tbody>
                    @forelse ($records as $record)
                        @php
                            $statusColor = match($record->status) {
                                'draft'       => 'bg-gray-100 text-gray-600',
                                'in_progress' => 'bg-yellow-100 text-yellow-700',
                                'completed'   => 'bg-success-100 text-success-700',
                                default       => 'bg-gray-100 text-gray-500',
                            };
                            $varianceCost = floatval($record->total_variance_cost);
                            $stockCost    = floatval($record->total_stock_cost);
                        @endphp
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 font-medium text-gray-700">
                                {{ $record->stock_take_date->format('d M Y') }}
                                @if ($record->stock_take_date->isToday())
                                    <span class="ml-1 text-xs text-brand-400">Today</span>
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
                            <td class="px-4 py-3 text-right tabular-nums font-medium {{ $varianceCost >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                                {{ $varianceCost >= 0 ? '+' : '' }}{{ number_format($varianceCost, 2) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('inventory.stock-takes.show', $record->id) }}" title="View / Edit"
                                       class="text-brand-500 hover:text-brand-700 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    @if ($record->status === 'draft' || $canDeleteRecords)
                                        <button wire:click="deleteStockTake({{ $record->id }})"
                                                wire:confirm="{{ $record->status === 'draft' ? 'Delete this stock take? This cannot be undone.' : 'Delete this COMPLETED stock take? This cannot be undone.' }}"
                                                title="{{ $record->status === 'draft' ? 'Delete' : 'Delete completed stock take' }}"
                                                class="text-danger-400 hover:text-danger-600 transition">
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
                            <td colspan="7" class="px-4 py-12 text-center text-gray-600">
                                <div class="text-3xl mb-2">📋</div>
                                <p class="font-medium">No stock takes yet</p>
                                <p class="text-xs mt-1">
                                    @canDo('inventory.stock_takes.record')
                                    <a href="{{ route('inventory.stock-takes.create') }}" class="text-brand-500 underline">Start a new stock take</a>
                                    @endcanDo
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if (method_exists($records, 'hasPages') && $records->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $records->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- ── Purchases Tab ─────────────────────────────────────────────────── --}}
    @if ($tab === 'purchases')
        <div class="card overflow-hidden">
            <table class="table-surface min-w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Department</th>
                        <th class="px-4 py-3 text-left">Supplier</th>
                        <th class="px-4 py-3 text-left">Reference</th>
                        <th class="px-4 py-3 text-right">Amount (RM)</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 font-medium text-gray-700">
                                {{ $record->purchase_date->format('d M Y') }}
                                @if ($record->purchase_date->isToday())
                                    <span class="ml-1 text-xs text-brand-400">Today</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $record->department?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $record->supplier_label }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $record->reference_number ?: '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">
                                {{ number_format(floatval($record->amount), 2) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('inventory.purchases.show', $record->id) }}" title="View / Edit"
                                       class="text-brand-500 hover:text-brand-700 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    @if ($canDelete['purchases'])
                                    <button wire:click="deletePurchase({{ $record->id }})"
                                            wire:confirm="Delete this purchase record? This cannot be undone."
                                            title="Delete"
                                            class="text-danger-400 hover:text-danger-600 transition">
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
                            <td colspan="6" class="px-4 py-12 text-center text-gray-600">
                                <div class="text-3xl mb-2">🧾</div>
                                <p class="font-medium">No purchases recorded yet</p>
                                <p class="text-xs mt-1">
                                    @canDo('inventory.purchases.record')
                                    <a href="{{ route('inventory.purchases.create') }}" class="text-brand-500 underline">Record a purchase</a>
                                    @endcanDo
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if (method_exists($records, 'hasPages') && $records->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $records->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- ── Staff Meals Tab ──────────────────────────────────────────────── --}}
    @if ($tab === 'staff-meals')
        <div class="card overflow-hidden">
            <table class="table-surface min-w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Reference</th>
                        <th class="px-4 py-3 text-center">Items</th>
                        <th class="px-4 py-3 text-right">Total Cost (RM)</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 font-medium text-gray-700">
                                {{ $record->meal_date->format('d M Y') }}
                                @if ($record->meal_date->isToday())
                                    <span class="ml-1 text-xs text-brand-400">Today</span>
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
                                       class="text-brand-500 hover:text-brand-700 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    @if ($canDelete['staff_meals'])
                                    <button wire:click="deleteStaffMeal({{ $record->id }})"
                                            wire:confirm="Delete this staff meal record? This cannot be undone."
                                            title="Delete"
                                            class="text-danger-400 hover:text-danger-600 transition">
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
                            <td colspan="5" class="px-4 py-12 text-center text-gray-600">
                                <div class="text-3xl mb-2">🍽️</div>
                                <p class="font-medium">No staff meal records yet</p>
                                <p class="text-xs mt-1">
                                    @canDo('inventory.staff_meals.record')
                                    <a href="{{ route('inventory.staff-meals.create') }}" class="text-brand-500 underline">Record today's staff meals</a>
                                    @endcanDo
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if ($records->count() > 0)
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200 text-sm font-semibold text-gray-700">
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-right text-xs text-gray-500 font-normal">
                                Page total ({{ $records->count() }} records)
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-purple-600">
                                {{ number_format($records->sum('total_cost'), 2) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>

            @if (method_exists($records, 'hasPages') && $records->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $records->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- ── Transfers Tab ─────────────────────────────────────────────────── --}}
    @if ($tab === 'transfers')
        <div class="card overflow-hidden">
            <table class="table-surface min-w-full">
                <thead>
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
                <tbody>
                    @forelse ($records as $transfer)
                        @php
                            $statusColor = match($transfer->status) {
                                'draft'      => 'bg-gray-100 text-gray-600',
                                'in_transit' => 'bg-yellow-100 text-yellow-700',
                                'received'   => 'bg-success-100 text-success-700',
                                'cancelled'  => 'bg-danger-100 text-danger-600',
                                default      => 'bg-gray-100 text-gray-500',
                            };
                        @endphp
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 font-medium text-gray-700">
                                {{ $transfer->transfer_date->format('d M Y') }}
                                @if ($transfer->transfer_date->isToday())
                                    <span class="ml-1 text-xs text-brand-400">Today</span>
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
                                       class="text-brand-500 hover:text-brand-700 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    @if ($transfer->status === 'draft' || $canDelete['transfers'])
                                        <button wire:click="deleteTransfer({{ $transfer->id }})"
                                                wire:confirm="{{ $transfer->status === 'draft' ? 'Delete this transfer? This cannot be undone.' : 'Delete this ' . str_replace('_', ' ', $transfer->status) . ' transfer? This cannot be undone.' }}"
                                                title="{{ $transfer->status === 'draft' ? 'Delete' : 'Delete transfer' }}"
                                                class="text-danger-400 hover:text-danger-600 transition">
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
                            <td colspan="7" class="px-4 py-12 text-center text-gray-600">
                                <div class="text-3xl mb-2">🔄</div>
                                <p class="font-medium">No transfers yet</p>
                                <p class="text-xs mt-1">
                                    @canDo('inventory.transfers.record')
                                    <a href="{{ route('inventory.transfers.create') }}" class="text-teal-600 underline">Create your first transfer</a>
                                    @endcanDo
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if (method_exists($records, 'hasPages') && $records->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $records->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- ── Wastage Tab ───────────────────────────────────────────────────── --}}
    @if ($tab === 'wastage')
        <div class="card overflow-hidden">
            <table class="table-surface min-w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Reference</th>
                        <th class="px-4 py-3 text-left">Department</th>
                        <th class="px-4 py-3 text-left">Reason</th>
                        <th class="px-4 py-3 text-center">Items</th>
                        <th class="px-4 py-3 text-right">Total Cost (RM)</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 font-medium text-gray-700">
                                {{ $record->wastage_date->format('d M Y') }}
                                @if ($record->wastage_date->isToday())
                                    <span class="ml-1 text-xs text-brand-400">Today</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $record->reference_number ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $record->department?->name ?: '—' }}</td>
                            {{-- Reason lives on the LINES, so one record can carry several.
                                 Distinct values, first two with a count for the rest — enough
                                 to scan the table without turning a cell into a list. --}}
                            <td class="px-4 py-3 text-gray-600">
                                @php $reasons = $record->lines->pluck('reason')->filter()->unique()->values(); @endphp
                                @if ($reasons->isEmpty())
                                    <span class="text-gray-400">—</span>
                                @else
                                    <span title="{{ $reasons->implode(', ') }}">{{ $reasons->take(2)->implode(', ') }}@if ($reasons->count() > 2)<span class="text-gray-400"> +{{ $reasons->count() - 2 }}</span>@endif</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ $record->lines_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-danger-600">
                                {{ number_format($record->total_cost, 2) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('inventory.wastage.show', $record->id) }}" title="Edit"
                                       class="text-brand-500 hover:text-brand-700 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    @if ($canDelete['wastage'])
                                    <button wire:click="deleteWastage({{ $record->id }})"
                                            wire:confirm="Delete this wastage record? This cannot be undone."
                                            title="Delete"
                                            class="text-danger-400 hover:text-danger-600 transition">
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
                            <td colspan="7" class="px-4 py-12 text-center text-gray-600">
                                <div class="text-3xl mb-2">🗑️</div>
                                <p class="font-medium">No wastage records yet</p>
                                <p class="text-xs mt-1">
                                    @canDo('inventory.wastage.record')
                                    <a href="{{ route('inventory.wastage.create') }}" class="text-brand-500 underline">Record today's wastage</a>
                                    @endcanDo
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if ($records->count() > 0)
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200 text-sm font-semibold text-gray-700">
                        <tr>
                            <td colspan="5" class="px-4 py-3 text-right text-xs text-gray-500 font-normal">
                                Page total ({{ $records->count() }} records)
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-danger-600">
                                {{ number_format($records->sum('total_cost'), 2) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>

            @if (method_exists($records, 'hasPages') && $records->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $records->links() }}
                </div>
            @endif
        </div>
    @endif

</div>
