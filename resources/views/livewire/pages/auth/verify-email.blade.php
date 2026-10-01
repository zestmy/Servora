<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.auth', ['title' => 'Verify your email', 'panel' => 'verify'])] class extends Component
{
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <div class="mb-7 text-center">
        <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-brand-50">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
            </svg>
        </div>
        <h1 class="font-display text-2xl font-semibold tracking-tight text-gray-900">Verify your email</h1>
        <p class="mt-2 text-sm text-gray-600">
            Thanks for signing up! We&rsquo;ve sent a link to
            <span class="font-medium text-gray-900">{{ auth()->user()->email }}</span>.
            Click it to confirm your address and get started.
        </p>
        <p class="mt-1 text-xs text-gray-500">Nothing there? Check your spam folder, or send another below.</p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-5 rounded-control bg-success-50 px-4 py-3 text-sm font-medium text-success-700">
            {{ __('A new verification link is on its way to your inbox.') }}
        </div>
    @endif

    <button type="button" wire:click="sendVerification" class="btn-primary btn-lg w-full" wire:loading.attr="disabled" wire:target="sendVerification">
        <span wire:loading.remove wire:target="sendVerification">{{ __('Resend verification email') }}</span>
        <span wire:loading wire:target="sendVerification">{{ __('Sending…') }}</span>
    </button>

    <p class="mt-8 border-t border-gray-100 pt-6 text-center text-sm text-gray-600">
        Signed up with the wrong address?
        <button type="button" wire:click="logout" class="font-medium text-brand-700 hover:underline">{{ __('Log out') }}</button>
    </p>
</div>
