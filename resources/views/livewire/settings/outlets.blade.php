<div>
    {{-- Flash --}}
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-700">Branches</h2>
            <p class="text-xs text-gray-400 mt-0.5">Manage your outlet locations</p>
        </div>
        <button wire:click="openCreate"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            + Add Branch
        </button>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Name</th>
                    <th class="px-4 py-3 text-left">Code</th>
                    <th class="px-4 py-3 text-left">Phone</th>
                    <th class="px-4 py-3 text-left">Address</th>
                    <th class="px-4 py-3 text-center">Users</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($outlets as $outlet)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $outlet->name }}</td>
                        <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ $outlet->code }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $outlet->phone ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500 text-xs max-w-xs truncate">{{ $outlet->address ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                {{ $outlet->users_count }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button wire:click="toggleActive({{ $outlet->id }})" title="Toggle active"
                                    class="px-2 py-0.5 rounded-full text-xs font-medium transition
                                        {{ $outlet->is_active ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }}">
                                {{ $outlet->is_active ? 'Active' : 'Inactive' }}
                            </button>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <button wire:click="openEdit({{ $outlet->id }})" title="Edit"
                                        class="text-indigo-500 hover:text-indigo-700 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button wire:click="delete({{ $outlet->id }})"
                                        wire:confirm="Delete branch '{{ $outlet->name }}'? This cannot be undone."
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
                        <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                            <p class="font-medium">No branches yet</p>
                            <p class="text-sm mt-1">Create your first branch to get started.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal --}}
    <div x-data="{}" x-show="$wire.showModal" x-cloak class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-gray-900/50" @click="$wire.closeModal()"></div>
        <div class="fixed inset-0 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md z-10">

            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-800">
                    {{ $editingId ? 'Edit Branch' : 'New Branch' }}
                </h3>
                <button @click="$wire.closeModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form wire:submit="save">
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <x-input-label for="o_name" value="Branch Name *" />
                        <x-text-input id="o_name" wire:model="name" type="text" class="mt-1 block w-full" placeholder="e.g. Main Branch" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="o_code" value="Branch Code *" />
                        <x-text-input id="o_code" wire:model="code" type="text" class="mt-1 block w-full uppercase" placeholder="e.g. MAIN" />
                        <p class="text-xs text-gray-400 mt-1">Short code used in document numbering (PO, DO, etc.)</p>
                        <x-input-error :messages="$errors->get('code')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="o_phone" value="Phone" />
                        <x-text-input id="o_phone" wire:model="phone" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="o_address" value="Address" />
                        <textarea id="o_address" wire:model="address" rows="2"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        <x-input-error :messages="$errors->get('address')" class="mt-1" />
                    </div>

                    @if ($editingId)
                        <div class="flex items-center gap-2">
                            <input type="checkbox" wire:model="is_active" id="o_active"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <label for="o_active" class="text-sm text-gray-700">Active</label>
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
                    <button type="button" @click="$wire.closeModal()"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">Cancel</button>
                    <button type="submit"
                            class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        {{ $editingId ? 'Update Branch' : 'Create Branch' }}
                    </button>
                </div>
            </form>
                </div>
            </div>
        </div>
    </div>
</div>
