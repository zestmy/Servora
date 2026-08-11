@php
    use App\Support\CostVerdict;

    /**
     * Cost % by category, against target.
     *
     * This block existed twice, thirty-five lines each, in business.blade.php
     * and finance.blade.php — with a third variant in reports/index.blade.php.
     * Three copies of the same threshold logic is three places to change when
     * the targets move, and they were about to move, because they were
     * hardcoded literals in five files. One copy now, reading config.
     *
     * Two things the copies got wrong, both fixed here:
     *
     * 1. The verdict was carried by colour alone. The COGS table one file over
     *    had already fixed exactly this — it appends a visually-hidden "over
     *    target" to every figure — and the gauges never got the same
     *    treatment. Both now go through CostVerdict, so they cannot disagree.
     *
     * 2. A percentage computed from four days of trading was painted red or
     *    green like a finding. It shows in ink until enough of the window has
     *    elapsed to mean something.
     *
     * The target line is drawn on the track. A bar with no marker asks the
     * reader to remember what 35% looks like as a width; with the marker, over
     * and under is a position rather than a recollection.
     *
     * Expects: $costSummary, $daysElapsed, and $monthlyCosting.
     */
    $ready  = CostVerdict::isReady($daysElapsed ?? 99);
    $target = (float) config('costing.target.over');

    // The track tops out at 100%, so the marker sits at the target's own
    // percentage of that scale. A cost over 100% is a real (and dire) result,
    // and the bar clamps rather than overflowing its own card.
    $markerLeft = min($target, 100);
@endphp

<x-card-title :action="$monthlyCosting ? null : 'Excludes stock movement'">Cost % against target</x-card-title>

@if (! empty($costSummary['categories']))
    <div class="space-y-4">
        @foreach ($costSummary['categories'] as $cat)
            @php
                $pct     = (float) $cat['cost_pct'];
                $verdict = $ready ? CostVerdict::for($pct) : CostVerdict::pending();
            @endphp

            <div>
                <div class="mb-1.5 flex items-center justify-between gap-3 text-sm">
                    <span class="flex min-w-0 items-center gap-2">
                        @if ($cat['color'])
                            {{-- The category's own colour from the database.
                                 Decorative: the name beside it identifies it. --}}
                            <span class="h-2.5 w-2.5 flex-none rounded-full"
                                  style="background:{{ $cat['color'] }}" aria-hidden="true"></span>
                        @endif
                        <span class="truncate font-medium text-gray-700">{{ $cat['name'] }}</span>
                    </span>

                    <span class="flex-none font-semibold tabular-nums {{ $verdict['tone'] }}">
                        {{ $cat['cost_pct'] }}%
                        <span class="sr-only">, {{ $verdict['word'] }}</span>
                    </span>
                </div>

                <div class="relative h-3 w-full overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
                    <div class="h-3 rounded-full {{ $verdict['bar'] }}"
                         style="width: {{ min($pct, 100) }}%"></div>
                    <span class="absolute inset-y-0 w-px bg-gray-500/70"
                          style="left: {{ $markerLeft }}%"></span>
                </div>
            </div>
        @endforeach

        @php
            $totalPct     = (float) $costSummary['totals']['cost_pct'];
            $totalVerdict = $ready ? CostVerdict::for($totalPct) : CostVerdict::pending();
        @endphp

        <div class="border-t border-gray-200 pt-3">
            <div class="mb-1.5 flex items-center justify-between gap-3 text-sm">
                <span class="font-semibold text-gray-900">Overall</span>
                <span class="flex-none text-lg font-semibold tabular-nums {{ $totalVerdict['tone'] }}">
                    {{ $costSummary['totals']['cost_pct'] }}%
                    <span class="sr-only">, {{ $totalVerdict['word'] }}</span>
                </span>
            </div>

            <div class="relative h-3 w-full overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
                <div class="h-3 rounded-full {{ $totalVerdict['bar'] }}"
                     style="width: {{ min($totalPct, 100) }}%"></div>
                <span class="absolute inset-y-0 w-px bg-gray-500/70"
                      style="left: {{ $markerLeft }}%"></span>
            </div>

            <p class="mt-2 text-xs text-gray-600">
                @if ($ready)
                    Target {{ rtrim(rtrim(number_format($target, 1), '0'), '.') }}% — marked on each bar.
                @else
                    Target {{ rtrim(rtrim(number_format($target, 1), '0'), '.') }}%. Too few days in this
                    period to call it yet, so these are shown without a verdict.
                @endif
            </p>
        </div>
    </div>
@else
    <div class="empty-state">
        <x-icon name="chart" size="h-8 w-8" stroke="1.5" class="text-gray-500" />
        <p class="empty-title">No cost data for this period</p>
        <p class="empty-body">
            Cost percentages appear once sales and purchases have been recorded against a
            category.
        </p>
    </div>
@endif
