{{-- PO Approver Dashboard — shown to any user with PO approval appointments --}}

{{-- Approver scope --}}
@if (!empty($approverOutletNames))
    <p class="alert-info mb-4 text-xs">
        <x-icon name="info" size="h-4 w-4" stroke="1.8" class="mt-px flex-none" />
        <span>PO approver for <strong class="font-semibold">{{ implode(', ', $approverOutletNames) }}</strong></span>
    </p>
@endif

{{-- Third copy of the alert markup, now the shared partial. --}}
@include('livewire.dashboard.partials.alerts')

{{-- These four are a pipeline: awaiting → approved → processing → pending
     receipt. They were coloured amber / brand / blue / green, which reads as
     four unrelated states rather than four stages of one queue, and blue is
     not in the palette.

     Only the two ends carry a tone, and only when they are non-zero: a queue
     with work in it, and goods sitting unreceived. The middle two are counts. --}}
@include('livewire.dashboard.partials.stat-cards', ['stats' => [
    ['label' => 'Awaiting approval', 'value' => $awaitingApproval, 'color' => $awaitingApproval > 0 ? 'amber' : null],
    ['label' => 'Approved',          'value' => $approvedPOs],
    ['label' => 'Processing (DO)',   'value' => $sentPOs],
    ['label' => 'Pending receipt',   'value' => $pendingGrns, 'color' => $pendingGrns > 0 ? 'amber' : null],
]])

{{-- Quick Approval Panel + Operational Stats --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

    {{-- PO Approval Queue --}}
    <div class="lg:col-span-2 card">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-600">PO Approval Queue</h3>
            @if ($awaitingApproval > 0)
                <a href="{{ route('purchasing.index', ['tab' => 'po', 'statusFilter' => 'submitted']) }}"
                   class="text-xs text-brand-600 hover:text-brand-800 font-medium">View All ({{ $awaitingApproval }}) &rarr;</a>
            @endif
        </div>
        @if ($recentSubmittedPOs->count() > 0)
            <div class="divide-y divide-gray-50">
                @foreach ($recentSubmittedPOs as $po)
                    <div class="px-6 py-4 flex items-center justify-between hover:bg-gray-50 transition">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('purchasing.orders.edit', $po->id) }}"
                                   class="font-mono text-xs font-medium text-brand-600 hover:underline">{{ $po->po_number }}</a>
                                <span class="text-xs text-gray-600">{{ $po->order_date->format('d M Y') }}</span>
                            </div>
                            <div class="flex items-center gap-3 mt-1">
                                <span class="text-sm text-gray-700">{{ $po->supplier?->name ?? '—' }}</span>
                                <span class="text-xs text-gray-600">{{ $po->outlet?->name ?? '' }}</span>
                            </div>
                            <div class="mt-1 text-sm font-semibold text-gray-800">RM {{ number_format($po->total_amount, 2) }}</div>
                        </div>
                        {{-- These three were 32px targets with the label only in
                             a `title`, which a touch user never sees and a
                             screen reader may not announce. .icon-btn keeps the
                             small look but pushes the hit area to 44px, and the
                             name is now a real accessible name.

                             The reject glyph was danger-400 on danger-50:
                             2.53:1, under the 3:1 floor for a meaningful icon.
                             danger-700 on the same tint is 6.2:1. --}}
                        <div class="ml-4 flex items-center gap-1">
                            <a href="{{ route('purchasing.pdf', ['type' => 'po', 'id' => $po->id]) }}" target="_blank"
                               class="icon-btn" aria-label="View PDF for {{ $po->po_number }}">
                                <x-icon name="inbox" size="h-4 w-4" stroke="2" />
                            </a>
                            <button type="button"
                                    wire:click="approvePo({{ $po->id }})"
                                    wire:confirm="Approve '{{ $po->po_number }}'?"
                                    class="icon-btn text-success-700 hover:bg-success-50 hover:text-success-800"
                                    aria-label="Approve {{ $po->po_number }}">
                                <x-icon name="check" size="h-4 w-4" stroke="2.4" />
                            </button>
                            <button type="button"
                                    wire:click="rejectPo({{ $po->id }})"
                                    wire:confirm="Reject '{{ $po->po_number }}'? This will cancel the PO."
                                    class="icon-btn icon-btn-danger text-danger-700"
                                    aria-label="Reject {{ $po->po_number }}">
                                <x-icon name="alert" size="h-4 w-4" stroke="2" />
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="px-6 py-12 text-center text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto mb-3 text-success-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
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
        <div class="card p-5">
            <h3 class="text-sm font-semibold text-gray-600 mb-3">Operations Snapshot</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Active Products</span>
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
                    <span class="text-sm font-bold text-success-600">{{ number_format($monthRevenue, 0) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Month Purchases</span>
                    <span class="text-sm font-semibold tabular-nums text-gray-900">{{ number_format($monthPurchases, 0) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Month Wastage</span>
                    <span class="text-sm font-semibold tabular-nums text-gray-900">{{ number_format($monthWastage, 0) }}</span>
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="card p-5">
            @include('livewire.dashboard.partials.quick-actions', ['actions' => [
                ['route' => 'purchasing.index', 'params' => ['tab' => 'po', 'statusFilter' => 'submitted'],
                 'icon'  => 'inbox',                        'label' => 'Review POs', 'count' => $awaitingApproval],
                ['route' => 'purchasing.orders.create',     'icon' => 'cart',     'label' => 'New purchase order'],
                ['route' => 'sales.create',                 'icon' => 'currency', 'label' => 'Record sales'],
                ['route' => 'inventory.stock-takes.create', 'icon' => 'database', 'label' => 'New stock take'],
            ]])
        </div>
    </div>
</div>

{{-- Revenue vs Purchases Trend --}}
<div class="card p-6">
    @include('livewire.dashboard.partials.trend-chart', ['trendMonths' => $trendMonths])
</div>
