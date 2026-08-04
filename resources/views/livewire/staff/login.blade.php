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
            <div class="mx-auto w-14 h-14 rounded-2xl bg-brand-600 flex items-center justify-center mb-3">
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

    @if (! $selected)
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
                        Nobody at this outlet has a PIN yet. Ask your manager.
                    </p>
                @endif
            @endif
        </div>
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
        </div>
    @endif
</div>
