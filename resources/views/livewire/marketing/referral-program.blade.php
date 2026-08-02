@php
    // Static class strings. The previous version built the column count with
    // `md:grid-cols-{{ min($programs->count(), 3) }}`, which Tailwind cannot
    // see when it scans the templates, so that class was never generated and
    // the grid silently stayed single-column.
    $programCols = match (min($programs->count(), 3)) {
        1       => 'sm:grid-cols-1',
        2       => 'sm:grid-cols-2',
        default => 'sm:grid-cols-2 lg:grid-cols-3',
    };
    $trialDays = $plans->first()?->trial_days ?? 30;
@endphp

<div>
    {{-- ── 1. Hero ─────────────────────────────────────────────────────────
         Two CTAs, two distinct intents (join, log in). The line about
         existing customers moved out of the hero and into its own note
         below, so the hero keeps to headline, subtext and buttons.
    --}}
    <section class="bg-gradient-to-b from-brand-50/70 to-white">
        <div class="mx-auto max-w-3xl px-4 pb-14 pt-16 text-center sm:px-6 lg:px-8 lg:pt-24">
            <h1 class="display-1 text-gray-950">Refer a kitchen. Earn on what they pay.</h1>

            <p class="mx-auto mt-5 max-w-prose text-lg leading-relaxed text-gray-600">
                Know someone running a restaurant, cafe or catering business? Send them your link
                and earn commission when they subscribe.
            </p>

            <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('affiliate.register') }}" class="btn-primary btn-lg">
                    Join as affiliate
                </a>
                <a href="{{ route('affiliate.login') }}" class="btn-secondary btn-lg">
                    Affiliate log in
                </a>
            </div>
        </div>
    </section>

    {{-- Existing customers already have this inside the product, so point
         them there rather than making them sign up twice. --}}
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <p class="alert-info">
            <x-icon name="sparkles" size="h-5 w-5" class="flex-none" />
            <span>
                Already a Servora customer? Your referral link is on the
                <a href="{{ route('login') }}" class="font-semibold underline underline-offset-2 hover:text-brand-900">Refer &amp; Earn</a>
                page in your dashboard. No separate affiliate account needed.
            </span>
        </p>
    </div>

    {{-- ── 2. How it works ─────────────────────────────────────────────────
         Connected timeline rather than "1. / 2. / 3." cards.
    --}}
    <section class="py-20 lg:py-24">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-12 lg:grid-cols-12 lg:gap-16">

                <div class="lg:col-span-4">
                    <h2 class="display-2 text-gray-950">Three things to do</h2>
                    <p class="body-base mt-5">
                        There is no approval queue. Generate a link and start sharing.
                    </p>
                </div>

                @php
                    $steps = [
                        ['Get your link',      'Sign up or log in, then generate your unique referral link from Billing.'],
                        ['Share it',           'Send it to owners, chefs and F&B managers. Anywhere you would normally recommend something.'],
                        ['Earn on conversion', 'When they subscribe to a paid plan you earn commission, tracked in your dashboard as it happens.'],
                    ];
                @endphp

                <ol class="relative lg:col-span-8">
                    <span aria-hidden="true" class="absolute bottom-3 left-[15px] top-3 w-px bg-gray-300"></span>
                    @foreach ($steps as $i => [$title, $desc])
                        <li data-reveal-index="{{ $i }}" class="reveal relative flex gap-5 pb-10 last:pb-0">
                            <span class="relative z-sticky mt-0.5 flex h-8 w-8 flex-none items-center justify-center
                                         rounded-full bg-brand-600 text-white shadow-btn">
                                <x-icon name="check" size="h-4 w-4" stroke="2.4" />
                            </span>
                            <div class="pt-1">
                                <h3 class="text-base font-semibold tracking-tight text-gray-950">{{ $title }}</h3>
                                <p class="mt-1.5 max-w-prose text-sm leading-relaxed text-gray-600">{{ $desc }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>

    {{-- ── 3. Commission ───────────────────────────────────────────────── --}}
    @if ($programs->isNotEmpty())
        <section class="border-y border-gray-200 bg-gray-50 py-20">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <h2 class="display-3 text-gray-950">What you earn</h2>
                <p class="body-base mt-3">Rates are set per plan and shown here exactly as they are paid.</p>

                <div class="mt-10 grid gap-6 {{ $programCols }}">
                    @foreach ($programs as $i => $program)
                        <div data-reveal-index="{{ $i }}" class="reveal card-pad">
                            <h3 class="text-sm font-medium text-gray-600">{{ $program->plan?->name ?? 'All plans' }}</h3>

                            <p class="tabular mt-2 text-4xl font-bold tracking-tight text-brand-700">
                                @if ($program->commission_type === 'percentage')
                                    {{ number_format($program->commission_value, 0) }}%
                                @else
                                    RM {{ number_format($program->commission_value, 0) }}
                                @endif
                            </p>
                            <p class="mt-1 text-xs text-gray-600">
                                {{ $program->commission_type === 'percentage' ? 'of each payment' : 'per referral' }}
                            </p>

                            <div class="mt-4 flex flex-wrap items-center gap-2">
                                <span class="{{ $program->is_recurring ? 'badge-success' : 'badge-neutral' }}">
                                    {{ $program->is_recurring ? 'Recurring' : 'First payment only' }}
                                </span>
                                @if ($program->max_payouts)
                                    <span class="badge-neutral">Up to {{ $program->max_payouts }} payouts</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ── 4. What you are referring ───────────────────────────────────── --}}
    @if ($plans->isNotEmpty())
        <section class="py-20">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <h2 class="display-3 text-gray-950">What you are referring</h2>
                <p class="body-base mt-3">
                    Every plan starts with the full product free for {{ $trialDays }} days.
                </p>

                <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($plans as $i => $plan)
                        <div data-reveal-index="{{ $i }}" class="reveal card-pad flex flex-col">
                            <h3 class="text-base font-semibold tracking-tight text-gray-950">{{ $plan->name }}</h3>

                            <p class="mt-2 flex items-baseline gap-1.5">
                                <span class="tabular text-3xl font-bold tracking-tight text-gray-950">
                                    {{ $plan->currency }} {{ number_format($plan->price_monthly, 0) }}
                                </span>
                                <span class="text-sm text-gray-600">/month</span>
                            </p>

                            <p class="mt-2 text-xs text-gray-600">
                                {{ $plan->max_outlets ?? 'Unlimited' }} outlets,
                                {{ $plan->max_users ?? 'Unlimited' }} users
                            </p>

                            @if ($plan->description)
                                <p class="mt-4 border-t border-gray-100 pt-4 text-sm leading-relaxed text-gray-600">
                                    {{ $plan->description }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ── 5. FAQ ──────────────────────────────────────────────────────── --}}
    <section class="border-t border-gray-200 bg-gray-50 py-20">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <h2 class="display-3 text-gray-950">Referral questions</h2>

            @php
                $faqs = [
                    ['Who can join?', 'Any Servora user can generate a referral link from their Billing page. You do not need a paid subscription to refer someone.'],
                    ['How is commission calculated?', 'Either a percentage of the referred company\'s subscription payment or a flat fee, depending on the programme. The rate can differ by plan, and the rates above are the live ones.'],
                    ['When do I get paid?', 'Commission is tracked automatically when the referred company pays. Payouts are processed manually, and we will contact you for bank details.'],
                    ['Is there a limit on referrals?', 'No. Refer as many businesses as you like. Every conversion earns commission.'],
                    ['How long does the referral last?', 'The referral cookie lasts 30 days. If someone clicks your link and signs up within that window, it is tracked to you.'],
                    ['Can I track my referrals?', 'Yes. Billing in your dashboard shows clicks, signups, conversions and total earnings as they happen.'],
                ];
            @endphp

            <div class="stack mt-8 border-t border-gray-200">
                @foreach ($faqs as [$q, $a])
                    <details class="group py-5">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4
                                        text-sm font-semibold text-gray-950 marker:content-none">
                            {{ $q }}
                            <x-icon name="arrow-right" size="h-4 w-4"
                                    class="flex-none text-gray-500 transition-transform duration-200 group-open:rotate-90" />
                        </summary>
                        <p class="mt-3 max-w-prose text-sm leading-relaxed text-gray-600">{{ $a }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── 6. Close ────────────────────────────────────────────────────── --}}
    <section class="bg-gray-950">
        <div class="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 lg:px-8">
            <h2 class="display-2 text-white">Start earning on your network</h2>
            <p class="mx-auto mt-5 max-w-prose text-lg leading-relaxed text-gray-300">
                Sign up, generate your link, and share it.
            </p>
            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('affiliate.register') }}" class="btn-primary btn-lg">Join as affiliate</a>
                <a href="{{ route('affiliate.login') }}" class="btn-on-dark btn-lg">Affiliate log in</a>
            </div>
        </div>
    </section>
</div>
