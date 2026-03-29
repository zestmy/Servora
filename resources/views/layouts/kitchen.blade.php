<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Servora') }} — Kitchen — {{ $title ?? 'Dashboard' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="font-sans antialiased bg-gray-100">

@php
    $authUser = Auth::user();
    $activeKitchen = $authUser->activeKitchen();
    $hasOutletAccess = $authUser->outlets()->count() > 0 || $authUser->canViewAllOutlets();
@endphp

<div class="flex h-screen overflow-hidden">
    {{-- Sidebar --}}
    <aside class="w-56 flex flex-col bg-gray-900 text-white flex-shrink-0 overflow-y-auto">

        {{-- Logo --}}
        <div class="flex items-center h-14 px-4 bg-gray-800 flex-shrink-0">
            <img src="/images/servora-logo-white.png" alt="Servora" class="h-9">
        </div>

        {{-- Kitchen badge --}}
        <div class="px-4 py-3 bg-purple-900/40 border-b border-gray-700">
            <p class="text-[10px] uppercase tracking-widest text-purple-300 font-semibold">Central Kitchen</p>
            <p class="text-sm font-medium text-white truncate">{{ $activeKitchen?->name ?? 'Kitchen' }}</p>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 py-3 px-3 space-y-0.5">
            @php
                $kitchenNav = [
                    ['label' => null, 'items' => [
                        ['route' => 'kitchen.index', 'label' => 'Dashboard'],
                    ]],
                    ['label' => 'Production', 'items' => [
                        ['route' => 'kitchen.recipes.index', 'label' => 'Production Recipes'],
                        ['route' => 'kitchen.orders.create', 'label' => 'Production Orders'],
                        ['route' => 'kitchen.index', 'label' => 'Inventory', 'query' => 'tab=inventory'],
                    ]],
                    ['label' => 'Procurement', 'items' => [
                        ['route' => 'ingredients.index', 'label' => 'Ingredients'],
                        ['route' => 'purchasing.index', 'label' => 'Purchasing'],
                    ]],
                    ['label' => 'Operations', 'items' => [
                        ['route' => 'kitchen.index', 'label' => 'Prep Requests', 'query' => 'tab=requests'],
                        ['route' => 'inventory.index', 'label' => 'Stock Takes'],
                    ]],
                    ['label' => 'Insights', 'items' => [
                        ['route' => 'reports.production-history', 'label' => 'Production History'],
                        ['route' => 'reports.yield-analysis', 'label' => 'Yield Analysis'],
                    ]],
                    ['label' => 'System', 'items' => [
                        ['route' => 'settings.index', 'label' => 'Settings'],
                    ]],
                ];
            @endphp

            @foreach ($kitchenNav as $group)
                @if ($group['label'])
                    <p class="mt-3 mb-1 px-3 text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $group['label'] }}</p>
                @endif
                @foreach ($group['items'] as $item)
                    @php
                        $href = route($item['route']) . (!empty($item['query']) ? '?' . $item['query'] : '');
                        $isActive = request()->routeIs($item['route']) || request()->routeIs($item['route'] . '.*');
                        if (!empty($item['query'])) {
                            parse_str($item['query'], $qp);
                            if ($isActive) $isActive = collect($qp)->every(fn($v, $k) => request()->query($k) === $v);
                        }
                    @endphp
                    <a href="{{ $href }}"
                       class="block rounded-lg text-sm font-medium transition-colors px-3 py-1.5
                              {{ $isActive ? 'bg-purple-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            @endforeach
        </nav>

        {{-- Bottom: Workspace Switcher + User --}}
        <div class="flex-shrink-0 border-t border-gray-700 p-3 space-y-2">
            @if ($hasOutletAccess)
                <a href="{{ route('workspace.switch', 'outlet') }}"
                   class="block w-full text-center px-3 py-1.5 text-xs font-medium text-gray-300 border border-gray-600 rounded-lg hover:bg-gray-700 hover:text-white transition">
                    Switch to Outlet Mode
                </a>
            @endif
            <div class="flex items-center gap-2 px-1">
                <div class="w-7 h-7 bg-purple-600 rounded-full flex items-center justify-center text-xs font-bold text-white">
                    {{ strtoupper(substr($authUser->name, 0, 2)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-white truncate">{{ $authUser->name }}</p>
                    <p class="text-[10px] text-purple-300 truncate">{{ $authUser->displayDesignation() }}</p>
                </div>
            </div>
        </div>
    </aside>

    {{-- Main Content --}}
    <main class="flex-1 overflow-y-auto p-6">
        {{ $slot }}
    </main>
</div>

</body>
</html>
