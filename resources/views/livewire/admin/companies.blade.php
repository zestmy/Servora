<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 bg-danger-50 border border-danger-200 text-danger-700 text-sm rounded-lg">{{ session('error') }}</div>
    @endif

    <h1 class="text-lg font-bold text-gray-800 mb-1">Companies</h1>
    <p class="text-xs text-gray-600 mb-6">Suspend, reactivate or permanently delete companies.</p>

    <div class="grid grid-cols-2 gap-4 mb-6 max-w-md">
        <div class="card p-4">
            <p class="text-xs text-gray-500 font-medium">Active</p>
            <p class="text-2xl font-bold text-success-600 mt-1">{{ $totalActive }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 font-medium">Suspended</p>
            <p class="text-2xl font-bold {{ $totalSuspended > 0 ? 'text-danger-600' : 'text-gray-500' }} mt-1">{{ $totalSuspended }}</p>
        </div>
    </div>

    <div class="flex items-center gap-3 mb-4">
        <div class="flex-1">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search company…"
                   class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500" />
        </div>
        <select wire:model.live="statusFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="">All Statuses</option>
            <option value="active">Active</option>
            <option value="suspended">Suspended</option>
        </select>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Company</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-left">Subscription</th>
                        <th class="px-4 py-3 text-center">Users</th>
                        <th class="px-4 py-3 text-center">Outlets</th>
                        <th class="px-4 py-3 text-left">Created</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($companies as $company)
                        @php $isProtected = in_array($company->id, $protectedIds, true); @endphp
                        <tr wire:key="co-{{ $company->id }}" class="hover:bg-gray-50 transition {{ ! $company->is_active ? 'bg-danger-50/40' : '' }}">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800">
                                    {{ $company->name }}
                                    @if ($isProtected)
                                        <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-purple-100 text-purple-700 align-middle" title="Contains platform admin members — cannot be suspended or deleted">Platform</span>
                                    @endif
                                </p>
                                <p class="text-xs text-gray-600">{{ $company->slug }}</p>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($company->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-success-100 text-success-700">Active</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-danger-100 text-danger-700">Suspended</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($company->subscription)
                                    <span class="text-xs text-gray-600">{{ $company->subscription->plan?->name ?? '—' }}</span>
                                    <span class="text-[11px] text-gray-600">· {{ $company->subscription->statusLabel() }}</span>
                                @else
                                    <span class="text-xs text-gray-500">None</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ $company->users_count }}</td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ $company->outlets_count }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ $company->created_at?->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-center">
                                @if ($isProtected)
                                    <span class="text-xs text-gray-500">—</span>
                                @else
                                    <div class="flex items-center justify-center gap-1.5 whitespace-nowrap">
                                        <button wire:click="toggleSuspend({{ $company->id }})"
                                                wire:confirm="{{ $company->is_active
                                                    ? 'Suspend ' . $company->name . '? Every member will be logged out and blocked until the company is reactivated.'
                                                    : 'Reactivate ' . $company->name . '? Members will be able to log in again.' }}"
                                                class="px-2.5 py-1 text-xs font-medium rounded-lg border transition {{ $company->is_active ? 'text-warning-600 border-warning-200 hover:bg-warning-50' : 'text-success-600 border-success-200 hover:bg-success-50' }}">
                                            {{ $company->is_active ? 'Suspend' : 'Reactivate' }}
                                        </button>
                                        <button wire:click="deleteCompany({{ $company->id }})"
                                                wire:confirm="DELETE {{ $company->name }}? The company and all its data disappear from the app immediately. Members whose only company this is lose their account; multi-company users keep their other companies. Only the platform team can restore it."
                                                class="px-2.5 py-1 text-xs font-medium text-danger-500 border border-danger-200 rounded-lg hover:bg-danger-50 transition">
                                            Delete
                                        </button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-gray-600">No companies match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $companies->links() }}
    </div>
</div>
