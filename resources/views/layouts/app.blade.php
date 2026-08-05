<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Dashboard' }} | {{ config('app.name', 'Servora') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

    {{-- PWA: installable on mobile, offline fallback, no-cache for auth routes --}}
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#111827">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Servora">
    <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">

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
            background: linear-gradient(90deg, #22a19d, #43bdb8);
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
    @include('partials.nav-theme')
</head>
<body class="font-sans antialiased bg-gray-100">

@include('partials.impersonation-banner')

<div x-data="{
        sidebarOpen: localStorage.getItem('sidebar') !== '0',
        toggleSidebar() {
            this.sidebarOpen = !this.sidebarOpen;
            localStorage.setItem('sidebar', this.sidebarOpen ? '1' : '0');
        },
        navTheme: localStorage.getItem('nav_theme') || 'dark',
        toggleNavTheme() {
            this.navTheme = this.navTheme === 'dark' ? 'light' : 'dark';
            localStorage.setItem('nav_theme', this.navTheme);
        },
        mobileNavOpen: false,
        // True whenever the sidebar should look expanded — either the desktop
        // toggle is on, OR the mobile drawer is currently open (which always
        // renders at full width). Used instead of bare sidebarOpen for anything
        // that shows / hides labels, logos, etc.
        get sidebarExpanded() { return this.sidebarOpen || this.mobileNavOpen; },
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

    {{-- Mobile scrim — tap to dismiss the drawer --}}
    <div x-show="mobileNavOpen" x-cloak
         x-transition:enter="transition-opacity ease-out duration-200"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @click="mobileNavOpen = false"
         class="fixed inset-0 bg-black/50 z-40 md:hidden"></div>

    {{-- ── Sidebar ──────────────────────────────────────────────────────── --}}
    {{-- Mobile: off-canvas drawer (fixed, slides in). Desktop: in-flow, toggles w-64/w-16. --}}
    <aside :class="{
               '-translate-x-full md:translate-x-0': !mobileNavOpen,
               'translate-x-0': mobileNavOpen,
               'md:w-16': !sidebarOpen,
               'md:w-64': sidebarOpen,
           }"
           @click="if ($event.target.closest && $event.target.closest('a')) mobileNavOpen = false"
           :data-nav-theme="navTheme"
           class="fixed inset-y-0 left-0 z-50 w-64 md:relative md:inset-auto md:z-auto flex flex-col bg-gray-900 text-white flex-shrink-0 overflow-hidden transform transition-all duration-300 ease-in-out">

        {{-- Logo + toggle --}}
        <div class="flex items-center h-16 px-3 bg-gray-800 flex-shrink-0 gap-2">
            <div x-show="sidebarExpanded"
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
                    class="hidden md:flex flex-shrink-0 w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:text-white hover:bg-gray-700 transition">
                <svg x-show="sidebarOpen" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7M18 19l-7-7 7-7" />
                </svg>
                <svg x-show="!sidebarOpen" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>

        {{-- Top CTAs --}}
        <div class="flex-shrink-0 space-y-1.5" :class="sidebarExpanded ? 'px-3 pt-3' : 'px-2 pt-3'">

            {{-- Scan Invoices — Price Watcher entry point --}}
            @if (Auth::user()->hasPermissionTo('ingredients.view'))
                <a href="{{ route('ingredients.scan-document') }}"
                   title="Scan a supplier invoice, quotation, or price list"
                   class="flex items-center gap-2 rounded-lg transition font-semibold shadow-sm
                          {{ request()->routeIs('ingredients.scan-document') ? 'bg-emerald-600 text-white' : 'bg-emerald-500 text-white hover:bg-emerald-400' }}"
                   :class="sidebarExpanded ? 'px-3 py-2 justify-center' : 'justify-center p-2'">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 7V5a2 2 0 012-2h12a2 2 0 012 2v2M4 7h16M4 7l1 10a2 2 0 002 2h10a2 2 0 002-2l1-10M9 11h6" />
                    </svg>
                    <span x-show="sidebarExpanded" class="text-[11px] uppercase tracking-widest whitespace-nowrap">Scan Invoices</span>
                </a>
            @endif

            {{-- Zeoniq Excel Import — Sales Zeoniq Excel import entry point --}}
            @if (Auth::user()->hasPermissionTo('sales.view'))
                <a href="{{ route('sales.index') }}?import=zeoniq-excel"
                   title="Import Zeoniq Excel"
                   class="flex items-center gap-2 rounded-lg transition font-semibold shadow-sm
                          {{ request()->routeIs('sales.index') && request()->query('import') === 'zeoniq-excel' ? 'bg-brand-700 text-white' : 'bg-brand-500 text-white hover:bg-brand-400' }}"
                   :class="sidebarExpanded ? 'px-3 py-2 justify-center' : 'justify-center p-2'">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span x-show="sidebarExpanded" class="text-[11px] uppercase tracking-widest whitespace-nowrap">Zeoniq Excel</span>
                </a>
            @endif

        </div>

        {{-- Navigation --}}
        <nav class="flex-1 overflow-y-auto py-4 space-y-1" :class="sidebarExpanded ? 'px-3' : 'px-2'">
            @php
                $authUser = Auth::user();
                $isSystemRole = $authUser->isSystemRole();

                // Filter helper
                $canSee = function($item) use ($authUser) {
                    if (!empty($item['capability']) && !$authUser->hasCapability($item['capability'])) return false;
                    if (($item['permission'] ?? null) !== null && !$authUser->hasPermissionTo($item['permission'])) return false;
                    // 'anyPermission': shown when the user holds ANY of them.
                    // The per-module Settings links need this — a module's
                    // settings are worth offering to whoever can open any one
                    // of the screens inside them, not only to settings.view.
                    if (!empty($item['anyPermission'])
                        && !collect($item['anyPermission'])->contains(fn ($p) => $authUser->can($p))) return false;
                    if (!empty($item['feature']) && $authUser->company) {
                        if (!app(\App\Services\SubscriptionService::class)->canUseFeature($authUser->company, $item['feature'])) return false;
                    }
                    if (!empty($item['kitchenOnly']) && !$authUser->isKitchenUser()) return false;
                    return true;
                };

                // Group header icons (heroicons outline paths)
                $gicons = [
                    'Procurement'           => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>',
                    'Inventory & Recipes'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>',
                    'Labels'                => '<path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.997 1.997 0 013 12V7a4 4 0 014-4z"/>',
                    'Sales'                 => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
                    'HR'                    => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>',
                    'Business Intelligence' => '<path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>',
                    'Settings'              => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
                ];

                // Grouped navigation
                $navGroups = [
                    [
                        'label' => null, // No header for main
                        'items' => [
                            ['route' => 'dashboard', 'svg' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'label' => 'Dashboard', 'permission' => null],
                        ],
                    ],
                    [
                        'label' => 'Procurement',
                        'items' => [
                            ['route' => 'purchasing.index',          'label' => 'Orders & Requests', 'permission' => 'purchasing.view'],
                            ['route' => 'settings.suppliers',       'label' => 'Suppliers',           'permission' => 'purchasing.view'],
                            ['route' => 'settings.supplier-mapping', 'label' => 'Product Mapping',   'permission' => 'purchasing.view'],
                            ['route' => 'settings.form-templates',  'label' => 'Form Templates',     'permission' => 'purchasing.view'],
                            ['route' => 'settings.price-alerts',    'label' => 'Price Alerts',       'permission' => 'purchasing.view'],
                            ['route' => 'settings.index', 'query' => 'module=procurement', 'label' => 'Procurement Settings',
                             'anyPermission' => ['settings.view']],
                        ],
                    ],
                    [
                        'label' => 'Inventory & Recipes',
                        'items' => [
                            ['route' => 'ingredients.index',          'label' => 'Market List',      'permission' => 'ingredients.view'],
                            ['route' => 'settings.categories',        'label' => 'Product Categories', 'permission' => 'ingredients.view'],
                            ['route' => 'recipes.index',              'label' => 'Recipes',          'permission' => 'recipes.view'],
                            ['route' => 'settings.recipe-categories', 'label' => 'Recipe Categories', 'permission' => 'recipes.view'],
                            ['route' => 'recipes.index',              'label' => 'Prep Items',       'permission' => 'recipes.view', 'query' => 'tab=prep-items'],
                            ['route' => 'settings.price-classes',     'label' => 'Price Classes',    'permission' => 'recipes.view'],
                            ['route' => 'inventory.index',            'label' => 'Stocks Management',     'permission' => 'inventory.view'],
                            ['route' => 'settings.par-levels',        'label' => 'Par Levels',       'permission' => 'inventory.view'],
                            ['route' => 'ingredients.review-documents', 'label' => 'Review Documents', 'permission' => 'ingredients.view'],
                            ['route' => 'settings.index', 'query' => 'module=kitchen-production', 'label' => 'Kitchen Settings',
                             'anyPermission' => ['settings.view']],
                        ],
                    ],
                    [
                        'label' => 'Labels',
                        'items' => [
                            ['route' => 'labels.print',      'label' => 'Print Labels',    'permission' => 'labels.print'],
                            ['route' => 'labels.sets',       'label' => 'Print Sets',      'permission' => 'labels.print'],
                            ['route' => 'labels.expiring',   'label' => 'Expiring',        'permission' => 'labels.print'],
                            ['route' => 'labels.log',        'label' => 'Print Log',       'permission' => 'labels.view_log'],
                            ['route' => 'labels.shelf-life', 'label' => 'Shelf Life',      'permission' => 'labels.manage'],
                            ['route' => 'labels.templates',  'label' => 'Templates',       'permission' => 'labels.manage'],
                            ['route' => 'labels.printers',   'label' => 'Label Printers',  'permission' => 'labels.manage'],
                            ['route' => 'labels.settings',   'label' => 'Label Settings',  'permission' => 'labels.manage'],
                        ],
                    ],
                    [
                        'label' => 'Sales',
                        'items' => [
                            ['route' => 'sales.index',              'label' => 'Sales Records',  'permission' => 'sales.view'],
                            ['route' => 'settings.sales-categories', 'label' => 'Sales Categories', 'permission' => 'sales.view'],
                            ['route' => 'settings.sales-targets',   'label' => 'Sales Targets',  'permission' => 'sales.view'],
                        ],
                    ],
                    [
                        'label' => 'HR',
                        // Sub-grouped by what the screen is FOR, so eleven items
                        // stop reading as one list. Order matters: items are
                        // rendered in sequence and the caption is emitted when
                        // the section changes, so each section must be contiguous.
                        'items' => [
                            ['route' => 'hr.employees',            'label' => 'Employees',       'permission' => 'hr.view',             'section' => 'People'],
                            ['route' => 'hr.staff-pins',           'label' => 'Staff PINs',      'permission' => 'staff.pins',          'section' => 'People'],

                            ['route' => 'hr.duty-roster',          'label' => 'Duty Roster',                                            'section' => 'Scheduling'], // Viewable by all users
                            ['route' => 'hr.shifts',               'label' => 'Shifts',          'permission' => 'roster.settings',     'section' => 'Scheduling'],

                            ['route' => 'hr.attendance',           'label' => 'Attendance Record', 'permission' => 'hr.attendance',     'section' => 'Time & Attendance'],
                            ['route' => 'hr.clock-ins',            'label' => 'Clock-Ins',       'permission' => 'hr.clock',            'section' => 'Time & Attendance'],
                            ['route' => 'hr.overtime-claims',      'label' => 'Overtime Claims', 'permission' => 'hr.claims',           'section' => 'Time & Attendance'],

                            ['route' => 'hr.compensation',         'label' => 'Compensation',    'permission' => 'hr.compensation',     'section' => 'Pay'],
                            ['route' => 'settings.labour-costs',   'label' => 'Labour Costs',    'permission' => 'hr.view',             'section' => 'Pay'],

                            ['route' => 'hr.documents',            'label' => 'Documents',       'permission' => 'hr.documents.view',   'section' => 'Records & Training'],
                            ['route' => 'settings.lms-users',      'label' => 'Training Portal', 'permission' => 'hr.view',             'section' => 'Records & Training'],

                            // Straight to this module's own settings — including
                            // Clock-In Settings, which moved there. Offered to
                            // anyone who can open any of them, not only to
                            // settings.view, or the person who administers pay
                            // would have no way in.
                            ['route' => 'settings.index', 'query' => 'module=hr-people', 'label' => 'HR Settings',
                             'anyPermission' => ['settings.view', 'hr.compensation', 'hr.documents.manage', 'hr.clock.manage'],
                             'section' => 'Configure'],
                        ],
                    ],
                    [
                        'label' => 'Business Intelligence',
                        'items' => [
                            ['route' => 'reports.hub',     'label' => 'Reports',     'permission' => 'reports.view'],
                            ['route' => 'analytics.index', 'label' => 'AI Analysis', 'permission' => 'reports.view', 'feature' => 'analytics'],
                            ['route' => 'settings.calendar-events', 'label' => 'Calendar Events', 'permission' => 'reports.view'],
                            ['route' => 'audit-logs.index', 'label' => 'Audit Logs', 'permission' => 'audit.view'],
                            ['route' => 'settings.index', 'query' => 'module=reporting', 'label' => 'Reporting Settings',
                             'anyPermission' => ['reports.view']],
                        ],
                    ],
                    [
                        'label' => 'Settings',
                        'items' => [
                            // No permission: the page shows only the modules the
                            // user actually administers, and shows an empty state
                            // to anyone who administers none.
                            ['route' => 'settings.index',            'label' => 'All Settings'],
                            ['route' => 'billing.index',             'label' => 'Billing',          'permission' => null, 'capability' => 'can_manage_users'],
                            ['route' => 'referral.dashboard',        'label' => 'Refer & Earn',     'permission' => null],
                        ],
                    ],
                ];

                $adminNavItems = [
                    ['route' => 'admin.users',               'icon' => '👥', 'label' => 'Users',         'permission' => null],
                    ['route' => 'admin.companies',           'icon' => '🏢', 'label' => 'Companies',     'permission' => null],
                    ['route' => 'company.create',            'icon' => '➕', 'label' => 'New Company',   'permission' => null],
                    ['route' => 'admin.role-templates',      'icon' => '🛡️', 'label' => 'Role Templates', 'permission' => null],
                    ['route' => 'admin.plans.index',         'icon' => '📦', 'label' => 'Plans',         'permission' => null],
                    ['route' => 'admin.subscriptions.index', 'icon' => '💳', 'label' => 'Subscriptions', 'permission' => null],
                    ['route' => 'admin.coupons',             'icon' => '🎟️', 'label' => 'Coupons',       'permission' => null],
                    ['route' => 'admin.trials.index',        'icon' => '⏱️', 'label' => 'Trials',        'permission' => null],
                    ['route' => 'admin.referrals.index',     'icon' => '🔗', 'label' => 'Referrals',     'permission' => null],
                    ['route' => 'admin.company-health',      'icon' => '💚', 'label' => 'Health',        'permission' => null],
                    ['route' => 'admin.announcements',       'icon' => '📢', 'label' => 'Announcements', 'permission' => null],
                    ['route' => 'admin.pages',               'icon' => '📄', 'label' => 'Pages',         'permission' => null],
                    ['route' => 'settings.api-keys',         'icon' => '🔑', 'label' => 'API Keys',      'permission' => null],
                ];
            @endphp

            @php
                // Find which group is active on page load
                $activeGroupSlug = null;
                foreach ($navGroups as $g) {
                    if (! $g['label']) continue;
                    $vis = $isSystemRole
                        ? array_filter($g['items'], fn($i) => in_array($i['route'], ['dashboard']))
                        : array_filter($g['items'], $canSee);
                    foreach ($vis as $vi) {
                        if (empty($vi['route'])) continue;
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
                        ? array_filter($group['items'], fn($i) => in_array($i['route'], ['dashboard']))
                        : array_filter($group['items'], $canSee);

                    // Check if any item in this group is active (auto-expand)
                    $groupHasActive = false;
                    foreach ($visibleItems as $vi) {
                        if (empty($vi['route'])) continue;
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
                        <div class="mt-2" x-show="sidebarExpanded" x-collapse>
                            <button @click="toggle('{{ $groupSlug }}')"
                                    class="w-full flex items-center justify-between px-4 py-1.5 text-[10px] uppercase tracking-widest text-gray-400 font-semibold hover:text-white transition">
                                <span class="flex items-center gap-2">
                                    @if (isset($gicons[$group['label']]))
                                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">{!! $gicons[$group['label']] !!}</svg>
                                    @endif
                                    <span>{{ $group['label'] }}</span>
                                </span>
                                <svg :class="activeGroup === '{{ $groupSlug }}' && 'rotate-180'" class="w-3 h-3 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="activeGroup === '{{ $groupSlug }}'">
                                {{-- A group may sub-divide its items with an optional
                                     'section' key. The caption is emitted at the first
                                     VISIBLE item of each section, so a section whose
                                     items are all hidden by permission never leaves a
                                     caption behind. Groups without the key are
                                     untouched. --}}
                                @php $lastSection = null; @endphp
                                @foreach ($visibleItems as $item)
                                    @if (! empty($item['section']) && $item['section'] !== $lastSection)
                                        @php $lastSection = $item['section']; @endphp
                                        <p class="px-4 pt-3 pb-1 ml-1 text-[9px] uppercase tracking-widest text-gray-500 font-semibold">
                                            {{ $item['section'] }}
                                        </p>
                                    @endif
                                    @if (!empty($item['comingSoon']))
                                        <span class="block rounded-lg text-sm font-medium px-4 py-1.5 ml-1 text-gray-400 cursor-default flex items-center justify-between">
                                            {{ $item['label'] }}
                                            <span class="text-[9px] uppercase tracking-wider bg-gray-700 text-gray-300 px-1.5 py-0.5 rounded">Soon</span>
                                        </span>
                                    @else
                                    @php
                                        $itemUrl = route($item['route']) . (!empty($item['query']) ? '?' . $item['query'] : '');
                                        $isActive = request()->routeIs($item['route']) || request()->routeIs($item['route'] . '.*');
                                        if ($item['route'] === 'reports.hub') $isActive = $isActive || request()->routeIs('reports.*');
                                        if (!empty($item['query']) && $isActive) {
                                            // Item has query param — only active if URL query matches
                                            parse_str($item['query'], $qp);
                                            $isActive = collect($qp)->every(fn($v, $k) => request()->query($k) === $v);
                                        } elseif (empty($item['query']) && $isActive) {
                                            // Item has NO query param — deactivate if URL has a tab param (another item owns it)
                                            if (request()->has('tab')) $isActive = false;
                                        }
                                    @endphp
                                    <a href="{{ $itemUrl }}"
                                       title="{{ $item['label'] }}"
                                       class="block rounded-lg text-sm font-medium transition-colors px-4 py-1.5 ml-1
                                              {{ $isActive ? 'bg-brand-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                        {{ $item['label'] }}
                                    </a>
                                    @endif
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
                               class="flex items-center gap-3 rounded-lg text-sm font-medium transition-colors py-2
                                      {{ $isActive ? 'bg-brand-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}"
                               :class="sidebarExpanded ? 'px-4' : 'px-2 justify-center'">
                                @if (!empty($item['svg']))
                                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['svg'] }}"/></svg>
                                @elseif (!empty($item['icon']))
                                    <span class="flex-shrink-0">{{ $item['icon'] }}</span>
                                @endif
                                <span x-show="sidebarExpanded" class="whitespace-nowrap">{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    @endif
                @endif
            @endforeach
            </div>{{-- end x-data activeGroup wrapper --}}

            {{-- Admin Section (System Admin only) --}}
            @if ($isSystemRole)
                <div class="mt-2 pt-2 border-t border-gray-700"
                     x-show="sidebarExpanded" x-collapse
                     x-data="{ open: {{ request()->routeIs('admin.*') ? 'true' : 'false' }} }"
                     x-init="let s = localStorage.getItem('nav_admin'); if (s !== null) open = s === '1'">
                    <button @click="open = !open; localStorage.setItem('nav_admin', open ? '1' : '0')"
                            class="w-full flex items-center justify-between px-4 py-1.5 text-[10px] uppercase tracking-widest text-gray-400 font-semibold hover:text-white transition">
                        <span>Admin</span>
                        <svg :class="open ? 'rotate-180' : ''" class="w-3 h-3 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open">
                        @foreach ($adminNavItems as $item)
                            @php $isActive = request()->routeIs($item['route']) || request()->routeIs($item['route'] . '.*'); @endphp
                            <a href="{{ route($item['route']) }}"
                               title="{{ $item['label'] }}"
                               class="block rounded-lg text-sm font-medium transition-colors px-4 py-1.5 ml-1
                                      {{ $isActive ? 'bg-brand-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </nav>

        {{-- ── Bottom: Company / Outlet / User ────────────────────────────── --}}
        <div class="flex-shrink-0 border-t border-gray-700">

            {{-- Company (expanded only) — switcher when the user belongs to multiple companies --}}
            <livewire:company-switcher />

            {{-- User button --}}
            <div class="p-2">
                <button x-ref="userBtn"
                        @click="sidebarOpen || toggleSidebar(); $nextTick(() => openUserMenu())"
                        class="flex items-center w-full rounded-lg px-3 py-2 hover:bg-gray-800 transition gap-3"
                        :class="sidebarExpanded ? '' : 'justify-center px-2'">
                    @if (Auth::user()->avatar)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url(Auth::user()->avatar) }}" alt=""
                             class="flex-shrink-0 w-8 h-8 rounded-full object-cover" />
                    @else
                        <div class="flex-shrink-0 w-8 h-8 bg-brand-600 rounded-full flex items-center justify-center text-white font-bold text-xs">
                            {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                        </div>
                    @endif
                    <div x-show="sidebarExpanded"
                         x-transition:enter="transition-opacity duration-150 delay-100"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition-opacity duration-75"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="flex-1 text-left overflow-hidden">
                        <p class="text-sm font-medium text-white truncate">{{ Auth::user()->name }}</p>
                        <p class="text-xs text-brand-200 truncate">{{ Auth::user()->displayDesignation() }}</p>
                    </div>
                    <svg x-show="sidebarExpanded" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                    </svg>
                </button>
            </div>
        </div>

    </aside>

    {{-- ── Main content ─────────────────────────────────────────────────── --}}
    <main class="flex-1 overflow-y-auto">
        {{-- Mobile top bar (md+ hidden). Sticky to top of the scroll container. --}}
        <div class="md:hidden sticky top-0 z-30 flex items-center h-14 px-3 bg-gray-900 text-white shadow">
            <button @click="mobileNavOpen = true"
                    class="-ml-2 p-3 rounded text-gray-300 hover:bg-gray-800 hover:text-white"
                    aria-label="Open menu">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <img src="/images/servora-logo-white.png" alt="Servora" class="h-7">
            @if (! empty($title))
                <span class="ml-auto text-sm text-gray-300 truncate max-w-[50%]">{{ $title }}</span>
            @endif
        </div>

        <div class="p-4 sm:p-6">
        {{-- Subscription banner --}}
        @if (!empty($subscriptionBanner))
            <div class="mb-4 px-4 py-3 rounded-lg flex items-center justify-between
                {{ $subscriptionBanner['type'] === 'expired' ? 'bg-danger-50 border border-danger-200' : 'bg-warning-50 border border-warning-200' }}">
                <div class="flex items-center gap-2">
                    @if ($subscriptionBanner['type'] === 'expired')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-danger-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <p class="text-sm text-danger-800">{{ $subscriptionBanner['message'] }}</p>
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-warning-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-sm text-warning-800">{{ $subscriptionBanner['message'] }}</p>
                    @endif
                </div>
                <a href="{{ $subscriptionBanner['action'] }}"
                   class="px-4 py-1.5 text-sm font-medium rounded-lg flex-shrink-0 transition
                       {{ $subscriptionBanner['type'] === 'expired' ? 'bg-danger-600 text-white hover:bg-danger-700' : 'bg-warning-600 text-white hover:bg-warning-700' }}">
                    {{ $subscriptionBanner['label'] }}
                </a>
            </div>
        @endif

        <div class="page-enter">
            {{ $slot }}
        </div>
        </div>{{-- end content padding wrapper --}}
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
            <div class="px-4 py-2.5 border-b border-gray-700 flex items-center gap-3">
                @if (Auth::user()->avatar)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url(Auth::user()->avatar) }}" alt=""
                         class="w-9 h-9 rounded-full object-cover flex-shrink-0" />
                @else
                    <div class="w-9 h-9 bg-brand-600 rounded-full flex items-center justify-center text-white font-bold text-xs flex-shrink-0">
                        {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                    </div>
                @endif
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-white truncate">{{ Auth::user()->name }}</p>
                    <p class="text-xs text-gray-400 mt-0.5 truncate">{{ Auth::user()->email }}</p>
                </div>
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

                @if (Auth::user()->isKitchenUser())
                    <a href="{{ route('workspace.switch', 'kitchen') }}"
                       class="flex items-center gap-2.5 px-4 py-2 text-sm text-brand-200 hover:bg-gray-700 hover:text-white transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        Switch to Central Kitchen
                    </a>
                @endif

                <button @click="toggleNavTheme()"
                        class="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition text-left">
                    <svg x-show="navTheme === 'dark'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <svg x-show="navTheme === 'light'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                    <span x-text="navTheme === 'dark' ? 'Light navigation' : 'Dark navigation'"></span>
                </button>
            </div>

            <div class="border-t border-gray-700 py-1">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-danger-400 hover:bg-gray-700 hover:text-danger-300 transition text-left">
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

{{-- PWA install prompt — only shown on mobile when the browser fires
     beforeinstallprompt. Dismissable and remembers for 7 days. --}}
<div id="pwa-install-banner"
     style="display:none;"
     class="fixed bottom-3 inset-x-3 z-[120] md:hidden flex items-center gap-3 px-3 py-2.5 bg-gray-900 text-white rounded-xl shadow-lg border border-gray-700">
    <img src="{{ asset('favicon.png') }}" alt="" class="h-8 w-8 rounded-lg">
    <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold">Install Servora</p>
        <p class="text-[11px] text-gray-300 leading-tight">Add to home screen for quick access.</p>
    </div>
    <button id="pwa-install-btn" class="btn-primary btn-sm">Install</button>
    <button id="pwa-install-dismiss" class="p-1 text-gray-400 hover:text-white" aria-label="Dismiss">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
</div>

<script>
// Service worker registration + install prompt handling
(function () {
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function (err) {
                console.warn('SW registration failed', err);
            });
        });
    }

    const DISMISS_KEY = 'pwa-install-dismissed-at';
    const DISMISS_DAYS = 7;
    const wasDismissedRecently = () => {
        try {
            const raw = localStorage.getItem(DISMISS_KEY);
            if (!raw) return false;
            const ts = parseInt(raw, 10);
            if (!ts) return false;
            return (Date.now() - ts) < DISMISS_DAYS * 24 * 60 * 60 * 1000;
        } catch (e) { return false; }
    };

    let deferredPrompt = null;
    const banner = document.getElementById('pwa-install-banner');
    const installBtn = document.getElementById('pwa-install-btn');
    const dismissBtn = document.getElementById('pwa-install-dismiss');

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        if (wasDismissedRecently()) return;
        deferredPrompt = e;
        banner.style.display = 'flex';
    });

    if (installBtn) {
        installBtn.addEventListener('click', async function () {
            if (!deferredPrompt) return;
            banner.style.display = 'none';
            deferredPrompt.prompt();
            await deferredPrompt.userChoice;
            deferredPrompt = null;
        });
    }
    if (dismissBtn) {
        dismissBtn.addEventListener('click', function () {
            banner.style.display = 'none';
            try { localStorage.setItem(DISMISS_KEY, String(Date.now())); } catch (e) {}
        });
    }

    window.addEventListener('appinstalled', function () {
        banner.style.display = 'none';
        deferredPrompt = null;
    });
})();
</script>

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
