{{-- Appointed Approver Dashboard — any user holding PO approval appointments.

     The approval queue on this page is the best-built thing on any of the
     seven dashboards: real inline actions, correct hit areas, proper
     accessible names. It was let down by an "Operations Snapshot" panel
     sitting beside it that restated four figures already shown as tiles on the
     same screen, and by a hand-pasted <svg> empty state.

     So this one is mostly subtraction. Removing the duplicate panel gives the
     queue the room to show what it was missing: how long each PO has waited,
     which is the whole question an approver is answering. --}}

{{-- Approver scope --}}
@if (! empty($approverOutletNames))
    <p class="alert-info mb-4 text-xs">
        <x-icon name="info" size="h-4 w-4" stroke="1.8" class="mt-px flex-none" />
        <span>PO approver for <strong class="font-semibold">{{ implode(', ', $approverOutletNames) }}</strong></span>
    </p>
@endif

@include('livewire.dashboard.partials.alerts')

@php
    $staleDays = (int) config('costing.po_stale_days');
    $stale = $oldestWait !== null && $oldestWait >= $staleDays;

    /*
     * These four are a pipeline: awaiting → approved → processing → pending
     * receipt. Only the two ends carry a tone, and only when they are
     * non-zero: a queue with work in it, and goods sitting unreceived. The
     * middle two are counts.
     */
    $tiles = [
        [
            'label' => 'Awaiting approval',
            'value' => number_format($awaitingApproval),
            'color' => $stale ? 'red' : ($awaitingApproval > 0 ? 'amber' : null),
            'sub'   => $oldestWait !== null
                        ? 'Oldest waiting ' . $oldestWait . ' ' . Str::plural('day', $oldestWait)
                        : 'Queue is empty',
        ],
        ['label' => 'Approved',        'value' => number_format($approvedPOs)],
        ['label' => 'Processing (DO)', 'value' => number_format($sentPOs)],
        [
            'label' => 'Pending receipt',
            'value' => number_format($pendingGrns),
            'color' => $pendingGrns > 0 ? 'amber' : null,
            'href'  => route('purchasing.index', ['tab' => 'grn']),
        ],
    ];
@endphp

@include('livewire.dashboard.partials.stat-cards', ['stats' => $tiles])

<div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">

    {{-- PO Approval Queue --}}
    <div class="card lg:col-span-2">
        <div class="px-6 pt-6">
            {{-- Oldest first. The queue used to come back newest first, which
                 is the opposite of the order an approver should work it in. --}}
            <x-card-title :action="$awaitingApproval > 0 ? 'View all (' . $awaitingApproval . ')' : null"
                          :action-href="route('purchasing.index', ['tab' => 'po', 'statusFilter' => 'submitted'])">
                Approval queue, oldest first
            </x-card-title>
        </div>

        @if ($recentSubmittedPOs->count() > 0)
            <ul class="stack border-t border-gray-100">
                @foreach ($recentSubmittedPOs as $po)
                    @php
                        $waited  = (int) $po->created_at->diffInDays(now());
                        $isStale = $waited >= $staleDays;
                    @endphp
                    <li class="flex items-center justify-between gap-4 px-6 py-4 transition-colors hover:bg-gray-50">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <a href="{{ route('purchasing.orders.edit', $po->id) }}"
                                   class="font-mono text-xs font-medium text-brand-700 hover:underline">
                                    {{ $po->po_number }}
                                </a>
                                <span class="{{ $isStale ? 'badge-danger' : 'badge-neutral' }} tabular-nums">
                                    {{ $waited === 0 ? 'Today' : $waited . 'd waiting' }}
                                </span>
                            </div>

                            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span class="text-sm text-gray-700">{{ $po->supplier?->name ?? '—' }}</span>
                                <span class="text-xs text-gray-600">{{ $po->outlet?->name ?? '' }}</span>
                            </div>

                            <p class="mt-1 font-semibold tabular-nums text-gray-900">
                                RM {{ number_format($po->total_amount, 2) }}
                            </p>
                        </div>

                        {{-- .icon-btn keeps the small look but pushes the hit
                             area to 44px, and each control carries a real
                             accessible name rather than a title attribute a
                             touch user never sees. --}}
                        <div class="ml-4 flex flex-none items-center gap-1">
                            <a href="{{ route('purchasing.pdf', ['type' => 'po', 'id' => $po->id]) }}" target="_blank"
                               class="icon-btn" aria-label="View PDF for {{ $po->po_number }}">
                                <x-icon name="document" size="h-4 w-4" stroke="2" />
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
                    </li>
                @endforeach
            </ul>
        @else
            {{-- Was a hand-pasted 40px <svg> in a bespoke centred block. The
                 .empty-state trio has been in the stylesheet all along. --}}
            <div class="px-6 pb-6">
                <div class="empty-state">
                    <x-icon name="check" size="h-8 w-8" stroke="1.8" class="text-success-600" />
                    <p class="empty-title">All caught up</p>
                    <p class="empty-body">No purchase orders are waiting for your approval.</p>
                </div>
            </div>
        @endif
    </div>

    {{-- Quick Actions. The Operations Snapshot that used to sit here listed
         six figures, four of which were already tiles on this page. --}}
    <div class="card p-6">
        @include('livewire.dashboard.partials.quick-actions', ['actions' => [
            ['route' => 'purchasing.index', 'params' => ['tab' => 'po', 'statusFilter' => 'submitted'],
             'icon'  => 'inbox',                        'label' => 'Review POs', 'count' => $awaitingApproval],
            ['route' => 'purchasing.orders.create',     'icon' => 'cart',     'label' => 'New purchase order'],
            ['route' => 'sales.create',                 'icon' => 'currency', 'label' => 'Record sales'],
            ['route' => 'inventory.stock-takes.create', 'icon' => 'database', 'label' => 'New stock take'],
        ]])

        <dl class="mt-6 space-y-2.5 border-t border-gray-200 pt-4 text-sm">
            <div class="flex items-center justify-between gap-3">
                <dt class="text-gray-600">Revenue this period</dt>
                <dd class="font-semibold tabular-nums text-gray-900">
                    RM {{ number_format($revenue['value'], 0) }}
                </dd>
            </div>
            <div class="flex items-center justify-between gap-3">
                <dt class="text-gray-600">Purchases</dt>
                <dd class="font-semibold tabular-nums text-gray-900">
                    RM {{ number_format($purchases['value'], 0) }}
                </dd>
            </div>
            <div class="flex items-center justify-between gap-3">
                <dt class="text-gray-600">Wastage</dt>
                <dd class="font-semibold tabular-nums text-gray-900">
                    RM {{ number_format($wastage['value'], 0) }}
                </dd>
            </div>
        </dl>
    </div>
</div>

{{-- Revenue vs cost of sales --}}
<div class="card p-6">
    @include('livewire.dashboard.partials.trend-chart', ['trendMonths' => $trendMonths])
</div>
