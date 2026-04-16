<div>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>

    {{-- Flash --}}
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div wire:key="flash-err-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ session('error') }}
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
            <p class="text-xs text-gray-400 mt-0.5">Drag to reorder categories and sub-categories.</p>
        </div>
        <button wire:click="openCreate"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            + Add Category
        </button>
    </div>

    {{-- Category List --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        @if ($categories->isEmpty())
            <div class="px-4 py-12 text-center text-gray-400">
                <div class="text-3xl mb-2">📂</div>
                <p class="font-medium">No recipe categories yet</p>
                <p class="text-xs mt-1">Create categories to organise your recipes.</p>
            </div>
        @else
            <div x-data x-init="new Sortable($el, {
                    handle: '.parent-drag',
                    animation: 200,
                    ghostClass: 'bg-indigo-50',
                    onEnd(e) {
                        let ids = Array.from(e.from.children).map(el => el.dataset.id);
                        $wire.reorderParents(ids);
                    }
                })">
                @foreach ($categories as $cat)
                    <div data-id="{{ $cat->id }}" class="border-b border-gray-100 last:border-b-0">
                        {{-- Parent row --}}
                        <div class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition group">
                            <div class="parent-drag cursor-grab active:cursor-grabbing text-gray-300 hover:text-gray-500 flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 8h16M4 16h16" />
                                </svg>
                            </div>
                            <div class="w-4 h-4 rounded-full flex-shrink-0" style="background-color: {{ $cat->color }}"></div>
                            <div class="flex-1 min-w-0">
                                <span class="font-semibold text-gray-800 text-sm">{{ $cat->name }}</span>
                                @if ($cat->children->count())
                                    <span class="text-xs text-gray-400 ml-1">({{ $cat->children->count() }} sub)</span>
                                @endif
                            </div>
                            <span class="text-xs text-gray-400 tabular-nums w-12 text-center">{{ $recipeCounts[$cat->name] ?? 0 }}</span>
                            @if ($cat->is_active)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-green-100 text-green-700">Active</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-500">Inactive</span>
                            @endif
                            <div class="flex items-center gap-1.5 opacity-0 group-hover:opacity-100 transition flex-shrink-0">
                                <button wire:click="openCreate({{ $cat->id }})" title="Add Sub" class="text-green-500 hover:text-green-700 p-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                </button>
                                <button wire:click="openEdit({{ $cat->id }})" title="Edit" class="text-indigo-500 hover:text-indigo-700 p-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                </button>
                                <button wire:click="toggleActive({{ $cat->id }})" title="{{ $cat->is_active ? 'Deactivate' : 'Activate' }}"
                                        class="{{ $cat->is_active ? 'text-green-500 hover:text-green-700' : 'text-gray-400 hover:text-gray-600' }} p-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </button>
                                <button wire:click="delete({{ $cat->id }})" wire:confirm="Delete '{{ $cat->name }}'? Recipes using this category will keep their category label." title="Delete"
                                        class="text-red-400 hover:text-red-600 p-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                        </div>

                        {{-- Sub-category rows (separately sortable) --}}
                        @if ($cat->children->isNotEmpty())
                            <div x-data x-init="new Sortable($el, {
                                    handle: '.sub-drag',
                                    animation: 200,
                                    ghostClass: 'bg-indigo-50',
                                    onEnd(e) {
                                        let ids = Array.from(e.from.children).map(el => el.dataset.id);
                                        $wire.reorderChildren({{ $cat->id }}, ids);
                                    }
                                })" class="border-t border-gray-100 bg-gray-50/40">
                                @foreach ($cat->children as $sub)
                                    <div data-id="{{ $sub->id }}" class="flex items-center gap-3 px-4 py-2.5 pl-10 hover:bg-gray-100/50 transition group border-b border-gray-50 last:border-b-0">
                                        <div class="sub-drag cursor-grab active:cursor-grabbing text-gray-300 hover:text-gray-500 flex-shrink-0">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 8h16M4 16h16" />
                                            </svg>
                                        </div>
                                        <div class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $sub->color }}"></div>
                                        <div class="flex-1 min-w-0">
                                            <span class="text-gray-700 text-sm">{{ $sub->name }}</span>
                                        </div>
                                        <span class="text-xs text-gray-400 tabular-nums w-12 text-center">{{ $recipeCounts[$sub->name] ?? 0 }}</span>
                                        @if ($sub->is_active)
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-green-100 text-green-700">Active</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-500">Inactive</span>
                                        @endif
                                        <div class="flex items-center gap-1.5 opacity-0 group-hover:opacity-100 transition flex-shrink-0">
                                            <button wire:click="openEdit({{ $sub->id }})" title="Edit" class="text-indigo-500 hover:text-indigo-700 p-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            </button>
                                            <button wire:click="toggleActive({{ $sub->id }})" title="{{ $sub->is_active ? 'Deactivate' : 'Activate' }}"
                                                    class="{{ $sub->is_active ? 'text-green-500 hover:text-green-700' : 'text-gray-400 hover:text-gray-600' }} p-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            </button>
                                            <button wire:click="delete({{ $sub->id }})" wire:confirm="Delete sub-category '{{ $sub->name }}'?" title="Delete"
                                                    class="text-red-400 hover:text-red-600 p-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
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
                    @if ($editingId)
                        Edit: {{ $name }}
                    @elseif ($parent_id)
                        New Sub-category
                    @else
                        New Recipe Category
                    @endif
                </h3>
                <button @click="$wire.closeModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form wire:submit="save">
                <div class="px-6 py-5 space-y-4">

                    {{-- Parent info (for sub-categories) --}}
                    @if ($parent_id)
                        @php $parentCat = \App\Models\RecipeCategory::find($parent_id); @endphp
                        @if ($parentCat)
                            <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 rounded-lg text-sm">
                                <div class="w-3 h-3 rounded-full" style="background-color: {{ $parentCat->color }}"></div>
                                <span class="text-gray-500">Sub-category of</span>
                                <span class="font-medium text-gray-700">{{ $parentCat->name }}</span>
                            </div>
                        @endif
                    @endif

                    {{-- Name --}}
                    <div>
                        <x-input-label for="rcat_name" value="{{ $parent_id ? 'Sub-category Name *' : 'Category Name *' }}" />
                        <x-text-input id="rcat_name" wire:model="name" type="text" class="mt-1 block w-full" placeholder="{{ $parent_id ? 'e.g. Pasta' : 'e.g. Main Course' }}" />
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

                    {{-- Active --}}
                    <div>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="is_active"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700 font-medium">Active</span>
                        </label>
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
