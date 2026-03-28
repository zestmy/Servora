<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">{{ session('error') }}</div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('settings.index') }}" class="text-gray-400 hover:text-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="text-lg font-semibold text-gray-700">Users</h2>
        </div>
        <button wire:click="openCreate" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">+ Add User</button>
    </div>

    {{-- Search --}}
    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search users..." class="w-full max-w-md rounded-lg border-gray-300 text-sm" />
    </div>

    {{-- User List --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-5 py-3 text-left">Name</th>
                    <th class="px-5 py-3 text-left">Email</th>
                    <th class="px-5 py-3 text-left">Designation</th>
                    <th class="px-5 py-3 text-left">Modules</th>
                    <th class="px-5 py-3 text-left">Outlets</th>
                    <th class="px-5 py-3 text-center w-24">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($users as $u)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 font-medium text-gray-800">{{ $u->name }}</td>
                        <td class="px-5 py-3 text-gray-500 text-xs">{{ $u->email }}</td>
                        <td class="px-5 py-3">
                            <span class="text-xs text-gray-600">{{ $u->designation ?? $u->roles->first()?->name ?? '—' }}</span>
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex flex-wrap gap-1">
                                @foreach ($u->getAllPermissions()->pluck('name') as $perm)
                                    @if (isset($modules[$perm]))
                                        <span class="px-1.5 py-0.5 bg-indigo-50 text-indigo-600 text-[10px] rounded font-medium">{{ $modules[$perm] }}</span>
                                    @endif
                                @endforeach
                            </div>
                        </td>
                        <td class="px-5 py-3">
                            @if ($u->can_view_all_outlets)
                                <span class="px-1.5 py-0.5 bg-green-50 text-green-600 text-[10px] rounded font-medium">All Outlets</span>
                            @else
                                <span class="text-xs text-gray-500">{{ $u->outlets->pluck('name')->implode(', ') ?: '—' }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-center">
                            <button wire:click="openEdit({{ $u->id }})" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium mr-1">Edit</button>
                            @if ($u->id !== Auth::id())
                                <button wire:click="delete({{ $u->id }})" wire:confirm="Delete {{ $u->name }}?" class="text-red-500 hover:text-red-700 text-xs">Delete</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-8 text-center text-gray-400">No users found.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($users->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $users->links() }}</div>
        @endif
    </div>

    {{-- Create/Edit Modal --}}
    @if ($showModal)
    @teleport('body')
    <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4" x-data>
        <div class="absolute inset-0 bg-gray-900/50" wire:click="closeModal"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-xl z-10 p-6 mt-8 mb-8">
            <h3 class="text-lg font-semibold text-gray-700 mb-5">{{ $editingId ? 'Edit' : 'New' }} User</h3>

            <div class="space-y-5">
                {{-- Basic Info --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Name *</label>
                        <input type="text" wire:model="name" class="w-full rounded-lg border-gray-300 text-sm" />
                        @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Designation</label>
                        <input type="text" wire:model="designation" class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. Kitchen Manager" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Email *</label>
                        <input type="email" wire:model="email" class="w-full rounded-lg border-gray-300 text-sm" />
                        @error('email') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Password {{ $editingId ? '(leave blank to keep)' : '*' }}</label>
                        <input type="password" wire:model="password" class="w-full rounded-lg border-gray-300 text-sm" />
                        @error('password') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                @if ($isSuperAdmin)
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Company</label>
                        <select wire:model="company_id" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">— Select —</option>
                            @foreach ($companies as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                {{-- Module Access --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-2">Module Access</label>
                    <div class="grid grid-cols-2 gap-2 border border-gray-200 rounded-lg p-3">
                        @foreach ($modules as $perm => $label)
                            <label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" wire:model="moduleAccess" value="{{ $perm }}"
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Outlets --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-2">Outlet Access</label>
                    <label class="flex items-center gap-2 mb-2 px-2 py-1.5 bg-indigo-50 rounded-lg cursor-pointer">
                        <input type="checkbox" wire:model.live="allOutlets" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span class="text-sm font-medium text-indigo-700">All Outlets</span>
                    </label>
                    @if (! $allOutlets)
                        <div class="max-h-32 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-1">
                            @foreach ($outlets as $o)
                                <label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox" wire:model="outletIds" value="{{ $o->id }}"
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                    <span class="text-sm text-gray-700">{{ $o->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Capabilities --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-2">Capabilities</label>
                    <div class="grid grid-cols-2 gap-2 border border-gray-200 rounded-lg p-3">
                        <label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" wire:model="can_manage_users" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">Manage users</span>
                        </label>
                        <label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" wire:model="can_approve_po" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">Approve POs</span>
                        </label>
                        <label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" wire:model="can_approve_pr" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">Approve PRs</span>
                        </label>
                        <label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" wire:model="can_delete_records" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">Delete records</span>
                        </label>
                        <label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" wire:model="can_view_all_outlets" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">View all outlets</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button wire:click="closeModal" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</button>
                <button wire:click="save" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                    {{ $editingId ? 'Update' : 'Create' }}
                </button>
            </div>
        </div>
    </div>
    @endteleport
    @endif
</div>
