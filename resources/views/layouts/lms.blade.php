<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $lmsCompany = Auth::guard('lms')->user()?->company;
        $brandName = $lmsCompany->brand_name ?? $lmsCompany->name ?? 'Training';
    @endphp

    <title>{{ $brandName }} — {{ $title ?? 'Training Portal' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="font-sans antialiased bg-gray-50">

    {{-- Top Navbar --}}
    <nav class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                {{-- Brand --}}
                <a href="{{ route('lms.dashboard') }}" class="flex items-center gap-3">
                    @if ($lmsCompany?->logo)
                        <img src="{{ Storage::disk('public')->url($lmsCompany->logo) }}" alt="{{ $brandName }}" class="h-9 max-w-[140px] object-contain">
                    @endif
                    <div>
                        <p class="text-sm font-bold text-gray-900 leading-tight">{{ $brandName }}</p>
                        @if ($lmsCompany?->brand_name && $lmsCompany->name !== $lmsCompany->brand_name)
                            <p class="text-xs text-gray-400 leading-tight">{{ $lmsCompany->name }}</p>
                        @endif
                    </div>
                </a>

                {{-- Right side --}}
                <div class="flex items-center gap-4" x-data="{ open: false }">
                    <a href="{{ route('lms.dashboard') }}" class="text-sm text-gray-600 hover:text-gray-900 font-medium transition">All SOPs</a>
                    <div class="relative">
                        <button @click="open = !open" class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 transition">
                            <div class="w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center text-white font-bold text-xs">
                                {{ strtoupper(substr(Auth::guard('lms')->user()->name, 0, 2)) }}
                            </div>
                            <span class="hidden sm:inline font-medium">{{ Auth::guard('lms')->user()->name }}</span>
                        </button>
                        <div x-show="open" @click.away="open = false" x-cloak
                             class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                            <div class="px-4 py-2 border-b border-gray-100">
                                <p class="text-sm font-medium text-gray-800">{{ Auth::guard('lms')->user()->name }}</p>
                                <p class="text-xs text-gray-400">{{ Auth::guard('lms')->user()->email }}</p>
                            </div>
                            <form method="POST" action="{{ route('lms.logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-50 transition">
                                    Sign Out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    {{-- Content --}}
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{ $slot }}
    </main>

    {{-- Footer --}}
    <footer class="border-t border-gray-200 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-center">
            <p class="text-xs text-gray-400">&copy; {{ date('Y') }} {{ $lmsCompany?->name ?? 'Company' }}. Training Portal powered by Servora.</p>
        </div>
    </footer>

</body>
</html>
