@php
    /**
     * COGS breakdown by category.
     *
     * The cost percentage was styled green / amber / red and nothing else, so
     * the one judgement in the table was carried by colour alone. Roughly 1 in
     * 12 men cannot separate that green from that red. Each figure now also
     * carries a visually-hidden word, so a screen reader and a colourblind
     * reader get the same verdict a sighted reader gets from the hue.
     *
     * The row padding and header colours were hand-written on top of
     * .table-surface, which meant this table drifted from every other one.
     * They are gone; the component class owns them.
     *
     * Totals moved to <tfoot>. It is a summary of the rows, not another row,
     * and assistive tech treats the two differently.
     */
    $verdict = function (float $pct): array {
        return match (true) {
            $pct > 35 => ['text-danger-700',  'over target'],
            $pct > 30 => ['text-warning-700', 'near target'],
            default   => ['text-success-700', 'on target'],
        };
    };
@endphp

<div class="table-scroll">
    <table class="table-surface">
        <caption class="sr-only">Revenue, cost of goods sold and cost percentage by category</caption>
        <thead>
            <tr>
                <th scope="col">Category</th>
                <th scope="col" class="text-right">Revenue</th>
                <th scope="col" class="text-right">COGS</th>
                <th scope="col" class="text-right">Cost %</th>
            </tr>
        </thead>

        <tbody>
            @foreach ($costSummary['categories'] as $cat)
                @php [$tone, $state] = $verdict((float) $cat['cost_pct']); @endphp
                <tr>
                    <th scope="row" class="px-4 py-3 text-left font-medium text-gray-900">
                        <span class="flex items-center gap-2">
                            @if ($cat['color'])
                                {{-- The category's own colour from the database.
                                     Decorative here: the name beside it is the
                                     actual identifier. --}}
                                <span class="h-2 w-2 flex-none rounded-full"
                                      style="background:{{ $cat['color'] }}" aria-hidden="true"></span>
                            @endif
                            {{ $cat['name'] }}
                        </span>
                    </th>
                    <td class="num text-right">{{ number_format($cat['revenue'], 2) }}</td>
                    <td class="num text-right">{{ number_format($cat['cogs'], 2) }}</td>
                    <td class="num text-right font-semibold {{ $tone }}">
                        {{ $cat['cost_pct'] }}%
                        <span class="sr-only">, {{ $state }}</span>
                    </td>
                </tr>
            @endforeach
        </tbody>

        @php [$totalTone, $totalState] = $verdict((float) $costSummary['totals']['cost_pct']); @endphp
        <tfoot>
            <tr class="bg-gray-50 font-semibold">
                <th scope="row" class="px-4 py-3 text-left text-gray-900">Total</th>
                <td class="num px-4 py-3 text-right text-gray-900">{{ number_format($costSummary['totals']['revenue'], 2) }}</td>
                <td class="num px-4 py-3 text-right text-gray-900">{{ number_format($costSummary['totals']['cogs'], 2) }}</td>
                <td class="num px-4 py-3 text-right {{ $totalTone }}">
                    {{ $costSummary['totals']['cost_pct'] }}%
                    <span class="sr-only">, {{ $totalState }}</span>
                </td>
            </tr>
        </tfoot>
    </table>
</div>
