{{-- The marketing site's header. Shared by layouts.marketing and the
     sign-in page so both carry the same nav. Expects $nav and $landing from
     the including view, and falls back to English when they are absent. --}}
@php
    $landing ??= null;
    $nav ??= fn (string $key, string $english) => $english;
    $headerPages = \App\Models\Page::inHeader()->get();

    // Nav labels are deliberately unchanged. Muscle memory and analytics
    // both key off them, so the one-line fix is the breakpoint and the
    // type scale, not the wording.
    $navLinks = [
        ['route' => 'features',         'label' => $nav('nav.features', 'Features')],
        ['route' => 'pricing',          'label' => $nav('nav.pricing', 'Pricing')],
        ['route' => 'marketplace',      'label' => 'Marketplace',   'module' => 'supplier_portal'],
        // Third, not last: it is the only item here somebody can use
        // without deciding anything first, and the nav is read left to
        // right until something looks free.
        ['route' => 'tools.index',      'label' => $nav('nav.tools', 'Free Tools')],
        ['route' => 'for-suppliers',    'label' => 'For Suppliers', 'module' => 'supplier_portal'],
        // The manual. Public, and linked from the marketing nav for the
        // same reason it is public: most of what it answers is asked
        // before anyone has an account.
        ['route' => 'help.index',       'label' => $nav('nav.help', 'Help')],
        ['route' => 'referral.program', 'label' => $nav('nav.refer', 'Refer & Earn')],
    ];

    // 'module': a platform switch in config/modules.php. A parked module's
    // links go from the header and footer both, or they would 404.
    $moduleOn = fn (array $link) => empty($link['module']) || config('modules.'.$link['module']);
    $navLinks = array_values(array_filter($navLinks, $moduleOn));
@endphp

{{-- ── Nav ──────────────────────────────────────────────────────────────
     Sticky, 64px, single line from lg up. Below lg it is a sheet: the
     full set plus any CMS header pages never fit a phone width, and the
     old 192px dropdown truncated them.
--}}
<header class="sticky top-0 z-sticky border-b border-gray-200/70 bg-white/85 backdrop-blur-md"
        x-data="{ open: false }">
    <nav class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-6 px-4 sm:px-6 lg:px-8"
         aria-label="Primary">

        <a href="{{ route('marketing.home') }}" class="flex-shrink-0" aria-label="Servora home">
            <img src="{{ brand_asset('images/servora-logo-black.png') }}" alt="Servora" class="h-8 w-auto">
        </a>

        <div class="hidden items-center gap-7 lg:flex">
            @foreach ($navLinks as $link)
                @php $isActive = request()->routeIs($link['route']); @endphp
                <a href="{{ route($link['route']) }}"
                   @class([
                       'text-[13px] font-medium transition-colors',
                       'text-brand-700' => $isActive,
                       'text-gray-600 hover:text-gray-900' => ! $isActive,
                   ])
                   @if ($isActive) aria-current="page" @endif>
                    {{ $link['label'] }}
                </a>
            @endforeach

            @foreach ($headerPages as $hp)
                <a href="{{ $hp->url() }}" target="{{ $hp->linkTarget() }}"
                   class="text-[13px] font-medium text-gray-600 transition-colors hover:text-gray-900">
                    {{ $hp->title }}
                </a>
            @endforeach
        </div>

        <div class="hidden items-center gap-3 lg:flex">
            @if ($landing)
                <a href="{{ route('marketing.home', ['lang' => 'en']) }}" hreflang="en"
                   class="text-[13px] font-medium text-gray-600 transition-colors hover:text-gray-900">
                    {{ $nav('nav.english', 'View in English') }}
                </a>
            @endif
            <a href="{{ route('login') }}"
               class="text-[13px] font-medium text-gray-600 transition-colors hover:text-gray-900">
                {{ $nav('nav.login', 'Log In') }}
            </a>
            <a href="{{ route('saas.register') }}" class="btn-primary btn-sm">
                {{ $nav('nav.cta', 'Start Free Trial') }}
            </a>
        </div>

        {{-- Mobile trigger --}}
        <button type="button" @click="open = ! open"
                class="btn-ghost btn-icon lg:hidden"
                :aria-expanded="open ? 'true' : 'false'"
                aria-controls="mobile-nav"
                aria-label="Toggle navigation">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path x-show="! open" stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16"/>
                <path x-show="open" x-cloak stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/>
            </svg>
        </button>
    </nav>

    {{-- Mobile sheet --}}
    <div id="mobile-nav" x-show="open" x-cloak @click.away="open = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="border-t border-gray-200 bg-white lg:hidden">
        <div class="mx-auto max-w-6xl px-4 py-4 sm:px-6">
            <div class="stack">
                @foreach ($navLinks as $link)
                    <a href="{{ route($link['route']) }}"
                       class="block py-3 text-sm font-medium text-gray-700 hover:text-brand-700">
                        {{ $link['label'] }}
                    </a>
                @endforeach
                @foreach ($headerPages as $hp)
                    <a href="{{ $hp->url() }}" target="{{ $hp->linkTarget() }}"
                       class="block py-3 text-sm font-medium text-gray-700 hover:text-brand-700">
                        {{ $hp->title }}
                    </a>
                @endforeach
                @if ($landing)
                    <a href="{{ route('marketing.home', ['lang' => 'en']) }}" hreflang="en"
                       class="block py-3 text-sm font-medium text-gray-700 hover:text-brand-700">
                        {{ $nav('nav.english', 'View in English') }}
                    </a>
                @endif
                <a href="{{ route('login') }}"
                   class="block py-3 text-sm font-medium text-gray-700 hover:text-brand-700">
                    {{ $nav('nav.login', 'Log In') }}
                </a>
            </div>
            <a href="{{ route('saas.register') }}" class="btn-primary mt-4 w-full">
                {{ $nav('nav.cta', 'Start Free Trial') }}
            </a>
        </div>
    </div>
</header>
