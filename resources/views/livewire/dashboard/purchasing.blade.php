{{-- Purchasing Dashboard — PO / DO / GRN.

     This was six counts and a bar chart of the same six counts. Nothing said
     how long anything had been waiting, which is the dimension the role turns
     on: four POs awaiting review is unremarkable if they arrived this morning
     and a problem if the oldest is nine days old, and the two rendered
     identically.

     The pipeline panel also read its four values out of $stats[0] through
     $stats[3] — positional coupling to the tile array, so inserting or
     reordering a tile relabelled the pipeline with no error at all. Every
     figure below comes in named. --}}

@include('livewire.dashboard.partials.alerts')

@php
    $stale = $oldestWait !== null && $oldestWait >= $staleDays;

    $tiles = [
        [
            'label' => 'Awaiting review',
            'value' => number_format($submittedPOs),
            'color' => $stale ? 'red' : ($submittedPOs > 0 ? 'amber' : null),
            'sub'   => $oldestWait !== null
                        ? 'Oldest waiting ' . $oldestWait . ' ' . Str::plural('day', $oldestWait)
                        : 'Queue is empty',
            'href'  => route('purchasing.index', ['tab' => 'po', 'statusFilter' => 'submitted']),
        ],
        [
            'label' => 'Approved',
            'value' => number_format($approvedPOs),
            'href'  => route('purchasing.index', ['tab' => 'po', 'statusFilter' => 'approved']),
        ],
        [
            'label' => 'Pending delivery',
            'value' => number_format($pendingDOs),
            'sub'   => $todayDOs > 0 ? $todayDOs . ' due today' : 'None due today',
            'href'  => route('purchasing.index', ['tab' => 'do']),
        ],
        [
            'label' => 'Pending receipt',
            'value' => number_format($pendingGRNs),
            'color' => $pendingGRNs > 0 ? 'amber' : null,
            'href'  => route('purchasing.index', ['tab' => 'grn']),
        ],
        [
            /*
             * Spend was passed as 'color' => 'red', which resolves to
             * text-danger-700 — routine procurement rendered in the colour the
             * product uses for failures. Reads in ink now, with a delta doing
             * the work the colour was pretending to do.
             */
            'label'   => 'Spend',
            'prefix'  => 'RM',
            'value'   => number_format($spend['value'], 0),
            'current' => $spend['value'],
            'prior'   => $spend['prior'],
            'inverse' => true,
            'compare' => $compareLabel,
            'spark'   => $spendSpark,
        ],
    ];
@endphp

@include('livewire.dashboard.partials.stat-cards', ['stats' => $tiles])

