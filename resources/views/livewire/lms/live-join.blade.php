<div class="mx-auto max-w-md">
    <div class="text-center mb-6">
        <x-icon name="play" size="h-10 w-10" class="mx-auto text-brand-600" />
        <h1 class="text-2xl font-bold text-gray-900 mt-3">Join a live session</h1>
        <p class="text-sm text-gray-600 mt-1">
            The PIN is on the screen your manager is showing.
        </p>
    </div>

    <div class="card p-6">
        @if ($joinError)
            <div class="alert-danger mb-4">
                <x-icon name="alert" size="h-5 w-5" class="flex-shrink-0" />
                <p>{{ $joinError }}</p>
            </div>
        @endif

        <div class="space-y-4">
            <div>
                <label class="label" for="live-pin">Game PIN</label>
                {{-- inputmode numeric brings up the number pad without
                     rejecting a paste, which type=number would mangle. --}}
                <input id="live-pin" type="text" inputmode="numeric" autocomplete="off"
                       maxlength="8" wire:model="pin"
                       class="input text-center text-2xl font-mono tracking-[0.3em] py-4"
                       placeholder="000000">
            </div>

            <div>
                <label class="label" for="live-nickname">Your name on the board</label>
                <input id="live-nickname" type="text" maxlength="60" wire:model="nickname"
                       class="input py-3" placeholder="What should we call you?">
            </div>

            <button type="button" wire:click="join" class="btn-primary w-full py-3">
                <span wire:loading.remove wire:target="join">Join</span>
                <span wire:loading wire:target="join">Joining…</span>
            </button>
        </div>

        <p class="help mt-4 text-center">
            You do not need an account to play — but sign in and your score counts towards your
            report card and the company leaderboard.
        </p>
    </div>

    <div class="mt-4 text-center">
        <a href="{{ route('lms.courses') }}" class="text-sm text-gray-600 hover:text-gray-900">Back to training</a>
    </div>
</div>
