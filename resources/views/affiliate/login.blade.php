<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Affiliate Login — Servora</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-800 antialiased">
    <nav class="bg-white border-b border-gray-100">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="{{ route('marketing.home') }}"><img src="{{ asset('images/servora-logo-black.png') }}" alt="Servora" class="h-8"></a>
                <a href="{{ route('referral.program') }}" class="text-sm text-gray-600 hover:text-gray-900 transition">Referral Program</a>
            </div>
        </div>
    </nav>

    <div class="max-w-md mx-auto px-4 py-16">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Affiliate Login</h1>
            <p class="text-sm text-gray-500 mt-2">Access your referral dashboard.</p>
        </div>

        @if (session('status'))
            <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
                @foreach ($errors->all() as $error) <p>{{ $error }}</p> @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('affiliate.login.submit') }}" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
            @csrf
            <div>
                <x-input-label for="email" value="Email" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required autofocus />
            </div>
            <div>
                <x-input-label for="password" value="Password" />
                <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required />
            </div>
            <div class="flex items-center justify-between">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="remember" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                    <span class="text-sm text-gray-600">Remember me</span>
                </label>
                <a href="{{ route('affiliate.forgot-password') }}" class="text-sm text-indigo-600 hover:underline">Forgot password?</a>
            </div>
            <button type="submit" class="w-full py-3 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 transition">
                Log In
            </button>
            <p class="text-xs text-center text-gray-400">
                Don't have an account? <a href="{{ route('affiliate.register') }}" class="text-indigo-600 hover:underline">Join the program</a>
            </p>
        </form>
    </div>
</body>
</html>
