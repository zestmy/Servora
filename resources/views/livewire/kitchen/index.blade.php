<div>
    {{-- Flash — persistent + dismissible: on a shared kitchen tablet a
         3-second toast is gone before anyone looks up. --}}
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg flex items-center justify-between gap-3">
            <span>{{ session('success') }}</span>
            <button @click="show = false" title="Dismiss" class="flex-shrink-0 text-success-400 hover:text-success-600 p-1.5">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 bg-danger-50 border border-danger-200 text-danger-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h2 class="page-title">Kitchen</h2>
        <div class="flex gap-2">
            {{-- Stock reaches outlets by transfer, not by request. --}}
            <a href="{{ route('inventory.transfers.create') }}"
               class="px-4 py-2 bg-white text-brand-600 text-sm font-medium rounded-lg border border-brand-200 hover:bg-brand-50 transition">
                + Send to Outlet
            </a>
            <a href="{{ route('kitchen.orders.create') }}"
               class="px-4 py-2 bg-brand-600 text-white text-sm font-medium rounded-lg hover:bg-brand-700 transition">
                + Production Order
            </a>
        </div>
    </div>

    {{-- Stats — click-through to the tab/status behind each number. --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        @foreach ($stats as $stat)
            @php
                $accent = ['indigo' => 'group-hover:border-brand-300', 'yellow' => 'group-hover:border-warning-300', 'green' => 'group-hover:border-success-300'][$stat['color']] ?? '';
                $value  = ['indigo' => 'text-brand-700', 'yellow' => 'text-warning-600', 'green' => 'text-success-700'][$stat['color']] ?? 'text-gray-800';
            @endphp
            <button type="button" wire:click="openStat('{{ $stat['tab'] }}', '{{ $stat['status'] }}')"
                    class="group text-left card p-5 transition hover:shadow-md {{ $accent }}">
                <p class="text-xs text-gray-600 uppercase tracking-wider">{{ $stat['label'] }}</p>
                <p class="text-2xl font-bold mt-1 {{ $stat['value'] > 0 ? $value : 'text-gray-500' }}">{{ $stat['value'] }}</p>
                <p class="text-[11px] text-gray-600 mt-1">{{ $stat['hint'] }}</p>
            </button>
        @endforeach
    </div>

    {{-- Tabs --}}
    <div class="border-b border-gray-200 mb-4">
        <nav class="flex gap-6 -mb-px">
            <button wire:click="$set('tab', 'orders')"
                    class="pb-3 px-1 text-sm font-medium border-b-2 transition {{ $tab === 'orders' ? 'border-brand-500 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Production Orders
            </button>
            <button wire:click="$set('tab', 'inventory')"
                    class="pb-3 px-1 text-sm font-medium border-b-2 transition {{ $tab === 'inventory' ? 'border-brand-500 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Inventory
            </button>
            <button wire:click="$set('tab', 'logs')"
                    class="pb-3 px-1 text-sm font-medium border-b-2 transition {{ $tab === 'logs' ? 'border-brand-500 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Production Logs
            </button>
            <a href="{{ route('kitchen.recipes.index') }}"
               class="pb-3 px-1 text-sm font-medium border-b-2 transition {{ request()->routeIs('kitchen.recipes.*') ? 'border-brand-500 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Production Recipes
            </a>
        </nav>
    </div>

    {{-- What the open tab is for: make it, hold it, then send it out. --}}
    @php
        $tabBlurb = [
            'orders'    => ['Production Orders', 'What this kitchen is making. Add production recipes or prep items, schedule the batch, then Execute to record what actually came out — finished quantities are added to kitchen stock.'],
            'inventory' => ['Kitchen Inventory', 'What this kitchen currently holds. Stock arrives when a production batch is completed, and leaves when you send it to an outlet with a transfer.'],
            'logs'      => ['Production Logs', 'Every completed batch, with planned versus actual yield so you can see where losses are happening.'],
        ][$tab] ?? null;
    @endphp
    @if ($tabBlurb)
        <div class="mb-4 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg">
            <p class="text-xs text-gray-600"><span class="font-semibold text-gray-700">{{ $tabBlurb[0] }}</span> — {{ $tabBlurb[1] }}</p>
        </div>
    @endif

    {{-- Filters --}}
    @if ($tab !== 'logs' || $tab === 'inventory')
    <div class="card p-4 mb-4">
        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
            @if ($tab === 'orders')
                <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <div class="flex items-center gap-1">
                    <input type="date" wire:model.live="dateFrom" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                    <span class="text-gray-600 text-xs">to</span>
                    <input type="date" wire:model.live="dateTo" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                </div>
            @endif
            {{-- Kitchen filter (all tabs) --}}
            @if ($kitchens->count() > 1)
                <select wire:model.live="kitchenFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
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
        <div class="card overflow-hidden">
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
                                        <a href="{{ route('kitchen.orders.edit', $order->id) }}" class="font-medium text-brand-600 hover:text-brand-800">
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
                                                'completed'   => 'bg-success-100 text-success-700',
                                                'cancelled'   => 'bg-danger-100 text-danger-600',
                                                default       => 'bg-gray-100 text-gray-500',
                                            } }}">
                                            {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                        </span>
                                        @php
                                            $orderHint = match($order->status) {
                                                'draft'       => 'Schedule it when ready',
                                                'scheduled'   => 'Ready — press Start to begin',
                                                'in_progress' => 'Enter actual quantities, then complete',
                                                'completed'   => 'Output added to kitchen stock',
                                                default       => null,
                                            };
                                        @endphp
                                        @if ($orderHint)
                                            <p class="text-[10px] text-gray-600 mt-0.5">{{ $orderHint }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-600">{{ $order->lines_count }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $order->createdBy?->name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        {{-- Tap-sized buttons: kitchens run on tablets --}}
                                        <div class="flex gap-2 justify-end">
                                            @if ($order->status === 'draft')
                                                @if ($canProduce)
                                                    <button wire:click="scheduleOrder({{ $order->id }})"
                                                            class="inline-flex items-center min-h-[40px] px-3.5 py-2 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition">Schedule</button>
                                                @endif
                                                @if ($canManage)
                                                    <button wire:click="cancelOrder({{ $order->id }})"
                                                            wire:confirm="Cancel {{ $order->order_number }}? This can't be undone — the order stays on record as cancelled."
                                                            class="inline-flex items-center min-h-[40px] px-3.5 py-2 text-danger-600 border border-danger-200 text-xs font-semibold rounded-lg hover:bg-danger-50 transition">Cancel</button>
                                                @endif
                                            @elseif ($order->status === 'scheduled')
                                                @if ($canProduce)
                                                    <a href="{{ route('kitchen.orders.execute', $order->id) }}"
                                                       class="inline-flex items-center min-h-[40px] px-3.5 py-2 bg-success-600 text-white text-xs font-semibold rounded-lg hover:bg-success-700 transition">Start</a>
                                                @endif
                                                @if ($canManage)
                                                    <button wire:click="cancelOrder({{ $order->id }})"
                                                            wire:confirm="Cancel {{ $order->order_number }}? This can't be undone — the order stays on record as cancelled."
                                                            class="inline-flex items-center min-h-[40px] px-3.5 py-2 text-danger-600 border border-danger-200 text-xs font-semibold rounded-lg hover:bg-danger-50 transition">Cancel</button>
                                                @endif
                                            @elseif ($order->status === 'in_progress')
                                                @if ($canProduce)
                                                    <a href="{{ route('kitchen.orders.execute', $order->id) }}"
                                                       class="inline-flex items-center min-h-[40px] px-3.5 py-2 bg-success-600 text-white text-xs font-semibold rounded-lg hover:bg-success-700 transition">Continue</a>
                                                @endif
                                            @endif
                                            @if (! $canProduce && ! $canManage && in_array($order->status, ['draft', 'scheduled', 'in_progress']))
                                                <span class="text-xs text-gray-600 self-center">View only</span>
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
                <div class="p-10 text-center">
                    <div class="text-3xl mb-2">🍳</div>
                    <p class="font-medium text-gray-600 text-sm">
                        {{ $statusFilter || $dateFrom || $dateTo ? 'No production orders match these filters' : 'No production orders yet' }}
                    </p>
                    <p class="text-xs text-gray-600 mt-1 max-w-md mx-auto">
                        A production order is a batch this kitchen will make. Add production recipes
                        or prep items, schedule it, then record what actually came out.
                    </p>
                    <a href="{{ route('kitchen.orders.create') }}"
                       class="inline-flex items-center mt-4 px-4 py-2 bg-brand-600 text-white text-sm font-medium rounded-lg hover:bg-brand-700 transition">
                        + Create your first production order
                    </a>
                </div>
            @endif
        </div>

    @elseif ($tab === 'logs')
        {{-- Logs Table --}}
        <div class="card overflow-hidden">
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
                                    {{-- A batch comes from either a production recipe or a prep item. --}}
                                    <td class="px-4 py-3 text-gray-700">{{ $log->producedItemName() }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-600">{{ rtrim(rtrim(number_format(floatval($log->planned_yield), 4), '0'), '.') }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-600">{{ rtrim(rtrim(number_format(floatval($log->actual_yield), 4), '0'), '.') }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums font-medium {{ $variance >= 0 ? 'text-success-600' : 'text-danger-600' }}">
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
                <div class="p-8 text-center text-gray-600 text-sm">
                    No completed batches yet. Logs appear here once a production order is executed,
                    showing planned versus actual yield.
                </div>
            @endif
        </div>

    @elseif ($tab === 'inventory')
        {{-- Kitchen Inventory --}}
        <div class="card overflow-hidden">
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
                <div class="p-8 text-center text-gray-600 text-sm">
                    No inventory yet. Complete a production order to stock items.
                </div>
            @endif
        </div>
    @endif
</div>
