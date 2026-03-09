<div>
    {{-- Flash --}}
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Back + Header --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('settings.index') }}" class="text-gray-400 hover:text-gray-600 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1">
            <p class="text-xs text-gray-400"><a href="{{ route('settings.index') }}" class="hover:underline">Settings</a> / Recipe Categories</p>
        </div>
        <button wire:click="openCreate"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            + Add Category
        </button>
    </div>

    {{-- List --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-center">Sort</th>
                    <th class="px-4 py-3 text-center">Recipes</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($categories as $cat)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-4 h-4 rounded-full flex-shrink-0" style="background-color: {{ $cat->color }}"></div>
                                <span class="font-medium text-gray-800">{{ $cat->name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $cat->sort_order }}</td>
                        <td class="px-4 py-3 text-center text-gray-700 font-medium">
                            {{ $recipeCounts[$cat->name] ?? 0 }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if ($cat->is_active)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
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
                                        wire:confirm="Delete '{{ $cat->name }}'? Recipes using this category will keep their category label."
                                        title="Delete"
                                        class="text-red-400 hover:text-red-600 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-gray-400">
                            <div class="text-3xl mb-2">📂</div>
                            <p class="font-medium">No recipe categories yet</p>
                            <p class="text-xs mt-1">Create categories to organise your recipes.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal --}}
    <div x-data="{}" x-show="$wire.showModal" x-cloak
         class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-gray-900/50" @click="$wire.closeModal()"></div>
        <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md z-10">

            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-800">
                    @if ($editingId) Edit: {{ $name }} @else New Recipe Category @endif
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
                        <x-input-label for="rcat_name" value="Category Name *" />
                        <x-text-input id="rcat_name" wire:model="name" type="text" class="mt-1 block w-full" placeholder="e.g. Main Course" />
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

                    {{-- Sort Order | Is Active --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="rcat_sort" value="Sort Order" />
                            <x-text-input id="rcat_sort" wire:model="sort_order" type="number" min="0" class="mt-1 block w-full" />
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
</div>
