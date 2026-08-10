@php
    // These are claims about shipped functionality, so treat them as a spec:
    // do not add a bullet here that does not exist in the product. Every line
    // below was checked against a live route or Livewire component; the map of
    // what exists is docs/03-modules.md, which is the place to start when this
    // needs updating again.
    //
    // The previous version described seven areas and stopped at the original
    // costing product. Labels, HR, central kitchen, the supplier portal and
    // most of the AI document capture had shipped since and were going
    // unmentioned, so the page was undersell rather than overreach.
    $groups = [
        [
            'id'    => 'ingredients',
            'icon'  => 'ingredient',
            'title' => 'Ingredients and recipe costing',
            'nav'   => 'Ingredients & costing',
            'desc'  => 'The foundation of food cost control. Every ingredient tracked, every recipe costed, food cost percentage current as prices move.',
            'items' => [
                'Ingredient database with UOM conversions (kg, g, L, ml, pcs and more)',
                'Pack size, yield percentage and wastage factor behind every cost',
                'Recipe builder with cost per serving and food cost percentage',
                'Ingredient and packaging lines costed on the same recipe',
                'Tiered recipe pricing by price class',
                'Cost history per ingredient, per supplier',
                'Ingredient and recipe category trees',
                'Per-outlet recipe tagging for menu customisation',
                'Recipe images for dine-in and takeaway plating, plus training video',
                'CSV and Excel import with AI-assisted ingredient and UOM matching',
            ],
        ],
        [
            'id'    => 'ai',
            'icon'  => 'sparkles',
            'title' => 'AI document capture and analysis',
            'nav'   => 'AI capture',
            'desc'  => 'The typing is the reason costing goes stale. Photograph the invoice and the numbers walk themselves in, with a review step before anything lands.',
            'items' => [
                'Supplier invoices and delivery orders read from a photo or PDF',
                'Extracted lines staged in a review queue, never imported blind',
                'Rows matched to your ingredients, with UOM corrected before import',
                'Ingredient cost and price history updated on approval',
                'Z-report and POS export captured from an image',
                'Three-way matching of invoice against purchase order and GRN',
                'Weekly and monthly written reviews of what moved and why',
                'AI insights over the sales log',
            ],
        ],
        [
            'id'    => 'purchasing',
            'icon'  => 'cart',
            'title' => 'Purchasing, RFQ and receiving',
            'nav'   => 'Purchasing',
            'desc'  => 'Request through to invoice, fully tracked. Nothing lost between the order, the delivery and what you were billed.',
            'items' => [
                'Purchase requests with an approval gate',
                'Purchase orders with par-level auto-ordering and template pre-fill',
                'One order split across several suppliers',
                'RFQ out to suppliers, quotes compared, accepted straight into a PO',
                'Convert a PO to a delivery order, then to a goods received note',
                'Supplier invoices matched against the PO and the GRN',
                'Credit notes against a supplier',
                'Price comparison across suppliers over time',
                'Price alert thresholds per ingredient and supplier',
                'Ingredient costs updated automatically on receipt',
                'PDF documents and supplier email at each step',
                'Approved requests consolidated by supplier for a central purchasing unit',
            ],
        ],
        [
            'id'    => 'inventory',
            'icon'  => 'database',
            'title' => 'Inventory and stock control',
            'nav'   => 'Inventory',
            'desc'  => 'What you hold and where it went. Counts, wastage, transfers and staff meals all land in the same ledger.',
            'items' => [
                'Stock takes as a per-ingredient variance count or a single summary total',
                'Count sheet templates and mobile-friendly entry',
                'Wastage by ingredient or by recipe, costed automatically',
                'Staff meal deductions from inventory',
                'Prep items with method steps and per-outlet availability',
                'Inter-outlet transfers with a send and receive workflow',
                'Chargeable transfers raise an invoice against the receiving outlet',
                'Par levels per ingredient, per outlet',
            ],
        ],
        [
            'id'    => 'kitchen',
            'icon'  => 'clipboard',
            'title' => 'Central kitchen and production',
            'nav'   => 'Central kitchen',
            'desc'  => 'For operations that produce centrally and distribute. Batch production planned, executed and measured against what it should have yielded.',
            'items' => [
                'Batch production orders raised against target outlets',
                'Execute an order: log actual yield and record production waste',
                'Outlets raise prep requests to the central kitchen',
                'Kitchen-only recipe library, separate from the menu',
                'Production history and yield analysis reporting',
                'Separate kitchen sign-in for production staff',
            ],
        ],
        [
            'id'    => 'labels',
            'icon'  => 'printer',
            'title' => 'Food safety labelling',
            'nav'   => 'Labelling',
            'desc'  => 'HACCP date labels printed at the bench, with the shelf life worked out for you and a record of every label that came off the printer.',
            'items' => [
                'Date labels printed to network label printers via PrintNode',
                'Shelf life rules per item, driving use-by dates automatically',
                'Label template designer with a visual layout and field tokens',
                'Label sets for a station or a prep list, printed in one go',
                'Staff app on your own subdomain, installable on a phone',
                'PIN access per person, so the app is not a shared login',
                'Prepared-by captured on every label',
                'Expiring-soon view for the walk-in',
                'Full print log by batch and by label',
                'Printable QR cards so staff reach a set from the bench',
            ],
        ],
        [
            'id'    => 'sales',
            'icon'  => 'currency',
            'title' => 'Sales and revenue',
            'nav'   => 'Sales',
            'desc'  => 'Every ringgit from every outlet, with Z-report capture so the daily numbers do not have to be typed twice.',
            'items' => [
                'Daily sales entry by meal period with pax counts',
                'Revenue split across your own sales categories',
                'Z-report image capture and CSV import from your POS',
                'Monthly and daily revenue targets per outlet',
                'Revenue analytics and average check',
                'Attachments held against a day\'s takings',
            ],
        ],
        [
            'id'    => 'reports',
            'icon'  => 'chart',
            'title' => 'Reports and analytics',
            'nav'   => 'Reports',
            'desc'  => 'Monthly cost summary, stock movement, purchasing behaviour, and a written review of what actually moved and why.',
            'items' => [
                'Cost summary with COGS and month-to-date comparison',
                'Performance, cost analysis, wastage and labour cost views',
                'Weekly and monthly periods throughout',
                'Labour cost with front and back of house split',
                'Ingredient price history and supplier trend',
                'Stock balance by product and by package, plus stock cards',
                'Stock count analysis, adjustments, transfers and inventory variance',
                'Purchase and order summaries, invoice summary, GRN and DO reports',
                'Menu ingredient usage read against sales',
                'CSV and PDF export throughout',
                'Scheduled report subscriptions by email',
            ],
        ],
        [
            'id'    => 'people',
            'icon'  => 'clock',
            'title' => 'People, attendance and claims',
            'nav'   => 'People & HR',
            'desc'  => 'The staff side of the cost line: who worked, who is owed, and the paperwork that comes with it.',
            'items' => [
                'Employee records filtered by outlet, section and status',
                'Attendance recorded against configurable codes',
                'Service charge periods distributed across the team',
                'Duty roster with stations, approvers and email recipients',
                'Overtime claims with approval routing',
                'OT claim PDFs, per employee and as a summary',
                'Employee document folders',
                'Employee export to PDF and Excel',
            ],
        ],
        [
            'id'    => 'training',
            'icon'  => 'academic',
            'title' => 'Training portal',
            'nav'   => 'Training',
            'desc'  => 'Standardise how a dish is made across every outlet, in a portal that carries your branding rather than ours.',
            'items' => [
                'SOP per recipe with step-by-step method',
                'Dine-in and takeaway plating galleries',
                'Training video embedding',
                'Separate staff portal with your company branding',
                'QR code access for printing in the kitchen',
                'SOP PDF export, one recipe or the whole book',
                'Staff registration with manager approval',
            ],
        ],
        [
            'id'    => 'suppliers',
            'icon'  => 'device',
            'title' => 'Supplier portal and marketplace',
            'nav'   => 'Suppliers',
            'desc'  => 'Your suppliers get a login of their own, so quoting and acknowledging orders stops happening over WhatsApp.',
            'items' => [
                'Suppliers sign in separately from your team',
                'They see and acknowledge the purchase orders you send',
                'They respond to RFQs with a quote',
                'Invoice and credit note history on their side',
                'They maintain their own catalogue, profile and bank details',
                'Supplier products mapped to your ingredients',
                'A public marketplace to search supplier products by category and state',
            ],
        ],
        [
            'id'    => 'control',
            'icon'  => 'shield',
            'title' => 'Multi-outlet, roles and control',
            'nav'   => 'Multi-outlet',
            'desc'  => 'One outlet or twenty, on shared data with access scoped to the people who should see it, and a record of who changed what.',
            'items' => [
                'Shared ingredient and recipe data across outlets',
                'Outlet-scoped data with quick switching, plus an all-outlets view',
                'Outlet groups for bulk operations',
                'Role-based permissions, scoped per company',
                'Guided onboarding for outlets, categories, suppliers and users',
                'Company branding, registration details and feature toggles',
                'Departments, sections and tax rates',
                'Audit log with CSV and PDF export',
                'API keys for integration',
            ],
        ],
    ];
