<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="text-center mb-10">
        <h1 class="text-3xl font-bold text-gray-900">Simple, Transparent Pricing</h1>
        <p class="text-sm text-gray-500 mt-2">All plans include a {{ $plans->first()?->trial_days ?? 30 }}-day free trial. No credit card required.</p>

        {{-- Cycle Toggle --}}
        <div class="mt-6 inline-flex items-center bg-gray-100 rounded-full p-1">
            <button wire:click="$set('cycle', 'monthly')"
                    class="px-4 py-1.5 text-sm font-medium rounded-full transition
                        {{ $cycle === 'monthly' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                Monthly
            </button>
            <button wire:click="$set('cycle', 'yearly')"
                    class="px-4 py-1.5 text-sm font-medium rounded-full transition
                        {{ $cycle === 'yearly' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                Yearly <span class="text-green-600 text-xs font-bold">Save up to 17%</span>
            </button>
        </div>
    </div>

    {{-- Plan Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @foreach ($plans as $plan)
            @php
                $isPopular = $plan->slug === 'professional';
                $price = $cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly;
                $perMonth = $cycle === 'yearly' ? $plan->price_yearly / 12 : $plan->price_monthly;
            @endphp
            <div class="relative bg-white rounded-2xl shadow-sm border {{ $isPopular ? 'border-indigo-500 ring-2 ring-indigo-500' : 'border-gray-100' }} p-6">
                @if ($isPopular)
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                        <span class="px-3 py-1 bg-indigo-600 text-white text-xs font-bold rounded-full">Most Popular</span>
                    </div>
                @endif

                <h3 class="text-lg font-bold text-gray-900">{{ $plan->name }}</h3>
                <p class="text-xs text-gray-500 mt-1 min-h-[2rem]">{{ $plan->description }}</p>

                <div class="mt-4">
                    <span class="text-3xl font-extrabold text-gray-900">{{ $plan->currency }} {{ number_format($perMonth, 0) }}</span>
                    <span class="text-sm text-gray-400">/month</span>
                </div>
                @if ($cycle === 'yearly')
                    <p class="text-xs text-gray-400 mt-1">Billed {{ $plan->currency }} {{ number_format($price, 0) }}/year</p>
                @endif

                <a href="{{ route('saas.register', ['plan' => $plan->slug]) }}"
                   class="block mt-5 w-full py-2.5 text-center text-sm font-semibold rounded-lg transition
                        {{ $isPopular ? 'bg-indigo-600 text-white hover:bg-indigo-700' : 'bg-gray-100 text-gray-800 hover:bg-gray-200' }}">
                    Start Free Trial
                </a>

                {{-- Limits --}}
                <ul class="mt-5 space-y-2 text-sm text-gray-600">
                    <li class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-green-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        {{ $plan->max_outlets ? $plan->max_outlets . ' ' . Str::plural('outlet', $plan->max_outlets) : 'Unlimited outlets' }}
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-green-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        {{ $plan->max_users ? $plan->max_users . ' users' : 'Unlimited users' }}
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-green-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        {{ $plan->max_recipes ? $plan->max_recipes . ' recipes' : 'Unlimited recipes' }}
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-green-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        {{ $plan->max_lms_users ? $plan->max_lms_users . ' LMS users' : 'Unlimited LMS users' }}
                    </li>
                </ul>

                {{-- Feature Flags --}}
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($plan->feature_flags ?? [] as $flag => $enabled)
                        <li class="flex items-center gap-2 {{ $enabled ? 'text-gray-600' : 'text-gray-300' }}">
                            @if ($enabled)
                                <svg class="h-4 w-4 text-green-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            @else
                                <svg class="h-4 w-4 text-gray-300 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            @endif
                            {{ str_replace('_', ' ', ucfirst($flag)) }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>

    {{-- FAQ --}}
    <div class="mt-16 max-w-2xl mx-auto">
        <h2 class="text-xl font-bold text-gray-900 text-center mb-8">Frequently Asked Questions</h2>
        <div class="space-y-4" x-data="{ open: null }">
            @php
                $faqs = [
                    ['q' => 'Can I change plans later?', 'a' => 'Yes, you can upgrade or downgrade at any time. Changes take effect immediately and billing is prorated.'],
                    ['q' => 'What payment methods do you accept?', 'a' => 'We accept FPX (online banking), credit/debit cards, and e-wallets through our payment partner CHIP-IN.'],
                    ['q' => 'Is my data secure?', 'a' => 'Absolutely. All data is encrypted, backed up daily, and each company\'s data is fully isolated from others.'],
                    ['q' => 'Can I export my data?', 'a' => 'Yes, all modules support CSV export. You own your data and can export it anytime.'],
                ];
            @endphp
            @foreach ($faqs as $i => $faq)
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <button @click="open = open === {{ $i }} ? null : {{ $i }}"
                            class="w-full flex items-center justify-between px-5 py-4 text-left">
                        <span class="text-sm font-medium text-gray-800">{{ $faq['q'] }}</span>
                        <svg :class="{ 'rotate-180': open === {{ $i }} }" class="h-4 w-4 text-gray-400 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div x-show="open === {{ $i }}" x-collapse x-cloak class="px-5 pb-4">
                        <p class="text-sm text-gray-500">{{ $faq['a'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
