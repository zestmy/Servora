<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $lmsUser = Auth::guard('lms')->user();
        $lmsCompany = $lmsUser?->company;
        $brandName = $lmsCompany->brand_name ?? $lmsCompany->name ?? 'Training';

        // Category sort-order lookup with parent hierarchy (parent_sort → parent_name → sub_sort → sub_name)
        $lmsCategorySortMap = [];
        $lmsRecipeCategories = \App\Models\RecipeCategory::outletScope()
            ->where('company_id', $lmsUser->company_id)
            ->with('parent')
            ->get();
        foreach ($lmsRecipeCategories as $rc) {
            $parentSort = $rc->parent ? $rc->parent->sort_order : $rc->sort_order;
            $parentName = $rc->parent ? strtolower($rc->parent->name) : strtolower($rc->name);
            $subSort    = $rc->parent ? $rc->sort_order : 0;
            $subName    = $rc->parent ? strtolower($rc->name) : '';
            $lmsCategorySortMap[strtolower($rc->name)] = [$parentSort, $parentName, $subSort, $subName];
        }

        // Build sidebar SOP list grouped by category (filtered to trainee's outlet).
        // Prep items use the same menu categories as recipes (recipes.category);
        // they sort by the same hierarchy but group after the recipe sections.
        $sidebarSops = \App\Models\Recipe::where('company_id', $lmsUser->company_id)
            ->where('is_active', true)
            ->where('exclude_from_lms', false)
            ->visibleToOutlets($lmsUser->accessibleOutletIds())
            ->select('id', 'name', 'code', 'category', 'menu_sort_order', 'is_prep')
            ->get()
            ->sortBy(function ($r) use ($lmsCategorySortMap) {
                $cs = $lmsCategorySortMap[strtolower($r->category ?? '')] ?? [PHP_INT_MAX, '~', 0, ''];
                return [$r->is_prep ? 1 : 0, $cs[0], $cs[1], $cs[2], $cs[3], $r->menu_sort_order ?? 0, strtolower($r->name)];
            })
            ->values()
            ->groupBy(fn ($r) => $r->is_prep
                ? 'Prep — ' . ($r->category ?? 'Uncategorised')
                : ($r->category ?? 'Uncategorised'));
    @endphp

    <title>{{ $title ?? 'Training Portal' }} | {{ $brandName }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="manifest" href="{{ asset('lms-manifest.json') }}">
    <meta name="theme-color" content="#111827">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ $brandName }} Training">
    <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">

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
        },
        closeMobile() {
            if (window.innerWidth < 1024) {
                this.sidebarOpen = false;
                localStorage.setItem('lms_sidebar', '0');
            }
        }
     }"
     class="flex h-screen overflow-hidden">

    {{-- ── Sidebar ── --}}
    <aside :class="sidebarOpen ? 'w-72' : 'w-0 lg:w-16'"
           class="flex flex-col bg-gray-900 text-white flex-shrink-0 transition-[width] duration-300 ease-in-out overflow-hidden">

        {{-- Brand: a white band carrying the logo and nothing else.

             The logo gets its own light surface rather than a chip on the dark
             one. It only renders when there IS a logo — an empty white strip
             above the nav would read as a rendering fault, not as branding.
             Still <x-brand-mark surface="light">, because a company with light
             artwork would now be the one that disappears. --}}
        @if ($lmsCompany?->logo)
            <div x-show="sidebarOpen"
                 x-transition:enter="transition-opacity duration-200 delay-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity duration-75"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="flex items-center justify-center bg-white px-4 py-3 flex-shrink-0">
                <a href="{{ route('lms.dashboard') }}" class="flex items-center justify-center">
                    <x-brand-mark :company="$lmsCompany" surface="light"
                                  size="h-12" width="max-w-[200px]" :alt="$brandName" />
                </a>
            </div>
        @endif

        {{-- Company name + toggle, on the dark band the header used to be. --}}
        <div class="relative flex items-center h-11 px-3 bg-gray-800 flex-shrink-0">
            <div x-show="sidebarOpen"
                 x-transition:enter="transition-opacity duration-200 delay-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity duration-75"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="flex-1 min-w-0">
                {{-- Centred under the logo, with px-10 so a long name truncates
                     before it reaches the toggle rather than running under it. --}}
                <a href="{{ route('lms.dashboard') }}" class="block px-10">
                    <span class="block text-sm font-bold text-white truncate text-center">{{ $brandName }}</span>
                </a>
            </div>

            {{-- Absolute while open so it does not pull the name off centre;
                 back in flow when collapsed, where it is the only thing here. --}}
            <button @click="toggleSidebar()"
                    :class="sidebarOpen ? 'absolute right-3' : 'mx-auto'"
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

            {{-- The SOP library, which is what this portal is for.

                 Training used to sit above this and has moved to the staff app,
                 where an employee reaches it with the PIN they already clock in
                 with — this login is invitation-only and reached a fraction of
                 the floor. Kept as a list of one rather than collapsed back to a
                 bare link: the rail below renders from the same array, and the
                 next thing added here should not have to rebuild both. --}}
            @php
                $lmsLinks = [
                    ['route' => 'lms.dashboard', 'label' => 'All SOPs', 'icon' => 'document', 'match' => 'lms.dashboard'],
                ];
            @endphp

            <div class="mb-4 space-y-0.5">
                @foreach ($lmsLinks as $link)
                    @php $isOn = request()->routeIs($link['match']); @endphp
                    <a href="{{ route($link['route']) }}" @click="closeMobile()"
                       @if ($isOn) aria-current="page" @endif
                       class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                              {{ $isOn ? 'bg-brand-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                        <x-icon :name="$link['icon']" size="h-4 w-4" stroke="2" class="flex-shrink-0" />
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </div>

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
                                <a href="{{ route('lms.sop.show', $sop->id) }}" @click="closeMobile()"
                                   class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm transition-colors
                                          {{ $isActive ? 'bg-brand-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 flex-shrink-0 {{ $isActive ? 'text-white' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
            {{-- Every destination stays reachable at the rail. The outlet
                 sidebar shipped a rail that could only reach one screen, and
                 NavigationPanelTest exists because of it. --}}
            @foreach ($lmsLinks ?? [] as $link)
                @php $isOn = request()->routeIs($link['match']); @endphp
                <a href="{{ route($link['route']) }}" title="{{ $link['label'] }}"
                   @if ($isOn) aria-current="page" @endif
                   class="w-10 h-10 flex items-center justify-center rounded-lg mb-2 transition
                          {{ $isOn ? 'bg-brand-600 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white' }}">
                    <x-icon :name="$link['icon']" size="h-5 w-5" stroke="2" />
                    <span class="sr-only">{{ $link['label'] }}</span>
                </a>
            @endforeach
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
                        <div class="flex-shrink-0 w-8 h-8 bg-brand-600 rounded-full flex items-center justify-center text-white font-bold text-xs">
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
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-danger-400 hover:bg-gray-700 hover:text-danger-300 transition">
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
                            class="w-10 h-10 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-700 hover:text-danger-400 transition">
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
                {{-- White bar, so here it is a LIGHT logo that would vanish. --}}
                <x-brand-mark :company="$lmsCompany" surface="light"
                              size="h-10" width="max-w-[140px]" :alt="$brandName" />
                <span class="text-sm font-bold text-gray-900">{{ $brandName }}</span>
            </a>
        </header>

        <main class="flex-1 overflow-y-auto p-6">
            {{ $slot }}
        </main>

        {{-- Footer --}}
        <footer class="border-t border-gray-200 flex-shrink-0">
            <div class="px-6 py-4 text-center">
                <p class="text-xs text-gray-600">&copy; {{ date('Y') }} {{ $lmsCompany?->name ?? 'Company' }}. Training Portal powered by Servora.</p>
            </div>
        </footer>
    </div>
</div>

{{-- PWA Install Banner.

     Was a block of inline hex, which is exactly the gap the indigo -> brand
     alias cannot cover: it only rewrites Tailwind classes. So while the rest
     of the app moved to teal, this banner stayed #1e1b4b — literally the
     pre-rebrand indigo-950, still shipping on every LMS page. On classes now,
     so the next accent change reaches it too.

     `display` stays inline because the script toggles that property
     directly. --}}
<div id="pwa-banner" style="display:none;"
     class="fixed inset-x-0 bottom-0 z-toast bg-gray-900 p-4 text-white">
    <div class="mx-auto flex max-w-[480px] items-start gap-3">
        <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-control bg-brand-600">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
        </div>
        <div class="min-w-0 flex-1">
            <div class="mb-1 text-sm font-semibold">Install Training App</div>
            {{-- brand-100 on gray-900 is 15.34:1. --}}
            <div id="pwa-instructions" class="text-xs leading-relaxed text-brand-100"></div>
        </div>
        <button onclick="dismissPwaBanner()" aria-label="Dismiss"
                class="icon-btn -mr-1 -mt-1 flex-shrink-0 text-gray-400 hover:bg-gray-800 hover:text-white">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>

<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/lms-sw.js').catch(function() {});
}

(function() {
    var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
    if (isStandalone) return;
    if (localStorage.getItem('pwa_dismissed')) return;

    var banner = document.getElementById('pwa-banner');
    var instructions = document.getElementById('pwa-instructions');
    var isIos = /iPhone|iPad|iPod/.test(navigator.userAgent);
    var isAndroid = /Android/.test(navigator.userAgent);

    if (isIos) {
        // currentColor, so the glyph follows the container's token instead of
        // pinning a brand shade that the next accent change would strand.
        instructions.innerHTML = 'Tap the <svg style="display:inline;vertical-align:middle;margin:0 2px;" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0-12l-4 4m4-4l4 4"/></svg> <strong>Share</strong> button in Safari, then tap <strong>"Add to Home Screen"</strong>.';
        banner.style.display = 'block';
    } else if (isAndroid) {
        var deferredPrompt = null;
        window.addEventListener('beforeinstallprompt', function(e) {
            e.preventDefault();
            deferredPrompt = e;
            instructions.innerHTML = 'Get quick access from your home screen.';
            var btn = document.createElement('button');
            btn.textContent = 'Install';
            btn.className = 'btn-primary btn-sm mt-2';
            btn.onclick = function() {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function() { banner.style.display = 'none'; });
            };
            instructions.appendChild(btn);
            banner.style.display = 'block';
        });
    }
})();

function dismissPwaBanner() {
    document.getElementById('pwa-banner').style.display = 'none';
    localStorage.setItem('pwa_dismissed', '1');
}
</script>

{{-- The delete confirmation gate. Inert until something on the page
     carries data-confirm-delete. See components/confirm-delete.blade.php. --}}
<x-confirm-delete />

</body>
</html>
