<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Join Referral Program — Servora</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-800 antialiased">
    <nav class="bg-white border-b border-gray-100">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="{{ route('marketing.home') }}"><img src="{{ asset('images/servora-logo-black.png') }}" alt="Servora" class="h-8"></a>
                <a href="{{ route('referral.program') }}" class="text-sm text-gray-600 hover:text-gray-900 transition">Back to Referral Program</a>
            </div>
        </div>
    </nav>

    <div class="max-w-md mx-auto px-4 py-16">
        <div class="text-center mb-8">
            <div class="w-14 h-14 bg-indigo-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Join Our Referral Program</h1>
            <p class="text-sm text-gray-500 mt-2">Earn commission by referring F&B businesses to Servora. No subscription needed.</p>
        </div>

        @if ($errors->any())
            <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
                @foreach ($errors->all() as $error) <p>{{ $error }}</p> @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('affiliate.register.submit') }}" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
            @csrf
            <div>
                <x-input-label for="name" value="Full Name *" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus />
            </div>
            <div>
                <x-input-label for="email" value="Email *" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required />
            </div>
            <div>
                <x-input-label for="phone" value="Phone (optional)" />
                <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone')" placeholder="+60-12-345-6789" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="password" value="Password *" />
                    <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required />
                </div>
                <div>
                    <x-input-label for="password_confirmation" value="Confirm *" />
                    <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required />
                </div>
            </div>
            <button type="submit" class="w-full py-3 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 transition">
                Create Affiliate Account
            </button>
            <p class="text-xs text-center text-gray-400">
                Already registered? <a href="{{ route('affiliate.login') }}" class="text-indigo-600 hover:underline">Log in</a>
            </p>
        </form>
    </div>
</body>
</html>
