<div class="max-w-lg mx-auto px-4 py-8">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('billing.index') }}" class="text-gray-400 hover:text-gray-600 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div>
            <p class="text-xs text-gray-400"><a href="{{ route('billing.index') }}" class="hover:underline">Billing</a> / Checkout</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-base font-semibold text-gray-800 mb-1">
            {{ $currentSubscription ? ($currentSubscription->isTrial() ? 'Upgrade to' : 'Switch to') : 'Subscribe to' }} {{ $selectedPlan->name }}
        </h2>
        <p class="text-xs text-gray-400 mb-5">Review your plan before confirming.</p>

        {{-- Plan Summary --}}
        <div class="bg-gray-50 rounded-lg p-4 mb-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-lg font-bold text-gray-900">{{ $selectedPlan->name }}</span>
                <div class="text-right">
                    <span class="text-xl font-bold text-gray-900">
                        {{ $selectedPlan->currency }} {{ number_format($billing_cycle === 'yearly' ? $selectedPlan->price_yearly : $selectedPlan->price_monthly, 0) }}
                    </span>
                    <span class="text-xs text-gray-400">/{{ $billing_cycle === 'yearly' ? 'year' : 'month' }}</span>
                </div>
            </div>
            <div class="text-xs text-gray-500 space-y-1">
                <p>{{ $selectedPlan->max_outlets ?? 'Unlimited' }} outlets, {{ $selectedPlan->max_users ?? 'Unlimited' }} users</p>
                @if (!$currentSubscription)
                    <p>{{ $selectedPlan->trial_days }}-day free trial included</p>
                @endif
            </div>
        </div>

        {{-- Billing Cycle --}}
        <div class="mb-5">
            <x-input-label value="Billing Cycle" />
            <div class="mt-2 flex gap-3">
                <label class="flex-1 relative cursor-pointer">
                    <input type="radio" wire:model.live="billing_cycle" value="monthly" class="peer sr-only" />
                    <div class="border-2 rounded-xl p-3 text-center transition
                                peer-checked:border-indigo-500 peer-checked:bg-indigo-50
                                border-gray-200 hover:border-gray-300">
                        <p class="text-sm font-semibold text-gray-800">Monthly</p>
                        <p class="text-xs text-gray-400">{{ $selectedPlan->currency }} {{ number_format($selectedPlan->price_monthly, 0) }}/mo</p>
                    </div>
                </label>
                <label class="flex-1 relative cursor-pointer">
                    <input type="radio" wire:model.live="billing_cycle" value="yearly" class="peer sr-only" />
                    <div class="border-2 rounded-xl p-3 text-center transition
                                peer-checked:border-indigo-500 peer-checked:bg-indigo-50
                                border-gray-200 hover:border-gray-300">
                        <p class="text-sm font-semibold text-gray-800">Yearly</p>
                        <p class="text-xs text-gray-400">{{ $selectedPlan->currency }} {{ number_format($selectedPlan->price_yearly, 0) }}/yr</p>
                        @if ($selectedPlan->yearlyDiscount() > 0)
                            <p class="text-[10px] text-green-600 font-medium mt-0.5">Save {{ $selectedPlan->yearlyDiscount() }}%</p>
                        @endif
                    </div>
                </label>
            </div>
        </div>

        {{-- Payment methods info --}}
        @if ($chipInConfigured)
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mb-5">
                <p class="text-xs text-gray-600 font-medium mb-1">Accepted payment methods</p>
                <p class="text-xs text-gray-400">FPX (online banking), credit/debit cards, and e-wallets via CHIP-IN.</p>
            </div>
        @endif

        {{-- Action Buttons --}}
        @if (!$currentSubscription)
            {{-- New user — start free trial --}}
            <button wire:click="subscribe"
                    wire:loading.attr="disabled"
                    class="w-full py-3 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 transition disabled:opacity-50">
                <span wire:loading.remove wire:target="subscribe">Start {{ $selectedPlan->trial_days }}-Day Free Trial</span>
                <span wire:loading wire:target="subscribe">Processing...</span>
            </button>
            <p class="text-xs text-gray-400 text-center mt-2">No payment required now. Pay when you're ready.</p>

        @elseif ($currentSubscription->isTrial())
            {{-- Trialing — show both options --}}
            @if ($chipInConfigured)
                <button wire:click="payNow"
                        wire:loading.attr="disabled"
                        class="w-full py-3 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 transition disabled:opacity-50">
                    <span wire:loading.remove wire:target="payNow">Pay Now & Activate</span>
                    <span wire:loading wire:target="payNow">Redirecting to payment...</span>
                </button>
                <p class="text-xs text-gray-400 text-center mt-2">You'll be redirected to CHIP-IN to complete payment.</p>
            @else
                <button wire:click="subscribe"
                        wire:loading.attr="disabled"
                        class="w-full py-3 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 transition disabled:opacity-50">
                    <span wire:loading.remove wire:target="subscribe">Select This Plan</span>
                    <span wire:loading wire:target="subscribe">Processing...</span>
                </button>
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mt-3 text-xs text-amber-700">
                    <p class="font-medium">Online payment coming soon</p>
                    <p class="mt-0.5">Payment via FPX, card, and e-wallet will be available shortly. Contact us to activate your subscription now.</p>
                </div>
            @endif

        @else
            {{-- Active subscription — switch plan --}}
            <button wire:click="subscribe"
                    wire:loading.attr="disabled"
                    class="w-full py-3 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 transition disabled:opacity-50">
                <span wire:loading.remove wire:target="subscribe">Switch to {{ $selectedPlan->name }}</span>
                <span wire:loading wire:target="subscribe">Processing...</span>
            </button>
        @endif
    </div>
</div>
