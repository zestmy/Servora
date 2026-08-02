@php
    /**
     * Revenue vs Purchases, last 6 months.
     *
     * Grouped bars on ONE axis. Both series are ringgit at the same scale, so
     * they share a scale; never two y-axes.
     *
     * Three things changed:
     *
     * 1. Purchases was painted danger-400. A status colour on a routine
     *    operating figure tells the reader the number is an error. Both series
     *    now come from the reserved categorical pair in tailwind.config.js,
     *    which is validated for colour-vision separation.
     *
     * 2. The values existed only in a CSS opacity hover — invisible to keyboard,
     *    invisible on touch, invisible to a screen reader. The numbers were
     *    unreachable for anyone not using a mouse. Each bar is now a focusable
     *    control carrying its own accessible name, and the same figures are
     *    available as a real table below.
     *
     * 3. Series classes are written out in full. `bg-{$tone}` would be
     *    invisible to Tailwind's scanner and would never be generated, which is
     *    the same bug the stat tiles had.
     */
    $maxVal  = max(collect($trendMonths)->max('revenue'), collect($trendMonths)->max('purchases'), 1);
    $chartId = 'trend-' . Str::random(6);

    $series = [
        ['key' => 'revenue',   'label' => 'Revenue',   'bar' => 'bg-chart-1', 'swatch' => 'bg-chart-1'],
        ['key' => 'purchases', 'label' => 'Purchases', 'bar' => 'bg-chart-2', 'swatch' => 'bg-chart-2'],
    ];
@endphp

<figure aria-labelledby="{{ $chartId }}-title">
    <figcaption id="{{ $chartId }}-title" class="text-sm font-semibold text-gray-900">
        Revenue vs purchases
    </figcaption>
    <p class="mt-0.5 text-xs text-gray-600">Last 6 months, in RM</p>

    {{-- Two series, so a legend is always present. Each swatch is paired with
         its word, so identity is never carried by colour alone. --}}
    <ul class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2">
        @foreach ($series as $s)
            <li class="flex items-center gap-2 text-xs font-medium text-gray-700">
                <span class="h-2.5 w-2.5 flex-none rounded-sm {{ $s['swatch'] }}" aria-hidden="true"></span>
                {{ $s['label'] }}
            </li>
        @endforeach
    </ul>

    <div class="mt-4 flex h-48 items-end gap-3" role="group" aria-label="Monthly revenue and purchases">
        @foreach ($trendMonths as $m)
            <div class="flex flex-1 flex-col items-center gap-1.5">
                {{-- gap-0.5 is the 2px surface gap between adjacent fills. --}}
                <div class="flex w-full items-end gap-0.5" style="height: 160px">
                    @foreach ($series as $s)
                        @php $pct = $maxVal > 0 ? ($m[$s['key']] / $maxVal * 100) : 0; @endphp
                        <button type="button"
                                class="group relative flex-1 rounded-t-[4px] {{ $s['bar'] }}
                                       focus-visible:outline focus-visible:outline-2
                                       focus-visible:outline-offset-2 focus-visible:outline-brand-600"
                                style="height: {{ $pct }}%; min-height: 2px"
                                aria-label="{{ $s['label'] }}, {{ $m['label'] }}: RM {{ number_format($m[$s['key']], 0) }}">
                            <span class="pointer-events-none absolute -top-7 left-1/2 z-popover -translate-x-1/2
                                         whitespace-nowrap rounded-control bg-gray-900 px-2 py-1 text-xs
                                         font-medium tabular-nums text-white opacity-0 transition-opacity
                                         group-hover:opacity-100 group-focus-visible:opacity-100"
                                  aria-hidden="true">
                                {{ number_format($m[$s['key']], 0) }}
                            </span>
                        </button>
                    @endforeach
                </div>
                <span class="text-xs font-medium text-gray-600">{{ $m['label'] }}</span>
            </div>
        @endforeach
    </div>

    {{-- The table view the colour encoding needs as a fallback. Collapsed so it
         does not compete with the chart, but it is real markup, reachable by
         keyboard and by find-in-page. --}}
    <details class="mt-5 border-t border-gray-200 pt-3">
        <summary class="cursor-pointer list-none text-xs font-medium text-gray-600 marker:content-none hover:text-gray-900">
            View as table
        </summary>
        <div class="table-scroll mt-3">
            <table class="table-surface">
                <caption class="sr-only">Revenue and purchases by month, in ringgit</caption>
                <thead>
                    <tr>
                        <th scope="col">Month</th>
                        <th scope="col" class="text-right">Revenue</th>
                        <th scope="col" class="text-right">Purchases</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($trendMonths as $m)
                        <tr>
                            <th scope="row" class="px-4 py-3 text-left font-medium text-gray-900">{{ $m['label'] }}</th>
                            <td class="num text-right">{{ number_format($m['revenue'], 2) }}</td>
                            <td class="num text-right">{{ number_format($m['purchases'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</figure>
