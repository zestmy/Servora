<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div wire:key="flash-err-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="mb-4 px-4 py-3 bg-danger-50 border border-danger-200 text-danger-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Top bar --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-xs text-gray-600"><a href="{{ route('settings.index') }}" class="hover:underline">Settings</a> / Price Classes</p>
            <h2 class="text-lg font-bold text-gray-800 mt-1">Recipe Price Classes</h2>
            <p class="text-sm text-gray-500 mt-0.5">Define selling price tiers for different outlet locations or sales channels.</p>
        </div>
        <button wire:click="openCreate"
                class="btn-primary">
            + Add Price Class
        </button>
    </div>

    {{-- Table --}}
    <div class="card overflow-hidden">
        @if ($priceClasses->count())
            <div class="overflow-x-auto"><table class="table-surface min-w-[780px]">
                <thead>
                    <tr>
                        <th class="px-6 py-3 text-left">Name</th>
                        <th class="px-6 py-3 text-center w-24">Order</th>
                        <th class="px-6 py-3 text-center w-24">Default</th>
                        <th class="px-6 py-3 text-center w-28">Recipes</th>
                        <th class="px-6 py-3 text-right w-32">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($priceClasses as $pc)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-3 font-medium text-gray-800">{{ $pc->name }}</td>
                            <td class="px-6 py-3 text-center text-gray-500">{{ $pc->sort_order }}</td>
                            <td class="px-6 py-3 text-center">
                                @if ($pc->is_default)
                                    <span class="inline-flex px-2 py-0.5 bg-brand-100 text-brand-700 text-xs font-semibold rounded-full">Default</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-center text-gray-500">{{ $usage[$pc->id] ?? 0 }}</td>
                            <td class="px-6 py-3 text-right space-x-2">
                                <button wire:click="openEdit({{ $pc->id }})"
                                        class="text-brand-600 hover:text-brand-800 text-xs font-medium">Edit</button>
                                <button wire:click="delete({{ $pc->id }})"
                                        data-confirm-delete="Delete &quot;{{ $pc->name }}&quot;?"
                                        class="text-danger-500 hover:text-danger-700 text-xs font-medium">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @else
            <div class="px-6 py-12 text-center text-gray-600">
                <p class="text-sm font-medium">No price classes yet</p>
                <p class="text-xs mt-1">Create price classes like "Dine-In", "Grab", "ShopeeFood" for different selling prices per recipe.</p>
            </div>
        @endif
    </div>

    {{-- Modal --}}
    @teleport('body')
        <div x-data="{ open: @entangle('showModal') }" x-show="open" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/40" @click="$wire.closeModal()"></div>
            <div x-show="open" x-transition
                 class="relative bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
                <h3 class="page-title">{{ $editingId ? 'Edit' : 'New' }} Price Class</h3>

                <div>
                    <x-input-label for="pc_name" value="Name *" />
                    <x-text-input id="pc_name" wire:model="name" type="text" class="mt-1 block w-full"
                                  placeholder="e.g. Dine-In, GrabFood, ShopeeFood" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="pc_sort" value="Sort Order" />
                    <x-text-input id="pc_sort" wire:model="sort_order" type="number" min="0" class="mt-1 block w-full" />
                </div>

                <div>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="is_default"
                               class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500" />
                        <span class="text-sm text-gray-700 font-medium">Default price class</span>
                    </label>
                    <p class="text-xs text-gray-600 mt-1">The default class price is used as the main selling price for food cost % calculations.</p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button wire:click="closeModal"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">Cancel</button>
                    <button wire:click="save"
                            class="btn-primary">
                        {{ $editingId ? 'Update' : 'Create' }}
                    </button>
                </div>
            </div>
        </div>
    @endteleport
</div>
