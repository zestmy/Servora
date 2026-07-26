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
        <div class="flex items-center gap-2" x-data>
            <button @click="$dispatch('open-role-guide')"
                    class="px-3 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                Role Guide
            </button>
            <button wire:click="openCreate" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">+ Add User</button>
        </div>
    </div>

    {{-- Role guide: what each access level includes --}}
    <div x-data="{ open: false }" @open-role-guide.window="open = true">
        <template x-teleport="body">
            <div x-show="open" x-cloak @keydown.escape.window="open = false" class="fixed inset-0 z-[100] overflow-y-auto">
                <div class="fixed inset-0 bg-black/50" @click="open = false"></div>
                <div class="relative min-h-full flex items-start justify-center p-4">
                    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-3xl my-8" @click.stop>
                        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-800">Role Guide — what each access level includes</h3>
                            <button @click="open = false" class="text-gray-400 hover:text-gray-600 p-1">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div class="p-5 max-h-[70vh] overflow-y-auto divide-y divide-gray-50">
                            @foreach ($assignableRoles as $roleName => $desc)
                                <div class="py-3 first:pt-0 last:pb-0">
                                    <div class="flex items-start justify-between gap-3 flex-wrap">
                                        <div class="min-w-[200px]">
                                            <p class="text-sm font-semibold text-gray-800">{{ $roleName }}</p>
                                            <p class="text-xs text-gray-500 mt-0.5">{{ $desc }}</p>
                                        </div>
                                        <div class="flex flex-wrap gap-1 max-w-md justify-end">
                                            @forelse (array_intersect($rolePermMap[$roleName] ?? [], array_keys($modules)) as $perm)
                                                <span class="px-1.5 py-0.5 bg-indigo-50 text-indigo-600 text-[10px] rounded font-medium whitespace-nowrap">{{ $modules[$perm] }}</span>
                                            @empty
                                                <span class="text-[11px] text-gray-300">No modules — add manually</span>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                            <div class="py-3">
                                <p class="text-sm font-semibold text-gray-800">Custom</p>
                                <p class="text-xs text-gray-500 mt-0.5">No role attached — the user gets exactly the modules you tick, nothing more.</p>
                            </div>
                        </div>
                        <p class="px-5 pb-4 text-[11px] text-gray-400">
                            A role guarantees its listed modules; you can grant extra modules on top of a role, and every capability
                            (approvals, deleting records, GRN, invoices…) stays individually adjustable per user per company.
                        </p>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Search --}}
    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search users..." class="w-full max-w-md rounded-lg border-gray-300 text-sm" />
    </div>

    {{-- User List — horizontally scrollable on mobile. --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-[960px] divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-5 py-3 text-left">Name</th>
                    <th class="px-5 py-3 text-left">Email</th>
                    @if ($isSuperAdmin)<th class="px-5 py-3 text-left">Company</th>@endif
                    <th class="px-5 py-3 text-left">Access Level</th>
                    <th class="px-5 py-3 text-left">Modules</th>
                    <th class="px-5 py-3 text-left">Outlets</th>
                    <th class="px-5 py-3 text-left">Created</th>
                    <th class="px-5 py-3 text-left">Last Active</th>
                    <th class="px-5 py-3 text-center w-24">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($users as $u)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 font-medium text-gray-800">{{ $u->name }}</td>
                        <td class="px-5 py-3 text-gray-500 text-xs">{{ $u->email }}</td>
                        @if ($isSuperAdmin)
                            <td class="px-5 py-3 text-xs text-gray-600">{{ $u->company?->name ?? '—' }}</td>
                        @endif
                        <td class="px-5 py-3">
                            @php $rowRole = $u->roles->first()?->name; @endphp
                            @if ($rowRole)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium whitespace-nowrap
                                             {{ in_array($rowRole, ['Super Admin', 'System Admin'], true) ? 'bg-purple-100 text-purple-700' : 'bg-indigo-100 text-indigo-700' }}">
                                    {{ $rowRole }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-500">Custom</span>
                            @endif
                            @if ($u->designation)
                                <p class="text-[11px] text-gray-400 mt-0.5">{{ $u->designation }}</p>
                            @endif
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
                            @php
                                // Per-company flag from the membership pivot; the
                                // users-table column only caches their ACTIVE company
                                $rowViewAll = $u->relationLoaded('companies') && $u->companies->isNotEmpty()
                                    ? (bool) $u->companies->first()->pivot->can_view_all_outlets
                                    : (bool) $u->can_view_all_outlets;
                            @endphp
                            @if ($rowViewAll)
                                <span class="px-1.5 py-0.5 bg-green-50 text-green-600 text-[10px] rounded font-medium">All Outlets</span>
                            @else
                                <span class="text-xs text-gray-500">{{ $u->outlets->pluck('name')->implode(', ') ?: '—' }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-xs text-gray-500">{{ $u->created_at?->format('d M Y') }}</td>
                        <td class="px-5 py-3 text-xs">
                            @if ($u->last_session_activity)
                                @php $lastActive = \Carbon\Carbon::createFromTimestamp($u->last_session_activity); @endphp
                                <span class="{{ $lastActive->diffInDays(now()) > 30 ? 'text-red-400' : ($lastActive->diffInDays(now()) > 7 ? 'text-amber-500' : 'text-green-600') }}"
                                      title="{{ $lastActive->format('d M Y, h:i A') }}">
                                    {{ $lastActive->diffForHumans() }}
                                </span>
                            @else
                                <span class="text-gray-300">Never</span>
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
                    <tr><td colspan="{{ $isSuperAdmin ? 9 : 8 }}" class="px-5 py-8 text-center text-gray-400">No users found.</td></tr>
                @endforelse
            </tbody>
        </table>
      </div>
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
                        @if (! $editingId)
                            <p class="text-[11px] text-gray-400 mt-1">If this email already has a Servora account, that user will be linked to your company instead (they keep their existing password).</p>
                        @endif
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

                {{-- Access Level (role template) --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Access Level</label>
                    <select wire:model.live="accessRole" class="w-full rounded-lg border-gray-300 text-sm">
                        @foreach ($assignableRoles as $roleName => $desc)
                            <option value="{{ $roleName }}">{{ $roleName }}</option>
                        @endforeach
                        <option value="custom">Custom — pick modules manually</option>
                    </select>
                    <p class="text-[11px] text-gray-400 mt-1">
                        @if ($accessRole !== 'custom' && isset($assignableRoles[$accessRole]))
                            {{ $assignableRoles[$accessRole] }}
                            <span class="text-gray-300">·</span>
                            The role's modules are locked below — add extras on top, or switch to Custom to fine-tune freely.
                        @else
                            No role attached — this user gets exactly the modules ticked below.
                        @endif
                    </p>
                </div>

                {{-- Module Access --}}
                <div>
                    @php
                        $lockedPerms = $accessRole !== 'custom' ? ($rolePermMap[$accessRole] ?? []) : [];
                    @endphp
                    <label class="block text-xs font-medium text-gray-500 mb-2">Module Access</label>
                    <div class="grid grid-cols-2 gap-2 border border-gray-200 rounded-lg p-3">
                        @foreach ($modules as $perm => $label)
                            @php $locked = in_array($perm, $lockedPerms, true); @endphp
                            <label wire:key="mod-{{ $accessRole }}-{{ $perm }}"
                                   class="flex items-center gap-2 px-2 py-1.5 rounded {{ $locked ? 'bg-indigo-50/60 cursor-default' : 'hover:bg-gray-50 cursor-pointer' }}">
                                @if ($locked)
                                    <input type="checkbox" checked disabled
                                           class="rounded border-gray-300 text-indigo-400" />
                                    <span class="text-sm text-gray-700">{{ $label }}</span>
                                    <span class="ml-auto text-[9px] uppercase tracking-wider text-indigo-400 font-semibold">role</span>
                                @else
                                    <input type="checkbox" wire:model="moduleAccess" value="{{ $perm }}"
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                    <span class="text-sm text-gray-700">{{ $label }}</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Outlet Access --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-2">Outlet Access</label>
                    <div class="space-y-1 mb-2">
                        <label class="flex items-center gap-2 px-2 py-1.5 rounded cursor-pointer {{ $outletMode === 'all' ? 'bg-indigo-50' : 'hover:bg-gray-50' }}">
                            <input type="radio" wire:model.live="outletMode" value="all" class="text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-sm font-medium {{ $outletMode === 'all' ? 'text-indigo-700' : 'text-gray-700' }}">All Outlets</span>
                        </label>
                        <label class="flex items-center gap-2 px-2 py-1.5 rounded cursor-pointer {{ $outletMode === 'all_except' ? 'bg-amber-50' : 'hover:bg-gray-50' }}">
                            <input type="radio" wire:model.live="outletMode" value="all_except" class="text-amber-600 focus:ring-amber-500" />
                            <span class="text-sm font-medium {{ $outletMode === 'all_except' ? 'text-amber-700' : 'text-gray-700' }}">All Outlets except:</span>
                        </label>
                        <label class="flex items-center gap-2 px-2 py-1.5 rounded cursor-pointer {{ $outletMode === 'selected' ? 'bg-blue-50' : 'hover:bg-gray-50' }}">
                            <input type="radio" wire:model.live="outletMode" value="selected" class="text-blue-600 focus:ring-blue-500" />
                            <span class="text-sm font-medium {{ $outletMode === 'selected' ? 'text-blue-700' : 'text-gray-700' }}">Selected Outlets only:</span>
                        </label>
                    </div>
                    @if (in_array($outletMode, ['all_except', 'selected']))
                        <div class="max-h-28 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-1">
                            @foreach ($regularOutlets as $o)
                                <label class="flex items-center gap-2 px-2 py-1 rounded hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox" wire:model="outletIds" value="{{ $o->id }}"
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                    <span class="text-sm text-gray-700">{{ $o->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="text-[11px] text-gray-400 mt-1">
                            {{ $outletMode === 'all_except' ? 'Check outlets to EXCLUDE' : 'Check outlets to INCLUDE' }}
                        </p>
                    @endif
                </div>

                {{-- Central Kitchen Access --}}
                @if ($kitchens->count() > 0)
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-2">Central Kitchen Access</label>
                    <div class="space-y-1 mb-2">
                        <label class="flex items-center gap-2 px-2 py-1.5 rounded cursor-pointer {{ $kitchenMode === 'none' ? 'bg-gray-100' : 'hover:bg-gray-50' }}">
                            <input type="radio" wire:model.live="kitchenMode" value="none" class="text-gray-600 focus:ring-gray-500" />
                            <span class="text-sm font-medium text-gray-700">No kitchen access</span>
                        </label>
                        <label class="flex items-center gap-2 px-2 py-1.5 rounded cursor-pointer {{ $kitchenMode === 'all' ? 'bg-purple-50' : 'hover:bg-gray-50' }}">
                            <input type="radio" wire:model.live="kitchenMode" value="all" class="text-purple-600 focus:ring-purple-500" />
                            <span class="text-sm font-medium {{ $kitchenMode === 'all' ? 'text-purple-700' : 'text-gray-700' }}">All Central Kitchens</span>
                        </label>
                        <label class="flex items-center gap-2 px-2 py-1.5 rounded cursor-pointer {{ $kitchenMode === 'all_except' ? 'bg-amber-50' : 'hover:bg-gray-50' }}">
                            <input type="radio" wire:model.live="kitchenMode" value="all_except" class="text-amber-600 focus:ring-amber-500" />
                            <span class="text-sm font-medium {{ $kitchenMode === 'all_except' ? 'text-amber-700' : 'text-gray-700' }}">All Central Kitchens except:</span>
                        </label>
                        <label class="flex items-center gap-2 px-2 py-1.5 rounded cursor-pointer {{ $kitchenMode === 'selected' ? 'bg-purple-50' : 'hover:bg-gray-50' }}">
                            <input type="radio" wire:model.live="kitchenMode" value="selected" class="text-purple-600 focus:ring-purple-500" />
                            <span class="text-sm font-medium {{ $kitchenMode === 'selected' ? 'text-purple-700' : 'text-gray-700' }}">Selected Kitchens only:</span>
                        </label>
                    </div>
                    @if (in_array($kitchenMode, ['all_except', 'selected']))
                        <div class="max-h-28 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-1">
                            @foreach ($kitchens as $k)
                                <label class="flex items-center gap-2 px-2 py-1 rounded hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox" wire:model="kitchenIds" value="{{ $k->id }}"
                                           class="rounded border-gray-300 text-purple-600 focus:ring-purple-500" />
                                    <span class="text-sm text-gray-700">{{ $k->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="text-[11px] text-gray-400 mt-1">
                            {{ $kitchenMode === 'all_except' ? 'Check kitchens to EXCLUDE' : 'Check kitchens to INCLUDE' }}
                        </p>
                    @endif
                </div>
                @endif

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
                        <label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" wire:model="can_receive_grn" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">Receive goods (GRN)</span>
                        </label>
                        <label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" wire:model="can_manage_invoices" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">Manage invoices & payments</span>
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
