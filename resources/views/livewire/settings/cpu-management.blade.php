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
        <div class="flex items-center gap-4">
            <a href="{{ route('settings.index') }}" class="text-gray-400 hover:text-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="text-lg font-semibold text-gray-700">Central Purchasing Units</h2>
        </div>
        <button wire:click="openCreate"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            + New CPU
        </button>
    </div>

    {{-- List --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        @if ($cpus->count() > 0)
            <div class="divide-y divide-gray-100">
                @foreach ($cpus as $cpu)
                    <div class="p-5 flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-3">
                                <h3 class="text-sm font-semibold text-gray-700">{{ $cpu->name }}</h3>
                                @if ($cpu->code)
                                    <span class="px-2 py-0.5 bg-gray-100 text-gray-500 text-xs rounded">{{ $cpu->code }}</span>
                                @endif
                                <span class="px-2 py-0.5 text-xs rounded {{ $cpu->is_active ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600' }}">
                                    {{ $cpu->is_active ? 'Active' : 'Inactive' }}
                                </span>
                                <span class="px-2 py-0.5 bg-indigo-50 text-indigo-600 text-xs rounded">
                                    {{ $cpu->delivery_mode === 'via_cpu' ? 'Deliver to CPU' : 'Direct to Outlet' }}
                                </span>
                            </div>
                            <div class="flex flex-wrap gap-4 mt-2 text-xs text-gray-400">
                                @if ($cpu->contact_person)
                                    <span>{{ $cpu->contact_person }}</span>
                                @endif
                                @if ($cpu->email)
                                    <span>{{ $cpu->email }}</span>
                                @endif
                                @if ($cpu->phone)
                                    <span>{{ $cpu->phone }}</span>
                                @endif
                            </div>
                            @if ($cpu->users->count() > 0)
                                <div class="flex flex-wrap gap-1 mt-2">
                                    @foreach ($cpu->users as $u)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] bg-gray-100 text-gray-600">
                                            {{ $u->name }}
                                            <span class="ml-1 text-gray-400">({{ $u->pivot->role }})</span>
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="flex gap-2 ml-4">
                            <button wire:click="openEdit({{ $cpu->id }})"
                                    class="text-sm text-indigo-600 hover:text-indigo-800 transition">Edit</button>
                            <button wire:click="delete({{ $cpu->id }})"
                                    wire:confirm="Are you sure you want to delete this CPU?"
                                    class="text-sm text-red-500 hover:text-red-700 transition">Delete</button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="p-8 text-center text-gray-400 text-sm">
                No Central Purchasing Units configured. Click "+ New CPU" to create one.
            </div>
        @endif
    </div>

    {{-- Create/Edit Modal --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data x-init="document.body.classList.add('overflow-hidden')"
             x-on:remove.window="document.body.classList.remove('overflow-hidden')">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-900/50 transition-opacity" wire:click="$set('showForm', false)"></div>
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg z-10 p-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">
                        {{ $editId ? 'Edit' : 'New' }} Central Purchasing Unit
                    </h3>

                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Name *</label>
                                <input type="text" wire:model="name" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Central Warehouse" />
                                @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Code</label>
                                <input type="text" wire:model="code" class="w-full rounded-lg border-gray-300 text-sm" placeholder="CPU-01" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Contact Person</label>
                                <input type="text" wire:model="contact_person" class="w-full rounded-lg border-gray-300 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Phone</label>
                                <input type="text" wire:model="phone" class="w-full rounded-lg border-gray-300 text-sm" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Email</label>
                            <input type="email" wire:model="email" class="w-full rounded-lg border-gray-300 text-sm" />
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Address</label>
                            <textarea wire:model="address" rows="2" class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Delivery Mode</label>
                            <select wire:model="delivery_mode" class="w-full rounded-lg border-gray-300 text-sm">
                                <option value="via_cpu">Supplier delivers to CPU (redistribute to outlets)</option>
                                <option value="direct_to_outlet">Supplier delivers directly to outlet</option>
                            </select>
                        </div>

                        <div>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm text-gray-600">Active</span>
                            </label>
                        </div>

                        {{-- User assignment --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-2">Assigned Users</label>
                            <div class="max-h-40 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-1">
                                @foreach ($companyUsers as $u)
                                    <label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 cursor-pointer">
                                        <input type="checkbox" wire:model="assignedUserIds" value="{{ $u->id }}"
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                        <span class="text-sm text-gray-700">{{ $u->name }}</span>
                                        <span class="text-xs text-gray-400">({{ $u->email }})</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <button wire:click="$set('showForm', false)"
                                class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">Cancel</button>
                        <button wire:click="save"
                                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                            {{ $editId ? 'Update' : 'Create' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
