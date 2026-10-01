<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.auth')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        /*
         * Always the dashboard — never redirectIntended().
         *
         * The Breeze default returns you to whatever URL bounced you to login,
         * which sounds helpful and is not. Sessions here expire while people
         * are mid-task, so the common case was signing in and landing on a
         * deep screen — a payroll run, a half-filled GRN — with no context
         * about what happened while you were away. The dashboard is where the
         * alerts, the pending approvals and the day's numbers are, and it is
         * the screen this product is designed to be entered through.
         *
         * The intended URL is FORGOTTEN rather than merely ignored: Laravel
         * leaves it in the session otherwise, and the next redirectIntended()
         * anywhere in the app would consume a URL from a login that happened
         * hours ago.
         */
        Session::forget('url.intended');

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-7">
        <h1 class="font-display text-2xl font-semibold tracking-tight text-gray-900">Welcome back</h1>
        <p class="mt-1.5 text-sm text-gray-600">Sign in to see today&rsquo;s numbers.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login" class="space-y-5">
        <div class="field">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="form.email" id="email" type="email" name="email"
                          placeholder="you@restaurant.com" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.email')" />
        </div>

        <div class="field" x-data="{ show: false }">
            <div class="flex items-center justify-between">
                <x-input-label for="password" :value="__('Password')" />
                @if (Route::has('password.request'))
                    <a class="text-sm font-medium text-brand-700 hover:text-brand-800 hover:underline"
                       href="{{ route('password.request') }}" wire:navigate>
                        {{ __('Forgot password?') }}
                    </a>
                @endif
            </div>
            <div class="relative">
                <x-text-input wire:model="form.password" id="password" name="password"
                              x-bind:type="show ? 'text' : 'password'" type="password"
                              class="pr-11" required autocomplete="current-password" />
                <button type="button" @click="show = ! show"
                        class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-control text-gray-500 hover:text-gray-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                        x-bind:aria-label="show ? 'Hide password' : 'Show password'" aria-label="Show password">
                    <svg x-show="! show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    <svg x-show="show" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('form.password')" />
        </div>

        <label for="remember" class="inline-flex items-center">
            <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500" name="remember">
            <span class="ms-2 text-sm text-gray-600">{{ __('Keep me signed in') }}</span>
        </label>

        <button type="submit" class="btn-primary btn-lg w-full" wire:loading.attr="disabled" wire:target="login">
            <span wire:loading.remove wire:target="login">{{ __('Log in') }}</span>
            <span wire:loading wire:target="login">{{ __('Signing you in…') }}</span>
        </button>
    </form>

    <div class="mt-8 border-t border-gray-100 pt-6 text-center">
        <p class="text-sm text-gray-600">New to Servora?</p>
        <a href="{{ route('saas.register') }}" class="btn-secondary mt-3 w-full">
            Start your free trial
            <span aria-hidden="true">&rarr;</span>
        </a>
        @if (config('modules.supplier_portal'))
            <p class="mt-4 text-xs text-gray-500">
                Supplying restaurants?
                <a href="{{ route('supplier.login') }}" class="font-medium text-brand-700 hover:underline">Supplier sign-in</a>
            </p>
        @endif
    </div>
</div>
