{{--
    The live-session layout.

    Its own layout rather than layouts.lms, for a reason that is structural and
    not cosmetic: a live round is joinable WITHOUT an account — that is most of
    what makes it usable at a shift briefing where the casuals have not
    registered yet — and layouts.lms opens by reading
    `Auth::guard('lms')->user()->company_id` to build the SOP sidebar. An
    anonymous player would take a null dereference before rendering a single
    pixel.

    It is also the right shape independently. A phone held up during a game
    should show the question and nothing else; a sidebar of SOP categories is
    exactly the wrong furniture for it.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        // The staff PIN session, not a guard — a live round is joinable by
        // somebody who has never signed in, so this is null as often as not.
        $liveUser    = app(\App\Services\Staff\StaffSession::class)->employee();
        $liveCompany = $liveUser?->company ?? (app()->bound('currentCompany') ? app('currentCompany') : null);
        $liveBrand   = $liveCompany->brand_name ?? $liveCompany->name ?? 'Training';
    @endphp

    <title>{{ $title ?? 'Live session' }} | {{ $liveBrand }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <meta name="theme-color" content="#111827">
    <meta name="apple-mobile-web-app-capable" content="yes">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="font-sans antialiased bg-gray-50 min-h-screen">

<header class="bg-gray-900 text-white">
    <div class="mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-3">
        <span class="flex items-center gap-2 min-w-0">
            @if ($liveCompany?->logo)
                <span class="rounded-control bg-white px-2 py-1">
                    <x-brand-mark :company="$liveCompany" surface="light" size="h-6" width="max-w-[110px]" :alt="$liveBrand" />
                </span>
            @endif
            <span class="truncate text-sm font-semibold">{{ $liveBrand }}</span>
        </span>

        @if ($liveUser)
            <a href="{{ route('clock.staff.learn') }}" class="text-xs text-gray-300 hover:text-white">Training</a>
        @endif
    </div>
</header>

<main class="mx-auto max-w-3xl px-4 py-6">
    {{ $slot }}
</main>

<footer class="px-4 pb-8 text-center">
    <p class="text-xs text-gray-600">Training powered by Servora</p>
</footer>

{{-- Nothing on a live-session screen deletes anything today. It is here
     because the gate FAILS CLOSED: a control carrying data-confirm-delete on a
     page without the dialog has its click cancelled with no way through, so it
     presents as a broken button rather than as a missing dialog. Every
     authenticated layout carries it for that reason, and a test enforces it. --}}
<x-confirm-delete />

</body>
</html>