<div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
    {{-- The ageing queue. The counts above say how many; this says which, and
         how long each has been sitting. --}}
    <div class="card lg:col-span-2">
        <div class="px-6 pt-6">
            <x-card-title :action="$submittedPOs > 0 ? 'View all (' . $submittedPOs . ')' : null"
                          :action-href="route('purchasing.index', ['tab' => 'po', 'statusFilter' => 'submitted'])">
                Submitted, oldest first
            </x-card-title>
        </div>

        @if ($ageingQueue->isNotEmpty())
            <ul class="stack border-t border-gray-100">
                @foreach ($ageingQueue as $po)
                    @php
                        $waited = (int) $po->created_at->diffInDays(now());
                        $isStale = $waited >= $staleDays;
                    @endphp
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-6 py-3.5 transition-colors hover:bg-gray-50">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <a href="{{ route('purchasing.orders.edit', $po->id) }}"
                                   class="font-mono text-xs font-medium text-brand-700 hover:underline">
                                    {{ $po->po_number }}
                                </a>
                                <span class="text-sm text-gray-700">{{ $po->supplier?->name ?? '—' }}</span>
                            </div>
                            <p class="mt-0.5 text-xs text-gray-600">
                                {{ $po->outlet?->name ?? '' }} · raised {{ $po->order_date?->format('d M Y') }}
                            </p>
                        </div>

                        <span class="font-semibold tabular-nums text-gray-900">
                            RM {{ number_format($po->total_amount, 2) }}
                        </span>

                        {{-- The wait is the point of this list, so it gets a
                             badge rather than being buried in the meta line.
                             The word "waiting" is in the badge, so the tone is
                             not the only thing saying it is late. --}}
                        <span class="{{ $isStale ? 'badge-danger' : 'badge-neutral' }} tabular-nums">
                            {{ $waited === 0 ? 'Today' : $waited . 'd waiting' }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="px-6 pb-6">
                <div class="empty-state">
                    <x-icon name="check" size="h-8 w-8" stroke="1.8" class="text-success-600" />
                    <p class="empty-title">Nothing waiting for review</p>
                    <p class="empty-body">Submitted purchase orders will appear here, oldest first.</p>
                </div>
            </div>
        @endif
    </div>

    {{-- Workflow pipeline --}}
    <div class="card p-6">
        <x-card-title>Workflow pipeline</x-card-title>

        @php
            /*
             * Four ordered stages of one process, so they take one hue
             * stepping darker along the pipeline rather than four unrelated
             * status colours. Sequential data gets a sequential ramp.
             *
             * Named values, not $stats[0..3].
             */
            $pipeline = [
                ['label' => 'POs submitted',        'value' => $submittedPOs, 'bar' => 'bg-brand-300'],
                ['label' => 'POs approved',         'value' => $approvedPOs,  'bar' => 'bg-brand-500'],
                ['label' => 'DOs pending delivery', 'value' => $pendingDOs,   'bar' => 'bg-brand-600'],
                ['label' => 'GRNs pending receipt', 'value' => $pendingGRNs,  'bar' => 'bg-brand-800'],
            ];
            $maxPipeline = max(collect($pipeline)->max('value'), 1);
        @endphp

        <div class="space-y-4">
            @foreach ($pipeline as $step)
                @php $pct = min(($step['value'] / $maxPipeline) * 100, 100); @endphp
                <div>
                    <div class="mb-1 flex items-center justify-between text-sm">
                        <span class="text-gray-700">{{ $step['label'] }}</span>
                        <span class="font-semibold tabular-nums text-gray-900">{{ $step['value'] }}</span>
                    </div>
                    {{-- The count beside the label is the real readout, so the
                         bar is presentational and stays out of the a11y tree. --}}
                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
                        <div class="h-2.5 rounded-full {{ $step['bar'] }}" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    {{-- Procurement cashflow.

         This is where Revenue vs Purchases went. Purchases is cash out in the
         period — it is not the cost of the goods that produced the period's
         revenue — so plotting it against revenue invited the gap to be read as
         profit. On this dashboard cash out IS the subject, so it gets a chart
         of its own and the misleading pairing is retired. --}}
    <div class="card p-6 lg:col-span-2">
        @include('livewire.dashboard.partials.trend-chart', [
            'trendMonths' => $trendMonths,
            'series'      => [
                ['key' => 'purchases', 'label' => 'Purchases', 'bar' => 'bg-chart-2'],
            ],
            'title'    => 'Procurement spend',
            'subtitle' => 'Last 6 months, in RM. Cash out, not cost of sales.',
            'caption'  => 'Purchase spend by month, in ringgit',
        ])
    </div>

    <div class="card p-6">
        <x-card-title>Where the money went</x-card-title>

        @if (! empty($topSuppliers))
            <ul class="space-y-3">
                @foreach ($topSuppliers as $supplier)
                    <li>
                        <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                            <span class="truncate font-medium text-gray-800">{{ $supplier['name'] }}</span>
                            <span class="flex-none font-semibold tabular-nums text-gray-900">
                                RM {{ number_format($supplier['total'], 0) }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
                                <div class="h-1.5 rounded-full bg-brand-500" style="width: {{ $supplier['share'] }}%"></div>
                            </div>
                            <span class="w-9 flex-none text-right text-xs tabular-nums text-gray-600">
                                {{ $supplier['share'] }}%
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
            <p class="mt-4 text-xs text-gray-600">Share of spend in this period.</p>
        @else
            <div class="empty-state">
                <x-icon name="truck" size="h-8 w-8" stroke="1.5" class="text-gray-500" />
                <p class="empty-title">No purchases in this period</p>
                <p class="empty-body">Supplier spend appears here once purchases are recorded.</p>
            </div>
        @endif
    </div>
</div>
