<div>
    {{-- Flash --}}
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="page-title">Production Recipes</h2>
            <p class="text-xs text-gray-600 mt-0.5">
                <a href="{{ route('kitchen.index') }}" class="hover:underline">Kitchen</a> / Recipes
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('kitchen.index') }}"
               class="btn-secondary">
                &larr; Back
            </a>
            <a href="{{ route('kitchen.recipes.create') }}"
               class="btn-primary">
                + New Production Recipe
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card p-4 mb-4">
        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
            <div class="relative flex-1 min-w-[200px]">
                <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                    <svg class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                    </svg>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search by name, code or category..."
                       class="w-full pl-9 pr-4 py-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" />
            </div>
            @if ($kitchens->count() > 1)
                <select wire:model.live="kitchenFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">All Kitchens</option>
                    @foreach ($kitchens as $k)
                        <option value="{{ $k->id }}">{{ $k->name }}</option>
                    @endforeach
                </select>
            @endif
            <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
    </div>

    {{-- Table --}}
    <div class="card overflow-hidden">
        @if ($recipes->count() > 0)
            <div class="overflow-x-auto">
                <table class="table-surface min-w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-left">Code</th>
                            <th class="px-4 py-3 text-left">Category</th>
                            <th class="px-4 py-3 text-left">Kitchen</th>
                            <th class="px-4 py-3 text-right">Yield</th>
                            <th class="px-4 py-3 text-left">Packaging</th>
                            @php $seesCosting = auth()->user()->can('reports.view') || auth()->user()->isSystemRole(); @endphp
                            @if ($seesCosting)
                                <th class="px-4 py-3 text-right">Cost/Unit</th>
                                <th class="px-4 py-3 text-right">Sell/Unit</th>
                            @endif
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recipes as $recipe)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3">
                                    <a href="{{ route('kitchen.recipes.edit', $recipe->id) }}" class="font-medium text-brand-600 hover:text-brand-800">
                                        {{ $recipe->name }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ $recipe->code ?? '-' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $recipe->category ?? '-' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $recipe->kitchen?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-700">
                                    {{ rtrim(rtrim(number_format(floatval($recipe->yield_quantity), 4), '0'), '.') }}
                                    <span class="text-gray-600 text-xs ml-0.5">{{ $recipe->yieldUom?->abbreviation ?? '' }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 text-xs">{{ $recipe->packaging_uom ?? '-' }}</td>
                                @if ($seesCosting)
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ number_format(floatval($recipe->total_cost_per_unit), 4) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ number_format(floatval($recipe->selling_price_per_unit), 4) }}</td>
                                @endif
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-0.5 text-xs rounded-full font-medium {{ $recipe->is_active ? 'bg-success-100 text-success-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $recipe->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{-- Tap-sized buttons: kitchens run on tablets. Delete is
                                         spaced away from the others so a mis-tap can't hit it. --}}
                                    <div class="flex gap-2 justify-end items-center">
                                        {{-- Straight from the recipe into a production order —
                                             the two were previously unconnected. --}}
                                        @if ($recipe->is_active)
                                            <a href="{{ route('kitchen.orders.create', ['recipe' => $recipe->id]) }}"
                                               title="Start a production order for this recipe"
                                               class="inline-flex items-center min-h-[40px] px-3.5 py-2 bg-purple-600 text-white text-xs font-semibold rounded-lg hover:bg-purple-700 transition">Produce</a>
                                        @endif
                                        <a href="{{ route('kitchen.recipes.edit', $recipe->id) }}"
                                           class="inline-flex items-center min-h-[40px] px-3.5 py-2 text-brand-600 border border-brand-200 text-xs font-semibold rounded-lg hover:bg-brand-50 transition">Edit</a>
                                        <button wire:click="toggleActive({{ $recipe->id }})"
                                                class="inline-flex items-center min-h-[40px] px-3.5 py-2 border text-xs font-semibold rounded-lg transition {{ $recipe->is_active ? 'text-yellow-600 border-yellow-200 hover:bg-yellow-50' : 'text-success-600 border-success-200 hover:bg-success-50' }}">
                                            {{ $recipe->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                        <button wire:click="deleteRecipe({{ $recipe->id }})"
                                                wire:confirm="Delete this recipe? This cannot be undone."
                                                class="inline-flex items-center min-h-[40px] px-3.5 py-2 text-danger-500 border border-danger-200 text-xs font-semibold rounded-lg hover:bg-danger-50 transition ml-3">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $recipes->links() }}
            </div>
        @else
            <div class="p-8 text-center text-gray-600 text-sm">
                No production recipes found.
            </div>
        @endif
    </div>
</div>
