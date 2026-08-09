<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 bg-danger-50 border border-danger-200 text-danger-700 text-sm rounded-lg">{{ session('error') }}</div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <a data-back href="{{ route('settings.index') }}" class="text-gray-600 hover:text-gray-900 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <p class="page-eyebrow">Settings</p>
                <h2 class="page-title mt-1">Roles &amp; Access</h2>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button wire:click="openCreate" class="btn-primary">+ Add User</button>
        </div>
    </div>

    {{-- The Role Guide modal that used to live here is now the Roles tab: it listed a
         role's modules as an unstructured wall of badges, which stopped being readable
         at 41 abilities. --}}
    <x-access-tabs current="users" />

    {{-- Search --}}
    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search users..." class="w-full max-w-md rounded-lg border-gray-300 text-sm" />
    </div>

    {{-- User List — horizontally scrollable on mobile. --}}
    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="table-surface min-w-[960px]">
            <thead>
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
            <tbody>
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
                                             {{ in_array($rowRole, ['Super Admin', 'System Admin'], true) ? 'bg-purple-100 text-purple-700' : 'bg-brand-100 text-brand-700' }}">
                                    {{ $roleDisplayMap[$rowRole] ?? $u->roles->first()?->display_name ?? $rowRole }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-500">Custom</span>
                            @endif
                            @if ($u->designation)
                                <p class="text-[11px] text-gray-600 mt-0.5">{{ $u->designation }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex flex-wrap gap-1">
                                @foreach ($u->getAllPermissions()->pluck('name') as $perm)
                                    @if (isset($modules[$perm]))
                                        <span class="px-1.5 py-0.5 bg-brand-50 text-brand-600 text-[10px] rounded font-medium">{{ $modules[$perm] }}</span>
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
                                <span class="px-1.5 py-0.5 bg-success-50 text-success-600 text-[10px] rounded font-medium">All Outlets</span>
                            @else
                                <span class="text-xs text-gray-500">{{ $u->outlets->pluck('name')->implode(', ') ?: '—' }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-xs text-gray-500">{{ $u->created_at?->format('d M Y') }}</td>
                        <td class="px-5 py-3 text-xs">
                            @if (isset($lastActive[$u->id]))
                                @php $la = $lastActive[$u->id]; @endphp
                                <span class="{{ $la->diffInDays(now()) > 30 ? 'text-danger-400' : ($la->diffInDays(now()) > 7 ? 'text-warning-500' : 'text-success-600') }}"
                                      title="{{ $la->format('d M Y, h:i A') }}">
                                    {{ $la->diffForHumans() }}
                                </span>
                            @else
                                {{-- Not "Never": activity tracking only began 2026-07-28, and the
                                     DB sessions fallback went stale when sessions moved to Redis
                                     (2026-06-29), so a blank here means unrecorded, not inactive. --}}
                                <span class="text-gray-500" title="Activity tracking started 28 Jul 2026 — earlier visits weren't recorded">No activity recorded</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-center">
                            <button wire:click="openEdit({{ $u->id }})" class="text-brand-600 hover:text-brand-800 text-xs font-medium mr-1">Edit</button>
                            @if ($u->id !== Auth::id())
                                <button wire:click="delete({{ $u->id }})" wire:confirm="Delete {{ $u->name }}?" class="text-danger-500 hover:text-danger-700 text-xs">Delete</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $isSuperAdmin ? 9 : 8 }}" class="px-5 py-8 text-center text-gray-600">No users found.</td></tr>
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
                        @error('name') <p class="text-xs text-danger-500 mt-1">{{ $message }}</p> @enderror
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
                        @error('email') <p class="text-xs text-danger-500 mt-1">{{ $message }}</p> @enderror
                        @if (! $editingId)
                            <p class="text-[11px] text-gray-600 mt-1">If this email already has a Servora account, that user will be linked to your company instead (they keep their existing password).</p>
                        @endif
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Password {{ $editingId ? '(leave blank to keep)' : '*' }}</label>
                        <input type="password" wire:model="password" class="w-full rounded-lg border-gray-300 text-sm" />
                        @error('password') <p class="text-xs text-danger-500 mt-1">{{ $message }}</p> @enderror
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
                            <option value="{{ $roleName }}">{{ $roleDisplayMap[$roleName] ?? $roleName }}</option>
                        @endforeach
                        <option value="custom">Custom — pick modules manually</option>
                    </select>
                    <p class="text-[11px] text-gray-600 mt-1">
                        @if ($accessRole !== 'custom' && isset($assignableRoles[$accessRole]))
                            {{ $assignableRoles[$accessRole] }}
                            <span class="text-gray-500">·</span>
                            The role's modules are locked below — add extras on top, or switch to Custom to fine-tune freely.
                        @else
                            No role attached — this user gets exactly the modules ticked below.
                        @endif
                    </p>
                </div>

                {{-- Module Access --}}
                @php
                    $lockedPerms = $accessRole !== 'custom' ? ($rolePermMap[$accessRole] ?? []) : [];
                    $grantable   = array_keys($modules);
                    $fromRole    = array_values(array_intersect($lockedPerms, $grantable));
                    $addedOnTop  = array_values(array_diff(array_intersect($moduleAccess, $grantable), $fromRole));
                @endphp
                {{-- Picking a role is the whole job for most people, so the 41-checkbox grid
                     starts collapsed behind a one-line summary of how this person differs
                     from their role. It opens automatically when there is no role to fall
                     back on, or when someone has already been fine-tuned — those are the two
                     cases where the detail is the point. The counts are recomputed in Alpine
                     from the checkboxes themselves, because wire:model here is deferred and a
                     server round-trip per tick would make a 41-box grid crawl. --}}
                <div x-data="{
                        open: @js($accessRole === 'custom' || count($addedOnTop) > 0),
                        added: @js(count($addedOnTop)),
                        recount() {
                            this.added = this.$refs.grid
                                ? this.$refs.grid.querySelectorAll('input[type=checkbox]:checked:not([disabled])').length
                                : this.added;
                        }
                     }">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <label class="block text-xs font-medium text-gray-500">Module Access</label>
                        <button type="button" @click="open = ! open"
                                class="text-xs font-medium text-brand-600 hover:text-brand-700">
                            <span x-show="! open">Customise</span>
                            <span x-show="open" x-cloak>Hide detail</span>
                        </button>
                    </div>

                    <div x-show="! open" class="rounded-control border border-gray-200 bg-gray-50 px-3 py-2.5">
                        @if ($accessRole !== 'custom')
                            <p class="text-sm text-gray-800">
                                <span class="font-medium">{{ $roleDisplayMap[$accessRole] ?? $accessRole }}</span>
                                <span class="text-gray-600">— {{ count($fromRole) }} {{ \Illuminate\Support\Str::plural('ability', count($fromRole)) }} from this role</span>
                            </p>
                        @else
                            <p class="text-sm text-gray-800"><span class="font-medium">Custom</span> <span class="text-gray-600">— no role attached</span></p>
                        @endif
                        <p class="help mt-0.5">
                            <span x-text="added"></span> granted on top of that.
                            <span class="text-gray-500">Open Customise to change which.</span>
                        </p>
                    </div>

                    <div x-show="open" x-cloak x-ref="grid" @change="recount()"
                         class="border border-gray-200 rounded-lg divide-y divide-gray-100">
                        @foreach ($moduleGrid as $groupLabel => $groupModules)
                            <div class="p-3">
                                <p class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold mb-2">{{ $groupLabel }}</p>
                                <div class="space-y-2">
                                    @foreach ($groupModules as $moduleKey => $module)
                                        @if ($module['single'])
                                            @php
                                                $ability = reset($module['abilities']);
                                                $perm    = $ability['name'];
                                                $locked  = in_array($perm, $lockedPerms, true);
                                            @endphp
                                            <label wire:key="mod-{{ $accessRole }}-{{ $perm }}"
                                                   title="{{ $ability['help'] ?? '' }}"
                                                   class="flex items-center gap-2 px-2 py-1.5 rounded {{ $locked ? 'bg-brand-50/60 cursor-default' : 'hover:bg-gray-50 cursor-pointer' }}">
                                                <input type="checkbox" @disabled($locked) @checked($locked)
                                                       @if (! $locked) wire:model="moduleAccess" value="{{ $perm }}" @endif
                                                       class="rounded border-gray-300 {{ $locked ? 'text-brand-400' : 'text-brand-600 focus:ring-brand-500' }}" />
                                                <span class="text-sm text-gray-700">{{ $module['label'] }}</span>
                                                @if ($locked)
                                                    <span class="ml-auto text-[9px] uppercase tracking-wider text-brand-400 font-semibold">role</span>
                                                @endif
                                            </label>
                                        @else
                                            <div wire:key="modgrp-{{ $accessRole }}-{{ $moduleKey }}" class="px-2">
                                                <p class="text-xs font-medium text-gray-600 mb-1">{{ $module['label'] }}</p>
                                                <div class="grid grid-cols-2 gap-1">
                                                    @foreach ($module['abilities'] as $ability)
                                                        @php
                                                            $perm   = $ability['name'];
                                                            $locked = in_array($perm, $lockedPerms, true);
                                                        @endphp
                                                        <label wire:key="mod-{{ $accessRole }}-{{ $perm }}"
                                                               title="{{ $ability['help'] ?? '' }}"
                                                               class="flex items-center gap-2 px-2 py-1 rounded {{ $locked ? 'bg-brand-50/60 cursor-default' : 'hover:bg-gray-50 cursor-pointer' }}">
                                                            <input type="checkbox" @disabled($locked) @checked($locked)
                                                                   @if (! $locked) wire:model="moduleAccess" value="{{ $perm }}" @endif
                                                                   class="rounded border-gray-300 {{ $locked ? 'text-brand-400' : 'text-brand-600 focus:ring-brand-500' }}" />
                                                            <span class="text-sm text-gray-700">{{ $ability['label'] }}</span>
                                                            @if ($locked)
                                                                <span class="ml-auto text-[9px] uppercase tracking-wider text-brand-400 font-semibold">role</span>
                                                            @endif
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Outlet Access --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-2">Outlet Access</label>
                    <div class="space-y-1 mb-2">
                        <label class="flex items-center gap-2 px-2 py-1.5 rounded cursor-pointer {{ $outletMode === 'all' ? 'bg-brand-50' : 'hover:bg-gray-50' }}">
                            <input type="radio" wire:model.live="outletMode" value="all" class="text-brand-600 focus:ring-brand-500" />
                            <span class="text-sm font-medium {{ $outletMode === 'all' ? 'text-brand-700' : 'text-gray-700' }}">All Outlets</span>
                        </label>
                        <label class="flex items-center gap-2 px-2 py-1.5 rounded cursor-pointer {{ $outletMode === 'all_except' ? 'bg-warning-50' : 'hover:bg-gray-50' }}">
                            <input type="radio" wire:model.live="outletMode" value="all_except" class="text-warning-600 focus:ring-warning-500" />
                            <span class="text-sm font-medium {{ $outletMode === 'all_except' ? 'text-warning-700' : 'text-gray-700' }}">All Outlets except:</span>
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
                                           class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                    <span class="text-sm text-gray-700">{{ $o->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="text-[11px] text-gray-600 mt-1">
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
                        <label class="flex items-center gap-2 px-2 py-1.5 rounded cursor-pointer {{ $kitchenMode === 'all_except' ? 'bg-warning-50' : 'hover:bg-gray-50' }}">
                            <input type="radio" wire:model.live="kitchenMode" value="all_except" class="text-warning-600 focus:ring-warning-500" />
                            <span class="text-sm font-medium {{ $kitchenMode === 'all_except' ? 'text-warning-700' : 'text-gray-700' }}">All Central Kitchens except:</span>
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
                        <p class="text-[11px] text-gray-600 mt-1">
                            {{ $kitchenMode === 'all_except' ? 'Check kitchens to EXCLUDE' : 'Check kitchens to INCLUDE' }}
                        </p>
                    @endif
                </div>
                @endif

                {{-- Outlet scope. The other six capability checkboxes that used to live
                     here — manage users, approve POs, approve PRs, delete records, receive
                     GRN, manage invoices — became permissions in Phase 1 and are ticked in
                     Module Access above. "Delete records" in particular was one switch
                     covering Sales, Purchasing, Inventory, Clock-In and Overtime Claims;
                     it is now five separate abilities. This one is not a capability: it
                     says WHERE the abilities apply, which is why it sits with the outlets. --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-2">Outlet Scope</label>
                    <div class="border border-gray-200 rounded-lg p-3">
                        <label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" wire:model="can_view_all_outlets" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                            <span class="text-sm text-gray-700">View all outlets</span>
                        </label>
                        <p class="help mt-1 px-2">Applies to every outlet in this company, including ones added later.</p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button wire:click="closeModal" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</button>
                <button wire:click="save" class="btn-primary">
                    {{ $editingId ? 'Update' : 'Create' }}
                </button>
            </div>
        </div>
    </div>
    @endteleport
    @endif
</div>
