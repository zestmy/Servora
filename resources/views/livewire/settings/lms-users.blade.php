<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="text-xs text-gray-400">HR / Training Portal</p>
            <h2 class="text-lg font-semibold text-gray-700 mt-1">Training Portal</h2>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('training.sop.pdf-all') }}" target="_blank"
               class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Export All SOPs
            </a>
        </div>
    </div>

    {{-- ── Public LMS URL ── --}}
    @if ($lmsUrl)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Public LMS Links</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Employee Login URL</p>
                    <div class="flex items-center gap-2">
                        <code class="flex-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-indigo-700 font-mono truncate">{{ $lmsUrl }}</code>
                        <button onclick="navigator.clipboard.writeText('{{ $lmsUrl }}'); this.textContent='Copied!'; setTimeout(() => this.textContent='Copy', 1500)"
                                class="flex-shrink-0 px-3 py-2 text-xs font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">
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
                        <code class="flex-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-indigo-700 font-mono truncate">{{ $lmsRegisterUrl }}</code>
                        <button onclick="navigator.clipboard.writeText('{{ $lmsRegisterUrl }}'); this.textContent='Copied!'; setTimeout(() => this.textContent='Copy', 1500)"
                                class="flex-shrink-0 px-3 py-2 text-xs font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">
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
                <p class="text-xs text-gray-400 mt-3">Portal branding: <span class="font-medium text-gray-600">{{ $company->brand_name }}</span> — change in <a href="{{ route('settings.company-details') }}" class="text-indigo-500 hover:underline">Company Details</a></p>
            @else
                <p class="text-xs text-gray-400 mt-3">No brand name set — portal will show "{{ $company?->name }}". Set a brand name in <a href="{{ route('settings.company-details') }}" class="text-indigo-500 hover:underline">Company Details</a></p>
            @endif
        </div>
    @endif

    {{-- ── Stats Cards ── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Available SOPs</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $totalSops }}</p>
            <p class="text-xs text-gray-400 mt-0.5">of {{ $totalRecipes }} recipes</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">With Video</p>
            <p class="text-2xl font-bold text-indigo-600 mt-1">{{ $recipesWithVideo }}</p>
            <p class="text-xs text-gray-400 mt-0.5">training videos</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Total Users</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $totalLmsUsers }}</p>
            <p class="text-xs text-gray-400 mt-0.5">LMS accounts</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-amber-500 uppercase tracking-wider">Pending</p>
            <p class="text-2xl font-bold text-amber-600 mt-1">{{ $pendingCount }}</p>
            <p class="text-xs text-gray-400 mt-0.5">awaiting approval</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-green-500 uppercase tracking-wider">Approved</p>
            <p class="text-2xl font-bold text-green-600 mt-1">{{ $approvedCount }}</p>
            <p class="text-xs text-gray-400 mt-0.5">active learners</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-red-500 uppercase tracking-wider">Rejected</p>
            <p class="text-2xl font-bold text-red-600 mt-1">{{ $rejectedCount }}</p>
            <p class="text-xs text-gray-400 mt-0.5">denied</p>
        </div>
    </div>

    {{-- ── SOP Categories ── --}}
    @if ($sopCategories->count())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">SOP Categories</h3>
            <div class="flex flex-wrap gap-2">
                @foreach ($sopCategories as $cat)
                    <span class="px-3 py-1 bg-indigo-50 text-indigo-700 text-xs font-medium rounded-full">{{ $cat }}</span>
                @endforeach
            </div>
            <p class="text-xs text-gray-400 mt-3">
                Only recipes with preparation steps appear in the LMS.
                <a href="{{ route('recipes.index') }}" class="text-indigo-500 hover:underline">Manage recipes</a>
            </p>
        </div>
    @else
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 mb-6">
            <p class="text-sm text-amber-700 font-medium">No SOPs available yet</p>
            <p class="text-xs text-amber-600 mt-1">
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
        <div class="flex rounded-lg overflow-hidden border border-gray-200 bg-white text-sm">
            @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $val => $label)
                <button wire:click="$set('statusFilter', '{{ $val }}')"
                        class="px-4 py-2 font-medium transition {{ $statusFilter === $val ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
        <div class="flex-1">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or email..."
                   class="w-full sm:max-w-xs rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        @if ($users->count())
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                        <tr>
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-left">Email</th>
                            <th class="px-4 py-3 text-left">Phone</th>
                            <th class="px-4 py-3 text-left">Outlet</th>
                            <th class="px-4 py-3 text-left">Registered</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($users as $user)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $user->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $user->email }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $user->phone ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $user->outlet?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 text-xs">{{ $user->created_at->format('d M Y') }}</td>
                                <td class="px-4 py-3">
                                    @if ($user->status === 'pending')
                                        <span class="px-2 py-0.5 bg-amber-100 text-amber-700 text-xs font-semibold rounded-full">Pending</span>
                                    @elseif ($user->status === 'approved')
                                        <span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs font-semibold rounded-full">Approved</span>
                                        @if ($user->approver)
                                            <p class="text-xs text-gray-400 mt-0.5">by {{ $user->approver->name }}</p>
                                        @endif
                                    @else
                                        <span class="px-2 py-0.5 bg-red-100 text-red-700 text-xs font-semibold rounded-full">Rejected</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if ($user->status === 'pending')
                                        <div class="flex items-center justify-center gap-2">
                                            <button wire:click="approve({{ $user->id }})"
                                                    wire:confirm="Approve {{ $user->name }}?"
                                                    class="px-3 py-1 text-xs font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition">
                                                Approve
                                            </button>
                                            <button wire:click="reject({{ $user->id }})"
                                                    wire:confirm="Reject {{ $user->name }}?"
                                                    class="px-3 py-1 text-xs font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition">
                                                Reject
                                            </button>
                                        </div>
                                    @elseif ($user->status === 'rejected')
                                        <button wire:click="approve({{ $user->id }})"
                                                class="px-3 py-1 text-xs font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">
                                            Approve
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
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
            <div class="px-6 py-12 text-center text-gray-400">
                <p class="font-medium">No {{ $statusFilter }} LMS users found.</p>
            </div>
        @endif
    </div>
</div>
