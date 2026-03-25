<div>
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-indigo-600 via-indigo-700 to-purple-800 text-white">
        <div class="absolute top-0 right-0 w-96 h-96 bg-white/5 rounded-full translate-x-1/3 -translate-y-1/3 blur-3xl"></div>
        <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
            <p class="text-xs font-bold text-indigo-300 uppercase tracking-widest mb-4">Pricing</p>
            <h1 class="text-4xl sm:text-5xl font-extrabold leading-tight">Simple, Transparent Pricing</h1>
            <p class="text-lg text-indigo-200 mt-4 max-w-xl mx-auto">
                All plans include a {{ $plans->first()?->trial_days ?? 30 }}-day free trial. No credit card required.
            </p>

            {{-- Cycle Toggle --}}
            <div class="mt-8 inline-flex items-center bg-white/10 backdrop-blur-sm rounded-full p-1 border border-white/20">
                <button wire:click="$set('cycle', 'monthly')"
                        class="px-5 py-2 text-sm font-medium rounded-full transition
                            {{ $cycle === 'monthly' ? 'bg-white text-indigo-700 shadow-md' : 'text-white/70 hover:text-white' }}">
                    Monthly
                </button>
                <button wire:click="$set('cycle', 'yearly')"
                        class="px-5 py-2 text-sm font-medium rounded-full transition
                            {{ $cycle === 'yearly' ? 'bg-white text-indigo-700 shadow-md' : 'text-white/70 hover:text-white' }}">
                    Yearly <span class="text-green-400 text-xs font-bold ml-1">Save up to 17%</span>
                </button>
            </div>
        </div>
    </section>

    {{-- Plan Cards --}}
    <section class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 relative z-10 pb-16">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach ($plans as $plan)
                @php
                    $isPopular = $plan->slug === 'professional';
                    $price = $cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly;
                    $perMonth = $cycle === 'yearly' ? $plan->price_yearly / 12 : $plan->price_monthly;
                @endphp
                <div class="relative bg-white rounded-2xl {{ $isPopular ? 'ring-2 ring-indigo-500 shadow-xl shadow-indigo-100 scale-[1.02]' : 'shadow-lg border border-gray-100' }} p-7 flex flex-col">
                    @if ($isPopular)
                        <div class="absolute -top-3.5 left-1/2 -translate-x-1/2">
                            <span class="px-4 py-1.5 bg-gradient-to-r from-indigo-600 to-purple-600 text-white text-xs font-bold rounded-full shadow-md">Most Popular</span>
                        </div>
                    @endif

                    <h3 class="text-xl font-bold text-gray-900">{{ $plan->name }}</h3>
                    <p class="text-xs text-gray-500 mt-1.5 min-h-[2.5rem] leading-relaxed">{{ $plan->description }}</p>

                    <div class="mt-5 mb-6">
                        <span class="text-4xl font-extrabold text-gray-900">{{ $plan->currency }} {{ number_format($perMonth, 0) }}</span>
                        <span class="text-sm text-gray-400">/month</span>
                        @if ($cycle === 'yearly')
                            <p class="text-xs text-gray-400 mt-1">Billed {{ $plan->currency }} {{ number_format($price, 0) }}/year</p>
                        @endif
                    </div>

                    <a href="{{ route('saas.register', ['plan' => $plan->slug]) }}"
                       class="block w-full py-3 text-center text-sm font-bold rounded-xl transition
                            {{ $isPopular ? 'bg-indigo-600 text-white hover:bg-indigo-700 shadow-md shadow-indigo-200' : 'bg-gray-100 text-gray-800 hover:bg-gray-200' }}">
                        Start Free Trial
                    </a>

                    <div class="mt-6 pt-6 border-t border-gray-100 flex-1">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">What's included</p>

                        {{-- Limits --}}
                        <ul class="space-y-3 text-sm text-gray-600">
                            <li class="flex items-center gap-2.5">
                                <svg class="h-5 w-5 text-indigo-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span><strong>{{ $plan->max_outlets ?? 'Unlimited' }}</strong> {{ Str::plural('outlet', $plan->max_outlets ?? 2) }}</span>
                            </li>
                            <li class="flex items-center gap-2.5">
                                <svg class="h-5 w-5 text-indigo-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span><strong>{{ $plan->max_users ?? 'Unlimited' }}</strong> users</span>
                            </li>
                            <li class="flex items-center gap-2.5">
                                <svg class="h-5 w-5 text-indigo-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span><strong>{{ $plan->max_recipes ?? 'Unlimited' }}</strong> recipes</span>
                            </li>
                            <li class="flex items-center gap-2.5">
                                <svg class="h-5 w-5 text-indigo-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span><strong>{{ $plan->max_lms_users ?? 'Unlimited' }}</strong> LMS users</span>
                            </li>
                        </ul>

                        {{-- Feature Flags --}}
                        <ul class="mt-4 space-y-3 text-sm">
                            @foreach ($plan->feature_flags ?? [] as $flag => $enabled)
                                <li class="flex items-center gap-2.5 {{ $enabled ? 'text-gray-600' : 'text-gray-300' }}">
                                    @if ($enabled)
                                        <svg class="h-5 w-5 text-green-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    @else
                                        <svg class="h-5 w-5 text-gray-200 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    @endif
                                    {{ str_replace('_', ' ', ucfirst($flag)) }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Features Comparison --}}
    <section class="bg-gray-50">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="text-center mb-10">
                <p class="text-xs font-bold text-indigo-600 uppercase tracking-widest mb-2">All Plans Include</p>
                <h2 class="text-2xl font-bold text-gray-900">Core Features on Every Plan</h2>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @php
                    $coreFeatures = [
                        ['icon' => '🥕', 'title' => 'Ingredients'],
                        ['icon' => '📋', 'title' => 'Recipes'],
                        ['icon' => '🛒', 'title' => 'Purchasing'],
                        ['icon' => '💰', 'title' => 'Sales'],
                        ['icon' => '📦', 'title' => 'Inventory'],
                        ['icon' => '📊', 'title' => 'Reports'],
                        ['icon' => '📄', 'title' => 'PDF Documents'],
                        ['icon' => '📥', 'title' => 'CSV Export'],
                    ];
                @endphp
                @foreach ($coreFeatures as $f)
                    <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
                        <span class="text-2xl">{{ $f['icon'] }}</span>
                        <p class="text-sm font-medium text-gray-700 mt-2">{{ $f['title'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="text-center mb-10">
            <p class="text-xs font-bold text-indigo-600 uppercase tracking-widest mb-2">FAQ</p>
            <h2 class="text-2xl font-bold text-gray-900">Frequently Asked Questions</h2>
        </div>
        <div class="space-y-3" x-data="{ open: null }">
            @php
                $faqs = [
                    ['q' => 'Can I change plans later?', 'a' => 'Yes, you can upgrade or downgrade at any time. Changes take effect immediately and billing is prorated.'],
                    ['q' => 'What payment methods do you accept?', 'a' => 'We accept FPX (online banking), credit/debit cards, and e-wallets through our payment partner CHIP-IN.'],
                    ['q' => 'Is my data secure?', 'a' => 'Absolutely. All data is encrypted, backed up daily, and each company\'s data is fully isolated from others.'],
                    ['q' => 'Can I export my data?', 'a' => 'Yes, all modules support CSV export. You own your data and can export it anytime.'],
                    ['q' => 'What happens when my trial ends?', 'a' => 'You can still view all your data, but creating or editing records will be disabled until you subscribe. Your data is preserved for 30 days.'],
                    ['q' => 'Do you offer custom plans?', 'a' => 'Yes! For large operations with specific requirements, contact us to discuss a tailored plan.'],
                ];
            @endphp
            @foreach ($faqs as $i => $faq)
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden hover:shadow-sm transition-shadow">
                    <button @click="open = open === {{ $i }} ? null : {{ $i }}"
                            class="w-full flex items-center justify-between px-6 py-4 text-left">
                        <span class="text-sm font-semibold text-gray-800">{{ $faq['q'] }}</span>
                        <svg :class="{ 'rotate-180': open === {{ $i }} }" class="h-5 w-5 text-gray-400 transition-transform flex-shrink-0 ml-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div x-show="open === {{ $i }}" x-collapse x-cloak class="px-6 pb-4">
                        <p class="text-sm text-gray-500 leading-relaxed">{{ $faq['a'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- CTA --}}
    <section class="bg-gradient-to-br from-gray-900 to-indigo-950">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
            <h2 class="text-3xl font-extrabold text-white">Start Managing Your F&B Business Today</h2>
            <p class="text-gray-400 mt-3">No credit card required. Cancel anytime.</p>
            <a href="{{ route('saas.register') }}"
               class="inline-block mt-8 px-8 py-4 bg-white text-indigo-700 font-bold rounded-xl hover:bg-indigo-50 transition shadow-lg text-sm">
                Start Your Free Trial
            </a>
        </div>
    </section>
</div>
