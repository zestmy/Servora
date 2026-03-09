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
            <h2 class="text-lg font-semibold text-gray-700">Users &amp; Roles</h2>
            <p class="text-xs text-gray-400 mt-0.5">Manage user accounts and their module access</p>
        </div>
        <button wire:click="openCreate"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            + Add User
        </button>
    </div>

    {{-- Role reference --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
        @php
            $roleMatrix = [
                'System Admin'     => ['settings' => true,  'users' => true,  'modules' => '—'],
                'Business Manager' => ['settings' => true,  'users' => true,  'modules' => 'All modules'],
                'Manager'          => ['settings' => false, 'users' => false, 'modules' => 'Ingredients, Recipes, Sales, Inventory, Purchasing, Reports'],
                'Chef'             => ['settings' => false, 'users' => false, 'modules' => 'Ingredients, Recipes, Inventory, Purchasing'],
                'Purchasing'       => ['settings' => false, 'users' => false, 'modules' => 'Purchasing'],
                'Finance'          => ['settings' => false, 'users' => false, 'modules' => 'Sales, Inventory, Purchasing, Reports'],
            ];
            $roleColors = [
                'System Admin'     => 'border-gray-300 bg-gray-50',
                'Business Manager' => 'border-indigo-200 bg-indigo-50',
                'Manager'          => 'border-blue-200 bg-blue-50',
                'Chef'             => 'border-orange-200 bg-orange-50',
                'Purchasing'       => 'border-yellow-200 bg-yellow-50',
                'Finance'          => 'border-green-200 bg-green-50',
            ];
        @endphp
        @foreach ($roleMatrix as $roleName => $info)
            <div class="rounded-lg border p-3 {{ $roleColors[$roleName] ?? 'border-gray-200 bg-white' }}">
                <p class="text-xs font-semibold text-gray-700">{{ $roleName }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $info['modules'] }}</p>
                <div class="flex gap-2 mt-1.5">
                    @if ($info['settings'])
                        <span class="text-xs text-gray-500 bg-white/70 px-1.5 py-0.5 rounded border border-gray-200">Settings</span>
                    @endif
                    @if ($info['users'])
                        <span class="text-xs text-gray-500 bg-white/70 px-1.5 py-0.5 rounded border border-gray-200">Users</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Filter --}}
    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search"
               placeholder="Search by name or email…"
               class="w-full max-w-sm rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Name</th>
                    <th class="px-4 py-3 text-left">Email</th>
                    <th class="px-4 py-3 text-left">Role</th>
                    @if ($isSuperAdmin)
                        <th class="px-4 py-3 text-left">Company</th>
                    @endif
                    <th class="px-4 py-3 text-left">Outlets</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($users as $user)
                    @php
                        $userRole = $user->roles->first()?->name ?? '—';
                        $isMe = $user->id === auth()->id();
                        $roleBadgeColors = [
                            'Super Admin'      => 'bg-gray-800 text-white',
                            'System Admin'     => 'bg-gray-200 text-gray-700',
                            'Business Manager' => 'bg-indigo-100 text-indigo-700',
                            'Manager'          => 'bg-blue-100 text-blue-700',
                            'Chef'             => 'bg-orange-100 text-orange-700',
                            'Purchasing'       => 'bg-yellow-100 text-yellow-700',
                            'Finance'          => 'bg-green-100 text-green-700',
                        ];
                        $badgeClass = $roleBadgeColors[$userRole] ?? 'bg-gray-100 text-gray-600';
                    @endphp
                    <tr class="hover:bg-gray-50 transition {{ $isMe ? 'bg-indigo-50/30' : '' }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 bg-indigo-600 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                </div>
                                <span class="font-medium text-gray-800">{{ $user->name }}</span>
                                @if ($isMe)
                                    <span class="text-xs text-indigo-400">(you)</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                                {{ $userRole }}
                            </span>
                        </td>
                        @if ($isSuperAdmin)
                            <td class="px-4 py-3 text-gray-500 text-xs">{{ $user->company?->name ?? '—' }}</td>
                        @endif
                        <td class="px-4 py-3 text-xs">
                            @if ($user->outlets->isNotEmpty())
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($user->outlets as $o)
                                        <span class="px-1.5 py-0.5 rounded bg-gray-100 text-gray-600">{{ $o->name }}</span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <button wire:click="openEdit({{ $user->id }})" title="Edit"
                                        class="text-indigo-500 hover:text-indigo-700 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                @if (!$isMe)
                                    <button wire:click="delete({{ $user->id }})"
                                            wire:confirm="Delete user {{ $user->name }}? This cannot be undone."
                                            title="Delete"
                                            class="text-red-400 hover:text-red-600 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isSuperAdmin ? 6 : 5 }}" class="px-4 py-12 text-center text-gray-400">
                            <p class="font-medium">No users found</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($users->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $users->links() }}</div>
        @endif
    </div>

    {{-- Modal --}}
    <div x-data="{}" x-show="$wire.showModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="fixed inset-0 bg-gray-900/50" @click="$wire.closeModal()"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md mx-4 z-10">

            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-800">
                    {{ $editingId ? 'Edit User' : 'New User' }}
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
                        <x-input-label for="u_name" value="Full Name *" />
                        <x-text-input id="u_name" wire:model="name" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="u_email" value="Email *" />
                        <x-text-input id="u_email" wire:model="email" type="email" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="u_pass" value="{{ $editingId ? 'New Password (leave blank to keep)' : 'Password *' }}" />
                        <x-text-input id="u_pass" wire:model="password" type="password" class="mt-1 block w-full"
                                      autocomplete="new-password" />
                        <x-input-error :messages="$errors->get('password')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="u_role" value="Role *" />
                        <select id="u_role" wire:model="role"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— Select role —</option>
                            @foreach ($assignableRoles as $r)
                                <option value="{{ $r }}">{{ $r }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('role')" class="mt-1" />
                    </div>

                    @if ($isSuperAdmin && $companies->isNotEmpty())
                        <div>
                            <x-input-label for="u_company" value="Company" />
                            <select id="u_company" wire:model.live="company_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— System / No company —</option>
                                @foreach ($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <x-input-label value="Branch Access *" />
                        <div class="mt-2 space-y-2 max-h-40 overflow-y-auto rounded-md border border-gray-200 p-3">
                            @forelse ($outlets as $outlet)
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" wire:model="outletIds" value="{{ $outlet->id }}"
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="text-sm text-gray-700">{{ $outlet->name }}</span>
                                    <span class="text-xs text-gray-400">({{ $outlet->code }})</span>
                                </label>
                            @empty
                                <p class="text-xs text-gray-400">No branches found. Create one in Settings first.</p>
                            @endforelse
                        </div>
                        @if ($outlets->count() > 1)
                            <button type="button" wire:click="$set('outletIds', {{ $outlets->pluck('id')->map(fn($id) => (string)$id)->toJson() }})"
                                    class="mt-1 text-xs text-indigo-600 hover:text-indigo-800">Select All</button>
                        @endif
                        <x-input-error :messages="$errors->get('outletIds')" class="mt-1" />
                    </div>

                </div>

                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
                    <button type="button" @click="$wire.closeModal()"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">Cancel</button>
                    <button type="submit"
                            class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        {{ $editingId ? 'Update User' : 'Create User' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
