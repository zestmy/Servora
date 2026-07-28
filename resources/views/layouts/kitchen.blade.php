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
    @include('partials.nav-theme')
</head>
<body class="font-sans antialiased bg-gray-100">

@include('partials.impersonation-banner')

@php
    $authUser = Auth::user();
    $activeKitchen = $authUser->activeKitchen();
    $hasOutletAccess = $authUser->outlets()->count() > 0 || $authUser->canViewAllOutlets();
@endphp

<div x-data="{
        mobileNavOpen: false,
        sidebarOpen: localStorage.getItem('ck_sidebar') !== '0',
        toggleSidebar() {
            this.sidebarOpen = ! this.sidebarOpen;
            localStorage.setItem('ck_sidebar', this.sidebarOpen ? '1' : '0');
        },
        // Expanded when the desktop toggle is on OR the mobile drawer is open
        // (the drawer always renders at full width).
        get sidebarExpanded() { return this.sidebarOpen || this.mobileNavOpen; },
        navTheme: localStorage.getItem('nav_theme') || 'dark',
        toggleNavTheme() {
            this.navTheme = this.navTheme === 'dark' ? 'light' : 'dark';
            localStorage.setItem('nav_theme', this.navTheme);
        },
     }" class="flex h-screen overflow-hidden">

    {{-- Mobile scrim --}}
    <div x-show="mobileNavOpen" x-cloak
         x-transition:enter="transition-opacity ease-out duration-200"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @click="mobileNavOpen = false"
         class="fixed inset-0 bg-black/50 z-40 md:hidden"></div>

    {{-- Sidebar: off-canvas on mobile, fixed-width on desktop --}}
    <aside :class="{
               '-translate-x-full md:translate-x-0': ! mobileNavOpen,
               'translate-x-0': mobileNavOpen,
               'md:w-16': ! sidebarOpen,
               'md:w-56': sidebarOpen,
           }"
           @click="if ($event.target.closest && $event.target.closest('a')) mobileNavOpen = false"
           :data-nav-theme="navTheme"
           class="fixed inset-y-0 left-0 z-50 w-64 md:relative md:inset-auto md:z-auto flex flex-col bg-gray-900 text-white flex-shrink-0 overflow-y-auto transform transition-all duration-300 ease-in-out">

        {{-- Logo + collapse toggle --}}
        <div class="flex items-center h-14 bg-gray-800 flex-shrink-0 gap-2" :class="sidebarExpanded ? 'px-4' : 'px-2'">
            <img x-show="sidebarExpanded" src="/images/servora-logo-white.png" alt="Servora" class="h-9">
            <button @click="toggleSidebar()"
                    :class="sidebarOpen ? 'ml-auto' : 'mx-auto'"
                    title="Toggle sidebar"
                    class="hidden md:flex flex-shrink-0 w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:text-white hover:bg-gray-700 transition">
                <svg x-show="sidebarOpen" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7M18 19l-7-7 7-7" />
                </svg>
                <svg x-show="! sidebarOpen" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>

        {{-- Kitchen badge --}}
        <div class="bg-purple-900/40 border-b border-gray-700" :class="sidebarExpanded ? 'px-4 py-3' : 'px-2 py-3 text-center'">
            <template x-if="! sidebarExpanded">
                <span class="text-lg" title="Central Kitchen — {{ $activeKitchen?->name ?? 'Kitchen' }}">🍳</span>
            </template>
            <div x-show="sidebarExpanded">
                <p class="text-[10px] uppercase tracking-widest text-purple-300 font-semibold">Central Kitchen</p>
                <p class="text-sm font-medium text-white truncate">{{ $activeKitchen?->name ?? 'Kitchen' }}</p>
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 py-3 space-y-0.5" :class="sidebarExpanded ? 'px-3' : 'px-2'">
            @php
                $ckIcons = [
                    'Production'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 16.121A3 3 0 1012.015 11L11 14H9c0 .768.293 1.536.879 2.121z"/>',
                    'Procurement' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>',
                    'Operations'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>',
                    'Insights'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>',
                    'System'      => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
                ];
                $kitchenNav = [
                    ['label' => null, 'items' => [
                        ['route' => 'kitchen.index', 'label' => 'Dashboard', 'svg' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                    ]],
                    ['label' => 'Production', 'items' => [
                        // List first, create second — the sidebar previously jumped
                        // straight to the create form while the page tab showed the list
                        ['route' => 'kitchen.index', 'label' => 'Production Orders', 'query' => 'tab=orders'],
                        ['route' => 'kitchen.orders.create', 'label' => 'New Production Order'],
                        ['route' => 'kitchen.recipes.index', 'label' => 'Production Recipes'],
                        ['route' => 'kitchen.index', 'label' => 'Inventory', 'query' => 'tab=inventory'],
                    ]],
                    ['label' => 'Procurement', 'items' => [
                        ['route' => 'ingredients.index', 'label' => 'Market List'],
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
                        {{-- Collapsed: icon-only button that expands the sidebar and opens the group --}}
                        <button x-show="! sidebarExpanded" @click="toggleSidebar(); activeGroup = '{{ $groupSlug }}'"
                                title="{{ $group['label'] }}"
                                class="w-full flex justify-center py-2 text-gray-400 hover:text-white hover:bg-gray-700 rounded-lg transition">
                            @if (isset($ckIcons[$group['label']]))
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">{!! $ckIcons[$group['label']] !!}</svg>
                            @endif
                        </button>
                        <button x-show="sidebarExpanded" @click="toggle('{{ $groupSlug }}')"
                                class="w-full flex items-center justify-between px-3 py-1.5 text-[10px] uppercase tracking-widest text-gray-500 font-semibold hover:text-gray-300 transition">
                            <span class="flex items-center gap-2">
                                @if (isset($ckIcons[$group['label']]))
                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">{!! $ckIcons[$group['label']] !!}</svg>
                                @endif
                                <span>{{ $group['label'] }}</span>
                            </span>
                            <svg :class="activeGroup === '{{ $groupSlug }}' && 'rotate-180'" class="w-3 h-3 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="sidebarExpanded && activeGroup === '{{ $groupSlug }}'">
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
                           title="{{ $item['label'] }}"
                           class="flex items-center gap-2.5 rounded-lg text-sm font-medium transition-colors py-2
                                  {{ $isActive ? 'bg-purple-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}"
                           :class="sidebarExpanded ? 'px-3' : 'px-2 justify-center'">
                            @if (!empty($item['svg']))
                                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['svg'] }}"/></svg>
                            @endif
                            <span x-show="sidebarExpanded" class="whitespace-nowrap">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                @endif
            @endforeach
            </div>
        </nav>

        {{-- Bottom: Company + User panel (profile, workspace switch, theme, sign out) --}}
        <div class="flex-shrink-0 border-t border-gray-700 p-2" x-data="{ userOpen: false }">
            @php $ckCompany = $authUser->company; @endphp
            @if ($ckCompany)
                <div x-show="sidebarExpanded" class="flex items-center gap-2 px-2 pb-2 mb-1 border-b border-gray-700/70">
                    <span class="text-sm leading-none">🏢</span>
                    <span class="flex-1 text-xs font-medium text-gray-300 truncate" title="{{ $ckCompany->name }}">{{ $ckCompany->name }}</span>
                </div>
            @endif

            <div x-show="userOpen" x-cloak
                 class="mb-1 rounded-lg border border-gray-700 bg-gray-800 py-1">
                <a href="{{ route('profile') }}"
                   class="flex items-center gap-2.5 px-3 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Profile
                </a>
                @if ($hasOutletAccess)
                    <a href="{{ route('workspace.switch', 'outlet') }}"
                       class="flex items-center gap-2.5 px-3 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        Switch to Outlet Mode
                    </a>
                @endif
                <button @click="toggleNavTheme()"
                        class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition text-left">
                    <svg x-show="navTheme === 'dark'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    <svg x-show="navTheme === 'light'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                    <span x-text="navTheme === 'dark' ? 'Light navigation' : 'Dark navigation'"></span>
                </button>
                <form method="POST" action="{{ route('logout') }}" class="border-t border-gray-700 mt-1 pt-1">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-red-400 hover:bg-gray-700 hover:text-red-300 transition text-left">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Sign Out
                    </button>
                </form>
            </div>

            <button @click="if (! sidebarExpanded) toggleSidebar(); userOpen = ! userOpen"
                    :class="sidebarExpanded ? 'px-2' : 'px-0 justify-center'"
                    class="w-full flex items-center gap-2 py-1.5 rounded-lg hover:bg-gray-800 transition">
                @if ($authUser->avatar)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($authUser->avatar) }}" alt=""
                         class="w-7 h-7 rounded-full object-cover flex-shrink-0" />
                @else
                    <div class="w-7 h-7 bg-purple-600 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0">
                        {{ strtoupper(substr($authUser->name, 0, 2)) }}
                    </div>
                @endif
                <div x-show="sidebarExpanded" class="flex-1 min-w-0 text-left">
                    <p class="text-xs font-medium text-white truncate">{{ $authUser->name }}</p>
                    <p class="text-[10px] text-purple-300 truncate">{{ $authUser->displayDesignation() }}</p>
                </div>
                <svg x-show="sidebarExpanded" :class="userOpen && 'rotate-180'" class="h-3.5 w-3.5 text-gray-400 transition-transform flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
            </button>
        </div>
    </aside>

    {{-- Main Content --}}
    <main class="flex-1 overflow-y-auto">
        {{-- Mobile top bar --}}
        <div class="md:hidden sticky top-0 z-30 flex items-center h-14 px-3 bg-gray-900 text-white shadow">
            <button @click="mobileNavOpen = true"
                    class="-ml-2 p-3 rounded text-gray-300 hover:bg-gray-800 hover:text-white"
                    aria-label="Open menu">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <img src="/images/servora-logo-white.png" alt="Servora" class="h-7">
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
