<div>
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="page-title">Stock Adjustment</h2>
            <p class="text-xs text-gray-600 mt-0.5">Order adjustment log entries showing field-level changes</p>
        </div>
        <a href="{{ route('reports.hub') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">Back to Reports</a>
    </div>

    {{-- Filters --}}
    <div class="card p-4 mb-4">
        <x-quick-ranges :options="$this::quickRangeOptions()" :current="$quickRange" class="mb-3" />

        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
            <div class="flex items-center gap-1">
                <input type="date" wire:model.live="dateFrom" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                <span class="text-gray-600 text-xs">to</span>
                <input type="date" wire:model.live="dateTo" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" />
            </div>
            <button wire:click="exportCsv" class="btn-secondary ml-auto">
                Export CSV
            </button>
        </div>
    </div>

    {{-- Table --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto"><table class="table-surface min-w-[1100px]">
            <thead>
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
            <tbody>
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
                        <td class="px-4 py-3 text-danger-600 text-xs tabular-nums">{{ $log->old_value ?? '-' }}</td>
                        <td class="px-4 py-3 text-success-600 text-xs tabular-nums">{{ $log->new_value ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $log->reason ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $log->adjustedBy?->name ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-gray-600">
                            <p class="font-medium">No adjustment records</p>
                            <p class="text-xs mt-1">No order adjustments found for the selected period.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table></div>

        @if ($logs->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
