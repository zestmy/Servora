@props([
    // Plain numbers, oldest first. Two points is the minimum that can carry a
    // shape; anything less renders nothing rather than a misleading dot.
    'values' => [],
    'height' => 28,
    'width'  => 96,
])

@php
    /**
     * The trend behind a stat figure.
     *
     * A delta says how this window compares to the last one. It cannot say
     * whether the number has been climbing for six months or bounced back
     * from a bad patch, and those are different situations that a single
     * percentage renders identically.
     *
     * Deliberately small and deliberately unlabelled: this is the shape of the
     * series, not a reading of it. The figure above it is the reading. Axis,
     * grid and values would make it a chart competing with the tile it sits
     * in — the trend chart lower down the page is where that belongs.
     *
     * aria-hidden, because it adds no fact the tile does not already state in
     * text, and a screen reader announcing a polyline is noise. The full
     * series is reachable as a real table in the trend chart below.
     */
    $points = collect($values)->map(fn ($v) => (float) $v)->values();
    $count  = $points->count();

    $min = $count ? $points->min() : 0.0;
    $max = $count ? $points->max() : 0.0;
    $span = $max - $min;

    // A flat series has no range to normalise against. Park it on the centre
    // line rather than dividing by zero or slamming it to the floor, which
    // would read as a collapse.
    $pad = 3;
    $y = function (float $v) use ($min, $span, $height, $pad) {
        $usable = $height - ($pad * 2);
        $ratio  = $span > 0 ? ($v - $min) / $span : 0.5;

        return round($height - $pad - ($ratio * $usable), 2);
    };

    $step = $count > 1 ? ($width - ($pad * 2)) / ($count - 1) : 0;

    $coords = $points->map(fn ($v, $i) => [round($pad + ($i * $step), 2), $y($v)]);
    $line   = $coords->map(fn ($c) => "{$c[0]},{$c[1]}")->implode(' ');
    $last   = $coords->last();

    // Closing the polyline down to the baseline gives the fill something to
    // bound. Same points as the line, so the two can never disagree.
    $area = $count > 1
        ? $line . ' ' . ($coords->last()[0]) . ',' . $height . ' ' . ($coords->first()[0]) . ',' . $height
        : '';

    $gradientId = 'spark-' . Str::random(6);
@endphp

@if ($count > 1)
    <svg {{ $attributes->merge(['class' => 'block']) }}
         width="{{ $width }}" height="{{ $height }}"
         viewBox="0 0 {{ $width }} {{ $height }}"
         fill="none" aria-hidden="true" focusable="false">
        <defs>
            <linearGradient id="{{ $gradientId }}" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%"   stop-color="currentColor" stop-opacity="0.18" />
                <stop offset="100%" stop-color="currentColor" stop-opacity="0" />
            </linearGradient>
        </defs>

        <polygon points="{{ $area }}" fill="url(#{{ $gradientId }})" />

        <polyline points="{{ $line }}"
                  stroke="currentColor" stroke-width="1.5"
                  stroke-linecap="round" stroke-linejoin="round" />

        {{-- The endpoint is emphasised because it is the value the tile is
             reporting. Everything to its left is context for it. --}}
        <circle cx="{{ $last[0] }}" cy="{{ $last[1] }}" r="2.25" fill="currentColor" />
    </svg>
@endif
