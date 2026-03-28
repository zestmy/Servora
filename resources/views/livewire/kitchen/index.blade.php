<div>
    {{-- Flash --}}
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-gray-700">Kitchen</h2>
        <div class="flex gap-2">
            <a href="{{ route('kitchen.prep-requests.create') }}"
               class="px-4 py-2 bg-white text-indigo-600 text-sm font-medium rounded-lg border border-indigo-200 hover:bg-indigo-50 transition">
                + Prep Request
            </a>
            <a href="{{ route('kitchen.orders.create') }}"
               class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                + Production Order
            </a>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        @foreach ($stats as $stat)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-xs text-gray-400 uppercase tracking-wider">{{ $stat['label'] }}</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Tabs --}}
    <div class="border-b border-gray-200 mb-4">
        <nav class="flex gap-6 -mb-px">
            <button wire:click="$set('tab', 'orders')"
                    class="pb-3 px-1 text-sm font-medium border-b-2 transition {{ $tab === 'orders' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Production Orders
            </button>
            <button wire:click="$set('tab', 'requests')"
                    class="pb-3 px-1 text-sm font-medium border-b-2 transition {{ $tab === 'requests' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Prep Requests
            </button>
            <button wire:click="$set('tab', 'inventory')"
                    class="pb-3 px-1 text-sm font-medium border-b-2 transition {{ $tab === 'inventory' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Inventory
            </button>
            <button wire:click="$set('tab', 'logs')"
                    class="pb-3 px-1 text-sm font-medium border-b-2 transition {{ $tab === 'logs' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Production Logs
            </button>
        </nav>
    </div>

    {{-- Filters --}}
    @if ($tab !== 'logs' || $tab === 'inventory')
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
            @if ($tab === 'orders')
                <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <div class="flex items-center gap-1">
                    <input type="date" wire:model.live="dateFrom" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <span class="text-gray-400 text-xs">to</span>
                    <input type="date" wire:model.live="dateTo" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>
            @elseif ($tab === 'requests')
                <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="submitted">Submitted</option>
                    <option value="approved">Approved</option>
                    <option value="fulfilled">Fulfilled</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            @endif
            {{-- Kitchen filter (all tabs) --}}
            @if ($kitchens->count() > 1)
                <select wire:model.live="kitchenFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Kitchens</option>
                    @foreach ($kitchens as $k)
                        <option value="{{ $k->id }}">{{ $k->name }}</option>
                    @endforeach
                </select>
            @endif
        </div>
    </div>
    @endif

    {{-- Tab Content --}}
    @if ($tab === 'orders')
        {{-- Orders Table --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            @if ($orders->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                            <tr>
                                <th class="px-4 py-3 text-left">Order #</th>
                                <th class="px-4 py-3 text-left">Kitchen</th>
                                <th class="px-4 py-3 text-left">Prod. Date</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-right">Lines</th>
                                <th class="px-4 py-3 text-left">Created By</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($orders as $order)
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('kitchen.orders.edit', $order->id) }}" class="font-medium text-indigo-600 hover:text-indigo-800">
                                            {{ $order->order_number }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ $order->kitchen?->name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $order->production_date->format('d M Y') }}</td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 text-xs rounded-full font-medium
                                            {{ match($order->status) {
                                                'draft'       => 'bg-gray-100 text-gray-600',
                                                'scheduled'   => 'bg-blue-100 text-blue-700',
                                                'in_progress' => 'bg-yellow-100 text-yellow-700',
                                                'completed'   => 'bg-green-100 text-green-700',
                                                'cancelled'   => 'bg-red-100 text-red-600',
                                                default       => 'bg-gray-100 text-gray-500',
                                            } }}">
                                            {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-600">{{ $order->lines_count }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $order->createdBy?->name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex gap-2 justify-end">
                                            @if ($order->status === 'draft')
                                                <button wire:click="scheduleOrder({{ $order->id }})"
                                                        class="text-xs text-blue-600 hover:text-blue-800 transition font-medium">Schedule</button>
                                                <button wire:click="cancelOrder({{ $order->id }})"
                                                        wire:confirm="Cancel this production order?"
                                                        class="text-xs text-red-500 hover:text-red-700 transition">Cancel</button>
                                            @elseif ($order->status === 'scheduled')
                                                <a href="{{ route('kitchen.orders.execute', $order->id) }}"
                                                   class="text-xs text-green-600 hover:text-green-800 transition font-medium">Start</a>
                                                <button wire:click="cancelOrder({{ $order->id }})"
                                                        wire:confirm="Cancel this production order?"
                                                        class="text-xs text-red-500 hover:text-red-700 transition">Cancel</button>
                                            @elseif ($order->status === 'in_progress')
                                                <a href="{{ route('kitchen.orders.execute', $order->id) }}"
                                                   class="text-xs text-green-600 hover:text-green-800 transition font-medium">Continue</a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $orders->links() }}
                </div>
            @else
                <div class="p-8 text-center text-gray-400 text-sm">
                    No production orders found.
                </div>
            @endif
        </div>

    @elseif ($tab === 'requests')
        {{-- Requests Table --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            @if ($requests->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                            <tr>
                                <th class="px-4 py-3 text-left">Request #</th>
                                <th class="px-4 py-3 text-left">Outlet</th>
                                <th class="px-4 py-3 text-left">Kitchen</th>
                                <th class="px-4 py-3 text-left">Needed Date</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-right">Lines</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($requests as $request)
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('kitchen.prep-requests.edit', $request->id) }}" class="font-medium text-indigo-600 hover:text-indigo-800">
                                            {{ $request->request_number }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ $request->outlet?->name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $request->kitchen?->name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $request->needed_date->format('d M Y') }}</td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 text-xs rounded-full font-medium
                                            {{ match($request->status) {
                                                'draft'     => 'bg-gray-100 text-gray-600',
                                                'submitted' => 'bg-yellow-100 text-yellow-700',
                                                'approved'  => 'bg-blue-100 text-blue-700',
                                                'fulfilled' => 'bg-green-100 text-green-700',
                                                'cancelled' => 'bg-red-100 text-red-600',
                                                default     => 'bg-gray-100 text-gray-500',
                                            } }}">
                                            {{ ucfirst($request->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-600">{{ $request->lines_count }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex gap-2 justify-end">
                                            @if ($request->status === 'submitted')
                                                <button wire:click="approveRequest({{ $request->id }})"
                                                        class="text-xs text-blue-600 hover:text-blue-800 transition font-medium">Approve</button>
                                            @endif
                                            @if (in_array($request->status, ['submitted', 'approved']))
                                                <button wire:click="fulfillRequest({{ $request->id }})"
                                                        class="text-xs text-green-600 hover:text-green-800 transition font-medium">Fulfill</button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $requests->links() }}
                </div>
            @else
                <div class="p-8 text-center text-gray-400 text-sm">
                    No prep requests found.
                </div>
            @endif
        </div>

    @elseif ($tab === 'logs')
        {{-- Logs Table --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            @if ($logs->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                            <tr>
                                <th class="px-4 py-3 text-left">Batch #</th>
                                <th class="px-4 py-3 text-left">Recipe</th>
                                <th class="px-4 py-3 text-right">Planned</th>
                                <th class="px-4 py-3 text-right">Actual</th>
                                <th class="px-4 py-3 text-right">Variance</th>
                                <th class="px-4 py-3 text-left">Produced By</th>
                                <th class="px-4 py-3 text-left">Produced At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($logs as $log)
                                @php
                                    $variance = floatval($log->yield_variance_pct);
                                @endphp
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $log->batch_number }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $log->recipe?->name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-600">{{ rtrim(rtrim(number_format(floatval($log->planned_yield), 4), '0'), '.') }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-600">{{ rtrim(rtrim(number_format(floatval($log->actual_yield), 4), '0'), '.') }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums font-medium {{ $variance >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ ($variance >= 0 ? '+' : '') . number_format($variance, 1) }}%
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $log->producedBy?->name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $log->produced_at?->format('d M Y H:i') ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $logs->links() }}
                </div>
            @else
                <div class="p-8 text-center text-gray-400 text-sm">
                    No production logs found.
                </div>
            @endif
        </div>

    @elseif ($tab === 'inventory')
        {{-- Kitchen Inventory --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            @if (isset($inventory) && $inventory->count() > 0)
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-3 text-left">Kitchen</th>
                            <th class="px-4 py-3 text-left">Prep Item</th>
                            <th class="px-4 py-3 text-right">On Hand</th>
                            <th class="px-4 py-3 text-center">UOM</th>
                            <th class="px-4 py-3 text-right">Unit Cost</th>
                            <th class="px-4 py-3 text-right">Value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($inventory as $inv)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-600 text-xs">{{ $inv->kitchen?->name ?? '—' }}</td>
                                <td class="px-4 py-3 font-medium text-gray-700">{{ $inv->ingredient?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">{{ number_format($inv->quantity_on_hand, 2) }}</td>
                                <td class="px-4 py-3 text-center text-gray-500">{{ $inv->uom?->abbreviation ?? '' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-600">{{ number_format($inv->unit_cost, 2) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ number_format(floatval($inv->quantity_on_hand) * floatval($inv->unit_cost), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($inventory->hasPages())
                    <div class="px-4 py-3 border-t border-gray-100">{{ $inventory->links() }}</div>
                @endif
            @else
                <div class="p-8 text-center text-gray-400 text-sm">
                    No inventory yet. Complete a production order to stock items.
                </div>
            @endif
        </div>
    @endif
</div>
