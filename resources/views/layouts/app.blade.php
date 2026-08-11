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

    {{-- The nav theme is read before first paint so the panel does not render
         dark and then repaint light a frame later. Falls back to the OS
         preference the first time somebody visits, rather than defaulting to
         dark and making them find the toggle. --}}
    <script>
        (function () {
            var stored = localStorage.getItem('nav_theme');
            if (!stored) {
                stored = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
            }
            window.__navTheme = stored;
        })();
    </script>
</head>
<body class="font-sans antialiased bg-gray-100">

@include('partials.impersonation-banner')

{{-- First tab stop on the page. Without it a keyboard user walked every link
     in an eight-group sidebar before reaching the content, on every
     navigation. --}}
<a href="#main-content" class="skip-link">Skip to main content</a>

<div x-data="{
        sidebarOpen: localStorage.getItem('sidebar') !== '0',
        toggleSidebar() {
            this.sidebarOpen = !this.sidebarOpen;
            localStorage.setItem('sidebar', this.sidebarOpen ? '1' : '0');
        },
        navTheme: window.__navTheme || 'dark',
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

    {{-- ── Sidebar ──────────────────────────────────────────────────────────
         Was 440 lines of hand-rolled markup here, with the menu itself as an
         array literal in the middle of it. Both moved: the structure to
         <x-app-nav>, the menu to App\Support\Navigation\NavMenu, so the
         kitchen layout renders the same component rather than its own
         near-copy. --}}
    @php
        $navUser   = Auth::user();
        $navGroups = $navUser->isSystemRole()
            ? \App\Support\Navigation\NavMenu::visibleForSystemRole(\App\Support\Navigation\NavMenu::outlet())
            : \App\Support\Navigation\NavMenu::visible(\App\Support\Navigation\NavMenu::outlet(), $navUser);

        $navAdmin = $navUser->isSystemRole() ? \App\Support\Navigation\NavMenu::admin() : [];
    @endphp

    <x-app-nav workspace="outlet"
               state-key="nav"
               :groups="$navGroups"
               :admin-groups="$navAdmin"
               logo="/images/servora-logo-white.png"
               logo-dark="/images/servora-logo-black.png">

        {{-- Top CTAs --}}
        <x-slot:top>
            {{-- Scan Invoices — Price Watcher entry point --}}
            @if ($navUser->can('ingredients.import'))
                <a href="{{ route('ingredients.scan-document') }}"
                   class="flex items-center gap-2 rounded-control font-semibold shadow-btn transition
                          {{ request()->routeIs('ingredients.scan-document') ? 'bg-success-700 text-white' : 'bg-success-600 text-white hover:bg-success-700' }}"
                   :class="sidebarExpanded ? 'px-3 py-2 justify-center' : 'justify-center h-11'">
                    <x-icon name="inbox" size="h-4 w-4" stroke="2" class="flex-shrink-0" />
                    <span x-show="sidebarExpanded" class="whitespace-nowrap text-[11px] uppercase tracking-widest">Scan Invoices</span>
                    <span x-show="!sidebarExpanded" class="sr-only">Scan invoices</span>
                </a>
            @endif

            {{-- Zeoniq Excel Import --}}
            @if ($navUser->can('sales.import'))
                @php $zeoniqOn = request()->routeIs('sales.index') && request()->query('import') === 'zeoniq-excel'; @endphp
                <a href="{{ route('sales.index') }}?import=zeoniq-excel"
                   class="flex items-center gap-2 rounded-control font-semibold shadow-btn transition
                          {{ $zeoniqOn ? 'bg-brand-700 text-white' : 'bg-brand-600 text-white hover:bg-brand-700' }}"
                   :class="sidebarExpanded ? 'px-3 py-2 justify-center' : 'justify-center h-11'">
                    <x-icon name="document" size="h-4 w-4" stroke="2" class="flex-shrink-0" />
                    <span x-show="sidebarExpanded" class="whitespace-nowrap text-[11px] uppercase tracking-widest">Zeoniq Excel</span>
                    <span x-show="!sidebarExpanded" class="sr-only">Import Zeoniq Excel</span>
                </a>
            @endif
        </x-slot:top>

        {{-- ── Bottom: Company / Outlet / User ────────────────────────────── --}}
        <x-slot:bottom>
            {{-- Company (expanded only) — switcher when the user belongs to multiple companies --}}
            <livewire:company-switcher />

            {{-- User button --}}
            <div class="p-2">
                <button x-ref="userBtn"
                        @click="sidebarOpen || toggleSidebar(); $nextTick(() => openUserMenu())"
                        class="nav-item w-full"
                        :class="sidebarExpanded ? '' : 'justify-center px-0 h-11'"
                        :aria-expanded="userMenuOpen.toString()">
                    @if (Auth::user()->avatar)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url(Auth::user()->avatar) }}" alt=""
                             class="h-8 w-8 flex-shrink-0 rounded-full object-cover" />
                    @else
                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white">
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
                         class="flex-1 overflow-hidden text-left">
                        <p class="nav-strong truncate text-sm font-medium">{{ Auth::user()->name }}</p>
                        <p class="nav-muted truncate text-xs">{{ Auth::user()->displayDesignation() }}</p>
                    </div>
                    <span x-show="!sidebarExpanded" class="sr-only">Open account menu</span>
                    <x-icon name="chevron-down" size="h-4 w-4" stroke="2"
                            class="nav-muted flex-shrink-0 rotate-180" x-show="sidebarExpanded" />
                </button>
            </div>
        </x-slot:bottom>
    </x-app-nav>

    {{-- ── Main content ─────────────────────────────────────────────────── --}}
    <main id="main-content" class="flex-1 overflow-y-auto">
        {{-- Mobile top bar (md+ hidden). Sticky to top of the scroll container.
             Carries .app-nav so it takes the same theme tokens as the drawer it
             opens — it was hardcoded bg-gray-900 and stayed black while the
             panel beside it went white. --}}
        <div class="app-nav md:hidden sticky top-0 z-sticky flex items-center h-14 px-3 shadow"
             :data-nav-theme="navTheme" data-workspace="outlet">
            <button @click="mobileNavOpen = true"
                    class="nav-icon-btn -ml-1 h-11 w-11"
                    aria-label="Open menu"
                    aria-controls="nav-primary">
                <x-icon name="bars" size="h-6 w-6" stroke="2" />
            </button>
            <img src="/images/servora-logo-white.png" alt="Servora" class="h-7" x-show="navTheme === 'dark'">
            <img src="/images/servora-logo-black.png" alt="Servora" class="h-7" x-show="navTheme === 'light'" x-cloak>
            @if (! empty($title))
                <span class="nav-muted ml-auto truncate text-sm max-w-[50%]">{{ $title }}</span>
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
