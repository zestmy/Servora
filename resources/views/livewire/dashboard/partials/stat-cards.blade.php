@php
    /**
     * Dashboard stat tiles.
     *
     * Previously fixed here: the column count was interpolated
     * (`lg:grid-cols-{{ count }}`), which Tailwind's text scanner never sees,
     * so the lg breakpoint silently did nothing; eight tiles each took their
     * own hue, spending the status colours on figures that had no status; and
     * the `sub` line the component computed was never rendered.
     *
     * What was still missing is the thing a stat tile is for. Every tile
     * reported a bare absolute. `Month revenue 128,400` is not high or low,
     * ahead or behind, until it sits beside something — and nothing on any of
     * the seven dashboards sat beside anything. Answering "is this normal?"
     * meant leaving the page.
     *
     * So a tile now carries, in order of how hard it is to miss:
     *
     *   value     unchanged, still the loudest thing in the tile
     *   delta     signed percentage against the prior window, in words
     *   spark     the shape of the series behind it, where one exists
     *   sub       the supporting line that was already being computed
     *
     * Accepted keys — all optional except label and value, so the tiles that
     * genuinely have no baseline (catalogue counts) keep working unchanged:
     *
     *   label     string
     *   value     string|int, preformatted for display
     *   prefix    'RM' and the like, set smaller and in muted ink
     *   current   raw number behind `value`, for the delta maths
     *   prior     the same figure for the prior window
     *   inverse   true where DOWN is the good direction (cost, wastage)
     *   compare   names the comparison — "vs same days last month"
     *   spark     array of raw numbers, oldest first
     *   sub       supporting line
     *   color     only for genuine state; anything else reads in ink
     *   href      makes the whole tile a link to what it summarises
     */
    $count = max(count($stats), 1);

    // Static strings. An interpolated class name is invisible to the scanner
    // and would never be generated — the bug this file used to have.
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
        @php
            $tone     = $stateTone[$stat['color'] ?? ''] ?? '';
            $hasDelta = array_key_exists('prior', $stat);
            $spark    = $stat['spark'] ?? [];
            $href     = $stat['href'] ?? null;

            // A linked tile is a real link, not a div with a click handler, so
            // it is reachable by keyboard and announced as a link. The hover
            // lift is the shared .card-hover so it matches every other
            // clickable surface in the product.
            $shell = $href
                ? 'card card-hover stat p-5 block focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600'
                : 'card stat p-5';
        @endphp

        <{{ $href ? 'a' : 'div' }}
            @if ($href) href="{{ $href }}" @endif
            class="{{ $shell }}">

            <span class="stat-label">{{ $stat['label'] }}</span>

            <span class="stat-value {{ $tone }}">
                @if (! empty($stat['prefix']))
                    <span class="text-base font-medium text-gray-600">{{ $stat['prefix'] }}</span>
                @endif
                {{ $stat['value'] }}
            </span>

            @if ($hasDelta)
                <x-delta :current="$stat['current'] ?? 0"
                         :previous="$stat['prior']"
                         :inverse="$stat['inverse'] ?? false"
                         :label="$stat['compare'] ?? ''" />
            @endif

            @if (! empty($spark))
                {{-- currentColor, so the spark inherits the tile's ink rather
                     than introducing a colour of its own. --}}
                <x-sparkline :values="$spark" class="mt-1 text-brand-600" />
            @endif

            @if (! empty($stat['sub']))
                <span class="stat-meta">{{ $stat['sub'] }}</span>
            @endif
        </{{ $href ? 'a' : 'div' }}>
    @endforeach
</div>
