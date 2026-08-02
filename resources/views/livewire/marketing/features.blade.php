@php
    // Content carried over from the previous version. These are claims about
    // shipped functionality, so treat them as a spec: do not add a bullet
    // here that does not exist in the product.
    $groups = [
        [
            'id'    => 'ingredients',
            'icon'  => 'ingredient',
            'title' => 'Ingredients and recipes',
            'desc'  => 'The foundation of food cost control. Every ingredient tracked, every recipe costed, food cost percentage current as prices move.',
            'items' => [
                'Ingredient database with UOM conversions (kg, g, L, ml, pcs and more)',
                'Purchase price tracking with supplier links and cost history',
                'Recipe builder with automatic cost per serving',
                'Food cost percentage tracking, with alerts for over-cost recipes',
                'Yield percentage and wastage factor calculations',
                'Ingredient categories with parent and sub-category hierarchy',
                'Recipe images for dine-in and takeaway plating standards',
                'Bulk CSV import and export',
            ],
        ],
        [
            'id'    => 'purchasing',
            'icon'  => 'cart',
            'title' => 'Purchasing and receiving',
            'desc'  => 'Purchase order through to goods received, fully tracked. Nothing lost between the order and the delivery.',
            'items' => [
                'Purchase order creation with par level auto-calculation',
                'Optional approval workflow with configurable approvers',
                'Convert a PO to a delivery order with line item adjustments',
                'Goods received note with quantity verification',
                'PDF generation for PO, DO and GRN',
                'Email notifications to suppliers and approvers',
                'Automatic ingredient cost updates on receipt',
                'Department-based cost tracking for P&L',
            ],
        ],
        [
            'id'    => 'sales',
            'icon'  => 'currency',
            'title' => 'Sales and revenue',
            'desc'  => 'Every ringgit from every outlet, with Z-report capture so the daily numbers do not have to be typed twice.',
            'items' => [
                'Daily sales entry with category breakdowns',
                'Z-report image upload with AI extraction',
                'CSV import for bulk sales data',
                'Pax count and meal period tracking',
                'Sales targets with monthly goal tracking',
                'Revenue analytics and average check',
                'Sales closure workflow for daily reconciliation',
            ],
        ],
        [
            'id'    => 'inventory',
            'icon'  => 'database',
            'title' => 'Inventory and stock control',
            'desc'  => 'What you hold and where it went. Counts, wastage, transfers and staff meals all land in the same ledger.',
            'items' => [
                'Physical stock takes with mobile-friendly count sheets',
                'Summary entry method for a quick closing stock',
                'Wastage recording with reason tracking',
                'Inter-outlet transfers with send and receive workflow',
                'Staff meal deductions from inventory',
                'Prep item tracking linked to recipes',
                'Par level management per outlet, per ingredient',
            ],
        ],
        [
            'id'    => 'reports',
            'icon'  => 'chart',
            'title' => 'Reports and analytics',
            'desc'  => 'Monthly P&L, cost breakdowns, and a written review of what actually moved and why.',
            'items' => [
                'Monthly cost summary with COGS breakdown',
                'P&L by cost category (opening + purchases + transfers - closing)',
                'Labour cost tracking with front and back of house split',
                'Weekly comparison and week-of-year navigation',
                'Ingredient price history and trend analysis',
                'CSV and PDF export on every report',
                'AI analytics with operational recommendations',
            ],
        ],
        [
            'id'    => 'training',
            'icon'  => 'academic',
            'title' => 'Training portal',
            'desc'  => 'Standardise how a dish is made across every outlet, in a portal that carries your branding rather than ours.',
            'items' => [
                'SOP builder per recipe',
                'Step-by-step preparation instructions',
                'Training video embedding (YouTube and Vimeo)',
                'Dine-in and takeaway plating image galleries',
                'Separate staff portal with company branding',
                'QR code access for printing in the kitchen',
                'PDF export for offline reference',
                'Staff registration with manager approval',
            ],
        ],
        [
            'id'    => 'multi-outlet',
            'icon'  => 'building',
            'title' => 'Multi-outlet and team',
            'desc'  => 'One outlet or twenty, on shared data with access scoped to the people who should see it.',
            'items' => [
                'Shared ingredient and recipe databases across outlets',
                'Outlet-scoped data with quick switching',
                'Role-based access for admin, manager, staff and more',
                'Per-outlet recipe tagging for menu customisation',
                'Centralised settings with per-outlet overrides',
                'All-outlets view for operations and business managers',
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
                Seven areas of the operation, on one set of numbers. Here is everything in each.
            </p>
        </div>
    </section>

    {{-- ── 2. Jump nav ─────────────────────────────────────────────────────
         This page is long by nature. A horizontal index beats making people
         scroll to find the one area they came to read about.
    --}}
    <nav class="sticky top-16 z-sticky border-y border-gray-200 bg-white/90 backdrop-blur-md"
         aria-label="Feature areas">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <ul class="hide-scrollbar flex gap-1 overflow-x-auto py-2">
                @foreach ($groups as $g)
                    <li class="flex-none">
                        <a href="#{{ $g['id'] }}"
                           class="block whitespace-nowrap rounded-control px-3 py-2 text-[13px] font-medium
                                  text-gray-600 transition-colors hover:bg-brand-50 hover:text-brand-800">
                            {{ $g['title'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </nav>

    {{-- ── 3. Capabilities ─────────────────────────────────────────────────
         One consistent structure per group, separated by hairlines rather
         than wrapped in seven identical cards. Bullets run two-up so a
         seven-item list reads as a block instead of a column to scroll.

         scroll-mt clears both the site header and the sticky index above,
         otherwise an anchor jump lands with the heading hidden behind them.
    --}}
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        @foreach ($groups as $i => $g)
            <section id="{{ $g['id'] }}"
                     class="scroll-mt-32 border-b border-gray-200 py-14 last:border-b-0 lg:py-16">
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
