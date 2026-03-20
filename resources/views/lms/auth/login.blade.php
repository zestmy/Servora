<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $company->brand_name ?? $company->name }} — Training Portal Login</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-50">
    <div class="min-h-screen flex flex-col items-center justify-center px-4">
        <div class="w-full max-w-md">
            {{-- Branding --}}
            <div class="text-center mb-8">
                @if ($company->logo)
                    <img src="{{ Storage::disk('public')->url($company->logo) }}" alt="{{ $company->brand_name ?? $company->name }}"
                         class="h-14 max-w-[200px] mx-auto mb-4 object-contain">
                @endif
                <h1 class="text-2xl font-bold text-gray-900">{{ $company->brand_name ?? $company->name }}</h1>
                <p class="text-sm text-gray-500 mt-1">Training Portal</p>
                @if ($company->brand_name && $company->name !== $company->brand_name)
                    <p class="text-xs text-gray-400 mt-0.5">{{ $company->name }}</p>
                @endif
            </div>

            {{-- Flash messages --}}
            @if (session('success'))
                <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Login form --}}
            <div class="bg-white shadow-sm rounded-xl border border-gray-200 p-8">
                <h2 class="text-lg font-semibold text-gray-800 mb-6">Sign In</h2>

                <form method="POST" action="{{ route('lms.login.submit', $company->slug) }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <input type="password" id="password" name="password" required
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" id="remember" name="remember"
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <label for="remember" class="ml-2 text-sm text-gray-600">Remember me</label>
                    </div>

                    <button type="submit"
                            class="w-full py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition">
                        Sign In
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-500">
                        Don't have an account?
                        <a href="{{ route('lms.register', $company->slug) }}" class="text-indigo-600 hover:underline font-medium">Register here</a>
                    </p>
                </div>
            </div>

            <p class="text-center text-xs text-gray-400 mt-6">Powered by Servora</p>
        </div>
    </div>
</body>
</html>
