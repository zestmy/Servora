<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 bg-danger-50 border border-danger-200 text-danger-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    <h1 class="text-lg font-bold text-gray-800 mb-1">All Users</h1>
    <p class="text-xs text-gray-600 mb-6">Every account across all companies — memberships, roles and activity.</p>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="card p-4">
            <p class="text-xs text-gray-500 font-medium">Total Users</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($totalUsers) }}</p>
        </div>
        <button wire:click="$set('typeFilter', '{{ $typeFilter === 'multi' ? '' : 'multi' }}')"
                class="text-left bg-white rounded-xl shadow-sm border p-4 transition {{ $typeFilter === 'multi' ? 'border-brand-400 ring-1 ring-brand-300' : 'border-gray-100 hover:border-brand-200' }}">
            <p class="text-xs text-gray-500 font-medium">Multi-Company</p>
            <p class="text-2xl font-bold text-brand-600 mt-1">{{ number_format($multiCompany) }}</p>
        </button>
        <button wire:click="$set('typeFilter', '{{ $typeFilter === 'system' ? '' : 'system' }}')"
                class="text-left bg-white rounded-xl shadow-sm border p-4 transition {{ $typeFilter === 'system' ? 'border-purple-400 ring-1 ring-purple-300' : 'border-gray-100 hover:border-purple-200' }}">
            <p class="text-xs text-gray-500 font-medium">System Admins</p>
            <p class="text-2xl font-bold text-purple-600 mt-1">{{ number_format($systemAdmins) }}</p>
        </button>
        <button wire:click="$set('typeFilter', '{{ $typeFilter === 'unverified' ? '' : 'unverified' }}')"
                class="text-left bg-white rounded-xl shadow-sm border p-4 transition {{ $typeFilter === 'unverified' ? 'border-warning-400 ring-1 ring-warning-300' : 'border-gray-100 hover:border-warning-200' }}">
            <p class="text-xs text-gray-500 font-medium">Unverified</p>
            <p class="text-2xl font-bold {{ $unverified > 0 ? 'text-warning-600' : 'text-gray-500' }} mt-1">{{ number_format($unverified) }}</p>
        </button>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
        <div class="flex-1">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search name, email, designation…"
                   class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500" />
        </div>
        <select wire:model.live="companyFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="">All Companies</option>
            @foreach ($companies as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
            @endforeach
        </select>
        <button wire:click="toggleAllAccessTags" class="btn-secondary btn-sm whitespace-nowrap order-last sm:order-none">
            {{ $accessTagsOpen ? 'Hide access tags' : 'Show access tags' }}
        </button>
        <select wire:model.live="roleFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="">All Roles</option>
            @foreach ($roleOptions as $r)
                <option value="{{ $r }}">{{ $r }}</option>
            @endforeach
        </select>
        <select wire:model.live="typeFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="">All Types</option>
            <option value="multi">Multi-Company</option>
            <option value="system">System Admins</option>
            <option value="unverified">Unverified</option>
        </select>
    </div>

    {{-- Table --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">User</th>
                        <th class="px-4 py-3 text-left">Companies</th>
                        <th class="px-4 py-3 text-left">Roles</th>
                        <th class="px-4 py-3 text-center">Verified</th>
                        <th class="px-4 py-3 text-left">Last Active</th>
                        <th class="px-4 py-3 text-left">Joined</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $u)
                        @php
                            $roleTags = collect($rolesByUser[$u->id] ?? []);
                            $showTags = $accessTagsOpen || ($expandedAccess[$u->id] ?? false);
                        @endphp
                        <tr wire:key="au-{{ $u->id }}" class="hover:bg-gray-50 transition {{ $showTags ? 'border-b-0' : '' }}">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800">
                                    {{ $u->name }}
                                    @if ($u->suspended_at)
                                        <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-danger-100 text-danger-700 align-middle">Suspended</span>
                                    @endif
                                </p>
                                <p class="text-xs text-gray-600">{{ $u->email }}</p>
                                @if ($u->designation)
                                    <p class="text-[11px] text-gray-600">{{ $u->designation }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1 max-w-xs">
                                    @forelse ($u->companies as $company)
                                        <span wire:key="au-{{ $u->id }}-c-{{ $company->id }}"
                                              title="{{ $company->id === $u->company_id ? 'Active company' : 'Member' }}"
                                              class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium whitespace-nowrap
                                                     {{ $company->id === $u->company_id ? 'bg-brand-100 text-brand-700 ring-1 ring-brand-300' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $company->name }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-gray-500">No membership</span>
                                    @endforelse
                                    @if ($u->companies_count > 1)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-brand-600 text-white">×{{ $u->companies_count }}</span>
                                    @endif
                                </div>
                            </td>
                            {{-- Just the switch. The tags themselves open as a full-width row
                                 below: a 180px column turns a handful of badges into a ragged
                                 stack, which is worse than the wall it was meant to tidy. --}}
                            <td class="px-4 py-3">
                                @if ($roleTags->isEmpty())
                                    <span class="text-xs text-gray-500">—</span>
                                @else
                                    <button wire:click="toggleAccess({{ $u->id }})"
                                            class="inline-flex items-center gap-1 text-[11px] font-medium whitespace-nowrap
                                                   {{ $showTags ? 'text-gray-500 hover:text-gray-700' : 'text-brand-600 hover:text-brand-700' }}">
                                        <span class="transition-transform {{ $showTags ? 'rotate-90' : '' }}">&rsaquo;</span>
                                        {{ $showTags ? 'Hide' : 'Show' }} access
                                        <span class="text-gray-500">({{ $roleTags->count() }})</span>
                                    </button>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($u->email_verified_at)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-success-100 text-success-700">Yes</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-warning-100 text-warning-700">No</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                {{ isset($lastActive[$u->id]) ? $lastActive[$u->id]->diffForHumans() : 'Never' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ $u->created_at?->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-center">
                                @php
                                    $isSystemAccount = collect($rolesByUser[$u->id] ?? [])->pluck('name')->intersect(['Super Admin', 'System Admin'])->isNotEmpty();
                                @endphp
                                <div class="flex items-center justify-center gap-1.5 whitespace-nowrap">
                                    <button wire:click="openEdit({{ $u->id }})"
                                            title="Edit account (name, email, password, verification)"
                                            class="btn-secondary btn-sm">
                                        Edit
                                    </button>
                                    @if (! $isSystemAccount && $u->id !== auth()->id())
                                        <button wire:click="impersonate({{ $u->id }})"
                                                wire:confirm="Log in as {{ $u->name }}? You will see the app exactly as they do until you click 'Return to admin'."
                                                title="Log in as this user"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-brand-600 border border-brand-200 rounded-lg hover:bg-brand-50 transition">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                            </svg>
                                            Login as
                                        </button>
                                        <button wire:click="toggleSuspend({{ $u->id }})"
                                                wire:confirm="{{ $u->suspended_at ? 'Reinstate ' . $u->name . '? They will be able to log in again.' : 'Suspend ' . $u->name . '? They will be logged out immediately and blocked from logging in.' }}"
                                                title="{{ $u->suspended_at ? 'Reinstate account' : 'Suspend account (blocks login)' }}"
                                                class="px-2.5 py-1 text-xs font-medium rounded-lg border transition {{ $u->suspended_at ? 'text-success-600 border-success-200 hover:bg-success-50' : 'text-warning-600 border-warning-200 hover:bg-warning-50' }}">
                                            {{ $u->suspended_at ? 'Reinstate' : 'Suspend' }}
                                        </button>
                                        <button wire:click="deleteUser({{ $u->id }})"
                                                wire:confirm="Delete {{ $u->name }} ({{ $u->email }}) PERMANENTLY? This removes their account and access to ALL companies and cannot be undone."
                                                title="Delete account across all companies"
                                                class="px-2.5 py-1 text-xs font-medium text-danger-500 border border-danger-200 rounded-lg hover:bg-danger-50 transition">
                                            Delete
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        {{-- Tags open here, across the whole table, so they read as a line
                             rather than a stack squeezed into one column. --}}
                        @if ($showTags)
                            <tr wire:key="au-tags-{{ $u->id }}" class="bg-brand-50/40">
                                <td colspan="7" class="px-4 pb-3 pt-0">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold mr-1">Roles</span>
                                        @foreach ($roleTags as $role)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium whitespace-nowrap
                                                         {{ in_array($role->name, ['Super Admin', 'System Admin'], true) ? 'bg-purple-100 text-purple-700' : 'bg-slate-100 text-slate-600' }}">
                                                {{ $role->label }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-gray-600">No users match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>

    {{-- Account edit modal --}}
    @if ($showEdit)
        @teleport('body')
        <div class="fixed inset-0 z-[100] flex items-start justify-center overflow-y-auto p-4">
            <div class="absolute inset-0 bg-gray-900/50" wire:click="closeEdit"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md z-10 p-6 mt-16">
                <h3 class="text-sm font-semibold text-gray-800 mb-4">Edit Account</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Name *</label>
                        <input type="text" wire:model="e_name" class="w-full rounded-lg border-gray-300 text-sm" />
                        @error('e_name') <p class="text-xs text-danger-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Email *</label>
                        <input type="email" wire:model="e_email" class="w-full rounded-lg border-gray-300 text-sm" />
                        @error('e_email') <p class="text-xs text-danger-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Designation</label>
                            <input type="text" wire:model="e_designation" class="w-full rounded-lg border-gray-300 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">New Password <span class="text-gray-600">(blank = keep)</span></label>
                            <input type="password" wire:model="e_password" class="w-full rounded-lg border-gray-300 text-sm" />
                            @error('e_password') <p class="text-xs text-danger-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" wire:model="e_verified" class="rounded border-gray-300 text-brand-600" />
                        Email verified
                    </label>
                    {{-- Role per company. Roles are per-company (Spatie teams mode), so an
                         account in two companies genuinely has two — a single dropdown here
                         would be a lie for anyone with more than one membership. --}}
                    @if (count($e_companies))
                        <div class="pt-3 border-t border-gray-100">
                            <p class="text-xs font-medium text-gray-500 mb-2">Role per company</p>
                            <div class="space-y-2">
                                @foreach ($e_companies as $company)
                                    <div wire:key="erole-{{ $company['id'] }}" class="space-y-1">
                                        <div class="flex items-center gap-3">
                                            <span class="text-sm text-gray-700 flex-1 min-w-0 truncate" title="{{ $company['name'] }}">{{ $company['name'] }}</span>
                                            <select wire:model="e_roles.{{ $company['id'] }}"
                                                    class="rounded-lg border-gray-300 text-sm w-48 shrink-0">
                                                <option value="">No role — custom access</option>
                                                @foreach ($e_roleOptions[$company['id']] ?? [] as $roleId => $label)
                                                    <option value="{{ $roleId }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        {{-- Abilities pinned to this person outlive the role they replace, which is
                                             why changing a role can otherwise look like it did nothing. --}}
                                        @if (($e_extras[$company['id']] ?? 0) > 0)
                                            <label class="flex items-start gap-2 pl-1">
                                                <input type="checkbox" wire:model="e_resetToRole.{{ $company['id'] }}"
                                                       class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                                <span class="text-[11px] text-gray-600">
                                                    Replace their access with the role's —
                                                    clears <span class="font-medium text-danger-600">{{ $e_extras[$company['id']] }}</span>
                                                    {{ \Illuminate\Support\Str::plural('ability', $e_extras[$company['id']]) }}
                                                    granted to them individually. Leave unticked to keep those on top of the new role.
                                                </span>
                                            </label>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            <p class="text-[11px] text-gray-600 mt-2">
                                Changing a role rewrites what this account can do in that company, immediately.
                                Abilities granted or removed for this person specifically are left alone — adjust those
                                in that company's <span class="font-medium">Settings &rsaquo; Roles &amp; Access</span>,
                                where the change is visible next to the role it modifies.
                            </p>
                        </div>
                    @endif
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button wire:click="closeEdit" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</button>
                    <button wire:click="saveEdit" class="btn-primary">Save</button>
                </div>
            </div>
        </div>
        @endteleport
    @endif
</div>
