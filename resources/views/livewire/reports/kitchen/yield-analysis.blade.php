<div>
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('reports.hub') }}" class="text-gray-400 hover:text-gray-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h2 class="text-lg font-semibold text-gray-700">Yield Analysis</h2>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex items-center gap-1">
            <input type="date" wire:model.live="dateFrom" class="rounded-lg border-gray-300 text-sm" />
            <span class="text-gray-400 text-xs">to</span>
            <input type="date" wire:model.live="dateTo" class="rounded-lg border-gray-300 text-sm" />
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Recipe</th>
                    <th class="px-4 py-3 text-center">Batches</th>
                    <th class="px-4 py-3 text-right">Total Planned</th>
                    <th class="px-4 py-3 text-right">Total Actual</th>
                    <th class="px-4 py-3 text-center">Avg Variance</th>
                    <th class="px-4 py-3 text-right">Total Cost</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($recipes as $r)
                    @php $variance = floatval($r->avg_variance); @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-700">{{ $r->recipe?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-center text-gray-600">{{ $r->batch_count }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-600">{{ number_format($r->total_planned, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-800 font-medium">{{ number_format($r->total_actual, 2) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $variance < -5 ? 'bg-red-100 text-red-700' : ($variance > 5 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600') }}">
                                {{ $variance > 0 ? '+' : '' }}{{ number_format($variance, 1) }}%
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ number_format($r->total_cost, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No production data yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($recipes->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $recipes->links() }}</div>
        @endif
    </div>
</div>
