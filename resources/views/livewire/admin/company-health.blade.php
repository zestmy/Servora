<div>
    <h1 class="text-lg font-bold text-gray-800 mb-1">Company Health</h1>
    <p class="text-xs text-gray-400 mb-6">Monitor company engagement and identify at-risk accounts.</p>

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-500 font-medium">Active / Healthy</p>
            <p class="text-2xl font-bold text-green-600 mt-1">{{ $totalActive }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-500 font-medium">At Risk (7-14d inactive)</p>
            <p class="text-2xl font-bold text-amber-600 mt-1">{{ $totalAtRisk }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-500 font-medium">Inactive (14d+)</p>
            <p class="text-2xl font-bold text-red-600 mt-1">{{ $totalInactive }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="flex items-center gap-3 mb-4">
        <div class="flex-1">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search company…"
                   class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <select wire:model.live="healthFilter"
                class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">All Status</option>
            <option value="active">Active (today)</option>
            <option value="healthy">Healthy (1-7d)</option>
            <option value="at_risk">At Risk (7-14d)</option>
            <option value="inactive">Inactive (14d+)</option>
        </select>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Company</th>
                    <th class="px-4 py-3 text-center">Outlets</th>
                    <th class="px-4 py-3 text-center">Users</th>
                    <th class="px-4 py-3 text-center">Recipes</th>
                    <th class="px-4 py-3 text-center">Ingredients</th>
                    <th class="px-4 py-3 text-left">Last Active</th>
                    <th class="px-4 py-3 text-center">Health</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($companies as $company)
                    @php $health = $healthData[$company->id] ?? []; @endphp
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-800">{{ $company->name }}</p>
                            <p class="text-xs text-gray-400">{{ $company->slug }}</p>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-600">{{ $company->outlets_count }}</td>
                        <td class="px-4 py-3 text-center text-gray-600">{{ $company->users_count }}</td>
                        <td class="px-4 py-3 text-center text-gray-600">{{ $company->recipes_count }}</td>
                        <td class="px-4 py-3 text-center text-gray-600">{{ $company->ingredients_count }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            {{ $health['last_active']?->diffForHumans() ?? 'Never' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $statusColors = ['active' => 'green', 'healthy' => 'green', 'at_risk' => 'amber', 'inactive' => 'red', 'no_data' => 'gray'];
                                $statusLabels = ['active' => 'Active', 'healthy' => 'Healthy', 'at_risk' => 'At Risk', 'inactive' => 'Inactive', 'no_data' => 'No Data'];
                                $s = $health['status'] ?? 'no_data';
                            @endphp
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $statusColors[$s] ?? 'gray' }}-100 text-{{ $statusColors[$s] ?? 'gray' }}-700">
                                {{ $statusLabels[$s] ?? 'Unknown' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-gray-400">No companies found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($companies->hasPages())
        <div class="mt-4">{{ $companies->links() }}</div>
    @endif
</div>
