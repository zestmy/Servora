<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 bg-danger-50 border border-danger-200 text-danger-700 text-sm rounded-lg">{{ session('error') }}</div>
    @endif

    {{-- Wraps, or the two buttons run off the side of a phone: the row could
         not break, so "+ Add User" sat 11px past the right edge and the whole
         page became draggable sideways rather than visibly breaking. --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex min-w-0 items-center gap-4">
            <a data-back href="{{ route('settings.index') }}" class="flex-shrink-0 text-gray-600 hover:text-gray-900 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div class="min-w-0">
                <p class="page-eyebrow">Settings</p>
                <h2 class="page-title mt-1">Roles &amp; Access</h2>
            </div>
        </div>
        @unless ($showModal)
            <div class="flex flex-wrap items-center justify-end gap-2">
                <button wire:click="toggleAllAccessTags" class="btn-secondary btn-sm">
                    {{ $accessTagsOpen ? 'Hide access tags' : 'Show access tags' }}
                </button>
                <button wire:click="openCreate" class="btn-primary">+ Add User</button>
            </div>
        @endunless
    </div>

    {{-- The Role Guide modal that used to live here is now the Roles tab: it listed a
         role's modules as an unstructured wall of badges, which stopped being readable
         at 41 abilities. --}}
    @unless ($showModal)
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
                    @php
                        $showTags = $accessTagsOpen || ($expandedAccess[$u->id] ?? false);
                        // Only resolved for rows actually showing them: getAllPermissions()
                        // is a query per row.
                        $heldTags = $showTags
                            ? collect($u->getAllPermissions()->pluck('name'))
                                ->filter(fn ($p) => isset($modules[$p]))->values()
                            : collect();
                    @endphp
                    <tr wire:key="su-{{ $u->id }}" class="hover:bg-gray-50">
                        <td class="px-5 py-3 font-medium text-gray-800">{{ $u->name }}</td>
                        <td class="px-5 py-3 text-gray-500 text-xs">{{ $u->email }}</td>
                        @if ($isSuperAdmin)
                            <td class="px-5 py-3 text-xs text-gray-600">{{ $u->company?->name ?? '—' }}</td>
                        @endif
                        <td class="px-5 py-3">
                            {{-- The role model, not its name: $roleDisplayMap is keyed by id
                                 because a company's own role may share a preset's name. --}}
                            @php $rowRole = $u->roles->first(); @endphp
                            @if ($rowRole)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium whitespace-nowrap
                                             {{ in_array($rowRole->name, ['Super Admin', 'System Admin'], true) ? 'bg-purple-100 text-purple-700' : 'bg-brand-100 text-brand-700' }}">
                                    {{ $roleDisplayMap[$rowRole->id] ?? $rowRole->display_name ?: $rowRole->name }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-500">Custom</span>
                            @endif
                            @if ($u->designation)
                                <p class="text-[11px] text-gray-600 mt-0.5">{{ $u->designation }}</p>
                            @endif
                        </td>
                        {{-- Just the switch; the abilities open as a full-width row below.
                             At 81 of them a column is the wrong container entirely. --}}
                        <td class="px-5 py-3">
                            <button wire:click="toggleAccess({{ $u->id }})"
                                    class="inline-flex items-center gap-1 text-[11px] font-medium whitespace-nowrap
                                           {{ $showTags ? 'text-gray-500 hover:text-gray-700' : 'text-brand-600 hover:text-brand-700' }}">
                                <span class="transition-transform {{ $showTags ? 'rotate-90' : '' }}">&rsaquo;</span>
                                {{ $showTags ? 'Hide' : 'Show' }} access
                            </button>
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
                                <button wire:click="delete({{ $u->id }})" data-confirm-delete="Delete {{ $u->name }}?" class="text-danger-500 hover:text-danger-700 text-xs">Delete</button>
                            @endif
                        </td>
                    </tr>
                    @if ($showTags)
                        <tr wire:key="su-tags-{{ $u->id }}" class="bg-brand-50/40">
                            <td colspan="{{ $isSuperAdmin ? 9 : 8 }}" class="px-5 pb-3 pt-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold mr-1">Access</span>
                                    @forelse ($heldTags as $perm)
                                        <span class="px-1.5 py-0.5 bg-brand-50 text-brand-600 text-[10px] rounded font-medium">{{ $modules[$perm] }}</span>
                                    @empty
                                        <span class="text-xs text-gray-500">No modules</span>
                                    @endforelse
                                </div>
                            </td>
                        </tr>
                    @endif
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

    @endunless

    {{-- Editor --}}
    {{-- Editor. A PAGE, not a modal: the form carries basic details, a role, an
         81-ability grid, outlets and kitchens — more than a dialog can show without
         its own scrollbar, which is what it had. Driven by ?edit= so it is
         addressable, survives a refresh and answers the browser's back button. --}}
    @if ($showModal)
        <div class="card p-5 sm:p-6">
            <div class="flex items-center justify-between gap-3 mb-5 pb-4 border-b border-gray-100">
                <div>
                    <p class="page-eyebrow">{{ $editingId ? 'Editing access' : 'New user' }}</p>
                    <h3 class="text-lg font-semibold text-gray-800 mt-0.5">{{ $editingId ? ($name ?: 'User') : 'Add a user' }}</h3>
                </div>
                <button wire:click="closeModal" class="btn-secondary btn-sm">&larr; Back to list</button>
            </div>


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
                    @php $selectedRole = $assignableRoles->firstWhere('id', (int) $accessRole); @endphp
                    {{-- Values are role IDs, not names: a company may have its own role
                         sharing a preset's name, so the name is a label here. --}}
                    <select wire:model.live="accessRole" class="w-full rounded-lg border-gray-300 text-sm">
                        @foreach ($assignableRoles->where('is_preset', true) as $role)
                            <option value="{{ $role->id }}">{{ $role->label }}</option>
                        @endforeach
                        @if ($assignableRoles->where('is_preset', false)->isNotEmpty())
                            <optgroup label="This company's own roles">
                                @foreach ($assignableRoles->where('is_preset', false) as $role)
                                    <option value="{{ $role->id }}">{{ $role->label }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                        <option value="custom">Custom — pick abilities manually</option>
                    </select>
                    <p class="text-[11px] text-gray-600 mt-1">
                        @if ($selectedRole)
                            {{ $selectedRole->description }}
                            <span class="text-gray-500">·</span>
                            Its abilities are ticked below — add more on top, or untick one to take it
                            away from this person only.
                        @else
                            No role attached — this user gets exactly the abilities ticked below.
                        @endif
                    </p>
                </div>

                {{-- Module Access --}}
                @php
                    $lockedPerms = $selectedRole ? ($rolePermMap[$selectedRole->id] ?? []) : [];
                    $grantable   = array_keys($modules);
                    $fromRole    = array_values(array_intersect($lockedPerms, $grantable));
                    $ticked      = array_intersect($moduleAccess, $grantable);
                    $addedOnTop  = array_values(array_diff($ticked, $fromRole));
                    $removed     = array_values(array_diff($fromRole, $ticked));
                @endphp
                {{-- Picking a role is the whole job for most people, so the 41-checkbox grid
                     starts collapsed behind a one-line summary of how this person differs
                     from their role. It opens automatically when there is no role to fall
                     back on, or when someone has already been fine-tuned.

                     The grid shows EFFECTIVE access, so a role's abilities are ticked here
                     like any other — and unticking one is how you say "this role, but not
                     that". Before Phase 3 they were rendered disabled, which is why removing
                     one bit of a role used to mean inventing a whole new role.

                     Counts are recomputed in Alpine from the checkboxes, because wire:model
                     here is deferred and a round-trip per tick would make the grid crawl.
                     Role-derived boxes carry data-role so the two tallies stay separable. --}}
                <div x-data="{
                        open: @js($accessRole === 'custom' || count($addedOnTop) > 0 || count($removed) > 0),
                        added: @js(count($addedOnTop)),
                        removed: @js(count($removed)),
                        recount() {
                            const g = this.$refs.grid;
                            if (! g) return;
                            this.added   = g.querySelectorAll('input[type=checkbox]:checked:not([data-role])').length;
                            this.removed = g.querySelectorAll('input[type=checkbox][data-role]:not(:checked)').length;
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
                        @if ($selectedRole)
                            <p class="text-sm text-gray-800">
                                <span class="font-medium">{{ $selectedRole->label }}</span>
                                <span class="text-gray-600">— {{ count($fromRole) }} {{ \Illuminate\Support\Str::plural('ability', count($fromRole)) }} from this role</span>
                            </p>
                        @else
                            <p class="text-sm text-gray-800"><span class="font-medium">Custom</span> <span class="text-gray-600">— no role attached</span></p>
                        @endif
                        <p class="help mt-0.5">
                            <span x-text="added"></span> added<template x-if="removed > 0"><span>, <span class="text-danger-600 font-medium" x-text="removed"></span> removed from the role</span></template>.
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
                                                $fromRole = in_array($perm, $lockedPerms, true);
                                            @endphp
                                            <label wire:key="mod-{{ $accessRole }}-{{ $perm }}"
                                                   title="{{ $ability['help'] ?? '' }}"
                                                   class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 cursor-pointer {{ $fromRole ? 'bg-brand-50/60' : '' }}">
                                                <input type="checkbox" wire:model="moduleAccess" value="{{ $perm }}"
                                                       @if ($fromRole) data-role="1" @endif
                                                       class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                                <span class="text-sm text-gray-700">{{ $module['label'] }}</span>
                                                @if ($fromRole)
                                                    <span class="ml-auto text-[9px] uppercase tracking-wider text-brand-500 font-semibold">role</span>
                                                @endif
                                            </label>
                                        @else
                                            <div wire:key="modgrp-{{ $accessRole }}-{{ $moduleKey }}" class="px-2">
                                                <p class="text-xs font-medium text-gray-600 mb-1">{{ $module['label'] }}</p>
                                                <div class="grid grid-cols-2 gap-1">
                                                    @foreach ($module['abilities'] as $ability)
                                                        @php
                                                            $perm     = $ability['name'];
                                                            $fromRole = in_array($perm, $lockedPerms, true);
                                                        @endphp
                                                        <label wire:key="mod-{{ $accessRole }}-{{ $perm }}"
                                                               title="{{ $ability['help'] ?? '' }}"
                                                               class="flex items-center gap-2 px-2 py-1 rounded hover:bg-gray-50 cursor-pointer {{ $fromRole ? 'bg-brand-50/60' : '' }}">
                                                            <input type="checkbox" wire:model="moduleAccess" value="{{ $perm }}"
                                                                   @if ($fromRole) data-role="1" @endif
                                                                   class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                                            <span class="text-sm text-gray-700">{{ $ability['label'] }}</span>
                                                            @if ($fromRole)
                                                                <span class="ml-auto text-[9px] uppercase tracking-wider text-brand-500 font-semibold">role</span>
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
    @endif
</div>
