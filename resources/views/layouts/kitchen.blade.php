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

<div x-data="{ mobileNavOpen: false }" class="flex h-screen overflow-hidden">

    {{-- Mobile scrim --}}
    <div x-show="mobileNavOpen" x-cloak
         x-transition:enter="transition-opacity ease-out duration-200"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @click="mobileNavOpen = false"
         class="fixed inset-0 bg-black/50 z-40 md:hidden"></div>

    {{-- Sidebar: off-canvas on mobile, fixed-width on desktop --}}
    <aside :class="mobileNavOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
           @click="if ($event.target.closest && $event.target.closest('a')) mobileNavOpen = false"
           class="fixed inset-y-0 left-0 z-50 w-64 md:relative md:inset-auto md:z-auto md:w-56 flex flex-col bg-gray-900 text-white flex-shrink-0 overflow-y-auto transform transition-transform duration-300 ease-in-out">

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

            @php
                // Find active group
                $ckActiveGroup = null;
                foreach ($kitchenNav as $g) {
                    if (! $g['label']) continue;
                    foreach ($g['items'] as $vi) {
                        if (request()->routeIs($vi['route']) || request()->routeIs($vi['route'] . '.*')) {
                            $ckActiveGroup = Str::slug($g['label']); break 2;
                        }
                    }
                }
            @endphp
            <div x-data="{
                    activeGroup: '{{ $ckActiveGroup ?? '' }}' || localStorage.getItem('ck_nav_active') || '',
                    toggle(key) {
                        this.activeGroup = this.activeGroup === key ? '' : key;
                        localStorage.setItem('ck_nav_active', this.activeGroup);
                    }
                 }">
            @foreach ($kitchenNav as $group)
                @if ($group['label'])
                    @php $groupSlug = Str::slug($group['label']); @endphp
                    <div class="mt-2">
                        <button @click="toggle('{{ $groupSlug }}')"
                                class="w-full flex items-center justify-between px-3 py-1.5 text-[10px] uppercase tracking-widest text-gray-500 font-semibold hover:text-gray-300 transition">
                            <span>{{ $group['label'] }}</span>
                            <svg :class="activeGroup === '{{ $groupSlug }}' && 'rotate-180'" class="w-3 h-3 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="activeGroup === '{{ $groupSlug }}'">
                            @foreach ($group['items'] as $item)
                                @php
                                    $href = route($item['route']) . (!empty($item['query']) ? '?' . $item['query'] : '');
                                    $isActive = request()->routeIs($item['route']) || request()->routeIs($item['route'] . '.*');
                                    if (!empty($item['query']) && $isActive) {
                                        parse_str($item['query'], $qp);
                                        $isActive = collect($qp)->every(fn($v, $k) => request()->query($k) === $v);
                                    }
                                @endphp
                                <a href="{{ $href }}"
                                   class="block rounded-lg text-sm font-medium transition-colors px-3 py-1.5 ml-1
                                          {{ $isActive ? 'bg-purple-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @else
                    @foreach ($group['items'] as $item)
                        @php $isActive = request()->routeIs($item['route']); @endphp
                        <a href="{{ route($item['route']) }}"
                           class="block rounded-lg text-sm font-medium transition-colors px-3 py-2
                                  {{ $isActive ? 'bg-purple-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                @endif
            @endforeach
            </div>
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
    <main class="flex-1 overflow-y-auto">
        {{-- Mobile top bar --}}
        <div class="md:hidden sticky top-0 z-30 flex items-center h-14 px-3 bg-gray-900 text-white shadow">
            <button @click="mobileNavOpen = true"
                    class="-ml-1 p-2 rounded text-gray-300 hover:bg-gray-800 hover:text-white"
                    aria-label="Open menu">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <img src="/images/servora-logo-white.png" alt="Servora" class="h-7 ml-1">
            <span class="ml-2 px-2 py-0.5 rounded bg-purple-900/60 text-[10px] uppercase tracking-widest text-purple-200 font-semibold">Kitchen</span>
            @if (! empty($title))
                <span class="ml-auto text-sm text-gray-300 truncate max-w-[40%]">{{ $title }}</span>
            @endif
        </div>

        <div class="p-4 sm:p-6">
            {{ $slot }}
        </div>
    </main>
</div>

</body>
</html>
