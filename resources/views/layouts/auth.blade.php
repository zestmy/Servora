<!DOCTYPE html>
{{-- Sign-in, the free trial signup, the forgot/reset password pages and email
     verification. They
     wear the marketing site's header and footer, so a visitor who lands on
     one from a bookmark or a search result can still
     find Pricing, Help or the free trial, with the form in a split card in
     between: a brand panel on the left from lg up, the form on the right. --}}
@php
    // What the brand panel says, by page: sign-in (the default), the free
    // trial signup, and the forgot/reset password pair. A page picks one with
    // ['panel' => '...'] in its layout params.
    $panels = [
        'signin' => [
            'kicker' => 'Back on the pass',
            'headline' => 'Your kitchen&rsquo;s numbers, ready before service.',
            'points' => [
                'AI reads your supplier invoices, so nobody keys them in.',
                'Recipe costs that move when your prices do.',
                'Purchasing, stock counts and staff training in one place.',
            ],
        ],
        'signup' => [
            'kicker' => 'Free trial',
            'headline' => 'Run a tighter kitchen from day one.',
            'points' => [
                'Every module unlocked while you try it.',
                'No credit card needed to start.',
                'Set up in a couple of minutes, not a couple of weeks.',
            ],
        ],
        'reset' => [
            'kicker' => 'Locked out?',
            'headline' => 'Happens to the best chefs. Let&rsquo;s get you back in.',
            'points' => [
                'Enter the email you sign in with.',
                'Open the reset link we send you.',
                'Pick a new password and you&rsquo;re back on the line.',
            ],
        ],
        'verify' => [
            'kicker' => 'One last step',
            'headline' => 'Check your inbox, then we&rsquo;re open for service.',
            'points' => [
                'We&rsquo;ve emailed you a verification link.',
                'Click it to confirm the address is yours.',
                'Then you&rsquo;re straight into your dashboard.',
            ],
        ],
    ];
    $panel = $panels[$panel ?? 'signin'] ?? $panels['signin'];
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Sign in' }} | {{ config('app.name', 'Servora') }}</title>
    <link rel="icon" type="image/png" href="{{ brand_asset('favicon.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800|space-grotesk:500,600,700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="mk flex min-h-[100dvh] flex-col bg-gray-50 text-gray-900 antialiased">
    @auth
        @include('partials.impersonation-banner')
    @endauth

    @include('partials.marketing.header')

    <main id="main" class="relative isolate flex-1 px-4 py-10 sm:px-6 sm:py-16">
        <div aria-hidden="true" class="mk-grid-bg pointer-events-none absolute inset-x-0 top-0 -z-10 h-[480px]"></div>

        <div class="mx-auto grid w-full max-w-md overflow-hidden rounded-panel border border-gray-200/80 bg-white shadow-e2 lg:max-w-5xl lg:grid-cols-[1fr_1.05fr]">

            {{-- Brand panel: a reminder of what is behind the door. Hidden on
                 a phone, where the form has to sit above the fold. --}}
            <aside class="relative hidden overflow-hidden bg-navy-950 p-10 text-white lg:flex lg:flex-col">
                <div aria-hidden="true" class="mk-grid-bg-dark pointer-events-none absolute inset-0"></div>
                <div aria-hidden="true" class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-brand-500/25 blur-3xl"></div>

                <div class="relative">
                    <p class="mk-kicker mk-kicker-dark">{{ $panel['kicker'] }}</p>
                    <h2 class="mt-3 font-display text-3xl font-semibold leading-tight">
                        {!! $panel['headline'] !!}
                    </h2>
                    <ul class="mt-8 space-y-4 text-sm text-gray-300">
                        @foreach ($panel['points'] as $point)
                            <li class="flex gap-3">
                                <svg class="mt-0.5 h-5 w-5 flex-none text-brand-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                </svg>
                                <span>{!! $point !!}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <p class="relative mt-auto pt-10 text-sm text-gray-400">
                    Made for F&amp;B operators in Malaysia.
                </p>
            </aside>

            <div class="px-6 py-8 sm:px-10 sm:py-10">
                {{ $slot }}
            </div>
        </div>
    </main>

    @include('partials.marketing.footer')
</body>
</html>
