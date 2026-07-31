{{-- min-h-full, not a vh value: the scroll area already has a definite
     height, so a viewport unit here would overshoot it and scroll. --}}
<div class="min-h-full flex flex-col justify-center">

    @php
        $logo = $company?->logo
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($company->logo)
            : null;
    @endphp

    <div class="text-center mb-6">
        @if ($logo)
            <img src="{{ $logo }}" alt="{{ $company?->brand_name ?? $company?->name }}"
                 class="mx-auto h-16 max-w-[200px] object-contain mb-3">
        @else
            {{-- No brand set: fall back to the app's own mark. --}}
            <div class="mx-auto w-14 h-14 rounded-2xl bg-indigo-600 flex items-center justify-center mb-3">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.997 1.997 0 013 12V7a4 4 0 014-4z"/>
                </svg>
            </div>
        @endif
        <h1 class="text-lg font-semibold text-gray-800">
            {{ $company?->brand_name ?? $company?->name ?? 'Labels' }}
        </h1>
        <p class="text-sm text-gray-500">Food safety labels</p>
    </div>

    @if (! $selected)
        {{-- Step one: who are you --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100">
                <p class="text-sm font-medium text-gray-700">Tap your name</p>
            </div>

            <div class="divide-y divide-gray-50 max-h-[55vh] overflow-y-auto">
                @forelse ($employees as $employee)
                    <button type="button" wire:click="selectEmployee({{ $employee->id }})"
                            wire:key="emp-{{ $employee->id }}"
                            class="w-full text-left px-4 py-4 active:bg-indigo-50 flex items-center gap-3">
                        <span class="w-9 h-9 shrink-0 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-sm font-semibold">
                            {{ strtoupper(mb_substr($employee->name, 0, 1)) }}
                        </span>
                        <span class="text-base text-gray-800">{{ $employee->name }}</span>
                    </button>
                @empty
                    <div class="px-4 py-10 text-center">
                        <p class="text-sm text-gray-500">Nobody has label access yet.</p>
                        <p class="text-xs text-gray-400 mt-1">
                            A manager sets up PINs in Servora under Labels &rarr; Staff Access.
                        </p>
                    </div>
                @endforelse
            </div>
        </div>
    @else
        {{-- Step two: PIN --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <button type="button" wire:click="back"
                    class="text-xs text-gray-400 active:text-gray-600 mb-3 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
                Not you?
            </button>

            <p class="text-center text-base font-semibold text-gray-800">{{ $selected->name }}</p>
            <p class="text-center text-xs text-gray-400 mt-0.5">Enter your PIN</p>

            {{-- Dots rather than digits: kitchens are overlooked. --}}
            <div class="flex justify-center gap-3 my-5" wire:key="pin-dots-{{ strlen($pin) }}">
                @for ($i = 0; $i < 6; $i++)
                    <span class="w-3 h-3 rounded-full {{ $i < strlen($pin) ? 'bg-indigo-600' : 'bg-gray-200' }}"></span>
                @endfor
            </div>

            @if ($error)
                <p class="text-center text-sm text-red-600 mb-3">{{ $error }}</p>
            @endif

            <div class="grid grid-cols-3 gap-2">
                @foreach ([1,2,3,4,5,6,7,8,9] as $digit)
                    <button type="button" wire:click="press('{{ $digit }}')"
                            class="py-4 rounded-xl bg-gray-50 border border-gray-200 text-xl font-semibold text-gray-800 active:bg-indigo-50">
                        {{ $digit }}
                    </button>
                @endforeach

                <button type="button" wire:click="backspace"
                        class="py-4 rounded-xl bg-gray-50 border border-gray-200 text-gray-500 active:bg-gray-100 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M3 12l6.414 6.414a2 2 0 001.414.586H19a2 2 0 002-2V7a2 2 0 00-2-2h-8.172a2 2 0 00-1.414.586L3 12z"/>
                    </svg>
                </button>

                <button type="button" wire:click="press('0')"
                        class="py-4 rounded-xl bg-gray-50 border border-gray-200 text-xl font-semibold text-gray-800 active:bg-indigo-50">
                    0
                </button>

                <button type="button" wire:click="submit" wire:loading.attr="disabled"
                        @disabled(strlen($pin) < 4)
                        class="py-4 rounded-xl bg-indigo-600 text-white font-semibold active:bg-indigo-700 disabled:opacity-40 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                </button>
            </div>
        </div>
    @endif
</div>
