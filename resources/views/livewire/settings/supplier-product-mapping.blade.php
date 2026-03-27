<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">{{ session('success') }}</div>
    @endif

    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('settings.index') }}" class="text-gray-400 hover:text-gray-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h2 class="text-lg font-semibold text-gray-700">Supplier Product Mapping</h2>
    </div>

    {{-- Supplier selection --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <select wire:model.live="supplierId" class="rounded-lg border-gray-300 text-sm min-w-48">
                <option value="">— Select Supplier —</option>
                @foreach ($suppliers as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>
            @if ($supplierId)
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search products..."
                       class="flex-1 rounded-lg border-gray-300 text-sm" />
            @endif
        </div>
    </div>

    @if ($supplierId && $products->count() > 0)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">SKU</th>
                        <th class="px-4 py-3 text-left">Supplier Product</th>
                        <th class="px-4 py-3 text-right">Price</th>
                        <th class="px-4 py-3 text-left">Mapped Ingredient</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($products as $p)
                        @php $mapping = $mappings->get($p->id); @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $p->sku }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-700">{{ $p->name }}</div>
                                @if ($p->category)
                                    <span class="text-xs text-gray-400">{{ $p->category }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ number_format($p->unit_price, 2) }}</td>
                            <td class="px-4 py-3">
                                @if ($mapping)
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 bg-green-50 text-green-700 text-xs rounded font-medium">
                                            {{ $mapping->ingredient->name }}
                                        </span>
                                    </div>
                                @else
                                    <select wire:change="mapProduct({{ $p->id }}, $event.target.value)"
                                            class="w-full rounded-lg border-gray-300 text-sm text-gray-500">
                                        <option value="">— Map to ingredient —</option>
                                        @foreach ($ingredients as $ing)
                                            <option value="{{ $ing->id }}">{{ $ing->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($mapping)
                                    <button wire:click="removeMapping({{ $mapping->id }})" wire:confirm="Remove this mapping?"
                                            class="text-xs text-red-500 hover:text-red-700">Remove</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($products->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">{{ $products->links() }}</div>
            @endif
        </div>
    @elseif ($supplierId)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center text-gray-400 text-sm">
            This supplier has no products in their catalog yet.
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center text-gray-400 text-sm">
            Select a supplier to view and map their products.
        </div>
    @endif
</div>
