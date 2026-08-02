@php
    // Computed from the real plans rather than a hardcoded "save up to 17%",
    // so the badge cannot drift out of date when prices change.
    $maxSaving = $plans
        ->filter(fn ($p) => $p->price_monthly > 0 && $p->price_yearly > 0)
        ->map(fn ($p) => (int) round((1 - ($p->price_yearly / 12) / $p->price_monthly) * 100))
        ->filter(fn ($pct) => $pct > 0)
        ->max();

    $trialDays = $plans->first()?->trial_days ?? 30;
@endphp

<div>
    {{-- ── 1. Hero ─────────────────────────────────────────────────────────
         Centred is the right call here: the page IS the price list, so there
         is no competing asset to sit beside. Compact, because the plans are
         what the visitor came for.
    --}}
    <section class="bg-gradient-to-b from-brand-50/70 to-white">
        <div class="mx-auto max-w-3xl px-4 pb-14 pt-16 text-center sm:px-6 lg:px-8 lg:pt-24">
            <h1 class="display-1 text-gray-950">Simple, transparent pricing</h1>
            <p class="mx-auto mt-5 max-w-prose text-lg leading-relaxed text-gray-600">
                Every plan is the full product for {{ $trialDays }} days. No card to start.
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
                        @if ($value === 'yearly' && $maxSaving)
                            <span @class([
                                'ml-1.5 text-xs font-bold',
                                'text-brand-50' => $cycle === 'yearly',
                                'text-brand-700' => $cycle !== 'yearly',
                            ])>save {{ $maxSaving }}%</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── 2. Plans ────────────────────────────────────────────────────────
         Equal columns on purpose. A pricing table is one of the few places
         where symmetry aids comparison, and breaking the grid to look
         designed would make the plans harder to read against each other.
    --}}
    <section class="mx-auto max-w-6xl px-4 pb-20 sm:px-6 lg:px-8">
        @if ($plans->isEmpty())
            <div class="empty-state">
                <p class="empty-title">Pricing is being updated</p>
                <p class="empty-body">Plans are not published right now. Start a trial and we will confirm pricing with you directly.</p>
                <a href="{{ route('saas.register') }}" class="btn-primary mt-2">Start free trial</a>
            </div>
        @else
            <div class="grid items-start gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($plans as $i => $plan)
                    @php
                        $isPopular = $plan->slug === 'professional';
                        $total     = $cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly;
                        $perMonth  = $cycle === 'yearly' ? $plan->price_yearly / 12 : $plan->price_monthly;

                        $limits = [
                            [$plan->max_outlets,   Str::plural('outlet', $plan->max_outlets ?? 2)],
                            [$plan->max_users,     'users'],
                            [$plan->max_recipes,   'recipes'],
                            [$plan->max_lms_users, 'training accounts'],
                        ];
                    @endphp

                    <div data-reveal-index="{{ $i }}"
                         @class([
                             'reveal relative flex flex-col rounded-surface bg-white p-7',
                             'border-2 border-brand-600 shadow-e3' => $isPopular,
                             'border border-gray-200 shadow-e1' => ! $isPopular,
                         ])>

                        @if ($isPopular)
                            <span class="absolute -top-3 left-7 rounded-control bg-brand-600 px-3 py-1
                                         text-xs font-bold uppercase tracking-wide text-white shadow-btn">
                                Most popular
                            </span>
                        @endif

                        <h2 class="text-lg font-semibold tracking-tight text-gray-950">{{ $plan->name }}</h2>
                        @if ($plan->description)
                            <p class="mt-2 min-h-[2.75rem] text-sm leading-relaxed text-gray-600">{{ $plan->description }}</p>
                        @endif

                        <p class="mt-5 flex items-baseline gap-1.5">
                            <span class="tabular text-4xl font-bold tracking-tight text-gray-950">
                                {{ $plan->currency }} {{ number_format($perMonth, 0) }}
                            </span>
                            <span class="text-sm text-gray-600">/month</span>
                        </p>
                        {{-- Reserved height so the cards stay aligned when the
                             yearly line appears and disappears. --}}
                        <p class="mt-1 min-h-[1.25rem] text-xs text-gray-600">
                            @if ($cycle === 'yearly')
                                Billed {{ $plan->currency }} {{ number_format($total, 0) }} yearly
                            @endif
                        </p>

                        <a href="{{ route('saas.register', ['plan' => $plan->slug]) }}"
                           @class(['mt-6 w-full', 'btn-primary' => $isPopular, 'btn-secondary' => ! $isPopular])>
                            Start free trial
                        </a>

                        <div class="mt-7 flex-1 border-t border-gray-200 pt-6">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-600">What you get</h3>

                            <ul class="mt-4 space-y-3 text-sm text-gray-700">
                                @foreach ($limits as [$value, $noun])
                                    <li class="flex items-start gap-2.5">
                                        <x-icon name="check" size="h-5 w-5" stroke="2.2" class="mt-px flex-none text-brand-600" />
                                        <span><strong class="font-semibold text-gray-950">{{ $value ?? 'Unlimited' }}</strong> {{ $noun }}</span>
                                    </li>
                                @endforeach

                                {{-- Only what the plan actually includes. Greyed-out
                                     rows for absent features were unreadable
                                     (text-gray-300 is roughly 1.6:1) and added
                                     nothing but noise. --}}
                                @foreach ($plan->feature_flags ?? [] as $flag => $enabled)
                                    @if ($enabled)
                                        <li class="flex items-start gap-2.5">
                                            <x-icon name="check" size="h-5 w-5" stroke="2.2" class="mt-px flex-none text-brand-600" />
                                            <span>{{ Str::headline($flag) }}</span>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ── 3. On every plan ────────────────────────────────────────────────
         Was an emoji grid. Emoji render differently on every OS and read as
         placeholder art on a paid product page.
    --}}
    <section class="border-y border-gray-200 bg-gray-50 py-20">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <h2 class="display-3 max-w-2xl text-gray-950">On every plan, including the trial</h2>

            @php
                $core = [
                    ['ingredient', 'Ingredients'],  ['clipboard', 'Recipes'],
                    ['cart',       'Purchasing'],   ['currency',  'Sales'],
                    ['database',   'Inventory'],    ['chart',     'Reports'],
                    ['printer',    'PDF documents'],['shield',    'Daily backups'],
                ];
            @endphp

            <ul class="mt-10 grid grid-cols-2 gap-x-8 gap-y-6 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($core as [$icon, $label])
                    <li class="flex items-center gap-3">
                        <x-icon :name="$icon" size="h-5 w-5" class="flex-none text-brand-600" />
                        <span class="text-sm font-medium text-gray-800">{{ $label }}</span>
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
                ['Can I change plans later?', 'Yes. Upgrade or downgrade at any time. Changes take effect immediately and billing is prorated.'],
                ['What payment methods do you accept?', 'FPX online banking, credit and debit cards, and e-wallets, through our payment partner CHIP-IN.'],
                ['Is my data secure?', 'Data is encrypted, backed up daily, and every company is fully isolated from every other company on the platform.'],
                ['Can I export my data?', 'Yes. Every module supports CSV export. The data is yours and you can take it out at any time.'],
                ['What happens when my trial ends?', 'You keep read access to everything. Creating and editing is paused until you subscribe, and your data is preserved for 30 days.'],
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