@endphp

<div>
    {{-- ── 1. Hero ─────────────────────────────────────────────────────────
         Light, matching the rest of the marketing site. The previous dark
         hero made this page read as a different site to the one the visitor
         arrived on.
    --}}
    <section class="bg-gradient-to-b from-brand-50/70 to-white">
        <div class="mx-auto max-w-3xl px-4 pb-14 pt-16 text-center sm:px-6 lg:px-8 lg:pt-24">
            <h1 class="display-1 text-gray-950">Built for how kitchens actually run</h1>
            <p class="mx-auto mt-5 max-w-prose text-lg leading-relaxed text-gray-600">
                {{ count($groups) }} areas of the operation, on one set of numbers. Here is everything in each.
            </p>
        </div>
    </section>

    {{-- ── 2. Jump nav ─────────────────────────────────────────────────────
         This page is long by nature. An index beats making people scroll to
         find the one area they came to read about.

         It used to scroll horizontally with the scrollbar hidden, so the
         first and last entries were simply cut off with nothing on screen to
         say so. Two changes: the index uses each group's SHORT label, not its
         full section heading — twelve five-word titles fit on no screen made
         — and on desktop it wraps instead of clipping. Narrow screens keep
         the scroll (wrapping there is a wall of pills) with an edge fade to
         show there is more.
    --}}
    <nav class="sticky top-16 z-sticky border-y border-gray-200 bg-white/90 backdrop-blur-md"
         aria-label="Feature areas">
        <div class="relative mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <ul class="hide-scrollbar flex gap-1 overflow-x-auto py-2
                       lg:flex-wrap lg:justify-center lg:overflow-x-visible">
                @foreach ($groups as $g)
                    <li class="flex-none">
                        <a href="#{{ $g['id'] }}"
                           title="{{ $g['title'] }}"
                           class="block whitespace-nowrap rounded-control px-2.5 py-2 text-[13px] font-medium
                                  text-gray-600 transition-colors hover:bg-brand-50 hover:text-brand-800">
                            {{ $g['nav'] ?? $g['title'] }}
                        </a>
                    </li>
                @endforeach
            </ul>

            {{-- Edge fades: only meaningful while the list still scrolls. --}}
            <div class="pointer-events-none absolute inset-y-0 left-0 w-6 bg-gradient-to-r from-white to-transparent lg:hidden"></div>
            <div class="pointer-events-none absolute inset-y-0 right-0 w-6 bg-gradient-to-l from-white to-transparent lg:hidden"></div>
        </div>
    </nav>

    {{-- ── 3. Capabilities ─────────────────────────────────────────────────
         One consistent structure per group, separated by hairlines rather
         than wrapped in identical cards. Bullets run two-up so a long list
         reads as a block instead of a column to scroll.

         scroll-mt clears both the site header and the sticky index above,
         otherwise an anchor jump lands with the heading hidden behind them.
         Sized for the index at TWO rows, which is what it takes between the
         lg breakpoint and the point where all twelve fit on one line; on a
         wide screen it just leaves a little more air above the heading.
    --}}
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        @foreach ($groups as $i => $g)
            <section id="{{ $g['id'] }}"
                     class="scroll-mt-40 border-b border-gray-200 py-14 last:border-b-0 lg:py-16">
                <div class="grid gap-8 lg:grid-cols-12 lg:gap-12">

                    <div class="lg:col-span-4">
                        <span class="flex h-11 w-11 items-center justify-center rounded-control bg-brand-50 text-brand-700">
                            <x-icon :name="$g['icon']" size="h-5 w-5" />
                        </span>
                        <h2 class="display-3 mt-4 text-gray-950">{{ $g['title'] }}</h2>
                        <p class="mt-3 max-w-prose text-sm leading-relaxed text-gray-600">{{ $g['desc'] }}</p>
                    </div>

                    <ul data-reveal-index="{{ $i }}"
                        class="reveal grid gap-x-8 gap-y-3 sm:grid-cols-2 lg:col-span-8 lg:content-start">
                        @foreach ($g['items'] as $item)
                            <li class="flex items-start gap-2.5 text-sm leading-relaxed text-gray-700">
                                <x-icon name="check" size="h-4 w-4" stroke="2.4" class="mt-1 flex-none text-brand-600" />
                                {{ $item }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endforeach
    </div>

    {{-- ── 4. Close ────────────────────────────────────────────────────── --}}
    <section class="mt-4 bg-gray-950">
        <div class="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 lg:px-8">
            <h2 class="display-2 text-white">See it against your own menu</h2>
            <p class="mx-auto mt-5 max-w-prose text-lg leading-relaxed text-gray-300">
                The trial is the full product for {{ $trialDays }} days, with no card required.
            </p>
            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('saas.register') }}" class="btn-primary btn-lg">
                    Start {{ $trialDays }}-day free trial
                </a>
                <a href="{{ route('pricing') }}" class="btn-on-dark btn-lg">
                    View pricing
                </a>
            </div>
        </div>
    </section>
</div>
