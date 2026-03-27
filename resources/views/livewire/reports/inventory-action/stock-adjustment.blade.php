<div>
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-700">Stock Adjustment</h2>
            <p class="text-xs text-gray-400 mt-0.5">Order adjustment log entries showing field-level changes</p>
        </div>
        <a href="{{ route('reports.hub') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">Back to Reports</a>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
            <div class="flex items-center gap-1">
                <input type="date" wire:model.live="dateFrom" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                <span class="text-gray-400 text-xs">to</span>
                <input type="date" wire:model.live="dateTo" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>
            <button wire:click="exportCsv" class="px-3 py-2 text-sm text-gray-600 hover:text-gray-800 border border-gray-300 rounded-lg hover:bg-gray-50 transition ml-auto">
                Export CSV
            </button>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Document Type</th>
                    <th class="px-4 py-3 text-left">Document Ref</th>
                    <th class="px-4 py-3 text-left">Field Changed</th>
                    <th class="px-4 py-3 text-left">Old Value</th>
                    <th class="px-4 py-3 text-left">New Value</th>
                    <th class="px-4 py-3 text-left">Reason</th>
                    <th class="px-4 py-3 text-left">Adjusted By</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($logs as $log)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 text-gray-700 text-xs">{{ $log->created_at->format('d M Y H:i') }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                                {{ class_basename($log->adjustable_type) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $log->adjustable?->reference_number ?? '#' . $log->adjustable_id }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800 text-xs">{{ str_replace('_', ' ', ucfirst($log->field)) }}</td>
                        <td class="px-4 py-3 text-red-600 text-xs tabular-nums">{{ $log->old_value ?? '-' }}</td>
                        <td class="px-4 py-3 text-green-600 text-xs tabular-nums">{{ $log->new_value ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $log->reason ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $log->adjustedBy?->name ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-gray-400">
                            <p class="font-medium">No adjustment records</p>
                            <p class="text-xs mt-1">No order adjustments found for the selected period.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($logs->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
