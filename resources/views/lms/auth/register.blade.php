<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $company->brand_name ?? $company->name }} — Register</title>
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
                <p class="text-sm text-gray-500 mt-1">Training Portal Registration</p>
            </div>

            {{-- Register form --}}
            <div class="bg-white shadow-sm rounded-xl border border-gray-200 p-8">
                <h2 class="text-lg font-semibold text-gray-800 mb-6">Create Account</h2>

                <form method="POST" action="{{ route('lms.register.submit', $company->slug) }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700">Phone (optional)</label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone') }}"
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>

                    <div>
                        <label for="outlet_id" class="block text-sm font-medium text-gray-700">Branch / Outlet</label>
                        <select id="outlet_id" name="outlet_id"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">— Select your branch —</option>
                            @foreach ($outlets as $outlet)
                                <option value="{{ $outlet->id }}" {{ old('outlet_id') == $outlet->id ? 'selected' : '' }}>{{ $outlet->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <input type="password" id="password" name="password" required
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                        @error('password')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" required
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>

                    <button type="submit"
                            class="w-full py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition">
                        Register
                    </button>
                </form>

                <p class="mt-4 text-xs text-gray-400 text-center">
                    Your registration will need to be approved by a manager before you can access the training portal.
                </p>

                <div class="mt-6 text-center">
                    <a href="{{ route('lms.login', $company->slug) }}" class="text-sm text-indigo-600 hover:underline font-medium">Already have an account? Sign in</a>
                </div>
            </div>

            <p class="text-center text-xs text-gray-400 mt-6">Powered by Servora</p>
        </div>
    </div>
</body>
</html>
