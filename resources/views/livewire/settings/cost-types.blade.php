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
            <p class="text-xs text-gray-400"><a href="{{ route('settings.index') }}" class="hover:underline">Settings</a> / Cost Types</p>
        </div>
        <button wire:click="openCreate"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            + Add Cost Type
        </button>
    </div>

    {{-- Guide --}}
    <div x-data="{ open: false }" class="mb-4">
        <button @click="open = !open" class="flex items-center gap-2 text-xs text-indigo-600 hover:text-indigo-800 font-medium transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span x-text="open ? 'Hide Guide' : 'How Cost Types Work'"></span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 transition-transform" :class="open ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </button>

        <div x-show="open" x-cloak class="mt-3 bg-indigo-50 border border-indigo-200 rounded-xl p-5 text-sm text-indigo-900 space-y-4">
            <div>
                <h4 class="font-bold text-indigo-800 mb-1">What are Cost Types?</h4>
                <p>Cost types classify how your business groups costs and revenue for analysis. Common examples: <strong>Food</strong>, <strong>Beverage</strong>, <strong>Merchandise</strong>. Each cost type flows through the system like this:</p>
            </div>

            <div class="bg-white/60 rounded-lg p-4 text-xs font-mono text-indigo-700 space-y-1">
                <p><strong>Cost Type</strong> (e.g. Food)</p>
                <p class="pl-4">&darr; assigned to <strong>Cost Centers</strong> (Ingredient Categories, e.g. Proteins, Produce)</p>
                <p class="pl-8">&darr; which group <strong>Ingredients</strong> for purchasing, stock &amp; COGS</p>
                <p class="pl-4">&darr; assigned to <strong>Sales Categories</strong> (e.g. Dine-In Food, Takeaway Food)</p>
                <p class="pl-8">&darr; which track <strong>Revenue</strong> in the sales form</p>
                <p class="pl-4">&darr; all combine in the <strong>Monthly Cost Summary (P&amp;L)</strong></p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div class="bg-white/60 rounded-lg p-3">
                    <p class="font-bold text-indigo-800 mb-1">Settings Hierarchy</p>
                    <ol class="list-decimal list-inside space-y-1 text-indigo-700">
                        <li><strong>Cost Types</strong> (this page) &mdash; Food, Beverage, etc.</li>
                        <li><strong>Ingredient Categories</strong> &mdash; cost centers assigned a type</li>
                        <li><strong>Sales Categories</strong> &mdash; revenue lines mapped to a cost center</li>
                    </ol>
                </div>
                <div class="bg-white/60 rounded-lg p-3">
                    <p class="font-bold text-indigo-800 mb-1">P&amp;L Formula Per Cost Center</p>
                    <ul class="space-y-1 text-indigo-700">
                        <li><strong>COGS</strong> = Opening Stock + Purchases + Transfer In &minus; Transfer Out &minus; Closing Stock</li>
                        <li><strong>Cost %</strong> = COGS &divide; Revenue &times; 100</li>
                    </ul>
                </div>
            </div>

            <p class="text-xs text-indigo-600">
                <strong>Tip:</strong> You can add custom types for your business (e.g. "Tobacco", "Catering"). A cost type cannot be deleted while it is assigned to any ingredient or sales category.
            </p>
        </div>
    </div>

    {{-- List --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Cost Type</th>
                    <th class="px-4 py-3 text-left">Slug</th>
                    <th class="px-4 py-3 text-center">Sort</th>
                    <th class="px-4 py-3 text-center">Cost Centers</th>
                    <th class="px-4 py-3 text-center">Sales Categories</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($costTypes as $ct)
                    <tr class="hover:bg-gray-50 transition {{ $ct->trashed() ? 'opacity-50' : '' }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-4 h-4 rounded-full flex-shrink-0" style="background-color: {{ $ct->color }}"></div>
                                <span class="font-medium text-gray-800">{{ $ct->name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $ct->slug }}</td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $ct->sort_order }}</td>
                        <td class="px-4 py-3 text-center text-gray-700 font-medium">{{ $categoryUsage[$ct->slug] ?? 0 }}</td>
                        <td class="px-4 py-3 text-center text-gray-700 font-medium">{{ $salesUsage[$ct->slug] ?? 0 }}</td>
                        <td class="px-4 py-3 text-center">
                            @if ($ct->trashed())
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-600">Deleted</span>
                            @elseif ($ct->is_active)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @unless ($ct->trashed())
                                <div class="flex items-center justify-center gap-2">
                                    <button wire:click="openEdit({{ $ct->id }})" title="Edit"
                                            class="text-indigo-500 hover:text-indigo-700 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button wire:click="toggleActive({{ $ct->id }})"
                                            title="{{ $ct->is_active ? 'Deactivate' : 'Activate' }}"
                                            class="{{ $ct->is_active ? 'text-green-500 hover:text-green-700' : 'text-gray-400 hover:text-gray-600' }} transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>
                                    <button wire:click="delete({{ $ct->id }})"
                                            wire:confirm="Delete '{{ $ct->name }}'? Only possible if not used by any category."
                                            title="Delete"
                                            class="text-red-400 hover:text-red-600 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                            <p class="font-medium">No cost types yet</p>
                            <p class="text-xs mt-1">Add types like Food, Beverage, Merchandise to classify your cost centers.</p>
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
                <h3 class="text-base font-semibold text-gray-800">
                    @if ($editingId) Edit: {{ $name }} @else New Cost Type @endif
                </h3>
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
                        <x-input-label for="ct_name" value="Name *" />
                        <x-text-input id="ct_name" wire:model.live="name" type="text" class="mt-1 block w-full" placeholder="e.g. Food" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    {{-- Slug --}}
                    <div>
                        <x-input-label for="ct_slug" value="Slug *" />
                        <x-text-input id="ct_slug" wire:model="slug" type="text" class="mt-1 block w-full font-mono text-sm" placeholder="e.g. food" />
                        <p class="mt-0.5 text-xs text-gray-400">Lowercase, no spaces. Used internally to link categories.</p>
                        <x-input-error :messages="$errors->get('slug')" class="mt-1" />
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
                        <x-input-error :messages="$errors->get('color')" class="mt-1" />
                    </div>

                    {{-- Sort Order | Active --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="ct_sort" value="Sort Order" />
                            <x-text-input id="ct_sort" wire:model="sort_order" type="number" min="0" class="mt-1 block w-full" />
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
                        Save Cost Type
                    </button>
                </div>
            </form>

        </div>
        </div>
        </div>
    </div>
    @endteleport
</div>
