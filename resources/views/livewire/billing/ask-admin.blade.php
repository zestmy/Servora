{{-- Billing for someone who cannot change the plan: every upgrade link in the
     product lands here, so it says what the module is and who can add it,
     instead of a 403. No plan figures or invoices — those are the admin's. --}}
<div class="max-w-xl">
    <h1 class="text-lg font-bold text-gray-800 mb-1">Billing & Plan</h1>
    <p class="text-xs text-gray-600 mb-6">Your company's plan is managed by its administrator.</p>

    <div class="card p-6">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700">
                <x-icon name="lock" size="h-5 w-5" />
            </span>
            <div>
                @if ($unlockPitch)
                    <p class="text-sm font-semibold text-gray-900">{{ $unlockPitch['name'] }} is not on your company's plan</p>
                    @if ($unlockPitch['pitch'])
                        <p class="mt-1 text-sm text-gray-600">{{ $unlockPitch['pitch'] }}</p>
                    @endif
                @else
                    <p class="text-sm font-semibold text-gray-900">Only your company admin can change the plan</p>
                @endif
                <p class="mt-3 text-sm text-gray-600">
                    Ask a person who manages users in your company to add it from Billing &amp; Plan.
                </p>
                <a href="{{ route('dashboard') }}" class="btn-secondary mt-4">Back to dashboard</a>
            </div>
        </div>
    </div>
</div>
