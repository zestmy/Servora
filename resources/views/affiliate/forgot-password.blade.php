<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password — Servora Affiliate</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-800 antialiased">
    <nav class="bg-white border-b border-gray-100">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="{{ route('marketing.home') }}"><img src="{{ asset('images/servora-logo-black.png') }}" alt="Servora" class="h-8"></a>
                <a href="{{ route('affiliate.login') }}" class="text-sm text-gray-600 hover:text-gray-900 transition">Back to Login</a>
            </div>
        </div>
    </nav>

    <div class="max-w-md mx-auto px-4 py-16">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Forgot Password</h1>
            <p class="text-sm text-gray-500 mt-2">Enter your email and we'll send you a reset link.</p>
        </div>

        @if (session('status'))
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-center">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <p class="text-sm text-gray-700 mb-4">{{ session('status') }}</p>
                <a href="{{ route('affiliate.login') }}" class="inline-block px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    Back to Login
                </a>
            </div>
        @else
            @if ($errors->any())
                <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
                    @foreach ($errors->all() as $error) <p>{{ $error }}</p> @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('affiliate.forgot-password.submit') }}" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                @csrf
                <div>
                    <x-input-label for="email" value="Email Address" />
                    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required autofocus />
                </div>
                <button type="submit" class="w-full py-3 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 transition">
                    Send Reset Link
                </button>
            </form>
        @endif
    </div>
</body>
</html>
