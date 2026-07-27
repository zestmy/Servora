<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">{{ session('success') }}</div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('purchasing.index') }}" class="text-gray-400 hover:text-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="text-lg font-semibold text-gray-700">Price Alerts</h2>
        </div>
        @if ($unreadCount > 0)
            <button wire:click="markAllRead" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">Mark all as read</button>
        @endif
    </div>

    {{-- Stats (increase/decrease cards toggle the direction filter) --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Unread Alerts</p>
            <p class="text-2xl font-bold {{ $unreadCount > 0 ? 'text-amber-600' : 'text-gray-800' }} mt-1">{{ $unreadCount }}</p>
        </div>
        <button wire:click="$set('directionFilter', '{{ $directionFilter === 'increase' ? '' : 'increase' }}')"
                class="text-left bg-white rounded-xl shadow-sm border p-5 transition {{ $directionFilter === 'increase' ? 'border-red-300 ring-1 ring-red-200' : 'border-gray-100 hover:border-red-200' }}">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Price Increases</p>
            <p class="text-2xl font-bold text-red-600 mt-1">{{ $increaseCount }}</p>
        </button>
        <button wire:click="$set('directionFilter', '{{ $directionFilter === 'decrease' ? '' : 'decrease' }}')"
                class="text-left bg-white rounded-xl shadow-sm border p-5 transition {{ $directionFilter === 'decrease' ? 'border-green-300 ring-1 ring-green-200' : 'border-gray-100 hover:border-green-200' }}">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Price Decreases</p>
            <p class="text-2xl font-bold text-green-600 mt-1">{{ $decreaseCount }}</p>
        </button>
    </div>

    {{-- Threshold Setting --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-5">
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
                <button wire:click="saveThreshold" class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 transition">Save</button>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
            <select wire:model.live="directionFilter" class="rounded-lg border-gray-300 text-sm">
                <option value="">All Changes</option>
                <option value="increase">Increases Only</option>
                <option value="decrease">Decreases Only</option>
            </select>
            <div class="flex items-center gap-1">
                <input type="date" wire:model.live="dateFrom" class="rounded-lg border-gray-300 text-sm" />
                <span class="text-gray-400 text-xs">to</span>
                <input type="date" wire:model.live="dateTo" class="rounded-lg border-gray-300 text-sm" />
            </div>
        </div>
    </div>

    {{-- Notifications Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto"><table class="min-w-[900px] divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
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
            <tbody class="divide-y divide-gray-50">
                @forelse ($notifications as $n)
                    <tr class="{{ $n->is_read ? '' : 'bg-amber-50/40' }} hover:bg-gray-50 transition">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5">
                                @if (! $n->is_read)
                                    <span class="inline-block w-2 h-2 bg-amber-500 rounded-full flex-shrink-0" title="Unread"></span>
                                @endif
                                <a href="{{ route('reports.price-history', ['search' => $n->ingredient?->name]) }}"
                                   title="View full price history for {{ $n->ingredient?->name }}"
                                   class="font-medium text-gray-700 hover:text-indigo-600 hover:underline">
                                    {{ $n->ingredient?->name ?? '—' }}
                                </a>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $n->supplier?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-500">{{ number_format($n->old_price, 4) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">{{ number_format($n->new_price, 4) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap
                                {{ $n->direction === 'increase' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                                {{ $n->direction === 'increase' ? '+' : '−' }}{{ number_format(abs($n->change_percent), 1) }}%
                            </span>
                            <div class="text-[11px] text-gray-400 mt-0.5 tabular-nums">
                                {{ $n->direction === 'increase' ? '+' : '−' }}{{ number_format(abs($n->change_amount ?? ($n->new_price - $n->old_price)), 2) }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-400 text-xs" title="{{ $n->detected_at->format('d M Y, h:i A') }}">{{ $n->detected_at->diffForHumans() }}</td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-1">
                                @if (! $n->is_read)
                                    <button wire:click="markRead({{ $n->id }})" title="Mark read" class="text-gray-400 hover:text-indigo-600 transition p-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                @endif
                                <button wire:click="dismiss({{ $n->id }})" title="Dismiss" class="text-gray-400 hover:text-red-600 transition p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400">
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
</div>
