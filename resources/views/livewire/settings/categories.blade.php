<div>
    {{-- Flash --}}
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Back + Header --}}
    <div class="flex items-center gap-3 mb-4">
        <a href="{{ route('settings.index') }}" class="text-gray-400 hover:text-gray-600 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1">
            <p class="text-xs text-gray-400"><a href="{{ route('settings.index') }}" class="hover:underline">Settings</a> / Categories</p>
        </div>
        <button wire:click="openCreateMain"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            + Add Main Category
        </button>
    </div>

    {{-- Guide --}}
    <div x-data="{ open: false }" class="mb-4">
        <button @click="open = !open" class="flex items-center gap-2 text-xs text-indigo-600 hover:text-indigo-800 font-medium transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span x-text="open ? 'Hide Guide' : 'How Ingredient Categories Work'"></span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 transition-transform" :class="open ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </button>

        <div x-show="open" x-cloak class="mt-3 bg-blue-50 border border-blue-200 rounded-xl p-5 text-sm text-blue-900 space-y-3">
            <div>
                <h4 class="font-bold text-blue-800 mb-1">Ingredient Categories</h4>
                <p>Categories group your ingredients for organisation, filtering, and reporting. Costs flow to the P&amp;L via <strong>Departments</strong> mapped to <strong>Sales Categories</strong>.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div class="bg-white/60 rounded-lg p-3">
                    <p class="font-bold text-blue-800 mb-1">Main Category Settings</p>
                    <ul class="space-y-1 text-blue-700">
                        <li><strong>Color</strong> &mdash; Visual identifier used in charts and reports</li>
                        <li><strong>Sort Order</strong> &mdash; Controls display order (lower = first)</li>
                    </ul>
                </div>
                <div class="bg-white/60 rounded-lg p-3">
                    <p class="font-bold text-blue-800 mb-1">Sub-categories</p>
                    <ul class="space-y-1 text-blue-700">
                        <li>Organise ingredients within a main category (e.g. Food &rarr; Proteins, Produce, Dairy)</li>
                        <li>Inherit the cost type from parent &mdash; no separate type needed</li>
                    </ul>
                </div>
            </div>

            <p class="text-xs text-blue-600">
                <strong>P&amp;L cost chain:</strong> <a href="{{ route('settings.departments') }}" class="underline">Departments</a> &rarr;
                <a href="{{ route('settings.sales-categories') }}" class="underline">Sales Categories</a> &rarr; P&amp;L report
            </p>
        </div>
    </div>

    {{-- Category List --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-center">Sub-cats</th>
                    <th class="px-4 py-3 text-center">Items</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($categories as $cat)
                    @php
                        $totalItems  = ($cat->ingredients_count + $cat->recipes_count)
                                     + $cat->children->sum(fn ($c) => $c->ingredients_count + $c->recipes_count);
                        $hasChildren = $cat->children->isNotEmpty();
                    @endphp

                    {{-- Main category row --}}
                    <tr x-data="{ open: false }" class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-4 h-4 rounded-full flex-shrink-0" style="background-color: {{ $cat->color }}"></div>
                                <span class="font-semibold text-gray-800">{{ $cat->name }}</span>
                                @if ($hasChildren)
                                    <button @click="open = !open"
                                            class="text-xs text-gray-400 hover:text-gray-600 flex items-center gap-1 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 transition-transform" :class="open ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                        {{ $cat->children_count }} sub
                                    </button>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if ($hasChildren)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">
                                    {{ $cat->children_count }}
                                </span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center text-gray-700 font-medium">{{ $totalItems }}</td>
                        <td class="px-4 py-3 text-center">
                            @if ($cat->is_active)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <button wire:click="openCreateSub({{ $cat->id }})" title="Add Sub-Category"
                                        class="text-xs px-2 py-1 bg-amber-50 text-amber-600 rounded hover:bg-amber-100 transition font-medium">
                                    + Sub
                                </button>
                                <button wire:click="openEdit({{ $cat->id }})" title="Edit"
                                        class="text-indigo-500 hover:text-indigo-700 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button wire:click="toggleActive({{ $cat->id }})"
                                        title="{{ $cat->is_active ? 'Deactivate' : 'Activate' }}"
                                        class="{{ $cat->is_active ? 'text-green-500 hover:text-green-700' : 'text-gray-400 hover:text-gray-600' }} transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </button>
                                <button wire:click="delete({{ $cat->id }})"
                                        wire:confirm="Delete '{{ $cat->name }}'?"
                                        title="Delete"
                                        class="text-red-400 hover:text-red-600 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>

                    {{-- Sub-category rows --}}
                    @if ($hasChildren)
                        @foreach ($cat->children as $sub)
                            @php $subItems = $sub->ingredients_count + $sub->recipes_count; @endphp
                            <tr x-show="open" x-cloak class="bg-gray-50/50 hover:bg-gray-100/60 transition border-l-4" style="border-left-color: {{ $cat->color }}">
                                <td class="pl-10 pr-4 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $sub->color }}"></div>
                                        <span class="text-gray-700">{{ $sub->name }}</span>
                                        <span class="text-xs text-gray-400">↳ sub of {{ $cat->name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <span class="text-gray-300">—</span>
                                </td>
                                <td class="px-4 py-2.5 text-center text-gray-300">—</td>
                                <td class="px-4 py-2.5 text-center text-gray-600">{{ $subItems }}</td>
                                <td class="px-4 py-2.5 text-center">
                                    @if ($sub->is_active)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center justify-center gap-2">
                                        <button wire:click="openEdit({{ $sub->id }})" title="Edit"
                                                class="text-indigo-500 hover:text-indigo-700 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button wire:click="toggleActive({{ $sub->id }})"
                                                title="{{ $sub->is_active ? 'Deactivate' : 'Activate' }}"
                                                class="{{ $sub->is_active ? 'text-green-500 hover:text-green-700' : 'text-gray-400 hover:text-gray-600' }} transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </button>
                                        <button wire:click="delete({{ $sub->id }})"
                                                wire:confirm="Delete '{{ $sub->name }}'?"
                                                title="Delete"
                                                class="text-red-400 hover:text-red-600 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @endif

                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-gray-400">
                            <div class="text-3xl mb-2">🏷️</div>
                            <p class="font-medium">No categories yet</p>
                            <p class="text-xs mt-1">Create a main category, then add sub-categories under it.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal --}}
    @teleport('body')
    <div x-data="{}" x-show="$wire.showModal" x-cloak
         class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-gray-900/50" @click="$wire.closeModal()"></div>
        <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md z-10">

            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div>
                    <h3 class="text-base font-semibold text-gray-800">
                        @if ($editingId)
                            Edit: {{ $name }}
                        @elseif ($parentId)
                            New Sub-Category
                        @else
                            New Main Category
                        @endif
                    </h3>
                    @if ($parentId && $parentCategory)
                        <p class="text-xs text-gray-500 mt-0.5">
                            Parent: <span class="font-medium" style="color: {{ $parentCategory->color }}">{{ $parentCategory->name }}</span>
                        </p>
                    @endif
                </div>
                <button @click="$wire.closeModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form wire:submit="save">
                <div class="px-6 py-5 space-y-4">

                    {{-- Name --}}
                    <div>
                        <x-input-label for="cat_name" value="Category Name *" />
                        <x-text-input id="cat_name" wire:model="name" type="text" class="mt-1 block w-full"
                                      placeholder="{{ $parentId ? 'e.g. Chicken, Seafood…' : 'e.g. Protein, Vegetables…' }}" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    {{-- Color Picker --}}
                    <div>
                        <x-input-label value="Color *" />
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($colorOptions as $hex => $label)
                                <button type="button"
                                        wire:click="$set('color', '{{ $hex }}')"
                                        title="{{ $label }}"
                                        class="w-8 h-8 rounded-full border-2 transition {{ $color === $hex ? 'border-gray-800 scale-110' : 'border-transparent hover:border-gray-400' }}"
                                        style="background-color: {{ $hex }}">
                                </button>
                            @endforeach
                        </div>
                        <div class="mt-3 flex items-center gap-2">
                            <div class="w-4 h-4 rounded-full" style="background-color: {{ $color }}"></div>
                            <span class="text-sm text-gray-600 font-medium">{{ $name ?: 'Preview' }}</span>
                        </div>
                        <x-input-error :messages="$errors->get('color')" class="mt-1" />
                    </div>

                    {{-- Sort Order | Is Active | Is Revenue --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="cat_sort" value="Sort Order" />
                            <x-text-input id="cat_sort" wire:model="sort_order" type="number" min="0" class="mt-1 block w-full" />
                            <p class="mt-0.5 text-xs text-gray-400">Lower = shown first</p>
                            <x-input-error :messages="$errors->get('sort_order')" class="mt-1" />
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="is_active"
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700 font-medium">Active</span>
                            </label>
                        </div>
                    </div>

                </div>

                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
                    <button type="button" @click="$wire.closeModal()"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">Cancel</button>
                    <button type="submit"
                            class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        Save Category
                    </button>
                </div>
            </form>

        </div>
        </div>
        </div>
    </div>
    @endteleport
</div>
