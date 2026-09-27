@php
    // These are claims about shipped functionality, so treat them as a spec:
    // do not add a bullet here that does not exist in the product. Every line
    // below was checked against a live route or Livewire component; the map of
    // what exists is docs/03-modules.md, which is the place to start when this
    // needs updating again.
    //
    // `plan` says where an area is sold (docs/pricing-model.md). It is a label,
    // not an entitlement: config/modules.php decides what a company can open.
    //
    // September 2026: Audits and Assets added as areas of their own; POS Sync
    // under Sales; payroll, clock-in and leave under People; courses, quizzes
    // and certificates under Training. The home page's module grid summarises
    // this list in the same order, so move both together.
    $groups = [
        [
            'id'    => 'ingredients',
            'icon'  => 'ingredient',
            'plan'  => 'Free and up',
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
            'plan'  => 'Basic · more with AI Insights',
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
            'plan'  => 'Basic',
            'title' => 'Purchasing and receiving',
            'nav'   => 'Purchasing',
            'desc'  => 'Request through to invoice, fully tracked. Nothing lost between the order, the delivery and what you were billed.',
            'items' => [
                'Purchase requests with an approval gate',
                'Purchase orders with par-level auto-ordering and template pre-fill',
                'One order split across several suppliers',
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
            'plan'  => 'Basic · stock counts on Free',
            'title' => 'Inventory and stock control',
            'nav'   => 'Inventory',
            'desc'  => 'What you hold and where it went. Counts, wastage, transfers and staff meals all land in the same ledger.',
            'items' => [
                'Stock takes as a per-ingredient variance count or a single summary total',
                'Count sheet templates and mobile-friendly entry',
                'Wastage by ingredient or by recipe, costed automatically',
                'Staff meal deductions from inventory',
                'Prep items with method steps and per-outlet availability',
                'Inter-outlet transfers of ingredients, prep items and recipes, with a send and receive workflow',
                'Chargeable transfers raise an invoice against the receiving outlet',
                'Par levels per ingredient, per outlet',
                'Summary and line-by-line exports for wastage, staff meals and transfers, as Excel or PDF',
                'A completed stock take exports to PDF or Excel, or consolidates several counts into one sheet',
            ],
        ],
        [
            'id'    => 'kitchen',
            'icon'  => 'clipboard',
            'plan'  => 'Central Kitchen · per kitchen',
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
            'plan'  => 'Food Safety Labels add-on',
            'title' => 'Food safety labelling',
            'nav'   => 'Labelling',
            'desc'  => 'HACCP date labels printed at the bench, with the shelf life worked out for you and a record of every label that came off the printer.',
            'items' => [
                'Date labels printed through PrintNode or Servora\'s own print agent on the outlet PC',
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
            'plan'  => 'Free and up · POS Sync add-on',
            'title' => 'Sales and revenue',
            'nav'   => 'Sales & POS',
            'desc'  => 'Every ringgit from every outlet, with Z-report capture so the daily numbers do not have to be typed twice, or POS Sync so they are not typed at all.',
            'items' => [
                'Daily sales entry by meal period with pax counts',
                'Revenue split across your own sales categories',
                'Z-report image capture and CSV import from your POS',
                'Monthly and daily revenue targets per outlet',
                'Revenue analytics and average check',
                'Attachments held against a day\'s takings',
                'POS Sync: sales imported from the till by a small agent on the outlet PC',
                'New POS departments mapped once, then remembered for every day after',
            ],
        ],
        [
            'id'    => 'reports',
            'icon'  => 'chart',
            'plan'  => 'Basic',
            'title' => 'Reports and analytics',
            'nav'   => 'Reports',
            'desc'  => 'The weekly management meeting in one screen, and the cost summary, stock movement and purchasing behaviour behind it.',
            'items' => [
                'Weekly WIP review: the week or month just closed against the one before, as a scrolling report, a full-screen slide deck for the meeting, or a PDF',
                'That review carries sales performance and forecast, wastage and transfers by department, overtime by section, and payroll labour cost',
                'Cost summary with COGS and month-to-date comparison',
                'Performance, cost analysis, wastage and labour cost views',
                'Weekly and monthly periods throughout',
                'Labour cost with front and back of house split',
                'Ingredient price history and supplier trend',
                'Stock balance by product and by package, plus stock cards',
                'Stock count analysis, adjustments, transfers and inventory variance',
                'Purchase and order summaries, invoice summary, GRN and DO reports',
                'Menu ingredient usage read against sales',
                'Interactive charts for purchases by supplier, and wastage and stock takes by department',
                'CSV, Excel and PDF export throughout',
                'Scheduled report subscriptions by email',
            ],
        ],
        [
            'id'    => 'people',
            'icon'  => 'users',
            'plan'  => 'HR & Payroll · per employee',
            'title' => 'People, attendance and claims',
            'nav'   => 'HR & payroll',
            'desc'  => 'The staff side of the cost line: who worked, who is owed, and the paperwork that comes with it, through to the payslip.',
            'items' => [
                'Employee records by outlet, section, status and employment type',
                'Duty roster with stations and approvers, or imported from your Excel roster as a PDF',
                'Clock-in at the outlet kiosk, or by phone against the kiosk\'s rotating QR code',
                'Attendance recorded against configurable codes',
                'Leave types and approvers, and overtime claims with approval routing',
                'Custom OT rates, with claim PDFs per employee and as a summary',
                'Meal and attendance allowances worked out from attendance',
                'Service charge pools distributed across the team',
                'Payroll runs with payslips, EA forms and Form E',
                'Labour cost transfers when staff are lent to another outlet, carried into the labour reports',
                'Employee document folders, and export to PDF and Excel',
            ],
        ],
        [
            'id'    => 'training',
            'icon'  => 'academic',
            'plan'  => 'Learn SOP add-on',
            'title' => 'Training portal',
            'nav'   => 'Learn SOP',
            'desc'  => 'Standardise how a dish is made across every outlet, in a portal that carries your branding rather than ours.',
            'items' => [
                'SOP per recipe with step-by-step method',
                'Dine-in and takeaway plating galleries, and training video',
                'Courses, quizzes and learning paths assigned to staff',
                'Live sessions, a leaderboard and certificates with a QR to verify them',
                'The SOP library opened from the staff portal on the staff PIN',
                'Separate portal with your company branding, installable on a phone',
                'QR code access for printing in the kitchen',
                'SOP PDF export, one recipe or the whole book',
                'Staff registration with manager approval',
            ],
        ],
        [
            'id'    => 'audits',
            'icon'  => 'shield',
            'plan'  => 'Audits & Compliance add-on',
            'title' => 'Outlet audits and compliance',
            'nav'   => 'Audits',
            'desc'  => 'Scored checklist audits of an outlet on a phone, where every non-conformance ends in a fix that somebody owns.',
            'items' => [
                'Scored checklist audits from your own forms, with a ROSE form ready to use',
                'Pass, Conditional pass or Fail, not just a percentage',
                'Every non-conformance becomes a corrective action with an owner',
                'Photos of the fix, and separate verification photos from the auditor',
                'Re-audit due dates for conditional passes, on the schedule and the dashboard',
                'Audit schedules per form and outlet, with email reminders when overdue',
                'Outlet staff see their own fixes in the staff portal',
                'Score trend report, and a PDF report with twelve months of history',
            ],
        ],
        [
            'id'    => 'assets',
            'icon'  => 'cube',
            'plan'  => 'Assets add-on',
            'title' => 'Assets and smallwares',
            'nav'   => 'Assets',
            'desc'  => 'Plates, pans and equipment tracked like stock: what each outlet holds, what it cost, and where it went.',
            'items' => [
                'An asset register with a photo on every item',
                'On hand per outlet from the last count, plus receipts, minus disposals',
                'Asset count sheets, printable with a photo on every row',
                'Assets on purchase requests, orders, deliveries and GRNs beside ingredients',
                'Transfers between outlets move assets in the register',
                'Returned or damaged items leave the register when a credit note is issued',
                'Valued at cost, with a tax class per asset',
            ],
        ],
        [
            'id'    => 'suppliers',
            'icon'  => 'device',
            'plan'  => 'Every plan',
            'title' => 'Supplier portal and marketplace',
            'nav'   => 'Suppliers',
            'desc'  => 'Your suppliers get a login of their own, so acknowledging orders stops happening over WhatsApp.',
            'items' => [
                'Suppliers sign in separately from your team',
                'They see and acknowledge the purchase orders you send',
                'Invoice and credit note history on their side',
                'They maintain their own catalogue, profile and bank details',
                'Supplier products mapped to your ingredients',
                'A public marketplace to search supplier products by category and state',
            ],
        ],
        [
            'id'    => 'control',
            'icon'  => 'building',
            'plan'  => 'Every plan',
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

    // The supplier portal is parked (config/modules.php); its section goes
    // with it rather than describing screens nobody can open.
    if (! config('modules.supplier_portal')) {
        $groups = array_values(array_filter($groups, fn ($g) => $g['id'] !== 'suppliers'));
    }

    $itemCount = array_sum(array_map(fn ($g) => count($g['items']), $groups));
@endphp

<div x-data="{ active: '{{ $groups[0]['id'] }}' }">
    {{-- ── 1. Hero ───────────────────────────────────────────────────────── --}}
    <section class="relative overflow-hidden bg-white">
        <div aria-hidden="true" class="mk-grid-bg pointer-events-none absolute inset-0"></div>
        <div aria-hidden="true" class="pointer-events-none absolute left-1/2 top-[-14rem] h-[30rem] w-[56rem] -translate-x-1/2 rounded-full bg-brand-100/50 blur-3xl"></div>

        <div class="relative mx-auto max-w-4xl px-4 pb-16 pt-14 text-center sm:px-6 lg:px-8 lg:pt-20">
            <span class="mk-pill mk-in">
                <x-icon name="sparkles" size="h-4 w-4" />
                Now with Audits, Assets, POS Sync and payroll
            </span>
            <h1 class="display-1 mk-in mt-7 text-gray-950" style="animation-delay:.08s">
                Built for how kitchens<br><span class="mk-accent">actually run</span>.
            </h1>
            <p class="mk-in mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-gray-600" style="animation-delay:.16s">
                {{ count($groups) }} areas of the operation, on one set of numbers. Here is everything in each,
                and which plan it comes with.
            </p>

            <dl class="mk-in mx-auto mt-10 grid max-w-2xl grid-cols-3 divide-x divide-gray-200 rounded-panel border border-gray-200 bg-white/80 py-5 shadow-e2 backdrop-blur"
                style="animation-delay:.24s" x-data="{ on: false }" x-intersect.once="on = true">
                @foreach ([[count($groups), 'areas'], [$itemCount, 'capabilities'], [3, 'staff phone apps']] as [$n, $label])
                    <div class="px-2">
                        <dt class="sr-only">{{ $label }}</dt>
                        <dd>
                            <p class="mk-display text-3xl font-semibold tabular-nums text-gray-950 sm:text-4xl"><span x-effect="on && mkCount($el, {{ $n }})">{{ $n }}</span></p>
                            <p class="mt-1 text-xs text-gray-600 sm:text-sm">{{ $label }}</p>
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- ── 2. Jump nav ─────────────────────────────────────────────────────
         Every area by its SHORT label. Wraps on desktop rather than clipping;
         scrolls with an edge fade on phones. The current section is lit as
         it passes under the header (x-intersect on each section below).
    --}}
    <nav class="sticky top-16 z-sticky border-y border-gray-200 bg-white/90 backdrop-blur-md" aria-label="Feature areas">
        <div class="relative mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <ul class="hide-scrollbar flex gap-1 overflow-x-auto py-2 lg:flex-wrap lg:justify-center lg:overflow-x-visible">
                @foreach ($groups as $g)
                    <li class="flex-none">
                        <a href="#{{ $g['id'] }}" title="{{ $g['title'] }}"
                           class="block whitespace-nowrap rounded-full px-3 py-2 text-[13px] font-medium transition-colors"
                           :class="active === '{{ $g['id'] }}' ? 'bg-brand-600 text-white shadow-btn' : 'text-gray-600 hover:bg-brand-50 hover:text-brand-800'">
                            {{ $g['nav'] ?? $g['title'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="pointer-events-none absolute inset-y-0 left-0 w-6 bg-gradient-to-r from-white to-transparent lg:hidden"></div>
            <div class="pointer-events-none absolute inset-y-0 right-0 w-6 bg-gradient-to-l from-white to-transparent lg:hidden"></div>
        </div>
    </nav>

    {{-- ── 3. Capabilities ─────────────────────────────────────────────────
         scroll-mt clears the header and the sticky index at two rows.
    --}}
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        @foreach ($groups as $i => $g)
            <section id="{{ $g['id'] }}"
                     x-intersect:enter.margin.-45%.0px.-50%.0px="active = '{{ $g['id'] }}'"
                     class="scroll-mt-40 border-b border-gray-200 py-16 last:border-b-0 lg:py-20">
                <div class="grid gap-10 lg:grid-cols-12 lg:gap-14">

                    <div class="lg:col-span-4">
                        <div class="lg:sticky lg:top-44">
                            <div class="flex items-center gap-3">
                                <span class="mk-icon-tile h-12 w-12"><x-icon :name="$g['icon']" size="h-6 w-6" /></span>
                                <span class="mk-mono text-sm font-medium text-gray-400">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                            </div>
                            <h2 class="display-3 mt-5 text-gray-950">{{ $g['title'] }}</h2>
                            <p class="mt-3 max-w-prose text-[15px] leading-relaxed text-gray-600">{{ $g['desc'] }}</p>
                            <p class="mt-5 inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">
                                <x-icon name="tag" size="h-3.5 w-3.5" />
                                {{ $g['plan'] }}
                            </p>
                        </div>
                    </div>

                    <ul class="grid gap-3 sm:grid-cols-2 lg:col-span-8 lg:content-start">
                        @foreach ($g['items'] as $k => $item)
                            <li data-reveal-index="{{ $k % 6 }}"
                                class="reveal group flex items-start gap-3 rounded-surface border border-gray-200 bg-white p-4 text-sm leading-relaxed text-gray-700 shadow-e1 transition-[border-color,box-shadow,transform] duration-300 hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-e2">
                                <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-50 text-brand-700 transition-colors group-hover:bg-brand-600 group-hover:text-white">
                                    <x-icon name="check" size="h-3 w-3" stroke="3" />
                                </span>
                                {{ $item }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endforeach
    </div>

    {{-- ── 4. Close ────────────────────────────────────────────────────── --}}
    <section class="relative mt-4 overflow-hidden bg-navy-950">
        <div aria-hidden="true" class="mk-grid-bg-dark pointer-events-none absolute inset-0"></div>
        <div aria-hidden="true" class="pointer-events-none absolute left-1/2 top-0 h-72 w-[36rem] -translate-x-1/2 rounded-full bg-brand-600/25 blur-3xl"></div>
        <div class="relative mx-auto max-w-3xl px-4 py-24 text-center sm:px-6 lg:px-8">
            <h2 class="display-2 text-white">See it against <span class="mk-accent mk-accent-dark">your own menu</span>.</h2>
            <p class="mx-auto mt-5 max-w-prose text-lg leading-relaxed text-gray-300">
                The trial is the full product for {{ $trialDays }} days, with no card required. After that, Free keeps one outlet costing for good.
            </p>
            <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('saas.register') }}" class="btn-primary btn-lg group">
                    Start {{ $trialDays }}-day free trial
                    <x-icon name="arrow-right" size="h-4 w-4" class="transition-transform group-hover:translate-x-0.5" />
                </a>
                <a href="{{ route('pricing') }}" class="btn-on-dark btn-lg">View pricing</a>
            </div>
        </div>
    </section>
</div>
