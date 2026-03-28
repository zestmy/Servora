<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} — Servora Supplier Portal</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased">
    @php $supplierUser = Auth::guard('supplier')->user(); @endphp

    {{-- Top nav --}}
    <nav class="bg-white border-b border-gray-100 sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-6">
                    <a href="{{ route('supplier.dashboard') }}">
                        <img src="{{ asset('images/servora-logo-black.png') }}" alt="Servora" class="h-8">
                    </a>
                    <div class="hidden sm:flex items-center gap-4">
                        @php
                            $navItems = [
                                ['route' => 'supplier.dashboard', 'label' => 'Dashboard'],
                                ['route' => 'supplier.products', 'label' => 'Products'],
                                ['route' => 'supplier.orders', 'label' => 'Orders'],
                                ['route' => 'supplier.invoices', 'label' => 'Invoices'],
                                ['route' => 'supplier.quotations', 'label' => 'Quotations'],
                                ['route' => 'supplier.credit-notes', 'label' => 'Credit Notes'],
                                ['route' => 'supplier.profile', 'label' => 'Profile'],
                            ];
                        @endphp
                        @foreach ($navItems as $item)
                            <a href="{{ route($item['route']) }}"
                               class="text-sm font-medium transition {{ request()->routeIs($item['route']) ? 'text-indigo-600' : 'text-gray-500 hover:text-gray-800' }}">
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-500">{{ $supplierUser->supplier?->name }}</span>
                    <form method="POST" action="{{ route('supplier.logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-sm text-gray-400 hover:text-gray-600 transition">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{ $slot }}
    </main>
</body>
</html>
