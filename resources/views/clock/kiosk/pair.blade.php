{{--
    Kiosk pairing.

    The one screen in the kiosk that is not behind a device token, because it
    is how a device gets one. Deliberately plain: no Livewire, no staff
    session, nothing that has to boot before somebody standing at a counter
    with a new tablet can type six characters into it.

    A manager reads the code off the devices screen in the back office. Nobody
    signs in here — an admin password typed into a tablet that lives on a
    public counter is the thing this whole two-step exists to avoid.
--}}
@php
    $brandName = $company?->brand_name ?? $company?->name ?? 'Servora';
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b1220">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Clock Kiosk">
    <link rel="manifest" href="{{ route('clock.kiosk.manifest') }}">
    <title>Pair this kiosk | {{ $brandName }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-gray-900 text-gray-100 antialiased">

<div class="min-h-full flex items-center justify-center p-6">
    <div class="w-full max-w-md">

        <div class="text-center mb-8">
            {{-- x-brand-mark renders NOTHING when a company has no logo, and it
                 does not merge attributes — so the centring lives on a wrapper
                 and the company name is the fallback rather than a blank gap. --}}
            <div class="flex justify-center">
                <x-brand-mark :company="$company" surface="dark" size="h-8"
                              width="max-w-[120px]" :alt="$brandName" />
            </div>
            <p class="text-sm font-semibold uppercase tracking-wide text-brand-300">{{ $brandName }}</p>
            <h1 class="mt-4 text-2xl font-semibold text-white">Pair this kiosk</h1>
            {{-- gray-400 is correct here and only here: 6.99:1 on gray-900.
                 The same class on a light card would fail AA outright. --}}
            <p class="mt-2 text-sm text-gray-400">
                Ask a manager to add this tablet in HR &rsaquo; Clock kiosks, then key the code they give you.
            </p>
        </div>

        @if ($alreadyPaired)
            <div class="mb-5 rounded-surface border border-brand-500/40 bg-brand-500/10 p-4 text-sm text-brand-100">
                This tablet is already paired.
                <a href="{{ route('clock.kiosk.screen') }}" class="font-semibold underline">Open the kiosk</a>.
                Keying a new code below will move it to a different device record.
            </div>
        @endif

        @if ($notice)
            <div class="mb-5 rounded-surface border border-warning-500/40 bg-warning-500/10 p-4 text-sm text-warning-100">
                {{ $notice }}
            </div>
        @endif

        <form method="POST" action="{{ route('clock.kiosk.pair.store') }}"
              class="rounded-panel bg-gray-800 p-6 shadow-e3">
            @csrf

            <label for="code" class="block text-sm font-medium text-gray-300">Pairing code</label>

            {{-- Uppercase, wide tracking, and a big target: this is keyed on a
                 tablet by somebody holding it in one hand. autocomplete off
                 because a pairing code is single-use and a browser offering
                 the last one back is offering something already spent. --}}
            <input id="code" name="code" type="text" inputmode="latin" autocomplete="off"
                   autocapitalize="characters" spellcheck="false" maxlength="12" required autofocus
                   class="mt-2 w-full rounded-control border border-gray-600 bg-gray-900 px-4 py-4
                          text-center text-3xl font-semibold uppercase tracking-[0.35em] text-white
                          placeholder:tracking-normal placeholder:text-gray-600
                          focus:border-brand-500 focus:ring-2 focus:ring-brand-500/40"
                   placeholder="ABC123">

            @error('code')
                <p class="mt-2 text-sm text-danger-300">{{ $message }}</p>
            @enderror

            <button type="submit"
                    class="mt-5 w-full rounded-control bg-brand-600 px-4 py-4 text-base font-semibold
                           text-white shadow-btn hover:bg-brand-700 active:bg-brand-700
                           focus:outline-none focus:ring-2 focus:ring-brand-400 focus:ring-offset-2
                           focus:ring-offset-gray-800">
                Pair this tablet
            </button>

            <p class="mt-4 text-xs leading-relaxed text-gray-400">
                Codes expire after {{ \App\Models\ClockDevice::PAIRING_TTL_MINUTES }} minutes.
                Once paired, this tablet stays paired — you will not need to do this again
                unless it is reset or a manager revokes it.
            </p>
        </form>

        <p class="mt-6 text-center text-xs text-gray-500">
            Staff can always clock in from the Staff Portal on their own phone.
            <a href="{{ route('clock.staff.login') }}" class="underline hover:text-gray-300">Open it</a>
        </p>
    </div>
</div>

</body>
</html>
