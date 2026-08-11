@php
    /**
     * A two-series monthly trend.
     *
     * Previously fixed here: Purchases was painted danger-400, which says "this
     * number is an error" about a routine operating figure; the values existed
     * only in a CSS opacity hover, unreachable by keyboard, touch or screen
     * reader; and the series classes were interpolated, so Tailwind's scanner
     * never generated them. Those stay fixed.
     *
     * What was wrong underneath is what the chart was comparing. "Revenue vs
     * Purchases" was the lead visual on four dashboards, drawn as paired bars
     * on a shared axis — a form that invites the reader to read the gap as
     * profit. It is not. Purchases is cash out in the period; it is not the
     * cost of the goods that produced that period's revenue. Stock bought in
     * March and sold in April lands in the wrong bar, and a big delivery on
     * the 30th makes a profitable month look like a loss.
     *
     * The dashboard was already computing real cost of sales for the same
     * window and not plotting it. The default series is Revenue vs COGS, where
     * the gap between the bars IS gross profit and the comparison the form
     * implies is the comparison being made.
     *
     * Purchases has not been thrown away — it moved to the purchasing
     * dashboard as a spend chart of its own, where cash out is the subject
     * rather than a stand-in for cost.
     *
     * Expects: $trendMonths. Optionally $series, $title, $subtitle, $caption.
     */
    $series = $series ?? [
        ['key' => 'revenue', 'label' => 'Revenue',       'bar' => 'bg-chart-1'],
        ['key' => 'cogs',    'label' => 'Cost of sales', 'bar' => 'bg-chart-2'],
    ];

    $title    = $title    ?? 'Revenue vs cost of sales';
    $subtitle = $subtitle ?? 'Last 6 months, in RM. The gap is gross profit.';
    $caption  = $caption  ?? 'Revenue and cost of sales by month, in ringgit';

    $maxVal = 1;
    foreach ($trendMonths as $m) {
        foreach ($series as $s) {
            $maxVal = max($maxVal, (float) ($m[$s['key']] ?? 0));
        }
    }

    $chartId = 'trend-' . Str::random(6);
@endphp

<figure aria-labelledby="{{ $chartId }}-title">
    <figcaption id="{{ $chartId }}-title" class="text-sm font-semibold text-gray-900">
        {{ $title }}
    </figcaption>
    <p class="mt-0.5 text-xs text-gray-600">{{ $subtitle }}</p>

    {{-- Two series, so a legend is always present. Each swatch is paired with
         its word, so identity is never carried by colour alone. --}}
    <ul class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2">
        @foreach ($series as $s)
            <li class="flex items-center gap-2 text-xs font-medium text-gray-700">
                <span class="h-2.5 w-2.5 flex-none rounded-sm {{ $s['bar'] }}" aria-hidden="true"></span>
                {{ $s['label'] }}
            </li>
        @endforeach
    </ul>

    {{-- Hidden on phones, where the bars are too narrow to read and the table
         below is the better instrument. --}}
    <div class="mt-4 hidden h-48 items-end gap-3 sm:flex" role="group" aria-label="{{ $caption }}">
        @foreach ($trendMonths as $m)
            <div class="flex flex-1 flex-col items-center gap-1.5">
                {{-- gap-0.5 is the 2px surface gap between adjacent fills. --}}
                <div class="flex w-full items-end gap-0.5" style="height: 160px">
                    @foreach ($series as $s)
                        @php
                            $val = (float) ($m[$s['key']] ?? 0);
                            $pct = $maxVal > 0 ? ($val / $maxVal * 100) : 0;
                        @endphp
                        <button type="button"
                                class="group relative flex-1 rounded-t-[4px] {{ $s['bar'] }}
                                       focus-visible:outline focus-visible:outline-2
                                       focus-visible:outline-offset-2 focus-visible:outline-brand-600"
                                style="height: {{ $pct }}%; min-height: 2px"
                                aria-label="{{ $s['label'] }}, {{ $m['label'] }}: RM {{ number_format($val, 0) }}">
                            <span class="pointer-events-none absolute -top-7 left-1/2 z-popover -translate-x-1/2
                                         whitespace-nowrap rounded-control bg-gray-900 px-2 py-1 text-xs
                                         font-medium tabular-nums text-white opacity-0 transition-opacity
                                         group-hover:opacity-100 group-focus-visible:opacity-100"
                                  aria-hidden="true">
                                {{ number_format($val, 0) }}
                            </span>
                        </button>
                    @endforeach
                </div>
                <span class="text-xs font-medium text-gray-600">{{ $m['label'] }}</span>
            </div>
        @endforeach
    </div>

    {{-- Phone: the table IS the chart. --}}
    <div class="table-scroll mt-4 sm:hidden">
        @include('livewire.dashboard.partials.trend-table', [
            'trendMonths' => $trendMonths,
            'series'      => $series,
            'caption'     => $caption,
        ])
    </div>

    {{-- Desktop: the same figures, collapsed, so the colour encoding has the
         fallback it needs without competing with the bars. --}}
    <details class="mt-5 hidden border-t border-gray-200 pt-3 sm:block">
        <summary class="cursor-pointer list-none text-xs font-medium text-gray-600 marker:content-none hover:text-gray-900">
            View as table
        </summary>
        <div class="table-scroll mt-3">
            @include('livewire.dashboard.partials.trend-table', [
                'trendMonths' => $trendMonths,
                'series'      => $series,
                'caption'     => $caption,
            ])
        </div>
    </details>
</figure>
