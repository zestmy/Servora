{{--
    Audience here is SUPPLIERS, not restaurants. Different reader, different
    job: they want distribution and to get paid, not food costing.

    The figures in the numbers band are live counts from the database
    (companies, ingredients, purchase orders). They are honest, but they will
    read thin while the platform is small. If that becomes a problem the
    answer is to drop the band, not to pad the numbers.
--}}
<div>
    {{-- ── 1. Hero ─────────────────────────────────────────────────────── --}}
    <section class="bg-gradient-to-b from-brand-50/70 to-white">
        <div class="mx-auto max-w-3xl px-4 pb-14 pt-16 text-center sm:px-6 lg:px-8 lg:pt-24">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-700">
                For F&amp;B suppliers
            </p>

            <h1 class="display-1 mt-4 text-gray-950">
                Get found by the kitchens already buying
            </h1>

            <p class="mx-auto mt-5 max-w-prose text-lg leading-relaxed text-gray-600">
                List your products once. Receive purchase orders digitally and track what you are
                owed, from one portal.
            </p>

            <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('supplier.register') }}" class="btn-primary btn-lg">
                    Register free
                </a>
                <a href="{{ route('supplier.login') }}" class="btn-secondary btn-lg">
                    Supplier log in
                </a>
            </div>
        </div>
    </section>

    {{-- ── 2. Why join ─────────────────────────────────────────────────────
         Four reasons, left-aligned in a grid. Previously four centred
         columns with four differently-coloured icon tiles; one accent reads
         as one product.
    --}}
    <section class="border-t border-gray-200 py-20 lg:py-24">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <h2 class="display-2 max-w-2xl text-gray-950">Why suppliers list with us</h2>

            @php
                $benefits = [
                    ['cart',     'Get discovered',
                     'Your catalogue is visible to every F&B business on the platform when they search for what you sell. No cold calling.'],
                    ['clipboard','Digital purchase orders',
                     'Orders arrive by email and in your portal, itemised and priced. No phone calls, no faxes, no handwriting to decipher.'],
                    ['currency', 'Know what you are owed',
                     'See invoices raised, what is still outstanding and when it is due, without chasing anyone for a statement.'],
                    ['building', 'Repeat business',
                     'Kitchens reorder from the same suppliers week after week. Being in the system where they order is how you stay in the rotation.'],
                ];
            @endphp

            <div class="mt-12 grid gap-x-10 gap-y-10 sm:grid-cols-2">
                @foreach ($benefits as $i => [$icon, $title, $body])
                    <div data-reveal-index="{{ $i }}" class="reveal flex gap-5">
                        <span class="flex h-11 w-11 flex-none items-center justify-center rounded-control bg-brand-50 text-brand-700">
                            <x-icon :name="$icon" size="h-5 w-5" />
                        </span>
                        <div>
                            <h3 class="text-base font-semibold tracking-tight text-gray-950">{{ $title }}</h3>
                            <p class="mt-2 max-w-prose text-sm leading-relaxed text-gray-600">{{ $body }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── 3. How it works ─────────────────────────────────────────────────
         Connected timeline. Deliberately not "Step 1 / Step 2 / Step 3"
         cards: the action is the label.
    --}}
    <section class="border-y border-gray-200 bg-gray-50 py-20 lg:py-24">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-12 lg:grid-cols-12 lg:gap-16">

                <div class="lg:col-span-4">
                    <h2 class="display-2 text-gray-950">Listing takes an afternoon</h2>
                    <p class="body-base mt-5">
                        No integration work and no fee to be listed.
                    </p>
                    <a href="{{ route('supplier.register') }}" class="btn-primary mt-7">Register free</a>
                </div>

                @php
                    $steps = [
                        ['Register your company', 'Company details, delivery areas and contact. Free, and there is no listing fee.'],
                        ['List your products',    'Add products with pack sizes and prices, or upload the price list you already send out by email.'],
                        ['Receive orders',        'Kitchens order against your catalogue. You get the PO by email and in the portal, then invoice from the same place.'],
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

    {{-- ── 4. Numbers ──────────────────────────────────────────────────────
         Live counts. The trailing "+" was removed: these are exact figures,
         and "+" implies a floor we are not measuring.
    --}}
    <section class="py-16 lg:py-20">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <dl class="grid gap-8 text-center sm:grid-cols-3">
                @php
                    $figures = [
                        [number_format($stats['companies']),   'F&B businesses on the platform'],
                        [number_format($stats['ingredients']), 'Products catalogued'],
                        [number_format($stats['orders']),      'Purchase orders processed'],
                    ];
                @endphp
                @foreach ($figures as [$value, $label])
                    <div>
                        <dt class="sr-only">{{ $label }}</dt>
                        <dd>
                            <span class="tabular block text-4xl font-bold tracking-tight text-brand-700">{{ $value }}</span>
                            <span class="mt-2 block text-sm text-gray-600">{{ $label }}</span>
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- ── 5. FAQ ──────────────────────────────────────────────────────────
         Native <details>: keyboard operable and screen-reader correct with
         no JavaScript, and findable by in-page search.
    --}}
    <section class="border-t border-gray-200 py-20">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <h2 class="display-3 text-gray-950">Supplier questions</h2>

            @php
                $faqs = [
                    ['Is it free to register?', 'Yes. There are no listing fees and no subscription for suppliers. You pay standard payment processing fees only when taking payment through the platform.'],
                    ['Who can see my products?', 'Any F&B business using Servora can find your products when they search for ingredients or suppliers. Your company profile and catalogue are visible to active users.'],
                    ['How do I receive orders?', 'When a business raises a purchase order against your products you get an email notification, and the full order appears in your supplier portal.'],
                    ['What can I list?', 'Food and beverage ingredients, packaging, cleaning supplies, kitchen equipment, and anything else F&B businesses buy. More products listed means more ways to be found.'],
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
            <h2 class="display-2 text-white">Put your price list where the orders are</h2>
            <p class="mx-auto mt-5 max-w-prose text-lg leading-relaxed text-gray-300">
                Registering is free and takes a few minutes.
            </p>
            <a href="{{ route('supplier.register') }}" class="btn-primary btn-lg mt-8">
                Register free
            </a>
        </div>
    </section>
</div>
