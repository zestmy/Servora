<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.auth', ['title' => 'Forgot password', 'panel' => 'reset'])] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div>
    @if (session('status'))
        {{-- Success state: hide the form, show the confirmation. --}}
        <div class="text-center">
            <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-success-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-success-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                </svg>
            </div>
            <h1 class="font-display text-2xl font-semibold tracking-tight text-gray-900">Check your inbox</h1>
            <p class="mt-2 text-sm text-gray-600">{{ session('status') }}</p>
            <p class="mt-1 text-xs text-gray-500">Nothing there? Check your spam folder, or try again in a minute.</p>
            <a href="{{ route('login') }}" wire:navigate class="btn-primary btn-lg mt-6 w-full">Back to log in</a>
        </div>
    @else
        <div class="mb-7">
            <h1 class="font-display text-2xl font-semibold tracking-tight text-gray-900">Forgot your password?</h1>
            <p class="mt-1.5 text-sm text-gray-600">No problem. Enter your email and we&rsquo;ll send you a link to pick a new one.</p>
        </div>

        <form wire:submit="sendPasswordResetLink" class="space-y-5">
            <div class="field">
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input wire:model="email" id="email" type="email" name="email" placeholder="you@restaurant.com" required autofocus autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <button type="submit" class="btn-primary btn-lg w-full" wire:loading.attr="disabled" wire:target="sendPasswordResetLink">
                <span wire:loading.remove wire:target="sendPasswordResetLink">{{ __('Email me a reset link') }}</span>
                <span wire:loading wire:target="sendPasswordResetLink">{{ __('Sending…') }}</span>
            </button>
        </form>

        <p class="mt-8 border-t border-gray-100 pt-6 text-center text-sm text-gray-600">
            Remembered it?
            <a href="{{ route('login') }}" wire:navigate class="font-medium text-brand-700 hover:underline">Back to log in</a>
        </p>
    @endif
</div>
