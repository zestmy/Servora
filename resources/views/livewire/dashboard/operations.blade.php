{{-- PO Approver Dashboard — shown to any user with PO approval appointments --}}

{{-- Approver scope --}}
@if (!empty($approverOutletNames))
    <div class="mb-4 px-4 py-2.5 bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs rounded-lg">
        PO Approver for: <strong>{{ implode(', ', $approverOutletNames) }}</strong>
    </div>
@endif

{{-- Alerts --}}
@if (count($alerts) > 0)
    <div class="mb-6 space-y-2">
        @foreach ($alerts as $alert)
            <div class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium
                {{ $alert['type'] === 'warning' ? 'bg-amber-50 text-amber-800 border border-amber-200' : '' }}
                {{ $alert['type'] === 'info' ? 'bg-blue-50 text-blue-800 border border-blue-200' : '' }}
                {{ $alert['type'] === 'alert' ? 'bg-red-50 text-red-800 border border-red-200' : '' }}">
                @if ($alert['type'] === 'warning')
                    <svg class="w-5 h-5 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                @elseif ($alert['type'] === 'alert')
                    <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                @else
                    <svg class="w-5 h-5 text-blue-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                @endif
                {{ $alert['message'] }}
            </div>
        @endforeach
    </div>
@endif

{{-- Purchasing Pipeline Stats --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-5 border {{ $awaitingApproval > 0 ? 'border-amber-200 bg-amber-50' : 'border-gray-100' }}">
        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Awaiting Approval</div>
        <div class="mt-1 text-2xl font-bold {{ $awaitingApproval > 0 ? 'text-amber-600' : 'text-gray-400' }}">{{ $awaitingApproval }}</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Approved</div>
        <div class="mt-1 text-2xl font-bold text-indigo-600">{{ $approvedPOs }}</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Processing (DO)</div>
        <div class="mt-1 text-2xl font-bold text-blue-600">{{ $sentPOs }}</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Pending Receipt</div>
        <div class="mt-1 text-2xl font-bold {{ $pendingGrns > 0 ? 'text-green-600' : 'text-gray-400' }}">{{ $pendingGrns }}</div>
    </div>
</div>

{{-- Quick Approval Panel + Operational Stats --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

    {{-- PO Approval Queue --}}
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-600">PO Approval Queue</h3>
            @if ($awaitingApproval > 0)
                <a href="{{ route('purchasing.index', ['tab' => 'po', 'statusFilter' => 'submitted']) }}"
                   class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View All ({{ $awaitingApproval }}) &rarr;</a>
            @endif
        </div>
        @if ($recentSubmittedPOs->count() > 0)
            <div class="divide-y divide-gray-50">
                @foreach ($recentSubmittedPOs as $po)
                    <div class="px-6 py-4 flex items-center justify-between hover:bg-gray-50 transition">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('purchasing.orders.edit', $po->id) }}"
                                   class="font-mono text-xs font-medium text-indigo-600 hover:underline">{{ $po->po_number }}</a>
                                <span class="text-xs text-gray-400">{{ $po->order_date->format('d M Y') }}</span>
                            </div>
                            <div class="flex items-center gap-3 mt-1">
                                <span class="text-sm text-gray-700">{{ $po->supplier?->name ?? '—' }}</span>
                                <span class="text-xs text-gray-400">{{ $po->outlet?->name ?? '' }}</span>
                            </div>
                            <div class="mt-1 text-sm font-semibold text-gray-800">RM {{ number_format($po->total_amount, 2) }}</div>
                        </div>
                        <div class="flex items-center gap-2 ml-4">
                            <a href="{{ route('purchasing.pdf', ['type' => 'po', 'id' => $po->id]) }}" target="_blank"
                               title="View PDF"
                               class="p-2 text-gray-400 hover:text-gray-600 transition rounded-lg hover:bg-gray-100">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </a>
                            <button wire:click="approvePo({{ $po->id }})"
                                    wire:confirm="Approve '{{ $po->po_number }}'?"
                                    title="Approve"
                                    class="p-2 bg-green-50 text-green-600 hover:bg-green-100 hover:text-green-700 transition rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            </button>
                            <button wire:click="rejectPo({{ $po->id }})"
                                    wire:confirm="Reject '{{ $po->po_number }}'? This will cancel the PO."
                                    title="Reject"
                                    class="p-2 bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 transition rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="px-6 py-12 text-center text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto mb-3 text-green-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="font-medium text-gray-500">All caught up</p>
                <p class="text-xs mt-1">No purchase orders awaiting approval.</p>
            </div>
        @endif
    </div>

    {{-- Right Column: Operational Snapshot --}}
    <div class="space-y-4">
        {{-- Operational Counts --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="text-sm font-semibold text-gray-600 mb-3">Operations Snapshot</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Active Ingredients</span>
                    <span class="text-sm font-bold text-gray-800">{{ number_format($totalIngredients) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Active Recipes</span>
                    <span class="text-sm font-bold text-gray-800">{{ number_format($activeRecipes) }}</span>
                </div>
                <div class="flex items-center justify-between border-t border-gray-100 pt-3">
                    <span class="text-sm text-gray-600">Today Revenue</span>
                    <span class="text-sm font-bold text-gray-800">{{ number_format($todayRevenue, 0) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Month Revenue</span>
                    <span class="text-sm font-bold text-green-600">{{ number_format($monthRevenue, 0) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Month Purchases</span>
                    <span class="text-sm font-bold text-red-600">{{ number_format($monthPurchases, 0) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Month Wastage</span>
                    <span class="text-sm font-bold text-orange-600">{{ number_format($monthWastage, 0) }}</span>
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="text-sm font-semibold text-gray-600 mb-3">Quick Actions</h3>
            <div class="space-y-2">
                <a href="{{ route('purchasing.index', ['tab' => 'po', 'statusFilter' => 'submitted']) }}"
                   class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-amber-300 hover:bg-amber-50 transition text-sm">
                    <span class="text-lg">📥</span>
                    <span class="font-medium text-gray-700">Review POs</span>
                    @if ($awaitingApproval > 0)
                        <span class="ml-auto px-2 py-0.5 bg-amber-100 text-amber-700 text-xs font-bold rounded-full">{{ $awaitingApproval }}</span>
                    @endif
                </a>
                <a href="{{ route('purchasing.orders.create') }}"
                   class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 transition text-sm">
                    <span class="text-lg">🛒</span>
                    <span class="font-medium text-gray-700">New Purchase Order</span>
                </a>
                <a href="{{ route('sales.create') }}"
                   class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 transition text-sm">
                    <span class="text-lg">💰</span>
                    <span class="font-medium text-gray-700">Record Sales</span>
                </a>
                <a href="{{ route('inventory.stock-takes.create') }}"
                   class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 transition text-sm">
                    <span class="text-lg">📦</span>
                    <span class="font-medium text-gray-700">New Stock Take</span>
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Revenue vs Purchases Trend --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    @include('livewire.dashboard.partials.trend-chart', ['trendMonths' => $trendMonths])
</div>
