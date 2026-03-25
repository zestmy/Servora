<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    <div class="flex items-center gap-3 mb-4">
        <div class="flex-1">
            <h1 class="text-lg font-bold text-gray-800">Subscriptions</h1>
            <p class="text-xs text-gray-400 mt-0.5">View and manage all company subscriptions.</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="flex items-center gap-3 mb-4">
        <div class="flex-1">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search company…"
                   class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <select wire:model.live="statusFilter"
                class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">All Statuses</option>
            <option value="trialing">Trial</option>
            <option value="active">Active</option>
            <option value="past_due">Past Due</option>
            <option value="cancelled">Cancelled</option>
            <option value="expired">Expired</option>
        </select>
        <select wire:model.live="planFilter"
                class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">All Plans</option>
            @foreach ($plans as $plan)
                <option value="{{ $plan->id }}">{{ $plan->name }}</option>
            @endforeach
        </select>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Company</th>
                    <th class="px-4 py-3 text-left">Plan</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center">Cycle</th>
                    <th class="px-4 py-3 text-center">Days Left</th>
                    <th class="px-4 py-3 text-left">Period End</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($subscriptions as $sub)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-800">{{ $sub->company->name ?? '—' }}</p>
                            <p class="text-xs text-gray-400">{{ $sub->company->slug ?? '' }}</p>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $sub->plan->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            @php $color = $sub->statusColor(); @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $color }}-100 text-{{ $color }}-700">
                                {{ $sub->statusLabel() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-600 capitalize">{{ $sub->billing_cycle }}</td>
                        <td class="px-4 py-3 text-center">
                            @if ($sub->isActive())
                                <span class="font-medium {{ $sub->daysRemaining() <= 3 ? 'text-red-600' : 'text-gray-700' }}">
                                    {{ $sub->daysRemaining() }}d
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600 text-xs">
                            {{ $sub->current_period_end?->format('d M Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                @if ($sub->isTrial())
                                    <button wire:click="extendTrial({{ $sub->id }}, 7)"
                                            wire:confirm="Extend trial by 7 days?"
                                            title="Extend trial +7 days"
                                            class="text-blue-500 hover:text-blue-700 transition text-xs font-medium">
                                        +7d
                                    </button>
                                @endif
                                @if ($sub->isActive() || $sub->isTrial())
                                    <button wire:click="cancelSubscription({{ $sub->id }})"
                                            wire:confirm="Cancel subscription for {{ $sub->company->name }}?"
                                            title="Cancel"
                                            class="text-red-400 hover:text-red-600 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                            <p class="font-medium">No subscriptions yet</p>
                            <p class="text-xs mt-1">Subscriptions will appear here when companies sign up.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($subscriptions->hasPages())
        <div class="mt-4">{{ $subscriptions->links() }}</div>
    @endif
</div>
