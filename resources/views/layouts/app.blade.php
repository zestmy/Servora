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
                $isSystemRole = $authUser->isSystemRole();

                // Filter helper
                $canSee = function($item) use ($authUser) {
                    if (!empty($item['capability']) && !$authUser->hasCapability($item['capability'])) return false;
                    if (($item['permission'] ?? null) !== null && !$authUser->hasPermissionTo($item['permission'])) return false;
                    if (!empty($item['feature']) && $authUser->company) {
                        if (!app(\App\Services\SubscriptionService::class)->canUseFeature($authUser->company, $item['feature'])) return false;
                    }
                    return true;
                };

                // Grouped navigation
                $navGroups = [
                    [
                        'label' => null, // No header for main
                        'items' => [
                            ['route' => 'dashboard', 'icon' => '🏠', 'label' => 'Dashboard', 'permission' => null],
                        ],
                    ],
                    [
                        'label' => 'Operations',
                        'items' => [
                            ['route' => 'ingredients.index',    'label' => 'Ingredients',   'permission' => 'ingredients.view'],
                            ['route' => 'recipes.index',        'label' => 'Recipes',       'permission' => 'recipes.view'],
                            ['route' => 'recipes.index',        'label' => 'Prep Items',    'permission' => 'recipes.view', 'query' => 'tab=prep-items'],
                            ['route' => 'kitchen.index',        'label' => 'Kitchen',       'permission' => 'inventory.view'],
                            ['route' => 'settings.labour-costs','label' => 'Labour Costs',  'permission' => 'settings.view'],
                        ],
                    ],
                    [
                        'label' => 'Purchasing',
                        'items' => [
                            ['route' => 'purchasing.index',          'label' => 'Purchase Orders', 'permission' => 'purchasing.view'],
                            ['route' => 'settings.suppliers',       'label' => 'Suppliers',        'permission' => 'purchasing.view'],
                            ['route' => 'settings.supplier-mapping', 'label' => 'Product Mapping', 'permission' => 'purchasing.view'],
                            ['route' => 'settings.form-templates',  'label' => 'Order Templates',  'permission' => 'purchasing.view'],
                        ],
                    ],
                    [
                        'label' => 'Sales',
                        'items' => [
                            ['route' => 'sales.index',              'label' => 'Sales Records',  'permission' => 'sales.view'],
                            ['route' => 'settings.sales-targets',   'label' => 'Sales Targets',  'permission' => 'sales.view'],
                        ],
                    ],
                    [
                        'label' => 'Warehouse',
                        'items' => [
                            ['route' => 'inventory.index', 'label' => 'Inventory', 'permission' => 'inventory.view'],
                        ],
                    ],
                    [
                        'label' => 'Training',
                        'items' => [
                            ['route' => 'settings.lms-users', 'label' => 'LMS Users',     'permission' => 'recipes.view'],
                            ['route' => 'training.sop.pdf-all', 'label' => 'Export All SOPs', 'permission' => 'recipes.view'],
                        ],
                    ],
                    [
                        'label' => 'Insights',
                        'items' => [
                            ['route' => 'reports.hub',     'label' => 'Reports',     'permission' => 'reports.view'],
                            ['route' => 'analytics.index', 'label' => 'AI Analysis', 'permission' => 'reports.view', 'feature' => 'analytics'],
                        ],
                    ],
                    [
                        'label' => 'Management',
                        'items' => [
                            ['route' => 'settings.index',     'label' => 'Settings',     'permission' => 'settings.view'],
                            ['route' => 'billing.index',      'label' => 'Billing',      'permission' => null, 'capability' => 'can_manage_users'],
                            ['route' => 'referral.dashboard', 'label' => 'Refer & Earn', 'permission' => null],
                        ],
                    ],
                ];

                $adminNavItems = [
                    ['route' => 'admin.plans.index',         'icon' => '📦', 'label' => 'Plans',         'permission' => null],
                    ['route' => 'admin.subscriptions.index', 'icon' => '💳', 'label' => 'Subscriptions', 'permission' => null],
                    ['route' => 'admin.trials.index',        'icon' => '⏱️', 'label' => 'Trials',        'permission' => null],
                    ['route' => 'admin.referrals.index',     'icon' => '🔗', 'label' => 'Referrals',     'permission' => null],
                    ['route' => 'admin.company-health',      'icon' => '💚', 'label' => 'Health',        'permission' => null],
                    ['route' => 'admin.announcements',       'icon' => '📢', 'label' => 'Announcements', 'permission' => null],
                    ['route' => 'admin.pages',               'icon' => '📄', 'label' => 'Pages',         'permission' => null],
                ];
            @endphp

            @php
                // Find which group is active on page load
                $activeGroupSlug = null;
                foreach ($navGroups as $g) {
                    if (! $g['label']) continue;
                    $vis = $isSystemRole
                        ? array_filter($g['items'], fn($i) => in_array($i['route'], ['dashboard', 'settings.index']))
                        : array_filter($g['items'], $canSee);
                    foreach ($vis as $vi) {
                        if (request()->routeIs($vi['route']) || request()->routeIs($vi['route'] . '.*') ||
                            ($vi['route'] === 'reports.hub' && request()->routeIs('reports.*'))) {
                            $activeGroupSlug = Str::slug($g['label']);
                            break 2;
                        }
                    }
                }
            @endphp
            <div x-data="{
                    activeGroup: '{{ $activeGroupSlug ?? '' }}' || localStorage.getItem('nav_active_group') || '',
                    toggle(key) {
                        this.activeGroup = this.activeGroup === key ? '' : key;
                        localStorage.setItem('nav_active_group', this.activeGroup);
                    }
                 }">
            @foreach ($navGroups as $gIdx => $group)
                @php
                    $visibleItems = $isSystemRole
                        ? array_filter($group['items'], fn($i) => in_array($i['route'], ['dashboard', 'settings.index']))
                        : array_filter($group['items'], $canSee);

                    // Check if any item in this group is active (auto-expand)
                    $groupHasActive = false;
                    foreach ($visibleItems as $vi) {
                        if (request()->routeIs($vi['route']) || request()->routeIs($vi['route'] . '.*')) {
                            $groupHasActive = true; break;
                        }
                        if ($vi['route'] === 'reports.hub' && request()->routeIs('reports.*')) {
                            $groupHasActive = true; break;
                        }
                    }
                @endphp

                @if (count($visibleItems) > 0)
                    @if ($group['label'])
                        {{-- Collapsible group --}}
                        @php $groupSlug = Str::slug($group['label']); @endphp
                        <div class="mt-2">
                            <button @click="toggle('{{ $groupSlug }}')"
                                    class="w-full flex items-center justify-between px-4 py-1.5 text-[10px] uppercase tracking-widest text-gray-500 font-semibold hover:text-gray-300 transition">
                                <span>{{ $group['label'] }}</span>
                                <svg :class="activeGroup === '{{ $groupSlug }}' && 'rotate-180'" class="w-3 h-3 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="activeGroup === '{{ $groupSlug }}'">
                                @foreach ($visibleItems as $item)
                                    @php
                                        $itemUrl = route($item['route']) . (!empty($item['query']) ? '?' . $item['query'] : '');
                                        $isActive = request()->routeIs($item['route']) || request()->routeIs($item['route'] . '.*');
                                        if ($item['route'] === 'reports.hub') $isActive = $isActive || request()->routeIs('reports.*');
                                        // For items with query param, check if query matches too
                                        if (!empty($item['query']) && $isActive) {
                                            parse_str($item['query'], $qp);
                                            $isActive = collect($qp)->every(fn($v, $k) => request()->query($k) === $v);
                                        }
                                    @endphp
                                    <a href="{{ $itemUrl }}"
                                       title="{{ $item['label'] }}"
                                       class="block rounded-lg text-sm font-medium transition-colors px-4 py-1.5 ml-1
                                              {{ $isActive ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                        {{ $item['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        {{-- No header (Dashboard) --}}
                        @foreach ($visibleItems as $item)
                            @php
                                $isActive = request()->routeIs($item['route']) || request()->routeIs($item['route'] . '.*');
                            @endphp
                            <a href="{{ route($item['route']) }}"
                               title="{{ $item['label'] }}"
                               class="block rounded-lg text-sm font-medium transition-colors px-4 py-2
                                      {{ $isActive ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    @endif
                @endif
            @endforeach
            </div>{{-- end x-data activeGroup wrapper --}}

            {{-- Admin Section (System Admin only) --}}
            @if ($isSystemRole)
                <div class="mt-2 pt-2 border-t border-gray-700"
                     x-data="{ open: {{ request()->routeIs('admin.*') ? 'true' : 'false' }} }"
                     x-init="let s = localStorage.getItem('nav_admin'); if (s !== null) open = s === '1'">
                    <button @click="open = !open; localStorage.setItem('nav_admin', open ? '1' : '0')"
                            class="w-full flex items-center justify-between px-4 py-1.5 text-[10px] uppercase tracking-widest text-gray-500 font-semibold hover:text-gray-300 transition">
                        <span>Admin</span>
                        <svg :class="open ? 'rotate-180' : ''" class="w-3 h-3 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open">
                        @foreach ($adminNavItems as $item)
                            @php $isActive = request()->routeIs($item['route']) || request()->routeIs($item['route'] . '.*'); @endphp
                            <a href="{{ route($item['route']) }}"
                               title="{{ $item['label'] }}"
                               class="block rounded-lg text-sm font-medium transition-colors px-4 py-1.5 ml-1
                                      {{ $isActive ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </nav>

        {{-- Kitchen mode switcher --}}
        @if (Auth::user()->isKitchenUser())
            <div class="px-3 pb-2">
                <a href="{{ route('workspace.switch', 'kitchen') }}"
                   class="block w-full text-center px-3 py-1.5 text-xs font-medium text-purple-300 border border-purple-700 rounded-lg hover:bg-purple-900/40 hover:text-white transition">
                    Switch to Central Kitchen Mode
                </a>
            </div>
        @endif

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
                        <p class="text-xs text-indigo-300 truncate">{{ Auth::user()->displayDesignation() }}</p>
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
