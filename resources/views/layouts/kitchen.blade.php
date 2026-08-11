<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Dashboard' }} | Kitchen | {{ config('app.name', 'Servora') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>

    {{-- Read before first paint so the panel does not render dark and repaint
         light a frame later, and fall back to the OS preference rather than
         defaulting to dark and making somebody find the toggle. --}}
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

<a href="#main-content" class="skip-link">Skip to main content</a>

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
        navTheme: window.__navTheme || 'dark',
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

    {{-- ── Sidebar ──────────────────────────────────────────────────────────
         Same component as the outlet layout, with the kitchen menu and the
         workspace accent. Previously a near-copy: it had the collapsed icon
         rail the outlet sidebar lacked, but its permission filter understood
         only `permission` — not capability, anyPermission, feature or
         kitchenOnly — so a kitchen item could not be gated on a subscription
         feature even though the key existed. --}}
    @php
        $ckGroups = \App\Support\Navigation\NavMenu::visible(
            \App\Support\Navigation\NavMenu::kitchen(),
            $authUser
        );
    @endphp

    <x-app-nav workspace="kitchen"
               state-key="ck_nav"
               :groups="$ckGroups"
               logo="/images/servora-logo-white.png"
               logo-dark="/images/servora-logo-black.png">

        {{-- Where you are standing.

             Was a 🍳 emoji when collapsed and a purple-900/40 band when
             expanded. The emoji is a different glyph on every OS and cannot
             take the icon set's stroke weight; the band's colour is now the
             workspace token, so the light theme does not need a hand-mixed
             remap for it. --}}
        <x-slot:top>
            <div class="rounded-control bg-workspace-600/15 px-3 py-2"
                 :class="sidebarExpanded ? '' : 'flex justify-center px-0 py-2'">
                <div x-show="sidebarExpanded">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-workspace-300"
                       :class="navTheme === 'light' && 'text-workspace-700'">
                        Central Kitchen
                    </p>
                    <p class="nav-strong truncate text-sm font-medium">{{ $activeKitchen?->name ?? 'Kitchen' }}</p>
                </div>
                <div x-show="!sidebarExpanded" class="text-workspace-300"
                     :class="navTheme === 'light' && 'text-workspace-700'">
                    <x-icon name="fire" size="h-5 w-5" stroke="1.8" />
                    <span class="sr-only">Central Kitchen — {{ $activeKitchen?->name ?? 'Kitchen' }}</span>
                </div>
            </div>
        </x-slot:top>

        {{-- Bottom: Company + User panel (profile, workspace switch, theme, sign out) --}}
        <x-slot:bottom>
            <div class="p-2" x-data="{ userOpen: false }">
                @php $ckCompany = $authUser->company; @endphp
                @if ($ckCompany)
                    <div x-show="sidebarExpanded"
                         class="nav-divider mb-1 flex items-center gap-2 border-b px-2 pb-2">
                        <x-icon name="building" size="h-4 w-4" stroke="1.8" class="nav-muted flex-shrink-0" />
                        <span class="nav-muted flex-1 truncate text-xs font-medium">{{ $ckCompany->name }}</span>
                    </div>
                @endif

                <div x-show="userOpen" x-cloak
                     class="nav-divider mb-1 rounded-control border py-1">
                    <a href="{{ route('profile') }}" class="nav-item text-sm">
                        <x-icon name="users" size="h-4 w-4" stroke="2" />
                        Profile
                    </a>
                    @if ($hasOutletAccess)
                        <a href="{{ route('workspace.switch', 'outlet') }}" class="nav-item text-sm">
                            <x-icon name="building" size="h-4 w-4" stroke="2" />
                            Switch to Outlet Mode
                        </a>
                    @endif
                    <button @click="toggleNavTheme()" class="nav-item w-full text-left text-sm">
                        <x-icon name="sun" size="h-4 w-4" stroke="2" x-show="navTheme === 'dark'" />
                        <x-icon name="moon" size="h-4 w-4" stroke="2" x-show="navTheme === 'light'" x-cloak />
                        <span x-text="navTheme === 'dark' ? 'Light navigation' : 'Dark navigation'"></span>
                    </button>
                    <form method="POST" action="{{ route('logout') }}" class="nav-divider mt-1 border-t pt-1">
                        @csrf
                        <button type="submit" class="nav-item w-full text-left text-sm text-danger-500 hover:text-danger-600">
                            <x-icon name="arrow-right" size="h-4 w-4" stroke="2" />
                            Sign Out
                        </button>
                    </form>
                </div>

                <button @click="if (! sidebarExpanded) toggleSidebar(); userOpen = ! userOpen"
                        :aria-expanded="userOpen.toString()"
                        class="nav-item w-full"
                        :class="sidebarExpanded ? '' : 'justify-center px-0 h-11'">
                    @if ($authUser->avatar)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($authUser->avatar) }}" alt=""
                             class="h-7 w-7 flex-shrink-0 rounded-full object-cover" />
                    @else
                        <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-workspace-600 text-xs font-bold text-white">
                            {{ strtoupper(substr($authUser->name, 0, 2)) }}
                        </div>
                    @endif
                    <div x-show="sidebarExpanded" class="min-w-0 flex-1 text-left">
                        <p class="nav-strong truncate text-xs font-medium">{{ $authUser->name }}</p>
                        <p class="nav-muted truncate text-[10px]">{{ $authUser->displayDesignation() }}</p>
                    </div>
                    <span x-show="!sidebarExpanded" class="sr-only">Open account menu</span>
                    <x-icon name="chevron-down" size="h-3.5 w-3.5" stroke="2"
                            class="nav-muted flex-shrink-0 transition-transform"
                            x-show="sidebarExpanded"
                            ::class="userOpen && 'rotate-180'" />
                </button>
            </div>
        </x-slot:bottom>
    </x-app-nav>

    {{-- Main Content --}}
    <main id="main-content" class="flex-1 overflow-y-auto">
        {{-- Mobile top bar. Carries .app-nav so it takes the same theme tokens
             as the drawer it opens — it was hardcoded bg-gray-900 and stayed
             black while the panel beside it went white. --}}
        <div class="app-nav md:hidden sticky top-0 z-sticky flex items-center h-14 px-3 shadow"
             :data-nav-theme="navTheme" data-workspace="kitchen">
            <button @click="mobileNavOpen = true"
                    class="nav-icon-btn -ml-1 h-11 w-11"
                    aria-label="Open menu"
                    aria-controls="ck_nav-primary">
                <x-icon name="bars" size="h-6 w-6" stroke="2" />
            </button>
            <img src="/images/servora-logo-white.png" alt="Servora" class="h-7" x-show="navTheme === 'dark'">
            <img src="/images/servora-logo-black.png" alt="Servora" class="h-7" x-show="navTheme === 'light'" x-cloak>
            <span class="ml-2 rounded-control bg-workspace-600 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-widest text-white">Kitchen</span>
            @if (! empty($title))
                <span class="nav-muted ml-auto truncate text-sm max-w-[40%]">{{ $title }}</span>
            @endif
        </div>

        <div class="p-4 sm:p-6">
            {{ $slot }}
        </div>
    </main>
</div>

</body>
</html>
