{{-- Shared by every staff app. See App\Livewire\Staff\StaffLogin for why
     there is one screen rather than one per app.

     min-h-full, not a vh value: the scroll area already has a definite
     height, so a viewport unit here would overshoot it and scroll. --}}
<div class="min-h-full flex flex-col justify-center">

    <div class="text-center mb-6">
        @if ($company?->logo)
            <div class="flex justify-center mb-3">
                <x-brand-mark :company="$company" surface="light"
                              size="h-16" width="max-w-[200px]" />
            </div>
        @else
            {{-- No brand set: fall back to the app's own mark. --}}
            <div class="mx-auto w-14 h-14 rounded-2xl bg-brand-600 flex items-center justify-center mb-3 shadow-btn">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}"/>
                </svg>
            </div>
        @endif
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">
            {{ $company?->brand_name ?? $company?->name ?? 'Servora' }}
        </h1>
        <p class="mt-0.5 text-sm text-gray-600">{{ $tagline }}</p>
    </div>

    {{-- Just signed in by email, and there is still a PIN on the account.
         Offered here rather than on the way in: turning off your own PIN is a
         change to how you get in, so it is made by the person themselves — but
         from a screen where they have just proved they are that person. --}}
    @if ($offerPinSwitch)
        <div class="panel p-5">
            <h2 class="text-base font-semibold text-gray-900">You are signed in</h2>
            <p class="mt-1 text-sm text-gray-600">
                You also have a PIN for this app. Would you like to stop using it and
                sign in by email from now on?
            </p>

            <div class="mt-5 grid gap-2">
                <button type="button" wire:click="disablePinLogin"
                        class="btn-primary min-h-[3.25rem] w-full justify-center">
                    Use email only from now on
                </button>
                <button type="button" wire:click="keepPinLogin"
                        class="btn-secondary min-h-[3.25rem] w-full justify-center">
                    Keep my PIN as well
                </button>
            </div>

            <p class="mt-3 text-xs text-gray-600">
                Your PIN is not deleted either way — your manager can switch it back on
                for you if you change your mind.
            </p>
        </div>

    @elseif ($method === 'email')
        {{-- Email route: address, then the code that lands in it. --}}
        <div class="panel p-5">
            @if (! $codeSent)
                <h2 class="text-base font-semibold text-gray-900">Sign in by email</h2>
                <p class="mt-1 text-sm text-gray-600">
                    We will send a {{ $codeTtl }}-minute code to the address your manager has on file.
                </p>

                <label for="staff-email" class="mt-4 block text-sm font-semibold text-gray-900 mb-1.5">
                    Your email
                </label>
                <input id="staff-email" type="email" inputmode="email" autocomplete="email"
                       wire:model="loginEmail" wire:keydown.enter="sendCode"
                       placeholder="you@example.com"
                       class="w-full min-h-[3.25rem] rounded-control border-gray-300 text-base" />

                @if ($error)
                    <p class="mt-2 text-sm font-medium text-danger-700" role="alert" aria-live="assertive">{{ $error }}</p>
                @endif

                <button type="button" wire:click="sendCode"
                        wire:loading.attr="disabled" wire:target="sendCode"
                        class="btn-primary mt-4 min-h-[3.25rem] w-full justify-center">
                    <span wire:loading.remove wire:target="sendCode">Email me a code</span>
                    <span wire:loading wire:target="sendCode">Sending…</span>
                </button>
            @else
                <button type="button" wire:click="$set('codeSent', false)"
                        class="-ml-2 mb-2 flex min-h-[2.75rem] items-center gap-1 rounded-control px-2 text-xs font-medium text-gray-600 active:bg-gray-100">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Different address
                </button>

                <h2 class="text-base font-semibold text-gray-900">Enter your code</h2>

                @if ($notice)
                    <p class="mt-1 text-sm text-gray-600" aria-live="polite">{{ $notice }}</p>
                @endif

                {{-- One wide field rather than six boxes: six inputs fight every
                     mobile keyboard and autofill, and a code pasted from the
                     notification has to land somewhere. --}}
                <input type="text" inputmode="numeric" autocomplete="one-time-code"
                       maxlength="6" wire:model="emailCode" wire:keydown.enter="submitCode"
                       placeholder="000000"
                       class="mt-4 w-full min-h-[3.75rem] rounded-control border-gray-300 text-center text-2xl font-semibold tracking-[0.5em] tabular-nums" />

                @if ($error)
                    <p class="mt-2 text-sm font-medium text-danger-700" role="alert" aria-live="assertive">{{ $error }}</p>
                @endif

                <button type="button" wire:click="submitCode"
                        wire:loading.attr="disabled" wire:target="submitCode"
                        class="btn-primary mt-4 min-h-[3.25rem] w-full justify-center">
                    <span wire:loading.remove wire:target="submitCode">Sign in</span>
                    <span wire:loading wire:target="submitCode">Checking…</span>
                </button>

                <button type="button" wire:click="sendCode" wire:loading.attr="disabled" wire:target="sendCode"
                        class="mt-2 min-h-[2.75rem] w-full rounded-control text-sm font-medium text-brand-700 active:bg-brand-50">
                    Send another code
                </button>
            @endif

            <button type="button" wire:click="usePin"
                    class="mt-4 min-h-[2.75rem] w-full rounded-control border-t border-gray-100 pt-4 text-sm font-medium text-gray-600 active:bg-gray-50">
                Use my PIN instead
            </button>
        </div>

    @elseif (! $selected)
        {{-- Step one: where are you, and who are you --}}
        <div class="panel p-5">
            @if ($outlets->isEmpty())
                <div class="py-8 text-center">
                    <p class="text-sm font-medium text-gray-900">No outlets set up yet.</p>
                    <p class="mt-1 text-sm text-gray-600">
                        A manager adds outlets in Servora under Settings &rarr; Outlets.
                    </p>
                </div>
            @else
                {{-- Always shown, even for a single outlet. Hiding it when
                     there was only one choice meant staff could not see which
                     outlet they were about to sign in at, and a company that
                     had only given PINs to one branch appeared to have lost
                     the selector altogether. Seeing the answer is worth more
                     than saving the tap. --}}
                <label for="staff-outlet" class="block text-sm font-semibold text-gray-900 mb-1.5">
                    Your outlet
                </label>
                <select id="staff-outlet" wire:model.live="outletId"
                        class="w-full min-h-[3.25rem] rounded-control border-gray-300 text-base mb-4">
                    <option value="">Choose an outlet…</option>
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                    @endforeach
                </select>

                <label for="staff-name" class="block text-sm font-semibold text-gray-900 mb-1.5">
                    Your name
                </label>
                <select id="staff-name" wire:model.live="employeeId"
                        @disabled(! $outletId)
                        class="w-full min-h-[3.25rem] rounded-control border-gray-300 text-base
                               disabled:bg-gray-50 disabled:text-gray-400">
                    <option value="">
                        {{ $outletId ? 'Choose your name…' : 'Choose an outlet first' }}
                    </option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                    @endforeach
                </select>

                @if ($outletId && $employees->isEmpty())
                    <p class="mt-2 text-sm text-gray-600">
                        Nobody at this outlet has a PIN yet — sign in by email instead.
                    </p>
                @endif
            @endif
        </div>

        {{-- The way in for everyone without a PIN, which on a real company is
             most of the staff. Deliberately below the fold of the panel rather
             than beside it: the tablet on the counter is the common case and
             its users have a PIN. --}}
        <button type="button" wire:click="useEmail"
                class="mt-3 min-h-[3.25rem] w-full rounded-control border border-gray-200 bg-white text-sm font-medium text-gray-700 active:bg-gray-50">
            No PIN? Email me a code
        </button>

    @else
        {{-- Step two: PIN --}}
        <div class="panel p-5">
            <button type="button" wire:click="back"
                    class="-ml-2 mb-2 flex min-h-[2.75rem] items-center gap-1 rounded-control px-2 text-xs font-medium text-gray-600 active:bg-gray-100">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
                Not you?
            </button>

            <p class="text-center text-lg font-semibold text-gray-900">{{ $selected->name }}</p>
            <p class="mt-0.5 text-center text-sm text-gray-600">Enter your PIN</p>

            {{-- Dots rather than digits: a doorway is overlooked. --}}
            <div class="my-5 flex justify-center gap-3" role="img"
                 aria-label="{{ strlen($pin) }} of 6 digits entered"
                 wire:key="pin-dots-{{ strlen($pin) }}">
                @for ($i = 0; $i < 6; $i++)
                    <span class="h-3 w-3 rounded-full {{ $i < strlen($pin) ? 'bg-brand-600' : 'bg-gray-300' }}"></span>
                @endfor
            </div>

            @if ($error)
                <p class="mb-3 text-center text-sm font-medium text-danger-700" role="alert" aria-live="assertive">{{ $error }}</p>
            @endif

            <div class="grid grid-cols-3 gap-2">
                @foreach ([1,2,3,4,5,6,7,8,9] as $digit)
                    <button type="button" wire:click="press('{{ $digit }}')"
                            class="rounded-control border border-gray-200 bg-gray-50 py-4 text-xl font-semibold tabular-nums text-gray-900 active:bg-brand-50">
                        {{ $digit }}
                    </button>
                @endforeach

                <button type="button" wire:click="backspace" aria-label="Delete last digit"
                        class="flex items-center justify-center rounded-control border border-gray-200 bg-gray-50 py-4 text-gray-600 active:bg-gray-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M3 12l6.414 6.414a2 2 0 001.414.586H19a2 2 0 002-2V7a2 2 0 00-2-2h-8.172a2 2 0 00-1.414.586L3 12z"/>
                    </svg>
                </button>

                <button type="button" wire:click="press('0')"
                        class="rounded-control border border-gray-200 bg-gray-50 py-4 text-xl font-semibold tabular-nums text-gray-900 active:bg-brand-50">
                    0
                </button>

                <button type="button" wire:click="submit" wire:loading.attr="disabled" aria-label="Sign in"
                        @disabled(strlen($pin) < 4)
                        class="btn-primary flex items-center justify-center py-4 disabled:opacity-40">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                </button>
            </div>

            <button type="button" wire:click="useEmail"
                    class="mt-4 min-h-[2.75rem] w-full rounded-control border-t border-gray-100 pt-4 text-sm font-medium text-gray-600 active:bg-gray-50">
                Forgotten your PIN? Email me a code
            </button>
        </div>
    @endif
</div>
