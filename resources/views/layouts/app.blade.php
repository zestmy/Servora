<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Servora') }} — {{ $title ?? 'Dashboard' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }

        /* ── Page transition: top progress bar ──────────────────────────── */
        #nav-progress {
            position: fixed; top: 0; left: 0; height: 3px; z-index: 9999;
            background: linear-gradient(90deg, #6366f1, #818cf8);
            width: 0; opacity: 0;
            transition: none;
            pointer-events: none;
        }
        #nav-progress.running {
            opacity: 1;
            animation: nav-grow 8s cubic-bezier(.2,.6,.4,1) forwards;
        }
        #nav-progress.done {
            width: 100% !important; opacity: 0;
            transition: opacity .3s .05s;
            animation: none;
        }
        @keyframes nav-grow { 0%{width:0} 30%{width:55%} 60%{width:78%} 100%{width:92%} }

        /* ── Page transition: content fade-in ───────────────────────────── */
        @keyframes page-enter { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
        .page-enter { animation: page-enter .25s ease-out both; }
    </style>
</head>
<body class="font-sans antialiased bg-gray-100">

<div x-data="{
        sidebarOpen: localStorage.getItem('sidebar') !== '0',
        toggleSidebar() {
            this.sidebarOpen = !this.sidebarOpen;
            localStorage.setItem('sidebar', this.sidebarOpen ? '1' : '0');
        },
        userMenuOpen: false,
        userMenuStyle: {},
        openUserMenu() {
            const rect = this.$refs.userBtn.getBoundingClientRect();
            this.userMenuStyle = {
                position: 'fixed',
                bottom: (window.innerHeight - rect.top + 4) + 'px',
                left: rect.left + 'px',
                width: rect.width + 'px',
            };
            this.userMenuOpen = true;
        }
     }"
     class="flex h-screen overflow-hidden">

    {{-- ── Sidebar ──────────────────────────────────────────────────────── --}}
    <aside :class="sidebarOpen ? 'w-64' : 'w-16'"
           class="flex flex-col bg-gray-900 text-white flex-shrink-0 transition-[width] duration-300 ease-in-out overflow-hidden">

        {{-- Logo + toggle --}}
        <div class="flex items-center h-16 px-3 bg-gray-800 flex-shrink-0 gap-2">
            <div x-show="sidebarOpen"
                 x-transition:enter="transition-opacity duration-200 delay-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity duration-75"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="flex-1 overflow-hidden whitespace-nowrap">
                <img src="/images/servora-logo-white.png" alt="Servora" class="h-11">
            </div>

            <button @click="toggleSidebar()"
                    :class="sidebarOpen ? '' : 'mx-auto'"
                    title="Toggle sidebar"
                    class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-white hover:bg-gray-700 transition">
                <svg x-show="sidebarOpen" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7M18 19l-7-7 7-7" />
                </svg>
                <svg x-show="!sidebarOpen" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 overflow-y-auto py-4 space-y-1" :class="sidebarOpen ? 'px-3' : 'px-2'">
            @php
                $authUser = Auth::user();
                $isSystemRole = $authUser->hasRole(['Super Admin', 'System Admin']);

                // System-level roles only see Dashboard + Settings
                // Business roles see modules based on actual role permissions
                $allNavItems = [
                    ['route' => 'dashboard',         'icon' => '🏠', 'label' => 'Dashboard',    'permission' => null],
                    ['route' => 'ingredients.index', 'icon' => '🥕', 'label' => 'Ingredients',  'permission' => 'ingredients.view'],
                    ['route' => 'recipes.index',     'icon' => '📋', 'label' => 'Recipes',      'permission' => 'recipes.view'],
                    ['route' => 'purchasing.index',  'icon' => '🛒', 'label' => 'Purchasing',   'permission' => 'purchasing.view'],
                    ['route' => 'sales.index',       'icon' => '💰', 'label' => 'Sales',        'permission' => 'sales.view'],
                    ['route' => 'inventory.index',   'icon' => '📦', 'label' => 'Inventory',    'permission' => 'inventory.view'],
                    ['route' => 'reports.hub',       'icon' => '📊', 'label' => 'Reports',      'permission' => 'reports.view'],
                    ['route' => 'settings.lms-users', 'icon' => '📖', 'label' => 'Training',    'permission' => 'settings.view'],
                    ['route' => 'analytics.index',   'icon' => '🤖', 'label' => 'AI Analysis', 'permission' => null, 'role' => ['Super Admin', 'System Admin', 'Company Admin', 'Business Manager', 'Operations Manager'], 'feature' => 'analytics'],
                    ['route' => 'settings.index',    'icon' => '⚙️',  'label' => 'Settings',     'permission' => 'settings.view'],
                    ['route' => 'billing.index',     'icon' => '💳', 'label' => 'Billing',      'permission' => null, 'role' => ['Super Admin', 'Company Admin', 'Business Manager']],
                    ['route' => 'referral.dashboard', 'icon' => '🔗', 'label' => 'Refer & Earn', 'permission' => null],
                ];

                // Admin nav items (System Admin only)
                $adminNavItems = [
                    ['route' => 'admin.plans.index',         'icon' => '📦', 'label' => 'Plans',         'permission' => null],
                    ['route' => 'admin.subscriptions.index', 'icon' => '💳', 'label' => 'Subscriptions', 'permission' => null],
                    ['route' => 'admin.trials.index',        'icon' => '⏱️', 'label' => 'Trials',        'permission' => null],
                    ['route' => 'admin.referrals.index',     'icon' => '🔗', 'label' => 'Referrals',     'permission' => null],
                    ['route' => 'admin.company-health',      'icon' => '💚', 'label' => 'Health',        'permission' => null],
                    ['route' => 'admin.announcements',       'icon' => '📢', 'label' => 'Announcements', 'permission' => null],
                    ['route' => 'admin.pages',               'icon' => '📄', 'label' => 'Pages',         'permission' => null],
                ];

                if ($isSystemRole) {
                    // System roles: Dashboard + Settings only
                    $navItems = array_filter($allNavItems, fn($i) => in_array($i['route'], ['dashboard', 'settings.index']));
                } else {
                    // Business roles: filter by actual role permissions (not Gate::before)
                    $navItems = array_filter($allNavItems, function($i) use ($authUser) {
                        if (!empty($i['role']) && !$authUser->hasRole($i['role'])) return false;
                        if ($i['permission'] !== null && !$authUser->hasPermissionTo($i['permission'])) return false;
                        // Feature flag check — hide nav items for disabled features
                        if (!empty($i['feature']) && $authUser->company) {
                            if (!app(\App\Services\SubscriptionService::class)->canUseFeature($authUser->company, $i['feature'])) return false;
                        }
                        return true;
                    });
                }
            @endphp

            @foreach ($navItems as $item)
                @php
                    $isActive = request()->routeIs($item['route']) || request()->routeIs($item['route'] . '.*');
                    if ($item['route'] === 'reports.hub') $isActive = $isActive || request()->routeIs('reports.*');
                @endphp
                <a href="{{ route($item['route']) }}"
                   :title="!sidebarOpen ? '{{ $item['label'] }}' : ''"
                   class="flex items-center rounded-lg text-sm font-medium transition-colors
                          {{ $isActive ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}"
                   :class="sidebarOpen ? 'gap-3 px-4 py-2.5' : 'justify-center px-2 py-3'">
                    <span class="flex-shrink-0 text-base leading-none">{{ $item['icon'] }}</span>
                    <span x-show="sidebarOpen"
                          x-transition:enter="transition-opacity duration-150 delay-100"
                          x-transition:enter-start="opacity-0"
                          x-transition:enter-end="opacity-100"
                          x-transition:leave="transition-opacity duration-75"
                          x-transition:leave-start="opacity-100"
                          x-transition:leave-end="opacity-0"
                          class="whitespace-nowrap overflow-hidden">
                        {{ $item['label'] }}
                    </span>
                </a>
            @endforeach

            {{-- Admin Section (System Admin only) --}}
            @if ($isSystemRole)
                <div class="mt-4 pt-3 border-t border-gray-700">
                    <p x-show="sidebarOpen" class="px-4 pb-2 text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Admin</p>
                    @foreach ($adminNavItems as $item)
                        @php $isActive = request()->routeIs($item['route']) || request()->routeIs($item['route'] . '.*'); @endphp
                        <a href="{{ route($item['route']) }}"
                           :title="!sidebarOpen ? '{{ $item['label'] }}' : ''"
                           class="flex items-center rounded-lg text-sm font-medium transition-colors
                                  {{ $isActive ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}"
                           :class="sidebarOpen ? 'gap-3 px-4 py-2.5' : 'justify-center px-2 py-3'">
                            <span class="flex-shrink-0 text-base leading-none">{{ $item['icon'] }}</span>
                            <span x-show="sidebarOpen"
                                  x-transition:enter="transition-opacity duration-150 delay-100"
                                  x-transition:enter-start="opacity-0"
                                  x-transition:enter-end="opacity-100"
                                  x-transition:leave="transition-opacity duration-75"
                                  x-transition:leave-start="opacity-100"
                                  x-transition:leave-end="opacity-0"
                                  class="whitespace-nowrap overflow-hidden">
                                {{ $item['label'] }}
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </nav>

        {{-- ── Bottom: Company / Outlet / User ────────────────────────────── --}}
        <div class="flex-shrink-0 border-t border-gray-700">

            {{-- Company + Active Outlet (expanded only) --}}
            @php
                $activeOutletId = Auth::user()->activeOutletId();
                $activeOutletName = $activeOutletId
                    ? \App\Models\Outlet::find($activeOutletId)?->name ?? '—'
                    : 'All Outlets';
            @endphp
            <div x-show="sidebarOpen"
                 x-transition:enter="transition-opacity duration-150 delay-100"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity duration-75"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="px-3 pt-3 pb-2 space-y-1">
                <div class="flex items-center gap-2 px-1">
                    <span class="text-sm leading-none">🏢</span>
                    <span class="text-xs font-medium text-gray-300 truncate">
                        {{ Auth::user()->company->name ?? '—' }}
                    </span>
                </div>
                <div class="flex items-center gap-2 px-1">
                    <span class="text-sm leading-none">📍</span>
                    <span class="text-xs text-gray-400 truncate">{{ $activeOutletName }}</span>
                </div>
            </div>

            {{-- User button --}}
            <div class="p-2">
                <button x-ref="userBtn"
                        @click="sidebarOpen || toggleSidebar(); $nextTick(() => openUserMenu())"
                        class="flex items-center w-full rounded-lg px-3 py-2 hover:bg-gray-800 transition gap-3"
                        :class="sidebarOpen ? '' : 'justify-center px-2'">
                    <div class="flex-shrink-0 w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center text-white font-bold text-xs">
                        {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                    </div>
                    <div x-show="sidebarOpen"
                         x-transition:enter="transition-opacity duration-150 delay-100"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition-opacity duration-75"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="flex-1 text-left overflow-hidden">
                        <p class="text-sm font-medium text-white truncate">{{ Auth::user()->name }}</p>
                        <p class="text-xs text-indigo-300 truncate">{{ Auth::user()->roles->first()?->name ?? '—' }}</p>
                    </div>
                    <svg x-show="sidebarOpen" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                    </svg>
                </button>
            </div>
        </div>

    </aside>

    {{-- ── Main content ─────────────────────────────────────────────────── --}}
    <main class="flex-1 overflow-y-auto p-6">
        {{-- Subscription banner --}}
        @if (!empty($subscriptionBanner))
            <div class="mb-4 px-4 py-3 rounded-lg flex items-center justify-between
                {{ $subscriptionBanner['type'] === 'expired' ? 'bg-red-50 border border-red-200' : 'bg-amber-50 border border-amber-200' }}">
                <div class="flex items-center gap-2">
                    @if ($subscriptionBanner['type'] === 'expired')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <p class="text-sm text-red-700">{{ $subscriptionBanner['message'] }}</p>
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-sm text-amber-700">{{ $subscriptionBanner['message'] }}</p>
                    @endif
                </div>
                <a href="{{ $subscriptionBanner['action'] }}"
                   class="px-4 py-1.5 text-sm font-medium rounded-lg flex-shrink-0 transition
                       {{ $subscriptionBanner['type'] === 'expired' ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-amber-600 text-white hover:bg-amber-700' }}">
                    {{ $subscriptionBanner['label'] }}
                </a>
            </div>
        @endif

        <div class="page-enter">
            {{ $slot }}
        </div>
    </main>

    {{-- ── User menu (teleported to body to escape overflow clipping) ────── --}}
    <template x-teleport="body">
        <div x-show="userMenuOpen"
             x-cloak
             @click.away="userMenuOpen = false"
             :style="userMenuStyle"
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-75"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="bg-gray-800 rounded-xl border border-gray-700 py-1 z-[200] shadow-2xl origin-bottom-left"
             style="position: fixed;">

            {{-- User info header --}}
            <div class="px-4 py-2.5 border-b border-gray-700">
                <p class="text-sm font-semibold text-white">{{ Auth::user()->name }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ Auth::user()->email }}</p>
            </div>

            <div class="py-1">
                <a href="{{ route('profile') }}"
                   @click="userMenuOpen = false"
                   class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    Profile
                </a>
                <a href="{{ route('profile') }}#switch-outlet"
                   @click="userMenuOpen = false"
                   class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                    Switch Outlet
                    <span class="ml-auto text-xs text-gray-500 truncate max-w-[100px]">{{ $activeOutletName }}</span>
                </a>
            </div>

            <div class="border-t border-gray-700 py-1">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-red-400 hover:bg-gray-700 hover:text-red-300 transition text-left">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Sign Out
                    </button>
                </form>
            </div>
        </div>
    </template>

</div>

{{-- Progress bar element --}}
<div id="nav-progress"></div>

<script>
(function(){
    const bar = document.getElementById('nav-progress');

    // Show progress bar when any internal link is clicked
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a[href]');
        if (!link) return;
        const href = link.getAttribute('href');
        // Skip external links, anchors, javascript:, and new-tab links
        if (!href || href.startsWith('#') || href.startsWith('javascript:')
            || link.target === '_blank' || e.ctrlKey || e.metaKey) return;
        // Only same-origin links
        try { if (new URL(href, location.origin).origin !== location.origin) return; } catch(e){ return; }

        bar.classList.remove('done');
        bar.style.width = '0';
        // Force reflow so animation restarts cleanly
        void bar.offsetWidth;
        bar.classList.add('running');
    });

    // Also trigger on form submissions
    document.addEventListener('submit', function() {
        bar.classList.remove('done');
        bar.style.width = '0';
        void bar.offsetWidth;
        bar.classList.add('running');
    });

    // Complete the bar when the new page loads (handled by next page's inline script)
    // For the current page load, animate in the content
    window.addEventListener('DOMContentLoaded', function() {
        bar.classList.remove('running');
        bar.classList.add('done');
    });
})();
</script>

</body>
</html>
