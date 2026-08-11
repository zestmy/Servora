@php
    use App\Support\CostVerdict;

    /**
     * COGS breakdown by category.
     *
     * Previously fixed here: the cost percentage carried its verdict in colour
     * alone, so roughly one man in twelve got no verdict at all; hand-written
     * padding and header colours sat on top of .table-surface and drifted from
     * every other table; and the totals row was a <tr> in <tbody> rather than
     * a <tfoot>, which assistive tech treats differently.
     *
     * The verdict logic has moved to CostVerdict so the gauges above this
     * table cannot reach a different conclusion about the same number — they
     * previously each carried their own copy of the thresholds.
     *
     * The remaining gap was comparison. A category at 33% is either drifting
     * up from 28% or recovering from 39%, and those are opposite situations
     * that the table rendered identically. The prior-period column is optional
     * because a rolling range and a month are not costed the same way, and
     * putting them side by side would invite a subtraction that isn't valid.
     *
     * Expects: $costSummary, $daysElapsed. Optionally $priorCostSummary.
     */
    $ready = CostVerdict::isReady($daysElapsed ?? 99);

    // Prior figures keyed by category name. Name rather than id because that
    // is what the summary exposes, and a renamed category should read as a new
    // row rather than silently inheriting another one's history.
    $prior = collect($priorCostSummary['categories'] ?? [])->keyBy('name');
    $showPrior = $prior->isNotEmpty();
@endphp

<div class="table-scroll">
    <table class="table-surface">
        {{-- Built in PHP, not with an inline @if. Blade only compiles a
             directive at a non-word boundary, so `category@if (...)` — the @
             sitting straight after a letter — was left uncompiled and the
             literal text "@if ($showPrior)" rendered into the caption. --}}
        <caption class="sr-only">
            {{ 'Revenue, cost of goods sold and cost percentage by category'
               . ($showPrior ? ', with the previous period for comparison' : '') }}
        </caption>

        <thead>
            <tr>
                <th scope="col">Category</th>
                <th scope="col" class="text-right">Revenue</th>
                <th scope="col" class="text-right">COGS</th>
                <th scope="col" class="text-right">Cost %</th>
                @if ($showPrior)
                    <th scope="col" class="text-right">Prior</th>
                    <th scope="col" class="text-right">Change</th>
                @endif
            </tr>
        </thead>

        <tbody>
            @foreach ($costSummary['categories'] as $cat)
                @php
                    $pct     = (float) $cat['cost_pct'];
                    $verdict = $ready ? CostVerdict::for($pct) : CostVerdict::pending();

                    $priorPct = $showPrior && isset($prior[$cat['name']])
                        ? (float) $prior[$cat['name']]['cost_pct']
                        : null;

                    // Percentage POINTS, not a percentage change. 30% to 33% is
                    // three points; calling it "+10%" would be arithmetically
                    // defensible and read as something else entirely.
                    $change = $priorPct !== null ? round($pct - $priorPct, 1) : null;
                @endphp

                <tr>
                    <th scope="row" class="px-4 py-3 text-left font-medium text-gray-900">
                        <span class="flex items-center gap-2">
                            @if ($cat['color'])
                                <span class="h-2 w-2 flex-none rounded-full"
                                      style="background:{{ $cat['color'] }}" aria-hidden="true"></span>
                            @endif
                            {{ $cat['name'] }}
                        </span>
                    </th>
                    <td class="num text-right">{{ number_format($cat['revenue'], 2) }}</td>
                    <td class="num text-right">{{ number_format($cat['cogs'], 2) }}</td>
                    <td class="num text-right font-semibold {{ $verdict['tone'] }}">
                        {{ $cat['cost_pct'] }}%
                        <span class="sr-only">, {{ $verdict['word'] }}</span>
                    </td>

                    @if ($showPrior)
                        <td class="num text-right text-gray-600">
                            {{ $priorPct !== null ? $priorPct . '%' : '—' }}
                        </td>
                        <td class="num text-right font-medium
                                   {{ $change === null ? 'text-gray-600' : ($change > 0 ? 'text-danger-700' : ($change < 0 ? 'text-success-700' : 'text-gray-600')) }}">
                            @if ($change === null)
                                —
                            @elseif ($change == 0.0)
                                No change
                            @else
                                {{ $change > 0 ? '+' : '' }}{{ number_format($change, 1) }} pts
                                {{-- Cost rising is bad news, so the words say so
                                     rather than leaving red to imply it. --}}
                                <span class="sr-only">, {{ $change > 0 ? 'worse than prior period' : 'better than prior period' }}</span>
                            @endif
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>

        @php
            $totalPct     = (float) $costSummary['totals']['cost_pct'];
            $totalVerdict = $ready ? CostVerdict::for($totalPct) : CostVerdict::pending();

            $priorTotalPct = $showPrior && isset($priorCostSummary['totals']['cost_pct'])
                ? (float) $priorCostSummary['totals']['cost_pct']
                : null;
            $totalChange = $priorTotalPct !== null ? round($totalPct - $priorTotalPct, 1) : null;
        @endphp

        <tfoot>
            <tr class="bg-gray-50 font-semibold">
                <th scope="row" class="px-4 py-3 text-left text-gray-900">Total</th>
                <td class="num px-4 py-3 text-right text-gray-900">{{ number_format($costSummary['totals']['revenue'], 2) }}</td>
                <td class="num px-4 py-3 text-right text-gray-900">{{ number_format($costSummary['totals']['cogs'], 2) }}</td>
                <td class="num px-4 py-3 text-right {{ $totalVerdict['tone'] }}">
                    {{ $costSummary['totals']['cost_pct'] }}%
                    <span class="sr-only">, {{ $totalVerdict['word'] }}</span>
                </td>

                @if ($showPrior)
                    <td class="num px-4 py-3 text-right text-gray-600">
                        {{ $priorTotalPct !== null ? $priorTotalPct . '%' : '—' }}
                    </td>
                    <td class="num px-4 py-3 text-right
                               {{ $totalChange === null ? 'text-gray-600' : ($totalChange > 0 ? 'text-danger-700' : ($totalChange < 0 ? 'text-success-700' : 'text-gray-600')) }}">
                        @if ($totalChange === null)
                            —
                        @elseif ($totalChange == 0.0)
                            No change
                        @else
                            {{ $totalChange > 0 ? '+' : '' }}{{ number_format($totalChange, 1) }} pts
                            <span class="sr-only">, {{ $totalChange > 0 ? 'worse than prior period' : 'better than prior period' }}</span>
                        @endif
                    </td>
                @endif
            </tr>
        </tfoot>
    </table>
</div>
