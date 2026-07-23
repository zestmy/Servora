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
        <button wire:click="openCreate"
                class="inline-flex items-center gap-1.5 px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Add Subscription
        </button>
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
                                <button wire:click="openEdit({{ $sub->id }})"
                                        title="Edit subscription"
                                        class="px-2 py-0.5 text-xs font-medium text-indigo-600 border border-indigo-200 rounded hover:bg-indigo-50 transition">
                                    Edit
                                </button>
                                @if ($sub->isTrial())
                                    <button wire:click="extendTrial({{ $sub->id }}, 7)"
                                            wire:confirm="Extend trial by 7 days?"
                                            title="Extend trial +7 days"
                                            class="text-blue-500 hover:text-blue-700 transition text-xs font-medium">
                                        +7d
                                    </button>
                                @endif
                                @if (! $sub->isActive() || $sub->isTrial())
                                    <button wire:click="activateSubscription({{ $sub->id }})"
                                            wire:confirm="Activate paid subscription for {{ $sub->company->name }}? A new billing period starts today."
                                            title="Activate (start paid period today)"
                                            class="text-green-600 hover:text-green-700 transition text-xs font-medium">
                                        Activate
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
                                <button wire:click="deleteSubscription({{ $sub->id }})"
                                        wire:confirm="Delete this subscription record for {{ $sub->company->name }}? With no subscription the company is treated as grandfathered (unlimited access). This cannot be undone."
                                        title="Delete subscription record"
                                        class="text-gray-300 hover:text-red-600 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
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

    {{-- Create / Edit modal --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/50" wire:click="closeModal"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
                <h2 class="text-base font-bold text-gray-800 mb-4">
                    {{ $editingId ? 'Edit Subscription' : 'Add Subscription' }}
                </h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Company *</label>
                        @if ($editingId)
                            @php $editCompany = \App\Models\Company::find($sub_company_id); @endphp
                            <input type="text" value="{{ $editCompany?->name ?? '—' }}" disabled
                                   class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-500" />
                        @else
                            <select wire:model="sub_company_id" class="w-full rounded-lg border-gray-300 text-sm">
                                <option value="">Select company…</option>
                                @foreach ($availableCompanies as $company)
                                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-gray-400 mt-1">Only companies without a live subscription are listed.</p>
                        @endif
                        @error('sub_company_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Plan *</label>
                            <select wire:model="sub_plan_id" class="w-full rounded-lg border-gray-300 text-sm">
                                <option value="">Select plan…</option>
                                @foreach ($plans as $plan)
                                    <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                                @endforeach
                            </select>
                            @error('sub_plan_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Billing Cycle *</label>
                            <select wire:model="sub_billing_cycle" class="w-full rounded-lg border-gray-300 text-sm">
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Status *</label>
                            <select wire:model.live="sub_status" class="w-full rounded-lg border-gray-300 text-sm">
                                <option value="trialing">Trial</option>
                                <option value="active">Active</option>
                                <option value="past_due">Past Due</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="expired">Expired</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Trial Ends</label>
                            <input type="date" wire:model="sub_trial_ends_at" class="w-full rounded-lg border-gray-300 text-sm"
                                   @if ($sub_status !== 'trialing') disabled @endif />
                            @error('sub_trial_ends_at') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Period Start</label>
                            <input type="date" wire:model="sub_period_start" class="w-full rounded-lg border-gray-300 text-sm" />
                            @error('sub_period_start') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Period End</label>
                            <input type="date" wire:model="sub_period_end" class="w-full rounded-lg border-gray-300 text-sm" />
                            @error('sub_period_end') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button wire:click="closeModal"
                            class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition">Cancel</button>
                    <button wire:click="saveSubscription"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        {{ $editingId ? 'Save Changes' : 'Create Subscription' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
