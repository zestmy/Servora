<div class="space-y-3">
    @if (session()->has('success'))
        <div class="rounded-control bg-success-50 border border-success-200 px-4 py-3 text-sm text-success-700">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="rounded-control bg-danger-50 border border-danger-200 px-4 py-3 text-sm text-danger-700">
            {{ session('error') }}
        </div>
    @endif

    {{-- Who you are signed in as, face first — the same shared avatar as the
         labels account screen and the boards: photo over initials, so no
         photo on file or a 404 degrades to the coloured disc, and the
         component's default photoRoute is already this app's PIN-session
         route. --}}
    <div class="panel flex items-center gap-3 p-5">
        <x-staff-avatar :name="$this->staff()->name"
                        :employee="$this->staff()->id"
                        :photo="$this->staff()->photo_path"
                        size="h-12 w-12 text-sm" />
        <div class="min-w-0">
            <p class="truncate text-lg font-semibold text-gray-900">{{ $this->staff()->name }}</p>
            <p class="mt-0.5 truncate text-sm text-gray-600">
                {{ $this->staff()->outlet?->name ?? 'No outlet set' }}
                @if ($this->staff()->designation)
                    · {{ $this->staff()->designation }}
                @endif
            </p>
        </div>
    </div>

    <div class="panel p-5">
        <h2 class="text-base font-semibold text-gray-900">How you sign in</h2>

        @php
            $employee   = $this->staff();
            $pinOn      = $employee->hasLabelPin() && ! $employee->pin_login_disabled_at;
            $hasEmail   = filled($employee->email);
        @endphp

        {{-- States the truth first. A settings screen that only offers changes
             makes you work out what is currently happening from which button
             is showing. --}}
        <p class="mt-1 text-sm text-gray-600">
            @if ($pinOn)
                Your PIN works, and you can ask for an emailed code instead at any time.
            @elseif ($hasEmail)
                You sign in with a code emailed to <span class="font-medium text-gray-900">{{ $employee->email }}</span>.
            @else
                You sign in with your PIN.
            @endif
        </p>

        @if ($employee->hasLabelPin())
            <div class="mt-4">
                @if ($pinOn)
                    <button type="button" wire:click="disablePinLogin"
                            wire:confirm="Switch your PIN off? You will sign in with an emailed code from then on."
                            @disabled(! $hasEmail)
                            class="btn-secondary min-h-[3.25rem] w-full justify-center disabled:opacity-40">
                        Switch my PIN off
                    </button>

                    @unless ($hasEmail)
                        <p class="mt-2 text-xs text-gray-600">
                            You need an email address on your record before you can turn the PIN off —
                            otherwise there would be no way back in. Ask your manager to add one.
                        </p>
                    @endunless
                @else
                    <button type="button" wire:click="enablePinLogin"
                            class="btn-primary min-h-[3.25rem] w-full justify-center">
                        Use my PIN again
                    </button>
                    <p class="mt-2 text-xs text-gray-600">
                        Your PIN was never deleted, so this puts it straight back to work.
                    </p>
                @endif
            </div>
        @else
            <p class="mt-4 text-xs text-gray-600">
                You do not have a PIN. Ask your manager for one if you would rather key in
                four digits than wait for an email.
            </p>
        @endif
    </div>
</div>
