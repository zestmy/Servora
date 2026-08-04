<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="page-eyebrow">HR / Training Portal</p>
                <h1 class="page-title mt-1">Training Portal</h1>
        </div>
        <div class="flex gap-2">
            @if ($sopCategories->count())
                <div class="relative" x-data="{ open: false }">
                    <button type="button" @click="open = !open" @click.outside="open = false"
                            class="btn-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Export by Category
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div x-show="open" x-transition style="display:none"
                         class="absolute right-0 mt-1 w-64 max-h-80 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg z-20 py-1">
                        @if ($sopCategoryGroups->count())
                            <p class="px-4 py-2 text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-100">Top-tier</p>
                            @foreach ($sopCategoryGroups as $group)
                                <x-download-link href="{{ route('training.sop.pdf-all', ['category_group' => $group['id']]) }}"
                                   class="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-brand-50 hover:text-brand-700 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 flex-shrink-0 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    All {{ $group['name'] }}
                                </x-download-link>
                            @endforeach
                        @endif
                        @if ($hasPrepSops)
                            <x-download-link href="{{ route('training.sop.pdf-all', ['prep' => 1]) }}"
                               class="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-warning-50 hover:text-warning-700 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 flex-shrink-0 text-warning-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                All Prep Items
                            </x-download-link>
                        @endif
                        <p class="px-4 py-2 text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-100">Single category</p>
                        @foreach ($sopCategories as $cat)
                            <x-download-link href="{{ route('training.sop.pdf-all', ['category' => $cat]) }}"
                               class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-brand-50 hover:text-brand-700 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 flex-shrink-0 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                {{ $cat }}
                            </x-download-link>
                        @endforeach
                        @if ($prepSopCategories->count())
                            <p class="px-4 py-2 text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-100">Prep items</p>
                            @foreach ($prepSopCategories as $prepCat)
                                <x-download-link href="{{ route('training.sop.pdf-all', ['prep_category' => $prepCat->id]) }}"
                                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-warning-50 hover:text-warning-700 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 flex-shrink-0 text-warning-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    {{ $prepCat->name }}
                                </x-download-link>
                            @endforeach
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- The whole catalogue renders too heavy for a request (~500 MB, ~100 s),
         so it is queued and collected from this panel rather than being a
         download link like the category exports above. --}}
    <div class="mb-6">
        @livewire('settings.sop-export-panel')
    </div>

    {{-- ── Public LMS URL ── --}}
    @if ($lmsUrl)
        <div class="card p-5 mb-6">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Public LMS Links</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Employee Login URL</p>
                    <div class="flex items-center gap-2">
                        <code class="flex-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-brand-700 font-mono truncate">{{ $lmsUrl }}</code>
                        <button onclick="navigator.clipboard.writeText('{{ $lmsUrl }}'); this.textContent='Copied!'; setTimeout(() => this.textContent='Copy', 1500)"
                                class="flex-shrink-0 px-3 py-2 text-xs font-medium text-brand-600 bg-brand-50 rounded-lg hover:bg-brand-100 transition">
                            Copy
                        </button>
                        <a href="{{ $lmsUrl }}" target="_blank"
                           class="flex-shrink-0 px-3 py-2 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                            Open
                        </a>
                    </div>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-1">Employee Registration URL</p>
                    <div class="flex items-center gap-2">
                        <code class="flex-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-brand-700 font-mono truncate">{{ $lmsRegisterUrl }}</code>
                        <button onclick="navigator.clipboard.writeText('{{ $lmsRegisterUrl }}'); this.textContent='Copied!'; setTimeout(() => this.textContent='Copy', 1500)"
                                class="flex-shrink-0 px-3 py-2 text-xs font-medium text-brand-600 bg-brand-50 rounded-lg hover:bg-brand-100 transition">
                            Copy
                        </button>
                        <a href="{{ $lmsRegisterUrl }}" target="_blank"
                           class="flex-shrink-0 px-3 py-2 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                            Open
                        </a>
                    </div>
                </div>
            </div>
            @if ($company?->brand_name)
                <p class="text-xs text-gray-600 mt-3">Portal branding: <span class="font-medium text-gray-600">{{ $company->brand_name }}</span> — change in <a href="{{ route('settings.company-details') }}" class="text-brand-500 hover:underline">Company Details</a></p>
            @else
                <p class="text-xs text-gray-600 mt-3">No brand name set — portal will show "{{ $company?->name }}". Set a brand name in <a href="{{ route('settings.company-details') }}" class="text-brand-500 hover:underline">Company Details</a></p>
            @endif
        </div>
    @endif

    {{-- ── Stats Cards ── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <div class="card p-4">
            <p class="text-xs text-gray-600 uppercase tracking-wider">Available SOPs</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $totalSops }}</p>
            <p class="text-xs text-gray-600 mt-0.5">of {{ $totalRecipes }} recipes</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-600 uppercase tracking-wider">With Video</p>
            <p class="text-2xl font-bold text-brand-600 mt-1">{{ $recipesWithVideo }}</p>
            <p class="text-xs text-gray-600 mt-0.5">training videos</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-600 uppercase tracking-wider">Total Users</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $totalLmsUsers }}</p>
            <p class="text-xs text-gray-600 mt-0.5">LMS accounts</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-warning-500 uppercase tracking-wider">Pending</p>
            <p class="text-2xl font-bold text-warning-600 mt-1">{{ $pendingCount }}</p>
            <p class="text-xs text-gray-600 mt-0.5">awaiting approval</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-success-500 uppercase tracking-wider">Approved</p>
            <p class="text-2xl font-bold text-success-600 mt-1">{{ $approvedCount }}</p>
            <p class="text-xs text-gray-600 mt-0.5">active learners</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-danger-500 uppercase tracking-wider">Rejected</p>
            <p class="text-2xl font-bold text-danger-600 mt-1">{{ $rejectedCount }}</p>
            <p class="text-xs text-gray-600 mt-0.5">denied</p>
        </div>
    </div>

    {{-- ── SOP Categories ── --}}
    @if ($sopCategories->count())
        <div class="card p-5 mb-6">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Export SOPs by Category</h3>
            @if ($sopCategoryGroups->count() || $hasPrepSops)
                <div class="flex flex-wrap gap-2 mb-3">
                    @foreach ($sopCategoryGroups as $group)
                        <a href="{{ route('training.sop.pdf-all', ['category_group' => $group['id']]) }}" target="_blank"
                           title="Export all SOPs under “{{ $group['name'] }}” as PDF"
                           class="inline-flex items-center gap-1.5 px-3 py-1 bg-brand-600 text-white text-xs font-semibold rounded-full hover:bg-brand-700 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            All {{ $group['name'] }}
                        </a>
                    @endforeach
                    @if ($hasPrepSops)
                        <a href="{{ route('training.sop.pdf-all', ['prep' => 1]) }}" target="_blank"
                           title="Export all prep-item SOPs as PDF"
                           class="inline-flex items-center gap-1.5 px-3 py-1 bg-warning-500 text-white text-xs font-semibold rounded-full hover:bg-warning-600 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            All Prep Items
                        </a>
                    @endif
                </div>
            @endif
            <div class="flex flex-wrap gap-2">
                @foreach ($sopCategories as $cat)
                    <a href="{{ route('training.sop.pdf-all', ['category' => $cat]) }}" target="_blank"
                       title="Export SOPs in “{{ $cat }}” as PDF"
                       class="inline-flex items-center gap-1.5 px-3 py-1 bg-brand-50 text-brand-700 text-xs font-medium rounded-full hover:bg-brand-100 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        {{ $cat }}
                    </a>
                @endforeach
            </div>
            @if ($prepSopCategories->count())
                <div class="flex flex-wrap gap-2 mt-3">
                    @foreach ($prepSopCategories as $prepCat)
                        <a href="{{ route('training.sop.pdf-all', ['prep_category' => $prepCat->id]) }}" target="_blank"
                           title="Export prep-item SOPs in “{{ $prepCat->name }}” as PDF"
                           class="inline-flex items-center gap-1.5 px-3 py-1 bg-warning-50 text-warning-700 text-xs font-medium rounded-full hover:bg-warning-100 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Prep — {{ $prepCat->name }}
                        </a>
                    @endforeach
                </div>
            @endif
            <p class="text-xs text-gray-600 mt-3">
                Click a category to export its SOPs as a PDF, or use <span class="font-medium text-gray-500">Full SOP Handbook</span> above for everything.
                Only recipes with preparation steps appear in the LMS.
                <a href="{{ route('recipes.index') }}" class="text-brand-500 hover:underline">Manage recipes</a>
            </p>
        </div>
    @else
        <div class="bg-warning-50 border border-warning-200 rounded-xl p-5 mb-6">
            <p class="text-sm text-warning-700 font-medium">No SOPs available yet</p>
            <p class="text-xs text-warning-600 mt-1">
                Add preparation steps to recipes to make them available in the Training Portal.
                <a href="{{ route('recipes.index') }}" class="underline hover:no-underline">Go to Recipes</a>
            </p>
        </div>
    @endif

    {{-- ── LMS Users Table ── --}}
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-semibold text-gray-700">LMS Users</h3>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col sm:flex-row gap-3 mb-4">
        <div class="seg">
            @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $val => $label)
                <button wire:click="$set('statusFilter', '{{ $val }}')"
                        class="seg-item {{ $statusFilter === $val ? 'seg-item-on' : '' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
        <div class="flex-1">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or email..."
                   class="w-full sm:max-w-xs rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" />
        </div>
    </div>

    {{-- Table --}}
    <div class="card overflow-hidden">
        @if ($users->count())
            <div class="overflow-x-auto">
                <table class="table-surface min-w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-left">Email</th>
                            <th class="px-4 py-3 text-left">Phone</th>
                            <th class="px-4 py-3 text-left">SOP Access</th>
                            <th class="px-4 py-3 text-left">Registered</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $user->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $user->email }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $user->phone ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">
                                    @php
                                        $accessNames = $user->outlets->pluck('name');
                                        if ($accessNames->isEmpty() && $user->outlet) {
                                            $accessNames = collect([$user->outlet->name]);
                                        }
                                    @endphp
                                    @if ($accessNames->isEmpty())
                                        <span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs font-medium rounded-full">All outlets</span>
                                    @elseif ($accessNames->count() >= $accessOutlets->count() && $accessOutlets->count() > 0)
                                        <span class="px-2 py-0.5 bg-brand-50 text-brand-600 text-xs font-medium rounded-full">All outlets ({{ $accessNames->count() }})</span>
                                    @elseif ($accessNames->count() <= 2)
                                        {{ $accessNames->implode(', ') }}
                                    @else
                                        <span title="{{ $accessNames->implode(', ') }}">{{ $accessNames->take(2)->implode(', ') }} <span class="text-xs text-gray-600">+{{ $accessNames->count() - 2 }} more</span></span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-500 text-xs">{{ $user->created_at->format('d M Y') }}</td>
                                <td class="px-4 py-3">
                                    @if ($user->status === 'pending')
                                        <span class="px-2 py-0.5 bg-warning-100 text-warning-700 text-xs font-semibold rounded-full">Pending</span>
                                    @elseif ($user->status === 'approved')
                                        <span class="px-2 py-0.5 bg-success-100 text-success-700 text-xs font-semibold rounded-full">Approved</span>
                                        @if ($user->approver)
                                            <p class="text-xs text-gray-600 mt-0.5">by {{ $user->approver->name }}</p>
                                        @endif
                                    @else
                                        <span class="px-2 py-0.5 bg-danger-100 text-danger-700 text-xs font-semibold rounded-full">Rejected</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        @if ($user->status === 'pending')
                                            <button wire:click="approve({{ $user->id }})"
                                                    wire:confirm="Approve {{ $user->name }}?"
                                                    class="px-3 py-1 text-xs font-medium text-white bg-success-600 rounded-lg hover:bg-success-700 transition">
                                                Approve
                                            </button>
                                            <button wire:click="reject({{ $user->id }})"
                                                    wire:confirm="Reject {{ $user->name }}?"
                                                    class="btn-danger btn-sm">
                                                Reject
                                            </button>
                                        @elseif ($user->status === 'rejected')
                                            <button wire:click="approve({{ $user->id }})"
                                                    class="px-3 py-1 text-xs font-medium text-brand-600 bg-brand-50 rounded-lg hover:bg-brand-100 transition">
                                                Approve
                                            </button>
                                        @endif
                                        <button wire:click="openAccess({{ $user->id }})"
                                                title="Choose which outlets' SOPs this user can see (including central kitchen)"
                                                class="px-3 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                                            Edit Access
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-gray-100">
                {{ $users->links() }}
            </div>
        @else
            <div class="px-6 py-12 text-center text-gray-600">
                <p class="font-medium">No {{ $statusFilter }} LMS users found.</p>
            </div>
        @endif
    </div>

    {{-- ── SOP Access modal ── --}}
    @if ($showAccessModal)
        @teleport('body')
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" wire:click="$set('showAccessModal', false)"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[85vh] flex flex-col">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-800">SOP Access — {{ $accessUserName }}</h3>
                    <p class="text-xs text-gray-600 mt-0.5">
                        Choose which outlets' SOPs this user can see. SOPs available at all outlets are always visible.
                        Central kitchen outlets are marked <span class="px-1 py-0.5 bg-purple-100 text-purple-700 text-[10px] font-semibold rounded">CK</span>.
                    </p>
                </div>

                <div class="px-6 py-4 overflow-y-auto flex-1">
                    <div class="flex items-center justify-end gap-3 mb-3 text-xs">
                        <button type="button" wire:click="selectAllAccessOutlets"
                                class="text-brand-500 hover:underline">Select all</button>
                        <button type="button" wire:click="$set('accessOutletIds', [])"
                                class="text-gray-600 hover:underline">Clear</button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach ($accessOutlets as $outlet)
                            <label class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition
                                {{ in_array((string) $outlet->id, array_map('strval', $accessOutletIds)) ? 'border-brand-300 bg-brand-50' : 'border-gray-200 hover:border-gray-300' }}">
                                <input type="checkbox"
                                       value="{{ $outlet->id }}"
                                       wire:model.live="accessOutletIds"
                                       class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500" />
                                <div class="min-w-0">
                                    <span class="text-sm font-medium text-gray-700 block truncate">
                                        {{ $outlet->name }}
                                        @if (in_array($outlet->id, $centralKitchenOutletIds))
                                            <span class="ml-1 px-1.5 py-0.5 bg-purple-100 text-purple-700 text-[10px] font-semibold rounded align-middle" title="Central kitchen location">CK</span>
                                        @endif
                                    </span>
                                    @if ($outlet->code)
                                        <span class="text-xs text-gray-600">{{ $outlet->code }}</span>
                                    @endif
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @if (empty($accessOutletIds))
                        <p class="mt-2 text-xs text-warning-500">Select at least one outlet — to block the user entirely, reject the account instead.</p>
                    @endif
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-end gap-3">
                    <button type="button" wire:click="$set('showAccessModal', false)"
                            class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 transition">Cancel</button>
                    <button type="button" wire:click="saveAccess"
                            wire:loading.attr="disabled" wire:target="saveAccess"
                            class="btn-primary">
                        <span wire:loading.remove wire:target="saveAccess">Save Access</span>
                        <span wire:loading wire:target="saveAccess">Saving…</span>
                    </button>
                </div>
            </div>
        </div>
        @endteleport
    @endif
</div>
