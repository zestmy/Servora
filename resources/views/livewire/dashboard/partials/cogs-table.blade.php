{{-- COGS Breakdown Table --}}
<div class="overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200">
                <th class="text-left py-2 px-3 font-medium text-gray-500">Category</th>
                <th class="text-right py-2 px-3 font-medium text-gray-500">Revenue</th>
                <th class="text-right py-2 px-3 font-medium text-gray-500">COGS</th>
                <th class="text-right py-2 px-3 font-medium text-gray-500">Cost %</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @foreach ($costSummary['categories'] as $cat)
                <tr>
                    <td class="py-2 px-3">
                        <span class="flex items-center gap-2">
                            @if ($cat['color'])
                                <span class="w-2 h-2 rounded-full" style="background:{{ $cat['color'] }}"></span>
                            @endif
                            <span class="font-medium text-gray-700">{{ $cat['name'] }}</span>
                        </span>
                    </td>
                    <td class="py-2 px-3 text-right text-gray-700">{{ number_format($cat['revenue'], 2) }}</td>
                    <td class="py-2 px-3 text-right text-gray-700">{{ number_format($cat['cogs'], 2) }}</td>
                    <td class="py-2 px-3 text-right font-semibold {{ $cat['cost_pct'] > 35 ? 'text-red-600' : ($cat['cost_pct'] > 30 ? 'text-amber-600' : 'text-green-600') }}">{{ $cat['cost_pct'] }}%</td>
                </tr>
            @endforeach
            <tr class="bg-gray-50 font-semibold">
                <td class="py-2 px-3 text-gray-800">Total</td>
                <td class="py-2 px-3 text-right text-gray-800">{{ number_format($costSummary['totals']['revenue'], 2) }}</td>
                <td class="py-2 px-3 text-right text-gray-800">{{ number_format($costSummary['totals']['cogs'], 2) }}</td>
                <td class="py-2 px-3 text-right {{ $costSummary['totals']['cost_pct'] > 35 ? 'text-red-600' : ($costSummary['totals']['cost_pct'] > 30 ? 'text-amber-600' : 'text-green-600') }}">{{ $costSummary['totals']['cost_pct'] }}%</td>
            </tr>
        </tbody>
    </table>
</div>
