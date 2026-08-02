@php
    /**
     * Dashboard stat tiles.
     *
     * Three things were wrong here.
     *
     * 1. The column count was built as `lg:grid-cols-{{ min(count($stats), 6) }}`.
     *    Tailwind scans templates as plain text, so an interpolated class name is
     *    never seen and never generated. The lg breakpoint silently did nothing
     *    and these tiles had been stuck at the md 3-column layout on every
     *    desktop. Static strings below, so the scanner can find them.
     *
     * 2. Every tile coloured its own figure (indigo, blue, sky, green, amber,
     *    purple, teal, gray). Eight hues for eight counts is decoration: it reads
     *    as though the numbers are different KINDS of thing, and it spends the
     *    status colours on tiles that have no status. The figure is ink now, and
     *    colour is kept for the cases that encode real state.
     *
     * 3. Dashboard.php computes a `sub` line for most tiles ("12 active",
     *    "3 past due", "last 30 days") and this partial never rendered it. The
     *    query was running and the answer was being thrown away.
     */
    $count = max(count($stats), 1);

    $cols = match (true) {
        $count <= 2  => 'sm:grid-cols-2',
        $count === 3 => 'sm:grid-cols-3',
        $count === 4 => 'sm:grid-cols-2 lg:grid-cols-4',
        $count === 5 => 'sm:grid-cols-3 lg:grid-cols-5',
        default      => 'sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6',
    };

    // Only genuine state keeps a colour. Everything else is a count and reads
    // in ink, like the rest of the page.
    $stateTone = [
        'green'  => 'text-success-700',
        'red'    => 'text-danger-700',
        'amber'  => 'text-warning-700',
        'yellow' => 'text-warning-700',
    ];
@endphp

<div class="mb-6 grid grid-cols-2 gap-4 {{ $cols }}">
    @foreach ($stats as $stat)
        <div class="card stat p-5">
            <span class="stat-label">{{ $stat['label'] }}</span>
            <span class="stat-value {{ $stateTone[$stat['color'] ?? ''] ?? '' }}">{{ $stat['value'] }}</span>
            @if (! empty($stat['sub']))
                <span class="stat-meta">{{ $stat['sub'] }}</span>
            @endif
        </div>
    @endforeach
</div>
