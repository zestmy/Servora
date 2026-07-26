<div>
    <h1 class="text-lg font-bold text-gray-800 mb-1">All Users</h1>
    <p class="text-xs text-gray-400 mb-6">Every account across all companies — memberships, roles and activity.</p>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-500 font-medium">Total Users</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($totalUsers) }}</p>
        </div>
        <button wire:click="$set('typeFilter', '{{ $typeFilter === 'multi' ? '' : 'multi' }}')"
                class="text-left bg-white rounded-xl shadow-sm border p-4 transition {{ $typeFilter === 'multi' ? 'border-indigo-400 ring-1 ring-indigo-300' : 'border-gray-100 hover:border-indigo-200' }}">
            <p class="text-xs text-gray-500 font-medium">Multi-Company</p>
            <p class="text-2xl font-bold text-indigo-600 mt-1">{{ number_format($multiCompany) }}</p>
        </button>
        <button wire:click="$set('typeFilter', '{{ $typeFilter === 'system' ? '' : 'system' }}')"
                class="text-left bg-white rounded-xl shadow-sm border p-4 transition {{ $typeFilter === 'system' ? 'border-purple-400 ring-1 ring-purple-300' : 'border-gray-100 hover:border-purple-200' }}">
            <p class="text-xs text-gray-500 font-medium">System Admins</p>
            <p class="text-2xl font-bold text-purple-600 mt-1">{{ number_format($systemAdmins) }}</p>
        </button>
        <button wire:click="$set('typeFilter', '{{ $typeFilter === 'unverified' ? '' : 'unverified' }}')"
                class="text-left bg-white rounded-xl shadow-sm border p-4 transition {{ $typeFilter === 'unverified' ? 'border-amber-400 ring-1 ring-amber-300' : 'border-gray-100 hover:border-amber-200' }}">
            <p class="text-xs text-gray-500 font-medium">Unverified</p>
            <p class="text-2xl font-bold {{ $unverified > 0 ? 'text-amber-600' : 'text-gray-300' }} mt-1">{{ number_format($unverified) }}</p>
        </button>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
        <div class="flex-1">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search name, email, designation…"
                   class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <select wire:model.live="companyFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="">All Companies</option>
            @foreach ($companies as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
            @endforeach
        </select>
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
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">User</th>
                        <th class="px-4 py-3 text-left">Companies</th>
                        <th class="px-4 py-3 text-left">Roles</th>
                        <th class="px-4 py-3 text-center">Verified</th>
                        <th class="px-4 py-3 text-left">Last Active</th>
                        <th class="px-4 py-3 text-left">Joined</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($users as $u)
                        <tr wire:key="au-{{ $u->id }}" class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800">{{ $u->name }}</p>
                                <p class="text-xs text-gray-400">{{ $u->email }}</p>
                                @if ($u->designation)
                                    <p class="text-[11px] text-gray-400">{{ $u->designation }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1 max-w-xs">
                                    @forelse ($u->companies as $company)
                                        <span wire:key="au-{{ $u->id }}-c-{{ $company->id }}"
                                              title="{{ $company->id === $u->company_id ? 'Active company' : 'Member' }}"
                                              class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium whitespace-nowrap
                                                     {{ $company->id === $u->company_id ? 'bg-indigo-100 text-indigo-700 ring-1 ring-indigo-300' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $company->name }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-gray-300">No membership</span>
                                    @endforelse
                                    @if ($u->companies_count > 1)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-600 text-white">×{{ $u->companies_count }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1 max-w-[180px]">
                                    @forelse ($rolesByUser[$u->id] ?? [] as $role)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium whitespace-nowrap
                                                     {{ in_array($role, ['Super Admin', 'System Admin'], true) ? 'bg-purple-100 text-purple-700' : 'bg-slate-100 text-slate-600' }}">
                                            {{ $role }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-gray-300">—</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($u->email_verified_at)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-green-100 text-green-700">Yes</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-100 text-amber-700">No</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                {{ isset($lastActive[$u->id]) ? $lastActive[$u->id]->diffForHumans() : 'Never' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ $u->created_at?->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">No users match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
</div>
