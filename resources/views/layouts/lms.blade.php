<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $lmsCompany = Auth::guard('lms')->user()?->company;
        $brandName = $lmsCompany->brand_name ?? $lmsCompany->name ?? 'Training';

        // Build sidebar SOP list grouped by category
        $sidebarSops = \App\Models\Recipe::where('company_id', Auth::guard('lms')->user()->company_id)
            ->where('is_active', true)
            ->where('is_prep', false)
            ->where('exclude_from_lms', false)
            ->select('id', 'name', 'code', 'category', 'menu_sort_order')
            ->orderBy('category')
            ->orderBy('menu_sort_order')
            ->orderBy('name')
            ->get()
            ->groupBy(fn ($r) => $r->category ?? 'Uncategorised');
    @endphp

    <title>{{ $brandName }} — {{ $title ?? 'Training Portal' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="font-sans antialiased bg-gray-50">

<div x-data="{
        sidebarOpen: localStorage.getItem('lms_sidebar') !== '0',
        toggleSidebar() {
            this.sidebarOpen = !this.sidebarOpen;
            localStorage.setItem('lms_sidebar', this.sidebarOpen ? '1' : '0');
        }
     }"
     class="flex h-screen overflow-hidden">

    {{-- ── Sidebar ── --}}
    <aside :class="sidebarOpen ? 'w-72' : 'w-0 lg:w-16'"
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
                <a href="{{ route('lms.dashboard') }}" class="flex items-center gap-2">
                    @if ($lmsCompany?->logo)
                        <img src="{{ Storage::disk('public')->url($lmsCompany->logo) }}" alt="{{ $brandName }}" class="h-14 max-w-[180px] object-contain">
                    @endif
                    <span class="text-sm font-bold text-white truncate">{{ $brandName }}</span>
                </a>
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
        <nav x-show="sidebarOpen"
             x-transition:enter="transition-opacity duration-200 delay-100"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity duration-75"
             class="flex-1 overflow-y-auto py-4 px-3">

            {{-- All SOPs link --}}
            <a href="{{ route('lms.dashboard') }}"
               class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-colors mb-3
                      {{ request()->routeIs('lms.dashboard') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                All SOPs
            </a>

            {{-- SOP list by category (accordion: one open at a time) --}}
            @php
                $activeSopId = request()->routeIs('lms.sop.show') ? (int) request()->route('id') : null;
                $activeCategory = null;
                if ($activeSopId) {
                    foreach ($sidebarSops as $catName => $catRecipes) {
                        if ($catRecipes->contains('id', $activeSopId)) {
                            $activeCategory = $catName;
                            break;
                        }
                    }
                }
            @endphp
            <div x-data="{ openCategory: @js($activeCategory) }">
                @foreach ($sidebarSops as $categoryName => $catRecipes)
                    <div class="mb-3">
                        <button @click="openCategory = (openCategory === @js($categoryName) ? null : @js($categoryName))"
                                class="w-full flex items-center justify-between px-3 py-1.5 text-xs font-semibold text-gray-400 uppercase tracking-wider hover:text-gray-200 transition">
                            <span class="truncate">{{ $categoryName }}</span>
                            <svg :class="openCategory === @js($categoryName) && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 flex-shrink-0 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="openCategory === @js($categoryName)" x-cloak class="mt-0.5 space-y-0.5">
                            @foreach ($catRecipes as $sop)
                                @php $isActive = $activeSopId === (int) $sop->id; @endphp
                                <a href="{{ route('lms.sop.show', $sop->id) }}"
                                   class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm transition-colors
                                          {{ $isActive ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 flex-shrink-0 {{ $isActive ? 'text-white' : 'text-gray-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <span class="truncate">{{ $sop->name }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

        </nav>

        {{-- Collapsed: icon-only nav --}}
        <nav x-show="!sidebarOpen"
             x-transition:enter="transition-opacity duration-200 delay-100"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity duration-75"
             class="hidden lg:flex flex-1 flex-col items-center py-4 px-2 overflow-y-auto">
            <a href="{{ route('lms.dashboard') }}" title="All SOPs"
               class="w-10 h-10 flex items-center justify-center rounded-lg mb-2 transition
                      {{ request()->routeIs('lms.dashboard') ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
            </a>
        </nav>

        {{-- Bottom: User --}}
        <div class="flex-shrink-0 border-t border-gray-700 p-2" x-data="{ userOpen: false }">
            <div x-show="sidebarOpen"
                 x-transition:enter="transition-opacity duration-150 delay-100"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity duration-75">
                <div class="relative">
                    <button @click="userOpen = !userOpen"
                            class="flex items-center w-full gap-3 px-3 py-2 rounded-lg hover:bg-gray-800 transition">
                        <div class="flex-shrink-0 w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center text-white font-bold text-xs">
                            {{ strtoupper(substr(Auth::guard('lms')->user()->name, 0, 2)) }}
                        </div>
                        <div class="flex-1 text-left overflow-hidden">
                            <p class="text-sm font-medium text-white truncate">{{ Auth::guard('lms')->user()->name }}</p>
                            <p class="text-xs text-gray-400 truncate">{{ Auth::guard('lms')->user()->email }}</p>
                        </div>
                    </button>
                    <div x-show="userOpen" @click.away="userOpen = false" x-cloak
                         class="absolute bottom-full left-0 mb-1 w-full bg-gray-800 rounded-lg border border-gray-700 py-1 shadow-lg">
                        <form method="POST" action="{{ route('lms.logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-400 hover:bg-gray-700 hover:text-red-300 transition">
                                Sign Out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div x-show="!sidebarOpen" class="hidden lg:flex justify-center">
                <form method="POST" action="{{ route('lms.logout') }}">
                    @csrf
                    <button type="submit" title="Sign Out"
                            class="w-10 h-10 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-700 hover:text-red-400 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- ── Main content ── --}}
    <div class="flex-1 flex flex-col overflow-hidden">
        {{-- Top bar (mobile toggle + brand) --}}
        <header class="lg:hidden bg-white border-b border-gray-200 h-14 flex items-center px-4 flex-shrink-0">
            <button @click="toggleSidebar()" class="text-gray-500 hover:text-gray-700 transition mr-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <a href="{{ route('lms.dashboard') }}" class="flex items-center gap-2">
                @if ($lmsCompany?->logo)
                    <img src="{{ Storage::disk('public')->url($lmsCompany->logo) }}" alt="{{ $brandName }}" class="h-11 max-w-[140px] object-contain">
                @endif
                <span class="text-sm font-bold text-gray-900">{{ $brandName }}</span>
            </a>
        </header>

        <main class="flex-1 overflow-y-auto p-6">
            {{ $slot }}
        </main>

        {{-- Footer --}}
        <footer class="border-t border-gray-200 flex-shrink-0">
            <div class="px-6 py-4 text-center">
                <p class="text-xs text-gray-400">&copy; {{ date('Y') }} {{ $lmsCompany?->name ?? 'Company' }}. Training Portal powered by Servora.</p>
            </div>
        </footer>
    </div>
</div>

</body>
</html>
