<div>
    <div class="mb-7">
        <h1 class="font-display text-2xl font-semibold tracking-tight text-gray-900">Start your free trial</h1>
        <p class="mt-1.5 text-sm text-gray-600">No credit card required. Get started in under 2 minutes.</p>
    </div>

    <form wire:submit="register" class="space-y-5">

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
        <div class="grid gap-4 sm:grid-cols-2">
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

        {{-- No plan to choose: every sign-up is a reverse trial — the whole
             product for the trial, then Free unless they pick a suite
             (docs/pricing-model.md). Asking for a plan before anyone has seen
             the product was a decision made blind. --}}
        <div class="alert-info text-sm">
            You get every module free for {{ $trialDays }} days. After that you stay on Free — or choose a plan from Billing. No card needed.
        </div>

        {{-- Coupon code (optional) --}}
        <div x-data="{ open: {{ $coupon_code ? 'true' : 'false' }} }">
            <button type="button" @click="open = !open" class="text-xs text-brand-600 hover:text-brand-700 font-medium">
                <span x-show="!open">+ Have a coupon code?</span>
                <span x-show="open" x-cloak>− Hide coupon</span>
            </button>
            <div x-show="open" x-cloak class="mt-2">
                <x-text-input wire:model="coupon_code" type="text"
                              placeholder="ENTER COUPON CODE"
                              class="block w-full font-mono uppercase" />
                <p class="mt-1 text-xs text-gray-600">Get free subscription with a valid promo code.</p>
                <x-input-error :messages="$errors->get('coupon_code')" class="mt-1" />
            </div>
        </div>

        {{-- Submit --}}
        <button type="submit"
                wire:loading.attr="disabled"
                class="btn-primary btn-lg w-full">
            <span wire:loading.remove>Start Free Trial</span>
            <span wire:loading>Creating your account…</span>
        </button>
    </form>

    <div class="mt-8 border-t border-gray-100 pt-6 text-center">
        <p class="text-sm text-gray-600">Already have an account?</p>
        <a href="{{ route('login') }}" class="btn-secondary mt-3 w-full">Log in</a>
    </div>
</div>
