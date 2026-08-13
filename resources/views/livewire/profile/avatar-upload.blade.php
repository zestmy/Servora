<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Profile Picture</h2>
        <p class="mt-1 text-sm text-gray-600">Shown in the navigation panel. JPG, PNG, GIF, WebP or HEIC, up to 5 MB. Large photos are resized automatically.</p>
    </header>

    @if (session()->has('avatar_success'))
        <p class="mt-3 text-sm text-success-600">{{ session('avatar_success') }}</p>
    @endif

    <div class="mt-5 flex items-center gap-5">
        @if (auth()->user()->avatar)
            <img src="{{ \Illuminate\Support\Facades\Storage::url(auth()->user()->avatar) }}"
                 alt="{{ auth()->user()->name }}"
                 class="w-16 h-16 rounded-full object-cover ring-2 ring-brand-100" />
        @else
            <div class="w-16 h-16 bg-brand-600 rounded-full flex items-center justify-center text-white font-bold text-lg">
                {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
            </div>
        @endif

        <div class="space-y-2">
            <label class="btn-primary cursor-pointer">
                <input type="file" wire:model="photo" accept="image/*" class="hidden" />
                <span wire:loading.remove wire:target="photo">{{ auth()->user()->avatar ? 'Change picture' : 'Upload picture' }}</span>
                <span wire:loading wire:target="photo">Uploading…</span>
            </label>
            @if (auth()->user()->avatar)
                <button wire:click="removeAvatar" wire:confirm="Remove your profile picture?"
                        class="block text-xs text-danger-500 hover:text-danger-700 underline">Remove picture</button>
            @endif
            @error('photo') <p class="text-xs text-danger-500">{{ $message }}</p> @enderror
        </div>
    </div>
</section>
