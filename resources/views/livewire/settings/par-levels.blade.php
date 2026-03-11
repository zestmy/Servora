<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('settings.index') }}" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1 min-w-0">
            <h2 class="text-lg font-semibold text-gray-700">Par Levels</h2>
            <p class="text-xs text-gray-400">Set par levels per ingredient per outlet. Used for auto-calculating order quantities.</p>
        </div>
        <button wire:click="saveAll"
                class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            Save All
        </button>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
        <div class="flex flex-wrap items-center gap-4">
            <select wire:model.live="outletId" class="rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                @foreach ($outlets as $outlet)
                    <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                @endforeach
            </select>

            <div class="flex-1 max-w-sm">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search ingredients..."
                       class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500" />
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Ingredient</th>
                        <th class="px-4 py-3 text-left w-24">Code</th>
                        <th class="px-4 py-3 text-left w-24">Base UOM</th>
                        <th class="px-4 py-3 text-right w-40">Par Level</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($ingredients as $ingredient)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-2.5 font-medium text-gray-800">{{ $ingredient->name }}</td>
                            <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $ingredient->code ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-gray-500">{{ $ingredient->baseUom?->abbreviation ?? '—' }}</td>
                            <td class="px-4 py-2.5">
                                <input type="number" step="0.01" min="0"
                                       wire:model.lazy="parLevels.{{ $ingredient->id }}"
                                       placeholder="0"
                                       class="w-full text-right rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-12 text-center text-gray-400">
                                No ingredients found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($ingredients->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $ingredients->links() }}
            </div>
        @endif
    </div>

    {{-- Footer save --}}
    <div class="flex justify-end mt-4">
        <button wire:click="saveAll"
                class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            Save All
        </button>
    </div>
</div>
