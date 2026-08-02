<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'Sign in' }} | {{ config('app.name', 'Servora') }}</title>
        <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    {{-- 100dvh rather than min-h-screen: on iOS Safari the address bar makes
         100vh taller than the visible area, which pushed the submit button
         under the fold on a phone. --}}
    <body class="min-h-[100dvh] bg-gray-100 font-sans text-gray-900 antialiased">
        @auth
            @include('partials.impersonation-banner')
        @endauth

        <div class="flex min-h-[100dvh] flex-col items-center justify-center px-4 py-10">
            <a href="/" wire:navigate class="flex-shrink-0">
                <img src="{{ asset('images/servora-logo-black.png') }}" alt="Servora" class="h-11 w-auto">
            </a>

            <div class="mt-8 w-full sm:max-w-md">
                <div class="panel px-6 py-7 sm:px-8">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
