<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="alert-success mb-3">
            {{ session('success') }}
        </div>
    @endif

    {{-- Who you are signed in as. This is the screen someone lands on when
         the wrong name is showing in the header, so it answers that first —
         and a face answers it faster than a name. Same component as the
         boards: photo over initials, so no photo on file (most of the floor)
         or a 404 degrades to the coloured disc, and the default photoRoute
         is already the PIN-session one this app is signed in with. --}}
    <div class="card-pad mb-3 flex items-center gap-3">
        <x-staff-avatar :name="$this->staff()->name"
                        :employee="$this->staff()->id"
                        :photo="$this->staff()->photo_path"
                        size="h-12 w-12 text-sm" />
        <div class="min-w-0">
            <p class="truncate text-lg font-semibold leading-tight text-gray-900">{{ $this->staff()->name }}</p>
            <p class="mt-0.5 truncate text-sm text-gray-600">{{ $this->outletName() ?: 'No outlet set' }}</p>
        </div>
    </div>

    <div class="card-pad mb-3">
        <h2 class="text-sm font-semibold text-gray-900">Change your PIN</h2>
        <p class="mt-1 mb-4 text-sm text-gray-600">
            4 to 6 digits. Changing it signs you out of any other device.
        </p>

        <div class="space-y-3">
            <div class="field">
                <label class="label" for="pin-current">Current PIN</label>
                <input id="pin-current" type="password" inputmode="numeric" autocomplete="current-password" wire:model="current"
                       class="input py-3 text-lg tracking-widest @error('current') input-error @enderror">
                @error('current') <p class="error-text">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label class="label" for="pin-new">New PIN</label>
                <input id="pin-new" type="password" inputmode="numeric" autocomplete="new-password" wire:model="new"
                       class="input py-3 text-lg tracking-widest @error('new') input-error @enderror">
                @error('new') <p class="error-text">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label class="label" for="pin-confirm">Confirm new PIN</label>
                <input id="pin-confirm" type="password" inputmode="numeric" autocomplete="new-password" wire:model="confirm"
                       class="input py-3 text-lg tracking-widest @error('confirm') input-error @enderror">
                @error('confirm') <p class="error-text">{{ $message }}</p> @enderror
            </div>
        </div>

        <button wire:click="changePin" class="btn-primary mt-4 w-full py-3.5">
            <span wire:loading.remove wire:target="changePin">Change PIN</span>
            <span wire:loading wire:target="changePin">Saving…</span>
        </button>

        <p class="help mt-3">Forgotten it? Your manager can reset it for you.</p>
    </div>

    <button wire:click="signOut"
            class="btn-secondary w-full py-3.5 text-danger-700 active:bg-danger-50">
        Sign out
    </button>
</div>
