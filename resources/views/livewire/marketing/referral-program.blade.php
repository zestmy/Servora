<div>
    {{-- Hero --}}
    <section class="bg-gradient-to-br from-indigo-600 via-indigo-700 to-purple-800 text-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
            <h1 class="text-3xl sm:text-4xl font-extrabold leading-tight">
                Refer F&B Businesses.<br>Earn Commission.
            </h1>
            <p class="text-lg text-indigo-200 mt-4 max-w-xl mx-auto">
                Know someone who runs a restaurant, cafe, or catering business? Refer them to Servora and earn commission on every subscription.
            </p>
            <div class="mt-8 flex items-center justify-center gap-4">
                <a href="{{ route('saas.register') }}"
                   class="px-6 py-3 bg-white text-indigo-700 font-bold rounded-lg hover:bg-indigo-50 transition shadow-lg">
                    Sign Up & Get Your Link
                </a>
                <a href="{{ route('login') }}"
                   class="px-6 py-3 border-2 border-white/30 text-white font-medium rounded-lg hover:bg-white/10 transition">
                    Already a Customer? Log In
                </a>
            </div>
        </div>
    </section>

    {{-- How It Works --}}
    <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <h2 class="text-2xl font-bold text-gray-900 text-center mb-10">How It Works</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="text-center">
                <div class="w-16 h-16 bg-indigo-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-gray-800 mb-2">1. Get Your Link</h3>
                <p class="text-sm text-gray-500">Sign up for Servora (or log in), go to Billing, and generate your unique referral link.</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 bg-green-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-gray-800 mb-2">2. Share It</h3>
                <p class="text-sm text-gray-500">Send your link to restaurant owners, chefs, F&B managers — anyone who could benefit from Servora.</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 bg-amber-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-gray-800 mb-2">3. Earn Commission</h3>
                <p class="text-sm text-gray-500">When they subscribe to a paid plan, you earn commission. Track everything from your dashboard.</p>
            </div>
        </div>
    </section>

    {{-- Commission Rates --}}
    @if ($programs->isNotEmpty())
        <section class="bg-gray-50">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                <h2 class="text-2xl font-bold text-gray-900 text-center mb-2">Commission Rates</h2>
                <p class="text-sm text-gray-500 text-center mb-10">Earn on every successful referral.</p>

                <div class="grid grid-cols-1 md:grid-cols-{{ min($programs->count(), 3) }} gap-6 max-w-3xl mx-auto">
                    @foreach ($programs as $program)
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
                            <p class="text-sm text-gray-500 mb-2">{{ $program->plan?->name ?? 'All Plans' }}</p>
                            <p class="text-3xl font-extrabold text-indigo-600">
                                @if ($program->commission_type === 'percentage')
                                    {{ number_format($program->commission_value, 0) }}%
                                @else
                                    RM {{ number_format($program->commission_value, 0) }}
                                @endif
                            </p>
                            <p class="text-xs text-gray-400 mt-1">
                                {{ $program->commission_type === 'percentage' ? 'of payment' : 'per referral' }}
                            </p>
                            <div class="mt-3 flex items-center justify-center gap-2 text-xs">
                                @if ($program->is_recurring)
                                    <span class="px-2 py-0.5 bg-green-100 text-green-700 rounded-full font-medium">Recurring</span>
                                @else
                                    <span class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full font-medium">First payment</span>
                                @endif
                                @if ($program->max_payouts)
                                    <span class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full font-medium">Max {{ $program->max_payouts }}x</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Plan Prices (so referrers know what they're referring) --}}
    <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <h2 class="text-2xl font-bold text-gray-900 text-center mb-2">Servora Plans</h2>
        <p class="text-sm text-gray-500 text-center mb-10">Here's what you'll be referring. Every plan includes a {{ $plans->first()?->trial_days ?? 30 }}-day free trial.</p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach ($plans as $plan)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
                    <h3 class="text-lg font-bold text-gray-900">{{ $plan->name }}</h3>
                    <p class="text-3xl font-extrabold text-gray-900 mt-2">
                        {{ $plan->currency }} {{ number_format($plan->price_monthly, 0) }}
                        <span class="text-sm text-gray-400 font-normal">/mo</span>
                    </p>
                    <p class="text-xs text-gray-400 mt-1">{{ $plan->max_outlets ?? 'Unlimited' }} outlets, {{ $plan->max_users ?? 'Unlimited' }} users</p>
                    @if ($plan->description)
                        <p class="text-xs text-gray-500 mt-3">{{ $plan->description }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    {{-- FAQ --}}
    <section class="bg-gray-50">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <h2 class="text-2xl font-bold text-gray-900 text-center mb-8">Frequently Asked Questions</h2>
            <div class="space-y-4" x-data="{ open: null }">
                @php
                    $faqs = [
                        ['q' => 'Who can join the referral program?', 'a' => 'Any Servora user can generate a referral link from their Billing page. You don\'t need a paid subscription to refer others.'],
                        ['q' => 'How is my commission calculated?', 'a' => 'Commission is calculated as a percentage of the referred company\'s subscription payment, or as a flat fee — depending on the program. The rate may vary by plan.'],
                        ['q' => 'When do I get paid?', 'a' => 'Commissions are tracked automatically when the referred company makes a payment. Payouts are processed manually — you\'ll be contacted for your bank details.'],
                        ['q' => 'Is there a limit to how many people I can refer?', 'a' => 'No limit! Refer as many F&B businesses as you want. Each successful conversion earns you commission.'],
                        ['q' => 'How long does the referral cookie last?', 'a' => '30 days. If someone clicks your link and signs up within 30 days, the referral is tracked to you.'],
                        ['q' => 'Can I track my referrals?', 'a' => 'Yes. Go to Billing in your Servora dashboard to see clicks, signups, conversions, and total earnings in real time.'],
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
    </section>

    {{-- CTA --}}
    <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
        <h2 class="text-2xl font-bold text-gray-900">Ready to Start Earning?</h2>
        <p class="text-sm text-gray-500 mt-2">Sign up, get your referral link, and start sharing today.</p>
        <div class="mt-6 flex items-center justify-center gap-4">
            <a href="{{ route('saas.register') }}"
               class="px-8 py-3 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 transition shadow-lg">
                Get Started Free
            </a>
            <a href="{{ route('login') }}"
               class="px-8 py-3 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition">
                Log In
            </a>
        </div>
    </section>
</div>
