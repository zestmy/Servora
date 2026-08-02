<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">{{ session('success') }}</div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('purchasing.index') }}" class="text-gray-600 hover:text-gray-900 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="page-title">Price Alerts</h2>
        </div>
        <div class="flex items-center gap-2">
            @if ($view === 'history')
                <x-download-link :href="route('settings.price-alerts.export-pdf', ['from' => $dateFrom, 'to' => $dateTo, 'search' => $search, 'direction' => $directionFilter])"
                        title="Export PDF"
                        class="px-2.5 md:px-3 py-2 text-sm font-medium text-danger-600 border border-danger-200 rounded-lg hover:bg-danger-50 transition flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    <span class="hidden sm:inline">PDF</span>
                </x-download-link>
                <x-download-link :href="route('settings.price-alerts.export-excel', ['from' => $dateFrom, 'to' => $dateTo, 'search' => $search, 'direction' => $directionFilter])"
                        title="Export Excel"
                        class="px-2.5 md:px-3 py-2 text-sm font-medium text-success-700 border border-success-200 rounded-lg hover:bg-success-50 transition flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                    <span class="hidden sm:inline">Excel</span>
                </x-download-link>
            @endif
            @if ($view === 'alerts' && $unreadCount > 0)
                <button wire:click="markAllRead" class="text-sm text-brand-600 hover:text-brand-800 font-medium">Mark all as read</button>
            @endif
        </div>
    </div>

    {{-- View switch: alert inbox vs full price movement timeline --}}
    <div class="inline-flex rounded-lg border border-gray-300 overflow-hidden text-sm mb-6">
        <button wire:click="setView('alerts')"
                class="px-4 py-2 {{ $view === 'alerts' ? 'bg-brand-600 text-white font-medium' : 'bg-white text-gray-600 hover:bg-gray-50' }}">
            Alerts
            @if ($unreadCount > 0)
                <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold {{ $view === 'alerts' ? 'bg-white/20 text-white' : 'bg-warning-100 text-warning-700' }}">{{ $unreadCount }}</span>
            @endif
        </button>
        <button wire:click="setView('history')"
                class="px-4 py-2 border-l border-gray-300 {{ $view === 'history' ? 'bg-brand-600 text-white font-medium' : 'bg-white text-gray-600 hover:bg-gray-50' }}">
            Price History
        </button>
    </div>

    @if ($view === 'alerts')
    {{-- Stats (increase/decrease cards toggle the direction filter) --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="card p-5">
            <p class="text-xs text-gray-600 uppercase tracking-wider">Unread Alerts</p>
            <p class="text-2xl font-bold {{ $unreadCount > 0 ? 'text-warning-600' : 'text-gray-800' }} mt-1">{{ $unreadCount }}</p>
        </div>
        <button wire:click="$set('directionFilter', '{{ $directionFilter === 'increase' ? '' : 'increase' }}')"
                class="text-left bg-white rounded-xl shadow-sm border p-5 transition {{ $directionFilter === 'increase' ? 'border-danger-300 ring-1 ring-danger-200' : 'border-gray-100 hover:border-danger-200' }}">
            <p class="text-xs text-gray-600 uppercase tracking-wider">Price Increases</p>
            <p class="text-2xl font-bold text-danger-600 mt-1">{{ $increaseCount }}</p>
        </button>
        <button wire:click="$set('directionFilter', '{{ $directionFilter === 'decrease' ? '' : 'decrease' }}')"
                class="text-left bg-white rounded-xl shadow-sm border p-5 transition {{ $directionFilter === 'decrease' ? 'border-success-300 ring-1 ring-success-200' : 'border-gray-100 hover:border-success-200' }}">
            <p class="text-xs text-gray-600 uppercase tracking-wider">Price Decreases</p>
            <p class="text-2xl font-bold text-success-600 mt-1">{{ $decreaseCount }}</p>
        </button>
    </div>

    {{-- Threshold Setting --}}
    <div class="card p-5 mb-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-gray-800">Alert Threshold</p>
                <p class="text-xs text-gray-500 mt-0.5">
                    Detects changes of <strong>{{ $threshold }}%</strong> or more against the previous supplier price — even across several same-price deliveries. Checked daily at 7:00 AM; only changes from the last 14 days are flagged.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <input type="number" step="0.1" min="0.1" max="100" wire:model="threshold"
                       class="w-20 rounded-lg border-gray-300 text-sm text-right" />
                <span class="text-sm text-gray-500">%</span>
                <button wire:click="saveThreshold" class="btn-primary btn-sm">Save</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Filters --}}
    <div class="card p-4 mb-4">
        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
            <div class="flex-1 min-w-[180px]">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search product or supplier…"
                       class="w-full rounded-lg border-gray-300 text-sm" />
            </div>
            <select wire:model.live="directionFilter" class="rounded-lg border-gray-300 text-sm">
                <option value="">All Changes</option>
                <option value="increase">Increases Only</option>
                <option value="decrease">Decreases Only</option>
            </select>
            <div class="flex items-center gap-1">
                <input type="date" wire:model.live="dateFrom" class="rounded-lg border-gray-300 text-sm" />
                <span class="text-gray-600 text-xs">to</span>
                <input type="date" wire:model.live="dateTo" class="rounded-lg border-gray-300 text-sm" />
            </div>
        </div>
    </div>

    @if ($view === 'history')
    @if ($historyStats && $historyStats['total'] > 0)
        {{-- Range stats --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <div class="card p-4">
                <p class="text-xs text-gray-600 uppercase tracking-wider">Price Changes</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ number_format($historyStats['total']) }}</p>
                <p class="text-[11px] text-gray-600 mt-0.5">
                    <span class="text-danger-600 font-medium">{{ $historyStats['increases'] }} up</span> ·
                    <span class="text-success-600 font-medium">{{ $historyStats['decreases'] }} down</span>
                </p>
            </div>
            <div class="card p-4">
                <p class="text-xs text-gray-600 uppercase tracking-wider">Net Value Change</p>
                <p class="text-2xl font-bold mt-1 tabular-nums {{ $historyStats['netAmount'] > 0 ? 'text-danger-600' : ($historyStats['netAmount'] < 0 ? 'text-success-600' : 'text-gray-800') }}">
                    {{ $historyStats['netAmount'] > 0 ? '+' : ($historyStats['netAmount'] < 0 ? '−' : '') }}{{ number_format(abs($historyStats['netAmount']), 2) }}
                </p>
                <p class="text-[11px] text-gray-600 mt-0.5">sum of all pack-price movements (RM)</p>
            </div>
            <div class="card p-4">
                <p class="text-xs text-gray-600 uppercase tracking-wider">Average Change</p>
                <p class="text-2xl font-bold mt-1 tabular-nums {{ ($historyStats['avgPct'] ?? 0) > 0 ? 'text-danger-600' : (($historyStats['avgPct'] ?? 0) < 0 ? 'text-success-600' : 'text-gray-800') }}">
                    {{ $historyStats['avgPct'] !== null ? (($historyStats['avgPct'] > 0 ? '+' : '') . $historyStats['avgPct'] . '%') : '—' }}
                </p>
                <p class="text-[11px] text-gray-600 mt-0.5">mean of per-change percentages</p>
            </div>
            <div class="card p-4">
                <p class="text-xs text-gray-600 uppercase tracking-wider">Biggest Move</p>
                @php $big = $historyStats['topChanges']->first(); @endphp
                @if ($big)
                    <p class="text-2xl font-bold mt-1 tabular-nums {{ $big->change_amt > 0 ? 'text-danger-600' : 'text-success-600' }}">
                        {{ $big->change_amt > 0 ? '+' : '−' }}{{ number_format(abs($big->change_pct ?? 0), 1) }}%
                    </p>
                    <p class="text-[11px] text-gray-600 mt-0.5 truncate" title="{{ $big->ingredient_name }}">{{ $big->ingredient_name }}</p>
                @else
                    <p class="text-2xl font-bold text-gray-500 mt-1">—</p>
                @endif
            </div>
        </div>

        {{-- Top movers + supplier breakdown --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <div class="card p-4">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Top 5 Changes</h3>
                <div class="space-y-2">
                    @foreach ($historyStats['topChanges'] as $t)
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-700 truncate">{{ $t->ingredient_name }}</p>
                                <p class="text-[11px] text-gray-600 truncate">{{ $t->supplier_name ?? 'Manual edit' }} · {{ \Carbon\Carbon::parse($t->effective_date)->format('d M') }}</p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap {{ $t->change_amt > 0 ? 'bg-danger-100 text-danger-700' : 'bg-success-100 text-success-700' }}">
                                    {{ $t->change_amt > 0 ? '+' : '−' }}{{ number_format(abs($t->change_pct ?? 0), 1) }}%
                                </span>
                                <p class="text-[11px] text-gray-600 mt-0.5 tabular-nums">{{ number_format($t->prev_cost, 2) }} → {{ number_format($t->cost, 2) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="card p-4">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Changes by Supplier</h3>
                <div class="space-y-2">
                    @foreach ($historyStats['bySupplier'] as $s)
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <p class="font-medium text-gray-700 truncate">{{ $s['name'] }}</p>
                            <div class="text-right flex-shrink-0">
                                <span class="text-xs text-gray-600">{{ $s['count'] }} {{ Str::plural('change', $s['count']) }}</span>
                                <span class="ml-2 text-xs font-medium tabular-nums {{ $s['net'] > 0 ? 'text-danger-600' : ($s['net'] < 0 ? 'text-success-600' : 'text-gray-600') }}">
                                    {{ $s['net'] > 0 ? '+' : ($s['net'] < 0 ? '−' : '') }}{{ number_format(abs($s['net']), 2) }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Price movement timeline, straight from ingredient_price_history --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto"><table class="table-surface min-w-[900px]">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left">Effective Date</th>
                    <th class="px-4 py-3 text-left">Product</th>
                    <th class="px-4 py-3 text-left">Supplier</th>
                    <th class="px-4 py-3 text-right">Old Price</th>
                    <th class="px-4 py-3 text-right">New Price</th>
                    <th class="px-4 py-3 text-center">Change</th>
                    <th class="px-4 py-3 text-center">Source</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($history as $h)
                    @php
                        $prev = (float) $h->prev_cost;
                        $curr = (float) $h->cost;
                        $pct  = $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : null;
                        $up   = $curr > $prev;
                    @endphp
                    <tr wire:key="h-{{ $h->id }}" class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ \Carbon\Carbon::parse($h->effective_date)->format('d M Y') }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('reports.price-history', ['search' => $h->ingredient_name]) }}"
                               title="View full price history for {{ $h->ingredient_name }}"
                               class="font-medium text-gray-700 hover:text-brand-600 hover:underline">{{ $h->ingredient_name }}</a>
                            @if ($h->uom_abbr)
                                <span class="text-[11px] text-gray-600">/ {{ $h->uom_abbr }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $h->supplier_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-500">{{ number_format($prev, 4) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">{{ number_format($curr, 4) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap {{ $up ? 'bg-danger-100 text-danger-700' : 'bg-success-100 text-success-700' }}">
                                {{ $up ? '+' : '−' }}{{ $pct !== null ? number_format(abs($pct), 1) : '?' }}%
                            </span>
                            <div class="text-[11px] text-gray-600 mt-0.5 tabular-nums">{{ $up ? '+' : '−' }}{{ number_format(abs($curr - $prev), 2) }}</div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-500">
                                {{ str_replace('_', ' ', $h->source ?? '—') }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-600">
                            <p>No price changes {{ $search || $directionFilter ? 'match the selected filters' : 'recorded in this date range' }}.</p>
                            <p class="text-xs mt-1">Every change to a supplier price — from GRN receives, deliveries and scanned invoices — appears here by its invoice/effective date.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table></div>
        @if ($history->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $history->links() }}</div>
        @endif
    </div>
    @else
    {{-- Notifications Table --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto"><table class="table-surface min-w-[900px]">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left">Product</th>
                    <th class="px-4 py-3 text-left">Supplier</th>
                    <th class="px-4 py-3 text-right">Old Price</th>
                    <th class="px-4 py-3 text-right">New Price</th>
                    <th class="px-4 py-3 text-center">Change</th>
                    <th class="px-4 py-3 text-center">Detected</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($notifications as $n)
                    <tr class="{{ $n->is_read ? '' : 'bg-warning-50/40' }} hover:bg-gray-50 transition">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5">
                                @if (! $n->is_read)
                                    <span class="inline-block w-2 h-2 bg-warning-500 rounded-full flex-shrink-0" title="Unread"></span>
                                @endif
                                <a href="{{ route('reports.price-history', ['search' => $n->ingredient?->name]) }}"
                                   title="View full price history for {{ $n->ingredient?->name }}"
                                   class="font-medium text-gray-700 hover:text-brand-600 hover:underline">
                                    {{ $n->ingredient?->name ?? '—' }}
                                </a>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $n->supplier?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-500">{{ number_format($n->old_price, 4) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">{{ number_format($n->new_price, 4) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap
                                {{ $n->direction === 'increase' ? 'bg-danger-100 text-danger-700' : 'bg-success-100 text-success-700' }}">
                                {{ $n->direction === 'increase' ? '+' : '−' }}{{ number_format(abs($n->change_percent), 1) }}%
                            </span>
                            <div class="text-[11px] text-gray-600 mt-0.5 tabular-nums">
                                {{ $n->direction === 'increase' ? '+' : '−' }}{{ number_format(abs($n->change_amount ?? ($n->new_price - $n->old_price)), 2) }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-600 text-xs" title="{{ $n->detected_at->format('d M Y, h:i A') }}">{{ $n->detected_at->diffForHumans() }}</td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-1">
                                @if (! $n->is_read)
                                    <button wire:click="markRead({{ $n->id }})" title="Mark read" class="text-gray-600 hover:text-brand-600 transition p-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                @endif
                                <button wire:click="dismiss({{ $n->id }})" title="Dismiss" class="text-gray-600 hover:text-danger-600 transition p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-600">
                            <p>No price changes {{ $directionFilter || $dateFrom ? 'match the selected filters' : 'detected' }}.</p>
                            <p class="text-xs mt-1">
                                Supplier prices are checked every morning at 7:00 — a change of
                                <span class="font-medium text-gray-500">{{ $threshold }}%</span> or more against the previous price creates an alert here.
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table></div>
        @if ($notifications->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $notifications->links() }}</div>
        @endif
    </div>
    @endif
</div>
