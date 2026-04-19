<div>
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-gray-900 via-indigo-950 to-gray-900 text-white">
        <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9ImdyaWQiIHdpZHRoPSI2MCIgaGVpZ2h0PSI2MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTSA2MCAwIEwgMCAwIDAgNjAiIGZpbGw9Im5vbmUiIHN0cm9rZT0icmdiYSgyNTUsMjU1LDI1NSwwLjAzKSIgc3Ryb2tlLXdpZHRoPSIxIi8+PC9wYXR0ZXJuPjwvZGVmcz48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSJ1cmwoI2dyaWQpIi8+PC9zdmc+')] opacity-50"></div>
        <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
            <p class="text-xs font-bold text-indigo-400 uppercase tracking-widest mb-4">Features</p>
            <h1 class="text-4xl sm:text-5xl font-extrabold leading-tight">Built for F&B Operations</h1>
            <p class="text-lg text-gray-400 mt-4 max-w-2xl mx-auto">Every feature designed with real restaurant, cafe, and catering workflows in mind.</p>
        </div>
    </section>

    @php
        $sections = [
            [
                'title' => 'Ingredient & Recipe Management',
                'desc' => 'The foundation of food cost control. Track every ingredient, build recipes with automatic costing, and know your food cost % in real time.',
                'icon' => '<svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>',
                'color' => 'orange',
                'items' => ['Ingredient database with UOM conversions (kg, g, L, ml, pcs, etc.)', 'Purchase price tracking with supplier links and cost history', 'Recipe builder with automatic cost per serving calculations', 'Food cost % tracking with alerts for over-cost recipes', 'Yield % and wastage factor calculations', 'Shared ingredient categories with parent/sub-category hierarchy', 'Recipe images (dine-in & takeaway) for plating standards', 'Bulk CSV import and export for ingredients'],
            ],
            [
                'title' => 'Purchasing & Receiving',
                'desc' => 'From purchase order to goods received — fully tracked. Never lose a PO or miss a delivery again.',
                'icon' => '<svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/></svg>',
                'color' => 'green',
                'items' => ['Purchase Order creation with par level auto-calculation', 'Optional PO approval workflow with configurable approvers', 'Convert PO to Delivery Order with line item adjustments', 'Goods Received Note (GRN) with quantity verification', 'PDF generation for PO, DO, and GRN documents', 'Email notifications to suppliers and approvers', 'Automatic ingredient cost updates on receipt', 'Department-based cost tracking for P&L'],
            ],
            [
                'title' => 'Sales & Revenue',
                'desc' => 'Track every ringgit from every outlet. Import Z-reports, set targets, and monitor performance.',
                'icon' => '<svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                'color' => 'emerald',
                'items' => ['Daily sales entry with category breakdowns', 'Z-Report image upload with AI-powered OCR extraction', 'CSV import for bulk sales data', 'Pax count and meal period tracking', 'Sales targets with monthly goal tracking', 'Revenue analytics and average check calculations', 'Sales closure workflow for daily reconciliation'],
            ],
            [
                'title' => 'Inventory & Stock Control',
                'desc' => 'Know exactly what you have and where it goes. Physical counts, wastage, transfers — all tracked.',
                'icon' => '<svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/></svg>',
                'color' => 'violet',
                'items' => ['Physical stock takes with mobile-friendly count sheets', 'Summary entry method for quick closing stock', 'Wastage recording with reason tracking', 'Inter-outlet transfers with send/receive workflow', 'Staff meal deductions from inventory', 'Prep item tracking linked to recipes', 'Par level management per outlet per ingredient'],
            ],
            [
                'title' => 'Reports & Analytics',
                'desc' => 'Turn data into actionable insights. Monthly P&L, cost breakdowns, and AI-powered recommendations.',
                'icon' => '<svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>',
                'color' => 'rose',
                'items' => ['Monthly cost summary with COGS breakdown', 'P&L by cost category (Opening + Purchases + Transfers - Closing)', 'Labour cost tracking with FOH/BOH breakdown', 'Weekly comparison and week-of-year navigation', 'Ingredient price history and trend analysis', 'CSV and PDF export for all reports', 'AI-powered analytics with operational recommendations'],
            ],
            [
                'title' => 'Training & LMS',
                'desc' => 'Standardize operations across all outlets with a branded staff training portal.',
                'icon' => '<svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/></svg>',
                'color' => 'cyan',
                'items' => ['Standard Operating Procedure (SOP) builder per recipe', 'Step-by-step preparation instructions with numbered steps', 'Training video embedding (YouTube/Vimeo)', 'Dine-in and takeaway plating image galleries', 'Separate staff portal with company branding', 'QR code access for kitchen printing', 'PDF export for offline reference', 'Staff registration with manager approval workflow'],
            ],
            [
                'title' => 'Multi-Outlet & Team',
                'desc' => 'Scale from one outlet to many. Shared data, role-based access, and easy switching.',
                'icon' => '<svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/></svg>',
                'color' => 'amber',
                'items' => ['Multi-outlet support with shared ingredient and recipe databases', 'Outlet-scoped data with easy switching', 'Role-based access: Admin, Manager, Staff, and more', 'Per-outlet recipe tagging for menu customization', 'Centralized settings with per-outlet overrides', 'All-outlets view for operations and business managers'],
            ],
            [
                'title' => 'Central Kitchen & Production',
                'desc' => 'Produce in bulk, distribute to outlets. Full production workflow with yield tracking and prep requests.',
                'icon' => '<svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
                'color' => 'lime',
                'items' => ['Central Kitchen management with dedicated production recipes', 'Production orders with planned vs. actual yield tracking', 'Outlet prep requests routed to the kitchen with deadlines', 'Kitchen inventory on-hand tracking', 'Production logs for each stage (prep, cook, plate, QC)', 'Bulk production feeding multiple outlets from one batch', 'Separate kitchen workspace with its own sidebar and dashboard'],
            ],
            [
                'title' => 'Central Purchasing & RFQ',
                'desc' => 'Consolidate purchases across outlets, get quotes from suppliers, and split mixed orders automatically.',
                'icon' => '<svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25"/></svg>',
                'color' => 'teal',
                'items' => ['Central Purchasing Unit (CPU) mode — consolidate outlet PRs into one PO', 'Purchase Request workflow with optional approval gate', 'Request-for-Quotation (RFQ) sent to multiple suppliers at once', 'Side-by-side supplier quote comparison and one-click accept → PO', 'Multi-supplier PO split: one order gets split into per-supplier POs automatically', 'Supplier Item Aliases so AI learns each supplier\'s naming convention', 'Three-way invoice matching (PO → GRN → Invoice) with variance detection', 'Credit notes for damaged / short / rejected goods, auto-applied to invoices'],
            ],
            [
                'title' => 'AI-Powered Automation',
                'desc' => 'Let AI handle the busywork — scan invoices, watch prices, and surface cost-saving insights.',
                'icon' => '<svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"/></svg>',
                'color' => 'purple',
                'items' => ['Price Watcher — snap a photo of a supplier doc, AI extracts items & prices', 'AI invoice scanning with confidence scoring and one-click import', 'Z-report OCR: upload a POS closing receipt and auto-create a sales record', 'Recipe smart-import with AI-assisted ingredient and UOM matching', 'Automatic price change alerts with configurable % thresholds', 'AI analytics: monthly review, cost optimisation, and trend recommendations', 'Cached analysis results so you only pay for fresh insights'],
            ],
            [
                'title' => 'HR, Labour & Overtime',
                'desc' => 'Payroll data and overtime claims live alongside your operations — so labour cost lands straight on the P&L.',
                'icon' => '<svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>',
                'color' => 'sky',
                'items' => ['Employee master with outlet, section, and designation', 'Overtime claim submission with auto-calculated hours and rate', 'Multi-level OT approver matrix by outlet and section', 'Cross-outlet approval dashboards (HR processes every outlet without switching)', 'Monthly labour cost entry with allowances and deductions', 'Labour cost rolled into the monthly P&L report', 'Overtime claim PDFs for payroll handoff'],
            ],
            [
                'title' => 'Supplier Portal',
                'desc' => 'A dedicated login for your suppliers — they manage their own catalogue, receive POs, and respond to RFQs.',
                'icon' => '<svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z"/></svg>',
                'color' => 'indigo',
                'items' => ['Suppliers log in to their own portal — no more email-and-spreadsheet chaos', 'Supplier catalogue with SKU, pack size, and preferred items', 'PO acknowledgement and delivery status updates from the supplier side', 'RFQ responses submitted directly in the portal', 'Invoice and credit note history visible to both sides', 'Supplier-managed profile and bank details for payment', 'WhatsApp or email notification preference per supplier'],
            ],
        ];

        $colorMap = [
            'orange' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-600', 'light' => 'bg-orange-50', 'border' => 'border-orange-200', 'check' => 'text-orange-500'],
            'green' => ['bg' => 'bg-green-100', 'text' => 'text-green-600', 'light' => 'bg-green-50', 'border' => 'border-green-200', 'check' => 'text-green-500'],
            'emerald' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-600', 'light' => 'bg-emerald-50', 'border' => 'border-emerald-200', 'check' => 'text-emerald-500'],
            'violet' => ['bg' => 'bg-violet-100', 'text' => 'text-violet-600', 'light' => 'bg-violet-50', 'border' => 'border-violet-200', 'check' => 'text-violet-500'],
            'rose' => ['bg' => 'bg-rose-100', 'text' => 'text-rose-600', 'light' => 'bg-rose-50', 'border' => 'border-rose-200', 'check' => 'text-rose-500'],
            'cyan' => ['bg' => 'bg-cyan-100', 'text' => 'text-cyan-600', 'light' => 'bg-cyan-50', 'border' => 'border-cyan-200', 'check' => 'text-cyan-500'],
            'amber' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-600', 'light' => 'bg-amber-50', 'border' => 'border-amber-200', 'check' => 'text-amber-500'],
            'lime' => ['bg' => 'bg-lime-100', 'text' => 'text-lime-600', 'light' => 'bg-lime-50', 'border' => 'border-lime-200', 'check' => 'text-lime-500'],
            'teal' => ['bg' => 'bg-teal-100', 'text' => 'text-teal-600', 'light' => 'bg-teal-50', 'border' => 'border-teal-200', 'check' => 'text-teal-500'],
            'purple' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-600', 'light' => 'bg-purple-50', 'border' => 'border-purple-200', 'check' => 'text-purple-500'],
            'sky' => ['bg' => 'bg-sky-100', 'text' => 'text-sky-600', 'light' => 'bg-sky-50', 'border' => 'border-sky-200', 'check' => 'text-sky-500'],
            'indigo' => ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-600', 'light' => 'bg-indigo-50', 'border' => 'border-indigo-200', 'check' => 'text-indigo-500'],
        ];
    @endphp

    <section class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="space-y-8">
            @foreach ($sections as $i => $section)
                @php $c = $colorMap[$section['color']]; @endphp
                <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden hover:shadow-lg transition-shadow">
                    {{-- Header --}}
                    <div class="flex items-center gap-4 p-6 sm:p-8 pb-4">
                        <div class="w-14 h-14 {{ $c['bg'] }} rounded-2xl flex items-center justify-center {{ $c['text'] }} flex-shrink-0">
                            {!! $section['icon'] !!}
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">{{ $section['title'] }}</h2>
                            <p class="text-sm text-gray-500 mt-0.5">{{ $section['desc'] }}</p>
                        </div>
                    </div>
                    {{-- Items --}}
                    <div class="px-6 sm:px-8 pb-6 sm:pb-8">
                        <ul class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2.5">
                            @foreach ($section['items'] as $item)
                                <li class="flex items-start gap-2.5 text-sm text-gray-600">
                                    <svg class="h-5 w-5 {{ $c['check'] }} flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ $item }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- CTA --}}
    <section class="bg-gradient-to-br from-indigo-600 to-purple-700">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
            <h2 class="text-3xl font-extrabold text-white">Ready to Transform Your Operations?</h2>
            <p class="text-lg text-indigo-200 mt-3">Start your {{ $trialDays }}-day free trial today. No credit card required.</p>
            <a href="{{ route('saas.register') }}"
               class="inline-block mt-8 px-8 py-4 bg-white text-indigo-700 font-bold rounded-xl hover:bg-indigo-50 transition shadow-lg text-sm">
                Start Your Free Trial
            </a>
        </div>
    </section>
</div>
