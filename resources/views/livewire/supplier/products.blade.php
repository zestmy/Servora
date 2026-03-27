<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">{{ session('success') }}</div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-gray-700">Product Catalog</h2>
        <div class="flex gap-2">
            <button wire:click="openCreate" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">+ Add Product</button>
        </div>
    </div>

    {{-- CSV Import --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex items-center gap-3">
            <input type="file" wire:model="csvFile" accept=".csv,.txt" class="text-sm" />
            @if ($csvFile)
                <button wire:click="importCsv" class="px-3 py-1.5 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition">Import CSV</button>
            @endif
            <span class="text-xs text-gray-400">Format: SKU, Name, Unit Price, Category, Pack Size</span>
        </div>
    </div>

    {{-- Search --}}
    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search products..."
               class="w-full max-w-md rounded-lg border-gray-300 text-sm" />
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">SKU</th>
                    <th class="px-4 py-3 text-left">Name</th>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-right">Price</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($products as $p)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $p->sku }}</td>
                        <td class="px-4 py-3 font-medium text-gray-700">{{ $p->name }}</td>
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ $p->category ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">
                            {{ number_format($p->unit_price, 2) }}
                            @if ($p->price_change_percent)
                                <span class="ml-1 text-xs {{ $p->price_change_percent > 0 ? 'text-red-500' : 'text-green-500' }}">
                                    {{ $p->price_change_percent > 0 ? '+' : '' }}{{ $p->price_change_percent }}%
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded text-xs {{ $p->is_active ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600' }}">
                                {{ $p->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button wire:click="openEdit({{ $p->id }})" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</button>
                            <button wire:click="delete({{ $p->id }})" wire:confirm="Delete?" class="text-sm text-red-500 hover:text-red-700 ml-2">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No products yet. Add your first product or import via CSV.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($products->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $products->links() }}</div>
        @endif
    </div>

    {{-- Create/Edit Modal --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-900/50" wire:click="$set('showForm', false)"></div>
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg z-10 p-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">{{ $editId ? 'Edit' : 'New' }} Product</h3>
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">SKU *</label>
                                <input type="text" wire:model="sku" class="w-full rounded-lg border-gray-300 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Category</label>
                                <input type="text" wire:model="category" class="w-full rounded-lg border-gray-300 text-sm" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Product Name *</label>
                            <input type="text" wire:model="name" class="w-full rounded-lg border-gray-300 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Description</label>
                            <textarea wire:model="description" rows="2" class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Unit Price *</label>
                                <input type="number" step="0.01" wire:model="unit_price" class="w-full rounded-lg border-gray-300 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Pack Size</label>
                                <input type="number" step="0.01" wire:model="pack_size" class="w-full rounded-lg border-gray-300 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">UOM</label>
                                <select wire:model="uom_id" class="w-full rounded-lg border-gray-300 text-sm">
                                    <option value="">—</option>
                                    @foreach ($uoms as $uom)
                                        <option value="{{ $uom->id }}">{{ $uom->abbreviation ?? $uom->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Min Order Qty</label>
                                <input type="number" step="0.01" wire:model="min_order_quantity" class="w-full rounded-lg border-gray-300 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Lead Time (days)</label>
                                <input type="number" wire:model="lead_time_days" class="w-full rounded-lg border-gray-300 text-sm" />
                            </div>
                        </div>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-indigo-600" />
                            <span class="text-sm text-gray-600">Active</span>
                        </label>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button wire:click="$set('showForm', false)" class="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button wire:click="save" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">{{ $editId ? 'Update' : 'Create' }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
