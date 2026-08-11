@php
    /**
     * The trend figures as a real table.
     *
     * Extracted so it can be rendered twice without being written twice: the
     * chart is hidden below the `sm` breakpoint — six grouped bar pairs across
     * 340px is about 24px per pair, which is a texture rather than a chart —
     * so on a phone this table is the primary read, and on a desktop it is the
     * collapsed fallback beneath the bars.
     *
     * Expects: $trendMonths, $series.
     */
@endphp

<table class="table-surface">
    <caption class="sr-only">{{ $caption ?? 'Figures by month, in ringgit' }}</caption>
    <thead>
        <tr>
            <th scope="col">Month</th>
            @foreach ($series as $s)
                <th scope="col" class="text-right">{{ $s['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($trendMonths as $m)
            <tr>
                <th scope="row" class="px-4 py-3 text-left font-medium text-gray-900">{{ $m['label'] }}</th>
                @foreach ($series as $s)
                    <td class="num text-right">{{ number_format($m[$s['key']] ?? 0, 2) }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
