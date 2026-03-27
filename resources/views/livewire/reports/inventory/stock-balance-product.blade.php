<div>
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-700">Stock Balance (Product)</h2>
            <p class="text-xs text-gray-400 mt-0.5">Ingredient balances with opening, movements, and closing values</p>
        </div>
        <a href="{{ route('reports.hub') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">Back to Reports</a>
    </div>

    {{-- Filters --}}
    @include('livewire.reports.partials.report-filters', ['showOutlet' => true, 'showSupplier' => false, 'exportAction' => 'exportCsv'])

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Ingredient</th>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-right">Opening</th>
                    <th class="px-4 py-3 text-right">Purchases</th>
                    <th class="px-4 py-3 text-right">Transfers In</th>
                    <th class="px-4 py-3 text-right">Transfers Out</th>
                    <th class="px-4 py-3 text-right">Wastage</th>
                    <th class="px-4 py-3 text-right">Closing</th>
                    <th class="px-4 py-3 text-right">Value</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($items as $item)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $item->name }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $item->category_name ?? '-' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ number_format($item->opening_qty, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-green-600">{{ number_format($item->purchases_qty, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-blue-600">{{ number_format($item->transfers_in, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-orange-600">{{ number_format($item->transfers_out, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-red-600">{{ number_format($item->wastage_qty, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-semibold text-gray-800">{{ number_format($item->closing_balance, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ number_format($item->closing_value, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-gray-400">
                            <p class="font-medium">No stock balance data</p>
                            <p class="text-xs mt-1">Record stock takes and purchases to see balance movements.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($items->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $items->links() }}
            </div>
        @endif
    </div>
</div>
