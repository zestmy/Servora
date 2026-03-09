@if (!empty($detail['groups']))
    <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-600">{{ $label }} Breakdown</h3>
            <span class="text-sm font-bold text-{{ $color }}-600">RM {{ number_format($detail['total'], 2) }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-2.5 text-left">Item</th>
                        <th class="px-4 py-2.5 text-left">Type</th>
                        <th class="px-4 py-2.5 text-right">Quantity</th>
                        <th class="px-4 py-2.5 text-left">UOM</th>
                        <th class="px-4 py-2.5 text-right">Total Cost (RM)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($detail['groups'] as $catName => $group)
                        <tr class="bg-gray-50 border-t border-gray-200">
                            <td colspan="4" class="px-4 py-2 font-semibold text-gray-700 text-xs uppercase tracking-wider">{{ $catName }}</td>
                            <td class="px-4 py-2 text-right font-semibold text-{{ $color }}-600">{{ number_format($group['total'], 2) }}</td>
                        </tr>
                        @foreach ($group['items'] as $item)
                            <tr class="border-b border-gray-50 hover:bg-gray-50/50">
                                <td class="px-4 py-2 pl-6 text-gray-800">
                                    <div class="flex items-center gap-2">
                                        {{ $item['name'] }}
                                        @if ($item['is_prep'])
                                            <span class="px-1.5 py-0.5 bg-amber-100 text-amber-700 text-xs font-semibold rounded">PREP</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-2">
                                    @if ($item['type'] === 'recipe')
                                        <span class="px-1.5 py-0.5 bg-indigo-100 text-indigo-700 text-xs font-semibold rounded">Recipe</span>
                                    @else
                                        <span class="text-gray-500 text-xs">Ingredient</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ number_format($item['quantity'], 2) }}</td>
                                <td class="px-4 py-2 text-gray-500 text-xs">{{ $item['uom'] }}</td>
                                <td class="px-4 py-2 text-right tabular-nums font-medium text-{{ $color }}-600">{{ number_format($item['total_cost'], 2) }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                    <tr>
                        <td colspan="4" class="px-4 py-2.5 text-right font-semibold text-gray-700">Total {{ $label }}</td>
                        <td class="px-4 py-2.5 text-right font-bold text-{{ $color }}-600 tabular-nums">{{ number_format($detail['total'], 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endif
