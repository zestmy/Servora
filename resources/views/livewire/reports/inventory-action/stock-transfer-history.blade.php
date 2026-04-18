<div>
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-700">Stock Transfer History</h2>
            <p class="text-xs text-gray-400 mt-0.5">Outlet transfers and CPU stock transfer orders</p>
        </div>
        <a href="{{ route('reports.hub') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">Back to Reports</a>
    </div>

    {{-- Filters --}}
    @include('livewire.reports.partials.report-filters', ['showOutlet' => true, 'showSupplier' => false, 'exportAction' => 'exportCsv'])

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto"><table class="min-w-[1100px] divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Reference</th>
                    <th class="px-4 py-3 text-left">From</th>
                    <th class="px-4 py-3 text-left">To</th>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-center">Type</th>
                    <th class="px-4 py-3 text-right">Items</th>
                    <th class="px-4 py-3 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($transfers as $transfer)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $transfer['reference'] }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $transfer['from'] }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $transfer['to'] }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $transfer['date'] }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium
                                {{ $transfer['type'] === 'CPU STO' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                                {{ $transfer['type'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ $transfer['items'] }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium
                                {{ strtolower($transfer['status']) === 'completed' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                {{ $transfer['status'] }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                            <p class="font-medium">No transfer records</p>
                            <p class="text-xs mt-1">No stock transfers found for the selected period.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table></div>

        @if ($transfers->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $transfers->links() }}
            </div>
        @endif
    </div>
</div>
