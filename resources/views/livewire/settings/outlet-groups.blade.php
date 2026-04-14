<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('settings.index') }}" class="text-gray-400 hover:text-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="text-lg font-semibold text-gray-700">Outlet Groups</h2>
        </div>
        <button wire:click="create"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            + New Group
        </button>
    </div>

    <p class="text-xs text-gray-400 mb-4">
        Group outlets together so you can quickly tag recipes to a set of outlets in one click.
    </p>

    {{-- List --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        @if ($groups->count() > 0)
            <div class="divide-y divide-gray-100">
                @foreach ($groups as $group)
                    <div class="p-5 flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-3">
                                <h3 class="text-sm font-semibold text-gray-700">{{ $group->name }}</h3>
                                <span class="px-2 py-0.5 text-xs rounded {{ $group->is_active ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $group->is_active ? 'Active' : 'Inactive' }}
                                </span>
                                <span class="text-xs text-gray-400">{{ $group->outlets->count() }} outlet{{ $group->outlets->count() === 1 ? '' : 's' }}</span>
                            </div>
                            @if ($group->outlets->count() > 0)
                                <div class="flex flex-wrap gap-1 mt-2">
                                    @foreach ($group->outlets as $o)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] bg-indigo-50 text-indigo-600">
                                            {{ $o->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <p class="mt-1 text-xs text-amber-500">No outlets in this group</p>
                            @endif
                        </div>
                        <div class="flex gap-2 ml-4">
                            <button wire:click="edit({{ $group->id }})"
                                    class="text-sm text-indigo-600 hover:text-indigo-800 transition">Edit</button>
                            <button wire:click="delete({{ $group->id }})"
                                    wire:confirm="Delete this outlet group? Recipes tagged through it remain tagged to individual outlets."
                                    class="text-sm text-red-500 hover:text-red-700 transition">Delete</button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="p-8 text-center text-gray-400 text-sm">
                No outlet groups yet. Click "+ New Group" to create one.
            </div>
        @endif
    </div>

    {{-- Create/Edit Modal --}}
    @if ($showForm)
    @teleport('body')
    <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4" x-data>
        <div class="absolute inset-0 bg-gray-900/50" wire:click="cancel"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg z-10 p-6 mt-8 mb-8">
            <h3 class="text-lg font-semibold text-gray-700 mb-4">
                {{ $editingId ? 'Edit' : 'New' }} Outlet Group
            </h3>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Name *</label>
                    <input type="text" wire:model="name" class="w-full rounded-lg border-gray-300 text-sm" placeholder="KL Branches" />
                    @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Sort Order</label>
                        <input type="number" wire:model="sort_order" class="w-full rounded-lg border-gray-300 text-sm" min="0" />
                    </div>
                    <div class="flex items-end">
                        <label class="inline-flex items-center gap-2 mb-2">
                            <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">Active</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-2">Outlets</label>
                    @if ($outlets->count() > 0)
                        <div class="max-h-60 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-1">
                            @foreach ($outlets as $o)
                                <label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox" value="{{ $o->id }}" wire:model="outletIds"
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                    <span class="text-sm text-gray-700">{{ $o->name }}</span>
                                    @if ($o->code)
                                        <span class="text-xs text-gray-400">{{ $o->code }}</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-gray-400">No active outlets available.</p>
                    @endif
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button type="button" wire:click="cancel"
                        class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">Cancel</button>
                <button type="button" wire:click="save"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    {{ $editingId ? 'Update' : 'Create' }}
                </button>
            </div>
        </div>
    </div>
    @endteleport
    @endif
</div>
