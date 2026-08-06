<div>
    <div class="flex items-center gap-4 mb-6">
        <a data-back href="{{ route('reports.hub') }}" class="text-gray-600 hover:text-gray-900 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h2 class="page-title">Yield Analysis</h2>
    </div>

    <div class="card p-4 mb-4">
        <div class="flex items-center gap-1">
            <input type="date" wire:model.live="dateFrom" class="rounded-lg border-gray-300 text-sm" />
            <span class="text-gray-600 text-xs">to</span>
            <input type="date" wire:model.live="dateTo" class="rounded-lg border-gray-300 text-sm" />
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto"><table class="table-surface min-w-[1100px]">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left">Recipe</th>
                    <th class="px-4 py-3 text-center">Batches</th>
                    <th class="px-4 py-3 text-right">Total Planned</th>
                    <th class="px-4 py-3 text-right">Total Actual</th>
                    <th class="px-4 py-3 text-center">Avg Variance</th>
                    <th class="px-4 py-3 text-right">Total Cost</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recipes as $r)
                    @php $variance = floatval($r->avg_variance); @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-700">{{ $r->recipe?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-center text-gray-600">{{ $r->batch_count }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-600">{{ number_format($r->total_planned, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-800 font-medium">{{ number_format($r->total_actual, 2) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $variance < -5 ? 'bg-danger-100 text-danger-700' : ($variance > 5 ? 'bg-success-100 text-success-700' : 'bg-gray-100 text-gray-600') }}">
                                {{ $variance > 0 ? '+' : '' }}{{ number_format($variance, 1) }}%
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ number_format($r->total_cost, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-600">No production data yet.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        @if ($recipes->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $recipes->links() }}</div>
        @endif
    </div>
</div>
