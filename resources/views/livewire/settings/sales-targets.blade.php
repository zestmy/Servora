<div>
    {{-- Flash --}}
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-700">Sales Targets</h2>
            <p class="text-sm text-gray-400 mt-0.5">Set monthly revenue and pax targets for your outlets</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('sales.index') }}"
               class="px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                Back
            </a>
            <button wire:click="openCreate"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                + New Target
            </button>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Period</th>
                    <th class="px-4 py-3 text-left">Outlet</th>
                    <th class="px-4 py-3 text-right">Target Revenue (RM)</th>
                    <th class="px-4 py-3 text-right">Target Pax</th>
                    <th class="px-4 py-3 text-left">Notes</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($targets as $target)
                    @php
                        $periodLabel = \Carbon\Carbon::createFromFormat('Y-m', $target->period)->format('M Y');
                    @endphp
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 font-medium text-gray-700">{{ $periodLabel }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $target->outlet?->name ?? 'All Outlets' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-semibold text-gray-800">{{ number_format($target->target_revenue, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-600">{{ $target->target_pax ? number_format($target->target_pax) : '—' }}</td>
                        <td class="px-4 py-3 text-gray-500 text-xs max-w-[200px] truncate">{{ $target->notes ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <button wire:click="openEdit({{ $target->id }})" title="Edit"
                                        class="text-indigo-500 hover:text-indigo-700 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button wire:click="delete({{ $target->id }})"
                                        wire:confirm="Delete this sales target for {{ $periodLabel }}?"
                                        title="Delete"
                                        class="text-red-400 hover:text-red-600 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-gray-400">
                            <p class="font-medium">No sales targets set</p>
                            <p class="text-xs mt-1">Click <button wire:click="openCreate" class="text-indigo-500 underline">+ New Target</button> to set your first monthly target</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($targets->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $targets->links() }}
            </div>
        @endif
    </div>

    {{-- Create/Edit Modal --}}
    @teleport('body')
    <div x-data="{}" x-show="$wire.showModal" x-cloak class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-gray-900/50" @click="$wire.closeModal()"></div>
        <div class="fixed inset-0 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md z-10" @click.stop>
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-800">
                            {{ $editingId ? 'Edit Sales Target' : 'New Sales Target' }}
                        </h3>
                    </div>
                    <div class="px-6 py-4 space-y-4">
                        {{-- Period --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Period</label>
                            <input type="month" wire:model="period"
                                   class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            @error('period') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Outlet --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Outlet</label>
                            <select wire:model="outlet_id"
                                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Outlets</option>
                                @foreach ($outlets as $outlet)
                                    <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Target Revenue --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Target Revenue (RM)</label>
                            <input type="number" wire:model="target_revenue" step="0.01" min="0"
                                   placeholder="e.g. 150000.00"
                                   class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            @error('target_revenue') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Target Pax --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Target Pax (optional)</label>
                            <input type="number" wire:model="target_pax" min="0"
                                   placeholder="e.g. 5000"
                                   class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            @error('target_pax') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Notes --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Notes (optional)</label>
                            <textarea wire:model="notes" rows="2"
                                      placeholder="e.g. CNY month, expect higher traffic"
                                      class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        </div>
                    </div>
                    <div class="px-6 py-3 bg-gray-50 rounded-b-xl flex items-center justify-end gap-2">
                        <button wire:click="closeModal"
                                class="px-4 py-2 text-xs text-gray-600 border border-gray-200 rounded-lg hover:bg-white transition">
                            Cancel
                        </button>
                        <button wire:click="save"
                                class="px-4 py-2 text-xs font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition">
                            {{ $editingId ? 'Update Target' : 'Create Target' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endteleport
</div>
