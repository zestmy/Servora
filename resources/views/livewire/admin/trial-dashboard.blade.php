<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="mb-4 px-4 py-3 bg-danger-50 border border-danger-200 text-danger-700 text-sm rounded-lg">{{ session('error') }}</div>
    @endif

    <h1 class="text-lg font-bold text-gray-800 mb-1">Trial Dashboard</h1>
    <p class="text-xs text-gray-600 mb-6">Monitor trial companies, conversion rates, and take action.</p>

    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="card p-4">
            <p class="text-xs text-gray-500 font-medium">Active Trials</p>
            <p class="text-2xl font-bold text-blue-600 mt-1">{{ $totalTrials }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 font-medium">Expiring in 3 Days</p>
            <p class="text-2xl font-bold {{ $expiringSoon > 0 ? 'text-danger-600' : 'text-gray-600' }} mt-1">{{ $expiringSoon }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 font-medium">Conversion Rate</p>
            <p class="text-2xl font-bold text-success-600 mt-1">{{ $conversionRate }}%</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 font-medium">Recently Expired</p>
            <p class="text-2xl font-bold text-gray-600 mt-1">{{ $recentlyExpired->count() }}</p>
        </div>
    </div>

    {{-- Active Trials --}}
    <div class="card overflow-hidden mb-6">
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">Active Trials</h2>
        </div>
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Company</th>
                    <th class="px-4 py-3 text-left">Plan</th>
                    <th class="px-4 py-3 text-center">Days Left</th>
                    <th class="px-4 py-3 text-left">Trial Ends</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($trials as $sub)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-800">{{ $sub->companyName() }}</p>
                            <p class="text-xs text-gray-600">{{ $sub->company?->email }}</p>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $sub->plan?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="font-bold {{ $sub->daysRemaining() <= 3 ? 'text-danger-600' : ($sub->daysRemaining() <= 7 ? 'text-warning-600' : 'text-success-600') }}">
                                {{ $sub->daysRemaining() }}d
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ $sub->trial_ends_at?->format('d M Y') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <button wire:click="extendTrial({{ $sub->id }}, 7)" wire:confirm="Extend trial by 7 days?"
                                        class="text-blue-500 hover:text-blue-700 text-xs font-medium transition">+7d</button>
                                <button wire:click="convertToPaid({{ $sub->id }})" wire:confirm="Convert to active paid subscription?"
                                        class="text-success-500 hover:text-success-700 text-xs font-medium transition">Activate</button>
                                <button wire:click="deactivate({{ $sub->id }})" wire:confirm="Deactivate this trial?"
                                        class="text-danger-400 hover:text-danger-600 text-xs font-medium transition">Deactivate</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-12 text-center text-gray-600">No active trials.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Recently Expired --}}
    @if ($recentlyExpired->isNotEmpty())
        <div class="card overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700">Recently Expired (last 7 days)</h2>
            </div>
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="text-gray-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Company</th>
                        <th class="px-4 py-3 text-left">Plan</th>
                        <th class="px-4 py-3 text-left">Expired On</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($recentlyExpired as $sub)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $sub->companyName() }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $sub->plan?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ $sub->updated_at->format('d M Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
