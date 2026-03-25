<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Servora' }} — Servora</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-50 text-gray-800 antialiased">

    @php
        $headerPages = \App\Models\Page::inHeader()->get();
        $footerPages = \App\Models\Page::inFooter()->get();
    @endphp

    {{-- Top Nav --}}
    <nav class="bg-white border-b border-gray-100">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="{{ route('marketing.home') }}">
                    <img src="{{ asset('images/servora-logo-black.png') }}" alt="Servora" class="h-8">
                </a>

                <div class="hidden sm:flex items-center gap-6">
                    <a href="{{ route('features') }}" class="text-sm text-gray-600 hover:text-gray-900 transition">Features</a>
                    <a href="{{ route('pricing') }}" class="text-sm text-gray-600 hover:text-gray-900 transition">Pricing</a>
                    <a href="{{ route('referral.program') }}" class="text-sm text-gray-600 hover:text-gray-900 transition">Refer & Earn</a>
                    @foreach ($headerPages as $hp)
                        <a href="{{ $hp->url() }}" target="{{ $hp->linkTarget() }}" class="text-sm text-gray-600 hover:text-gray-900 transition">{{ $hp->title }}</a>
                    @endforeach
                    <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-gray-900 transition">Log In</a>
                    <a href="{{ route('saas.register') }}"
                       class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        Start Free Trial
                    </a>
                </div>

                {{-- Mobile menu --}}
                <div class="sm:hidden" x-data="{ open: false }">
                    <button @click="open = !open" class="text-gray-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div x-show="open" @click.away="open = false" x-cloak
                         class="absolute right-4 mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-100 py-2 z-50">
                        <a href="{{ route('features') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Features</a>
                        <a href="{{ route('pricing') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Pricing</a>
                        <a href="{{ route('referral.program') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Refer & Earn</a>
                        @foreach ($headerPages as $hp)
                            <a href="{{ $hp->url() }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ $hp->title }}</a>
                        @endforeach
                        <a href="{{ route('login') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Log In</a>
                        <a href="{{ route('saas.register') }}" class="block px-4 py-2 text-sm text-indigo-600 font-medium hover:bg-gray-50">Start Free Trial</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    {{-- Main Content --}}
    <main>
        {{ $slot }}
    </main>

    {{-- Footer --}}
    <footer class="bg-gray-900 text-gray-400 mt-20">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="mb-8">
                <img src="{{ asset('images/servora-logo-white.png') }}" alt="Servora" class="h-8">
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                <div>
                    <h4 class="text-white font-semibold text-sm mb-3">Product</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('features') }}" class="hover:text-white transition">Features</a></li>
                        <li><a href="{{ route('pricing') }}" class="hover:text-white transition">Pricing</a></li>
                        <li><a href="{{ route('referral.program') }}" class="hover:text-white transition">Refer & Earn</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold text-sm mb-3">Company</h4>
                    <ul class="space-y-2 text-sm">
                        @php $companyPages = $footerPages->filter(fn($p) => in_array($p->slug, ['about', 'about-us', 'contact', 'contact-us'])); @endphp
                        @forelse ($companyPages as $cp)
                            <li><a href="{{ $cp->url() }}" target="{{ $cp->linkTarget() }}" class="hover:text-white transition">{{ $cp->title }}</a></li>
                        @empty
                            <li><a href="#" class="hover:text-white transition">About</a></li>
                        @endforelse
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold text-sm mb-3">Legal</h4>
                    <ul class="space-y-2 text-sm">
                        @php $legalPages = $footerPages->filter(fn($p) => in_array($p->slug, ['privacy-policy', 'privacy', 'terms-of-use', 'terms', 'terms-of-service'])); @endphp
                        @forelse ($legalPages as $lp)
                            <li><a href="{{ $lp->url() }}" target="{{ $lp->linkTarget() }}" class="hover:text-white transition">{{ $lp->title }}</a></li>
                        @empty
                            <li><a href="#" class="hover:text-white transition">Privacy Policy</a></li>
                            <li><a href="#" class="hover:text-white transition">Terms of Service</a></li>
                        @endforelse
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold text-sm mb-3">Resources</h4>
                    <ul class="space-y-2 text-sm">
                        @php $otherPages = $footerPages->reject(fn($p) => in_array($p->slug, ['about', 'about-us', 'contact', 'contact-us', 'privacy-policy', 'privacy', 'terms-of-use', 'terms', 'terms-of-service'])); @endphp
                        @foreach ($otherPages as $op)
                            <li><a href="{{ $op->url() }}" target="{{ $op->linkTarget() }}" class="hover:text-white transition">{{ $op->title }}</a></li>
                        @endforeach
                        <li><a href="{{ route('saas.register') }}" class="hover:text-white transition">Get Started</a></li>
                    </ul>
                </div>
            </div>
            <div class="mt-10 pt-6 border-t border-gray-800 text-sm text-center">
                {!! \App\Models\AppSetting::get('footer_copyright', '&copy; ' . date('Y') . ' Servora. All rights reserved.') !!}
            </div>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
