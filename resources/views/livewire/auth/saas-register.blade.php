<div class="max-w-lg mx-auto px-4 py-12">
    <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Start Your Free Trial</h1>
        <p class="text-sm text-gray-500 mt-2">No credit card required. Get started in under 2 minutes.</p>
    </div>

    <form wire:submit="register" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">

        {{-- Company Name --}}
        <div>
            <x-input-label for="company_name" value="Company / Business Name *" />
            <x-text-input id="company_name" wire:model="company_name" type="text" class="mt-1 block w-full"
                          placeholder="e.g. Restoran Sedap Sdn Bhd" autofocus />
            <x-input-error :messages="$errors->get('company_name')" class="mt-1" />
        </div>

        {{-- Your Name --}}
        <div>
            <x-input-label for="reg_name" value="Your Name *" />
            <x-text-input id="reg_name" wire:model="name" type="text" class="mt-1 block w-full"
                          placeholder="e.g. Ahmad Ibrahim" />
            <x-input-error :messages="$errors->get('name')" class="mt-1" />
        </div>

        {{-- Email --}}
        <div>
            <x-input-label for="reg_email" value="Email *" />
            <x-text-input id="reg_email" wire:model="email" type="email" class="mt-1 block w-full"
                          placeholder="you@company.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        {{-- Password --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="reg_pass" value="Password *" />
                <x-text-input id="reg_pass" wire:model="password" type="password" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('password')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="reg_pass_confirm" value="Confirm Password *" />
                <x-text-input id="reg_pass_confirm" wire:model="password_confirmation" type="password" class="mt-1 block w-full" />
            </div>
        </div>

        {{-- Plan Selection --}}
        <div>
            <x-input-label value="Select Plan" />
            <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
                @foreach ($plans as $plan)
                    <label class="relative cursor-pointer">
                        <input type="radio" wire:model="plan_id" value="{{ $plan->id }}" class="peer sr-only" />
                        <div class="border-2 rounded-xl p-3 text-center transition
                                    peer-checked:border-indigo-500 peer-checked:bg-indigo-50
                                    border-gray-200 hover:border-gray-300">
                            <p class="text-sm font-bold text-gray-800">{{ $plan->name }}</p>
                            <p class="text-lg font-bold text-indigo-600 mt-1">
                                {{ $plan->currency }} {{ number_format($billing_cycle === 'yearly' ? $plan->price_yearly / 12 : $plan->price_monthly, 0) }}
                            </p>
                            <p class="text-[10px] text-gray-400">/month</p>
                            @if ($plan->trial_days > 0)
                                <p class="text-[10px] text-green-600 font-medium mt-1">{{ $plan->trial_days }}-day free trial</p>
                            @endif
                        </div>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('plan_id')" class="mt-1" />
        </div>

        {{-- Billing Cycle --}}
        <div>
            <div class="flex items-center gap-4">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="radio" wire:model.live="billing_cycle" value="monthly"
                           class="text-indigo-600 focus:ring-indigo-500" />
                    <span class="text-sm text-gray-700">Monthly</span>
                </label>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="radio" wire:model.live="billing_cycle" value="yearly"
                           class="text-indigo-600 focus:ring-indigo-500" />
                    <span class="text-sm text-gray-700">Yearly <span class="text-green-600 font-medium">(save up to 17%)</span></span>
                </label>
            </div>
        </div>

        {{-- Submit --}}
        <button type="submit"
                wire:loading.attr="disabled"
                class="w-full py-3 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700 transition disabled:opacity-50">
            <span wire:loading.remove>Start Free Trial</span>
            <span wire:loading>Creating your account…</span>
        </button>

        <p class="text-xs text-center text-gray-400">
            Already have an account? <a href="{{ route('login') }}" class="text-indigo-600 hover:underline">Log in</a>
        </p>
    </form>
</div>
