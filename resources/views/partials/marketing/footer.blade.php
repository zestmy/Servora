{{-- The marketing site's footer. Shared by layouts.marketing and the
     sign-in page. Expects $nav from the including view, English otherwise. --}}
@php
    $nav ??= fn (string $key, string $english) => $english;
    $footerPages = \App\Models\Page::inFooter()->get();
    $moduleOn = fn (array $link) => empty($link['module']) || config('modules.'.$link['module']);
@endphp
{{-- ── Footer ───────────────────────────────────────────────────────── --}}
<footer class="border-t border-white/10 bg-navy-950 text-gray-400">
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8">

        @php
            /* The static columns as data.

               The Product column had quietly become a dumping ground —
               nine links, four of them tools — while Company held one and
               Legal two. Columns of 9/1/2 do not read as columns; they read
               as a list that ran out of room. Grouping by what a visitor is
               trying to DO puts four or five in each. */
            $footerColumns = [
                'Product' => [
                    ['route' => 'features',            'label' => 'Features'],
                    ['route' => 'pricing',             'label' => 'Pricing'],
                    ['route' => 'marketplace',         'label' => 'Marketplace',   'module' => 'supplier_portal'],
                    ['route' => 'for-suppliers',       'label' => 'For Suppliers', 'module' => 'supplier_portal'],
                    ['route' => 'marketing.downloads', 'label' => 'Downloads'],
                    ['route' => 'help.index',          'label' => 'Help Centre'],
                ],
                'Free tools' => [
                    ['route' => 'tools.recipe-cost', 'label' => 'Recipe Cost Calculator'],
                    ['route' => 'tools.food-cost',   'label' => 'Food Cost Calculator'],
                    ['route' => 'tools.menu-matrix', 'label' => 'Menu Engineering Matrix'],
                    ['route' => 'tools.salary',      'label' => 'Salary Calculator'],
                    ['route' => 'tools.ea-form',     'label' => 'Borang EA Generator'],
                    ['route' => 'tools.index',       'label' => 'All free tools', 'muted' => true],
                ],
            ];

            /* One list per column, and `other` is the exact complement of
               the two, so a CMS page can never fall out of the footer by
               being given a slug nobody anticipated. */
            $companySlugs = ['about', 'about-us', 'contact', 'contact-us', 'careers'];
            $legalSlugs   = ['privacy-policy', 'privacy', 'terms', 'terms-of-use', 'terms-of-service', 'refund-policy'];

            $companyPages = $footerPages->filter(fn ($p) => in_array($p->slug, $companySlugs));
            $legalPages   = $footerPages->filter(fn ($p) => in_array($p->slug, $legalSlugs));
            $otherPages   = $footerPages->reject(fn ($p) => in_array($p->slug, array_merge($companySlugs, $legalSlugs)));

            $footerColumns = array_map(fn ($links) => array_values(array_filter($links, $moduleOn)), $footerColumns);
        @endphp

        {{-- Four equal link columns rather than three uneven ones. Brand
             sits above them until there is room to put it alongside: two
             by two on a tablet (three columns would strand the fourth on
             a row of its own), all five in a line from lg. --}}
        <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-[1.6fr_repeat(4,1fr)]">

            <div class="max-w-xs md:col-span-2 lg:col-span-1">
                <img src="{{ brand_asset('images/servora-logo-white.png') }}" alt="Servora" class="h-8 w-auto">
                <p class="mt-4 text-sm leading-relaxed">
                    {{ $nav('nav.tagline', 'Costing, purchasing, inventory and training for F&B operators who need to know their numbers before month end.') }}
                </p>
                <a href="{{ route('saas.register') }}"
                   class="mt-5 inline-flex items-center gap-1.5 text-sm font-medium text-brand-300 transition-colors hover:text-brand-200">
                    Start your free trial
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>

            @foreach ($footerColumns as $heading => $links)
                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-white">{{ $heading }}</h2>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        @foreach ($links as $link)
                            <li>
                                <a href="{{ route($link['route']) }}"
                                   class="transition-colors hover:text-white {{ ($link['muted'] ?? false) ? 'text-gray-500' : '' }}">
                                    {{ $link['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wider text-white">Company</h2>
                <ul class="mt-4 space-y-2.5 text-sm">
                    @foreach ($companyPages as $cp)
                        <li><a href="{{ $cp->url() }}" target="{{ $cp->linkTarget() }}" class="transition-colors hover:text-white">{{ $cp->title }}</a></li>
                    @endforeach

                    {{-- A referral programme is something you join, not a
                         product you buy — it reads oddly under Product. --}}
                    <li><a href="{{ route('referral.program') }}" class="transition-colors hover:text-white">Refer &amp; Earn</a></li>
                    <li><a href="{{ route('login') }}" class="transition-colors hover:text-white">Log in</a></li>
                </ul>
            </div>

            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wider text-white">Legal</h2>
                <ul class="mt-4 space-y-2.5 text-sm">
                    @forelse ($legalPages as $lp)
                        <li><a href="{{ $lp->url() }}" target="{{ $lp->linkTarget() }}" class="transition-colors hover:text-white">{{ $lp->title }}</a></li>
                    @empty
                        {{-- Placeholders, so the column is never empty on a
                             fresh install and it is obvious what belongs
                             here once the pages exist. --}}
                        <li><span class="text-gray-600">Privacy Policy</span></li>
                        <li><span class="text-gray-600">Terms of Service</span></li>
                    @endforelse

                    @foreach ($otherPages as $op)
                        <li><a href="{{ $op->url() }}" target="{{ $op->linkTarget() }}" class="transition-colors hover:text-white">{{ $op->title }}</a></li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="mt-12 flex flex-col gap-3 border-t border-white/10 pt-6 text-sm sm:flex-row sm:items-center sm:justify-between">
            <p>{!! \App\Models\AppSetting::get('footer_copyright', '&copy; ' . date('Y') . ' Servora. All rights reserved.') !!}</p>
            <p class="text-gray-500">Made for F&amp;B operators in Malaysia.</p>
        </div>
    </div>
</footer>
