<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 bg-danger-50 border border-danger-200 text-danger-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    <x-page-header title="Asset List" eyebrow="Assets"
                   subtitle="Utensils, appliances, equipment, furniture — what the company owns, with who sells it and what it costs.">
        <x-slot:actions>
            <a href="{{ route('assets.register') }}" wire:navigate class="btn-secondary">
                <x-icon name="chart" size="h-4 w-4" />
                <span class="hidden sm:inline">Register</span>
            </a>
            @canDo('assets.counts.record')
                <a href="{{ route('assets.counts.create') }}" wire:navigate class="btn-secondary">
                    <x-icon name="clipboard" size="h-4 w-4" />
                    <span class="hidden sm:inline">New count</span>
                </a>
            @endcanDo
            @if ($this->canManage)
                <button wire:click="openCreateCategory" class="btn-secondary">
                    <span class="hidden sm:inline">+ Category</span>
                    <span class="sm:hidden">+ Cat</span>
                </button>
                <button wire:click="openCreate" class="btn-primary">
                    <span class="sm:hidden">+ Add</span>
                    <span class="hidden sm:inline">+ Add Asset</span>
                </button>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Filters --}}
    <div class="toolbar mb-4">
        <div class="flex w-full flex-col flex-wrap gap-3 sm:flex-row">
            <div class="flex-1 min-w-[200px]">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search by name, code, brand or model…"
                       class="input" />
            </div>

            <select wire:model.live="categoryFilter" class="input sm:w-52">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">
                        {{ $category->parent ? $category->parent->name . ' ▸ ' : '' }}{{ $category->name }}
                        ({{ $category->assets_count }})
                    </option>
                @endforeach
            </select>

            <select wire:model.live="supplierFilter" class="input sm:w-48">
                <option value="">Any supplier</option>
                <option value="none">No supplier linked</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="statusFilter" class="input sm:w-36">
                <option value="all">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>

            <button wire:click="resetFilters" class="btn-ghost">Reset</button>
        </div>

        @if ($selectedIds && $this->canDelete)
            <div class="mt-3 flex items-center gap-3 border-t border-gray-200/80 pt-3">
                <span class="text-sm text-gray-600">{{ count($selectedIds) }} selected</span>
                <button wire:click="bulkDelete" class="btn-danger"
                        data-confirm-delete="Delete {{ count($selectedIds) }} asset(s). Counts and receipts that already name them keep their own records.">
                    Delete selected
                </button>
            </div>
        @endif
    </div>

    {{-- Table --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-[860px]">
                <thead>
                    <tr>
                        @if ($this->canDelete)
                            <th class="px-4 py-3 w-10">
                                <input type="checkbox" wire:model.live="selectAll"
                                       class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                            </th>
                        @endif
                        <th class="px-4 py-3 text-left">Asset</th>
                        <th class="px-4 py-3 text-left w-44">Category</th>
                        <th class="px-4 py-3 text-center w-20">UOM</th>
                        <th class="px-4 py-3 text-right w-32">Unit cost</th>
                        <th class="px-4 py-3 text-left w-48">Suppliers</th>
                        <th class="px-4 py-3 text-center w-24">Status</th>
                        <th class="px-4 py-3 text-center w-28">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assets as $asset)
                        <tr wire:key="asset-{{ $asset->id }}"
                            class="hover:bg-gray-50 {{ $asset->is_active ? '' : 'opacity-60' }}">
                            @if ($this->canDelete)
                                <td class="px-4 py-3">
                                    <input type="checkbox" wire:model.live="selectedIds" value="{{ $asset->id }}"
                                           class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                </td>
                            @endif

                            <td class="px-4 py-3">
                                <span class="font-medium text-gray-700">{{ $asset->name }}</span>
                                @if ($asset->code)
                                    <span class="text-gray-600 ml-1">({{ $asset->code }})</span>
                                @endif
                                @if ($asset->brand || $asset->model)
                                    <span class="block text-xs text-gray-600">{{ trim($asset->brand . ' ' . $asset->model) }}</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-gray-600">
                                @if ($asset->category)
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="h-2 w-2 rounded-full" style="background-color: {{ $asset->category->color }}"></span>
                                        {{ $asset->category->parent ? $asset->category->parent->name . ' ▸ ' : '' }}{{ $asset->category->name }}
                                    </span>
                                @else
                                    <span class="text-gray-500">Uncategorised</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-center text-gray-600">{{ $asset->uom?->abbreviation ?? '—' }}</td>

                            <td class="px-4 py-3 text-right tabular-nums text-gray-700">
                                {{ number_format((float) $asset->unit_cost, 2) }}
                            </td>

                            <td class="px-4 py-3 text-gray-600">
                                @forelse ($asset->suppliers as $supplier)
                                    <span class="{{ $supplier->pivot->is_preferred ? 'font-medium text-gray-700' : '' }}">
                                        {{ $supplier->name }}@if ($supplier->pivot->is_preferred) <span class="text-xs text-brand-600">(preferred)</span>@endif
                                    </span>@if (! $loop->last)<span class="text-gray-500">, </span>@endif
                                @empty
                                    <span class="text-gray-500">—</span>
                                @endforelse
                            </td>

                            <td class="px-4 py-3 text-center">
                                @if ($this->canManage)
                                    <button wire:click="toggleActive({{ $asset->id }})"
                                            class="{{ $asset->is_active ? 'badge-success' : 'badge-neutral' }}">
                                        {{ $asset->is_active ? 'Active' : 'Inactive' }}
                                    </button>
                                @else
                                    <span class="{{ $asset->is_active ? 'badge-success' : 'badge-neutral' }}">
                                        {{ $asset->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                @endif
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-1">
                                    @if ($this->canManage)
                                        <button wire:click="openEdit({{ $asset->id }})" class="icon-btn" title="Edit">
                                            <x-icon name="pencil" size="h-4 w-4" />
                                        </button>
                                    @endif
                                    @if ($this->canDelete)
                                        <button wire:click="delete({{ $asset->id }})" class="icon-btn icon-btn-danger"
                                                title="Delete"
                                                data-confirm-delete="Delete “{{ $asset->name }}”. Counts and receipts that already name it keep their own records.">
                                            <x-icon name="trash" size="h-4 w-4" />
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $this->canDelete ? 8 : 7 }}" class="px-4 py-10">
                                <div class="empty-state">
                                    <p class="empty-title">No assets yet</p>
                                    <p class="empty-body">
                                        Add the things this company owns — knives, plates, mixers, tables — and the
                                        register can tell you what they are worth.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($assets->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $assets->links() }}</div>
        @endif
    </div>

    {{-- Categories --}}
    @if ($categories->isNotEmpty())
        <div class="card p-5 mt-4">
            <x-card-title>Categories</x-card-title>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($categories as $category)
                    <span wire:key="cat-{{ $category->id }}"
                          class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-sm">
                        <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $category->color }}"></span>
                        <span class="{{ $category->is_active ? 'text-gray-700' : 'text-gray-500 line-through' }}">
                            {{ $category->parent ? $category->parent->name . ' ▸ ' : '' }}{{ $category->name }}
                        </span>
                        <span class="text-xs text-gray-600">{{ $category->assets_count }}</span>
                        @if ($this->canManage)
                            <button wire:click="openEditCategory({{ $category->id }})"
                                    class="text-gray-500 hover:text-brand-600" title="Edit category">
                                <x-icon name="pencil" size="h-3.5 w-3.5" />
                            </button>
                        @endif
                        @if ($this->canDelete)
                            <button wire:click="deleteCategory({{ $category->id }})"
                                    class="text-gray-500 hover:text-danger-600" title="Delete category"
                                    data-confirm-delete="Delete the category “{{ $category->name }}”.">
                                <x-icon name="trash" size="h-3.5 w-3.5" />
                            </button>
                        @endif
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Asset modal --}}
    @teleport('body')
    <div x-data="{}" x-show="$wire.showModal" x-cloak class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-gray-900/50" @click="$wire.closeModal()"></div>

        <div class="fixed inset-0 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative z-10 w-full max-w-2xl rounded-panel bg-white shadow-e3">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                        <h3 class="text-base font-semibold text-gray-800">
                            @if ($editingId) Edit: {{ $name }} @else New Asset @endif
                        </h3>
                        <button @click="$wire.closeModal()" class="icon-btn" aria-label="Close">
                            <x-icon name="close" size="h-5 w-5" />
                        </button>
                    </div>

                    <form wire:submit="save">
                        <div class="max-h-[70vh] space-y-4 overflow-y-auto px-6 py-5">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label class="label" for="asset-name">Name</label>
                                    <input id="asset-name" type="text" wire:model="name" class="input"
                                           placeholder="e.g. CHEF KNIFE 10 INCH" />
                                    @error('name') <p class="error-text">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="label" for="asset-code">Code</label>
                                    <input id="asset-code" type="text" wire:model="code" class="input" />
                                    @error('code') <p class="error-text">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="label" for="asset-category">Category</label>
                                    <select id="asset-category" wire:model="asset_category_id" class="input">
                                        <option value="">Uncategorised</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}">
                                                {{ $category->parent ? $category->parent->name . ' ▸ ' : '' }}{{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="label" for="asset-uom">Unit</label>
                                    <select id="asset-uom" wire:model="uom_id" class="input">
                                        <option value="">Choose…</option>
                                        @foreach ($uoms as $uom)
                                            <option value="{{ $uom->id }}">{{ $uom->name }} ({{ $uom->abbreviation }})</option>
                                        @endforeach
                                    </select>
                                    <p class="help">Bought and counted in this unit — piece, set, pair.</p>
                                    @error('uom_id') <p class="error-text">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="label" for="asset-cost">Unit cost</label>
                                    <input id="asset-cost" type="number" step="0.01" min="0" wire:model="unit_cost"
                                           class="input" @disabled(! $this->canSetCost) />
                                    @unless ($this->canSetCost)
                                        <p class="help">You can edit this asset, but not its cost.</p>
                                    @endunless
                                    @error('unit_cost') <p class="error-text">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="label" for="asset-brand">Brand</label>
                                    <input id="asset-brand" type="text" wire:model="brand" class="input" />
                                </div>

                                <div>
                                    <label class="label" for="asset-model">Model</label>
                                    <input id="asset-model" type="text" wire:model="model" class="input" />
                                </div>

                                <div class="sm:col-span-2">
                                    <label class="label" for="asset-remark">Remark</label>
                                    <textarea id="asset-remark" wire:model="remark" rows="2" class="input"></textarea>
                                </div>

                                <div class="sm:col-span-2">
                                    <label class="inline-flex items-center gap-2">
                                        <input type="checkbox" wire:model="is_active"
                                               class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                        <span class="text-sm text-gray-700">Active</span>
                                    </label>
                                </div>
                            </div>

                            {{-- Suppliers --}}
                            <div class="border-t border-gray-100 pt-4">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-sm font-semibold text-gray-700">Suppliers</h4>
                                    <button type="button" wire:click="addSupplierRow" class="btn-ghost">+ Add supplier</button>
                                </div>

                                @forelse ($supplierLinks as $i => $link)
                                    <div wire:key="supplier-row-{{ $i }}" class="mt-3 grid gap-2 sm:grid-cols-12 sm:items-end">
                                        <div class="sm:col-span-5">
                                            <label class="label">Supplier</label>
                                            <select wire:model="supplierLinks.{{ $i }}.supplier_id" class="input">
                                                <option value="">Choose…</option>
                                                @foreach ($suppliers as $supplier)
                                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="sm:col-span-3">
                                            <label class="label">Their code</label>
                                            <input type="text" wire:model="supplierLinks.{{ $i }}.supplier_sku" class="input" />
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="label">Last cost</label>
                                            <input type="number" step="0.01" min="0"
                                                   wire:model="supplierLinks.{{ $i }}.last_cost"
                                                   class="input" @disabled(! $this->canSetCost) />
                                        </div>
                                        <div class="sm:col-span-2 flex items-center gap-2 pb-2">
                                            <label class="inline-flex items-center gap-1.5">
                                                <input type="radio" name="preferred-supplier"
                                                       wire:click="setPreferredSupplier({{ $i }})"
                                                       @checked($link['is_preferred'] ?? false)
                                                       class="border-gray-300 text-brand-600 focus:ring-brand-500" />
                                                <span class="text-xs text-gray-600">Preferred</span>
                                            </label>
                                            <button type="button" wire:click="removeSupplierRow({{ $i }})"
                                                    class="icon-btn icon-btn-danger" title="Remove">
                                                <x-icon name="trash" size="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                @empty
                                    <p class="help mt-2">
                                        No supplier linked yet. The preferred one is what a purchase request reaches
                                        for when this asset is added to it.
                                    </p>
                                @endforelse
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 border-t border-gray-100 px-6 py-4">
                            <button type="button" @click="$wire.closeModal()" class="btn-secondary">Cancel</button>
                            <button type="submit" class="btn-primary">
                                {{ $editingId ? 'Save changes' : 'Add asset' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endteleport

    {{-- Category modal --}}
    @teleport('body')
    <div x-data="{}" x-show="$wire.showCategoryModal" x-cloak class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-gray-900/50" @click="$wire.closeCategoryModal()"></div>

        <div class="fixed inset-0 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative z-10 w-full max-w-md rounded-panel bg-white shadow-e3">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                        <h3 class="text-base font-semibold text-gray-800">
                            {{ $editingCategoryId ? 'Edit category' : 'New category' }}
                        </h3>
                        <button @click="$wire.closeCategoryModal()" class="icon-btn" aria-label="Close">
                            <x-icon name="close" size="h-5 w-5" />
                        </button>
                    </div>

                    <form wire:submit="saveCategory">
                        <div class="space-y-4 px-6 py-5">
                            <div>
                                <label class="label" for="cat-name">Name</label>
                                <input id="cat-name" type="text" wire:model="catName" class="input"
                                       placeholder="e.g. Kitchen Equipment" />
                                @error('catName') <p class="error-text">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="label" for="cat-parent">Sits under</label>
                                <select id="cat-parent" wire:model="catParentId" class="input">
                                    <option value="">Top level</option>
                                    @foreach ($rootCategories as $root)
                                        @continue($root->id === $editingCategoryId)
                                        <option value="{{ $root->id }}">{{ $root->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="label" for="cat-color">Colour</label>
                                    <select id="cat-color" wire:model="catColor" class="input">
                                        @foreach (\App\Models\AssetCategory::colorOptions() as $hex => $label)
                                            <option value="{{ $hex }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="label" for="cat-sort">Sort order</label>
                                    <input id="cat-sort" type="number" wire:model="catSortOrder" class="input" />
                                </div>
                            </div>

                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" wire:model="catIsActive"
                                       class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                <span class="text-sm text-gray-700">Active</span>
                            </label>
                        </div>

                        <div class="flex justify-end gap-2 border-t border-gray-100 px-6 py-4">
                            <button type="button" @click="$wire.closeCategoryModal()" class="btn-secondary">Cancel</button>
                            <button type="submit" class="btn-primary">
                                {{ $editingCategoryId ? 'Save changes' : 'Add category' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endteleport
</div>
