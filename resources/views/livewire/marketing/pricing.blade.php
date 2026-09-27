@php
    // docs/pricing-model.md. Suite prices come from the plans table and add-on
    // prices from config/modules.php, so this page cannot drift from what
    // checkout charges.
    $trialDays = $plans->firstWhere('slug', 'full')?->trial_days ?? 14;
    $catalogue = config('modules.catalogue');
    $addonNames = collect($catalogue)->where('kind', 'addon')->pluck('name');

    $bullets = [
        'free'  => [
            '1 outlet, 2 users',
            '150 market list items, 30 recipes',
            'Recipe costing and prep items',
            'Stock counts and daily sales entry',
            'Food-cost reports',
        ],
        'basic' => [
            'Unlimited items, recipes and users',
            'Purchasing: requests, orders, GRN, supplier invoices',
            'Inventory control: transfers, wastage, par levels',
            'Every report, scheduled to your inbox',
            'AI invoice capture — 30 scans per outlet a month',
            'Add up to 2 add-ons',
        ],
        'full'  => array_merge(['Everything in Basic, plus:'], $addonNames->all()),
    ];
@endphp

<div>
    {{-- ── 1. Hero ───────────────────────────────────────────────────────── --}}
    <section class="bg-gradient-to-b from-brand-50/70 to-white">
        <div class="mx-auto max-w-3xl px-4 pb-14 pt-16 text-center sm:px-6 lg:px-8 lg:pt-24">
            <h1 class="display-1 text-gray-950">Free to start. Per outlet as you grow.</h1>
            <p class="mx-auto mt-5 max-w-prose text-lg leading-relaxed text-gray-600">
                Every sign-up gets the whole product for {{ $trialDays }} days, then keeps Free for as long as it likes. No card to start.
            </p>

            {{-- Billing cycle. A real radiogroup so it is operable by keyboard
                 and announced as a choice, not two unrelated buttons. --}}
            <div class="mt-9 inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white p-1 shadow-e1"
                 role="radiogroup" aria-label="Billing cycle">
                @foreach ([['monthly', 'Monthly'], ['yearly', 'Yearly']] as [$value, $label])
                    <button type="button" wire:click="$set('cycle', '{{ $value }}')"
                            role="radio" aria-checked="{{ $cycle === $value ? 'true' : 'false' }}"
                            @class([
                                'rounded-full px-5 py-2 text-sm font-semibold transition-colors',
                                'bg-brand-600 text-white' => $cycle === $value,
                                'text-gray-600 hover:text-gray-900' => $cycle !== $value,
                            ])>
                        {{ $label }}
                        @if ($value === 'yearly')
                            <span @class([
                                'ml-1.5 text-xs font-bold',
                                'text-brand-50' => $cycle === 'yearly',
                                'text-brand-700' => $cycle !== 'yearly',
                            ])>2 months free</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── 2. Plans ────────────────────────────────────────────────────────
         Equal columns on purpose. A pricing table is one of the few places
         where symmetry aids comparison.
    --}}
    <section class="mx-auto max-w-6xl px-4 pb-16 sm:px-6 lg:px-8">
        @if ($plans->isEmpty())
            <div class="empty-state">
                <p class="empty-title">Pricing is being updated</p>
                <p class="empty-body">Plans are not published right now. Start a trial and we will confirm pricing with you directly.</p>
                <a href="{{ route('saas.register') }}" class="btn-primary mt-2">Start free trial</a>
            </div>
        @else
            <div class="grid items-start gap-6 md:grid-cols-3">
                @foreach ($plans as $i => $plan)
                    @php
                        $isFull   = $plan->slug === 'full';
                        $isFree   = (float) $plan->price_monthly === 0.0;
                        // Yearly is ten months' price for twelve.
                        $perMonth = $cycle === 'yearly' ? $plan->price_monthly * 10 / 12 : $plan->price_monthly;
                    @endphp

                    <div data-reveal-index="{{ $i }}"
                         @class([
                             'reveal relative flex flex-col rounded-surface bg-white p-7',
                             'border-2 border-brand-600 shadow-e3' => $isFull,
                             'border border-gray-200 shadow-e1' => ! $isFull,
                         ])>

                        @if ($isFull)
                            <span class="absolute -top-3 left-7 rounded-control bg-brand-600 px-3 py-1
                                         text-xs font-bold uppercase tracking-wide text-white shadow-btn">
                                Everything
                            </span>
                        @endif

                        <h2 class="text-lg font-semibold tracking-tight text-gray-950">{{ $plan->name }}</h2>
                        @if ($plan->description)
                            <p class="mt-2 min-h-[2.75rem] text-sm leading-relaxed text-gray-600">{{ $plan->description }}</p>
                        @endif

                        <p class="mt-5 flex items-baseline gap-1.5">
                            <span class="tabular text-4xl font-bold tracking-tight text-gray-950">
                                RM{{ number_format($perMonth, 0) }}
                            </span>
                            @unless ($isFree)
                                <span class="text-sm text-gray-600">/ outlet / month</span>
                            @endunless
                        </p>
                        <p class="mt-1 min-h-[1.25rem] text-xs text-gray-600">
                            @if ($cycle === 'yearly' && ! $isFree)
                                Billed RM{{ number_format($plan->price_monthly * 10, 0) }} per outlet yearly
                            @elseif ($isFree)
                                Forever
                            @endif
                        </p>

                        <a href="{{ route('saas.register') }}"
                           @class(['mt-6 w-full', 'btn-primary' => $isFull, 'btn-secondary' => ! $isFull])>
                            {{ $isFree ? 'Start free' : "Try it free for {$trialDays} days" }}
                        </a>

                        <div class="mt-7 flex-1 border-t border-gray-200 pt-6">
                            <ul class="space-y-3 text-sm text-gray-700">
                                @foreach ($bullets[$plan->slug] ?? [] as $line)
                                    <li class="flex items-start gap-2.5">
                                        <x-icon name="check" size="h-5 w-5" stroke="2.2" class="mt-px flex-none text-brand-600" />
                                        <span>{{ $line }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endforeach
            </div>

            <p class="mt-6 text-center text-sm text-gray-600">
                More outlets cost less each: 10% off outlets 6–10, 15% off 11–19. Twenty or more? We will quote it.
            </p>
        @endif
    </section>

    {{-- ── 3. Add-ons ──────────────────────────────────────────────────── --}}
    <section class="border-y border-gray-200 bg-gray-50 py-20">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <h2 class="display-3 max-w-2xl text-gray-950">Add only what you run</h2>
            <p class="mt-3 max-w-prose text-gray-600">
                Add-ons are one price per company, however many outlets you have. Full includes all six;
                Basic takes up to two. HR and Central Kitchen go on either suite.
            </p>

            <ul class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($catalogue as $key => $m)
                    @continue(! in_array($m['kind'], ['addon', 'metered'], true))
                    <li class="card p-5">
                        <p class="text-sm font-semibold text-gray-950">{{ $m['name'] }}</p>
                        <p class="mt-2 tabular text-xl font-bold text-gray-950">
                            RM{{ $m['price'] }}
                            <span class="text-xs font-normal text-gray-600">
                                / {{ $m['kind'] === 'metered' ? $m['unit'].' ' : '' }}month
                            </span>
                        </p>
                        @if (($m['min_quantity'] ?? 1) > 1)
                            <p class="mt-1 text-xs text-gray-600">Minimum {{ $m['min_quantity'] }} {{ Str::plural($m['unit']) }}</p>
                        @elseif ($m['kind'] === 'addon')
                            <p class="mt-1 text-xs text-gray-600">Included in Full</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- ── 4. FAQ ──────────────────────────────────────────────────────────
         Native <details>. Works with no JavaScript, is keyboard operable and
         screen-reader correct for free, and browsers now expose it to
         in-page find. The old version was a div with a click handler.
    --}}
    <section class="mx-auto max-w-3xl px-4 py-20 sm:px-6 lg:px-8">
        <h2 class="display-3 text-gray-950">Questions we get asked</h2>

        @php
            $faqs = [
                ['Can I change plans later?', 'Yes. An upgrade starts straight away and you pay only the difference for the rest of your period. A downgrade starts when the period you have paid for ends.'],
                ['What payment methods do you accept?', 'FPX online banking, credit and debit cards, and e-wallets, through our payment partner CHIP-IN.'],
                ['Is my data secure?', 'Data is encrypted, backed up daily, and every company is fully isolated from every other company on the platform.'],
                ['Can I export my data?', 'Yes. Every module exports to CSV, and the reports and inventory screens export to Excel and PDF as well. The data is yours and you can take it out at any time.'],
                ['What happens when my trial ends?', 'You move to the Free plan: nothing is deleted, and recipe costing for one outlet keeps working. Paid modules lock until you upgrade.'],
                ['Do you offer custom plans?', 'For larger groups with specific requirements, get in touch and we will put together a plan that fits.'],
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
    </section>

    {{-- ── 5. Close ────────────────────────────────────────────────────────
         The single dark block, directly above the dark footer.
    --}}
    <section class="bg-gray-950">
        <div class="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 lg:px-8">
            <h2 class="display-2 text-white">Try it on your own numbers</h2>
            <p class="mx-auto mt-5 max-w-prose text-lg leading-relaxed text-gray-300">
                Cost one real recipe and see whether the margin matches what you assumed.
            </p>
            <a href="{{ route('saas.register') }}" class="btn-primary btn-lg mt-8">
                Start free trial
            </a>
        </div>
    </section>
</div>
