<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password — Servora Affiliate</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-800 antialiased">
    <nav class="bg-white border-b border-gray-100">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="{{ route('marketing.home') }}"><img src="{{ asset('images/servora-logo-black.png') }}" alt="Servora" class="h-8"></a>
            </div>
        </div>
    </nav>

    <div class="max-w-md mx-auto px-4 py-16">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Reset Password</h1>
            <p class="text-sm text-gray-500 mt-2">Enter your new password below.</p>
        </div>

        @if ($errors->any())
            <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
                @foreach ($errors->all() as $error) <p>{{ $error }}</p> @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('affiliate.reset-password.submit') }}" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">
            <div>
                <x-input-label for="password" value="New Password" />
                <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autofocus />
            </div>
            <div>
                <x-input-label for="password_confirmation" value="Confirm Password" />
                <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required />
            </div>
            <button type="submit" class="w-full py-3 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 transition">
                Reset Password
            </button>
        </form>
    </div>
</body>
</html>
