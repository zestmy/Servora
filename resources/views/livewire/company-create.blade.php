<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <h1 class="text-lg font-bold text-gray-800">Create New Company</h1>
        <p class="text-sm text-gray-400 mt-0.5">
            A separate company under your current login — its own outlets, data, and subscription.
            You can switch between companies anytime from the sidebar.
        </p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-6">
        {{-- Company name --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Company Name *</label>
            <input type="text" wire:model="company_name" placeholder="e.g. My Second Restaurant Sdn Bhd"
                   class="w-full rounded-lg border-gray-300 text-sm text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:ring-indigo-500" />
            @error('company_name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Billing cycle --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Billing Cycle *</label>
            <div class="inline-flex rounded-lg border border-gray-200 p-1 bg-gray-50">
                <button type="button" wire:click="$set('billing_cycle', 'monthly')"
                        class="px-4 py-1.5 text-sm rounded-md transition {{ $billing_cycle === 'monthly' ? 'bg-white shadow text-indigo-700 font-medium' : 'text-gray-500 hover:text-gray-700' }}">
                    Monthly
                </button>
                <button type="button" wire:click="$set('billing_cycle', 'yearly')"
                        class="px-4 py-1.5 text-sm rounded-md transition {{ $billing_cycle === 'yearly' ? 'bg-white shadow text-indigo-700 font-medium' : 'text-gray-500 hover:text-gray-700' }}">
                    Yearly
                </button>
            </div>
        </div>

        {{-- Plan --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Plan *</label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach ($plans as $plan)
                    <label class="flex items-start gap-3 p-4 border rounded-xl cursor-pointer transition
                                  {{ (int) $plan_id === (int) $plan->id ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500' : 'border-gray-200 hover:border-gray-300' }}">
                        <input type="radio" wire:model.live="plan_id" value="{{ $plan->id }}"
                               class="mt-0.5 text-indigo-600 focus:ring-indigo-500" />
                        <span class="flex-1">
                            <span class="block text-sm font-semibold text-gray-800">{{ $plan->name }}</span>
                            <span class="block text-lg font-bold text-gray-900 mt-1">
                                RM{{ number_format($billing_cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly, 2) }}
                                <span class="text-xs font-normal text-gray-400">/{{ $billing_cycle === 'yearly' ? 'year' : 'month' }}</span>
                            </span>
                            @if ($plan->trial_days)
                                <span class="inline-block mt-1.5 px-2 py-0.5 bg-green-50 text-green-700 text-[11px] font-medium rounded-full">
                                    {{ $plan->trial_days }}-day free trial
                                </span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
            @error('plan_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Coupon --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Coupon Code <span class="text-gray-400 font-normal">(optional)</span></label>
            <input type="text" wire:model="coupon_code" placeholder="e.g. LIFETIME2026"
                   class="w-full sm:w-64 rounded-lg border-gray-300 text-sm text-gray-900 placeholder-gray-400 uppercase focus:border-indigo-500 focus:ring-indigo-500" />
            <p class="text-xs text-gray-400 mt-1">Discount or lifetime-deal coupons are applied to the new company's subscription immediately.</p>
            @error('coupon_code') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="flex items-center justify-end gap-3 mt-6">
        <a href="{{ route('dashboard') }}"
           class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition">Cancel</a>
        <button wire:click="create" wire:loading.attr="disabled"
                class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition disabled:opacity-60">
            <span wire:loading.remove wire:target="create">Create Company</span>
            <span wire:loading wire:target="create">Creating…</span>
        </button>
    </div>
</div>
