<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="mb-3 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 p-5 mb-3">
        <p class="text-base font-semibold text-gray-800">{{ $this->staff()->name }}</p>
        <p class="text-xs text-gray-400 mt-0.5">{{ $this->outletName() ?: 'No outlet set' }}</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5 mb-3">
        <p class="text-sm font-semibold text-gray-700 mb-1">Change your PIN</p>
        <p class="text-xs text-gray-400 mb-4">
            4 to 6 digits. Changing it signs you out of any other device.
        </p>

        <div class="space-y-3">
            <div>
                <label class="text-xs font-medium text-gray-600">Current PIN</label>
                <input type="password" inputmode="numeric" autocomplete="off" wire:model="current"
                       class="mt-1 w-full rounded-xl border-gray-300 text-lg py-3 tracking-widest">
                @error('current') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600">New PIN</label>
                <input type="password" inputmode="numeric" autocomplete="off" wire:model="new"
                       class="mt-1 w-full rounded-xl border-gray-300 text-lg py-3 tracking-widest">
                @error('new') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600">Confirm new PIN</label>
                <input type="password" inputmode="numeric" autocomplete="off" wire:model="confirm"
                       class="mt-1 w-full rounded-xl border-gray-300 text-lg py-3 tracking-widest">
                @error('confirm') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <button wire:click="changePin"
                class="mt-4 w-full py-3.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl active:bg-indigo-700">
            Change PIN
        </button>

        <p class="mt-3 text-xs text-gray-400">
            Forgotten it? Your manager can reset it for you.
        </p>
    </div>

    <button wire:click="signOut"
            class="w-full py-3.5 bg-white border border-gray-200 text-sm font-medium text-red-600 rounded-xl active:bg-red-50">
        Sign out
    </button>
</div>
