@props([
    // The figure being reported, and the same figure for the prior window.
    'current'  => 0,
    'previous' => null,

    // Set for figures where DOWN is the good direction — cost, wastage,
    // purchases. Without it a 30% jump in wastage renders in success green,
    // which is worse than rendering no colour at all.
    'inverse'  => false,

    // Names the comparison in words. Never leave this to an arrow alone:
    // "vs same days last month" and "vs previous 7 days" are different
    // claims and the reader cannot tell which one an arrow is making.
    'label'    => '',
])

@php
    /**
     * The delta line under a stat figure.
     *
     * `.stat-up` and `.stat-down` have been sitting in app.css since the
     * design system landed, defined and used by nothing. The dashboard
     * reported 44 figures across 7 roles and not one of them carried a
     * baseline — so every number on the page was a fact with no verdict
     * attached, and answering "is this normal?" meant leaving for Reports.
     *
     * This is the missing half of a stat tile.
     *
     * Four cases, and each says something different:
     *
     *   no prior data   nothing to compare against — render the words, not a
     *                   fabricated 0%. A first month is not a flat month.
     *   prior was zero  a percentage against zero is either infinity or a lie.
     *                   Say "first activity" and stop.
     *   flat            under half a percent either way is noise. Colouring it
     *                   trains people to ignore the colour.
     *   moved           signed percentage, toned by whether the direction is
     *                   good for THIS figure, which is what `inverse` decides.
     *
     * The sign carries the direction and the label carries the comparison, so
     * the tone is reinforcing information that is already in the text rather
     * than being the only place it exists.
     */
    $prior   = $previous === null ? null : (float) $previous;
    $now     = (float) $current;

    $state = match (true) {
        $prior === null                     => 'unknown',
        abs($prior) < 0.00001 && $now == 0.0 => 'unknown',
        abs($prior) < 0.00001               => 'new',
        default                             => 'measured',
    };

    $pct = $state === 'measured'
        ? round((($now - $prior) / abs($prior)) * 100, 1)
        : null;

    if ($state === 'measured' && abs($pct) < 0.5) {
        $state = 'flat';
    }

    // Good/bad, not up/down. A 12% fall in wastage and a 12% rise in revenue
    // are the same news and should read the same way.
    $isGood = $pct !== null && ($inverse ? $pct < 0 : $pct > 0);

    $tone = match ($state) {
        'measured' => $isGood ? 'stat-up' : 'stat-down',
        default    => 'stat-meta',
    };
@endphp

<span {{ $attributes->merge(['class' => $tone . ' inline-flex items-center gap-1']) }}>
    @if ($state === 'measured')
        <span aria-hidden="true">{{ $pct > 0 ? '▲' : '▼' }}</span>
        <span class="tabular-nums">{{ $pct > 0 ? '+' : '' }}{{ number_format($pct, 1) }}%</span>
        @if ($label)
            <span class="font-normal text-gray-600">{{ $label }}</span>
        @endif
    @elseif ($state === 'flat')
        <span class="tabular-nums">No change</span>
        @if ($label)
            <span>{{ $label }}</span>
        @endif
    @elseif ($state === 'new')
        <span>First activity {{ $label ? Str::replaceFirst('vs ', 'in this window vs ', $label) : '' }}</span>
    @else
        <span>No prior data</span>
    @endif
</span>
