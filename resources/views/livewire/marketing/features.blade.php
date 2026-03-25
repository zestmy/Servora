<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="text-center mb-12">
        <h1 class="text-3xl font-bold text-gray-900">Built for F&B Operations</h1>
        <p class="text-sm text-gray-500 mt-2">Every feature designed with real restaurant workflows in mind.</p>
    </div>

    @php
        $sections = [
            [
                'title' => 'Ingredient & Recipe Management',
                'desc' => 'The foundation of food cost control.',
                'items' => [
                    'Ingredient database with UOM conversions (kg, g, L, ml, pcs, etc.)',
                    'Purchase price tracking with supplier links and cost history',
                    'Recipe builder with automatic cost per serving calculations',
                    'Food cost % tracking with alerts for over-cost recipes',
                    'Yield % and wastage factor calculations',
                    'Shared ingredient categories with parent/sub-category hierarchy',
                    'Recipe images (dine-in & takeaway) for plating standards',
                    'Bulk CSV import and export for ingredients',
                ],
            ],
            [
                'title' => 'Purchasing & Receiving',
                'desc' => 'From purchase order to goods received — fully tracked.',
                'items' => [
                    'Purchase Order creation with par level auto-calculation',
                    'Optional PO approval workflow with configurable approvers',
                    'Convert PO to Delivery Order with line item adjustments',
                    'Goods Received Note (GRN) with quantity verification',
                    'PDF generation for PO, DO, and GRN documents',
                    'Email notifications to suppliers and approvers',
                    'Automatic ingredient cost updates on receipt',
                    'Department-based cost tracking for P&L',
                ],
            ],
            [
                'title' => 'Sales & Revenue',
                'desc' => 'Track every ringgit from every outlet.',
                'items' => [
                    'Daily sales entry with category breakdowns',
                    'Z-Report image upload with AI-powered OCR extraction',
                    'CSV import for bulk sales data',
                    'Pax count and meal period tracking',
                    'Sales targets with monthly goal tracking',
                    'Revenue analytics and average check calculations',
                    'Sales closure workflow for daily reconciliation',
                ],
            ],
            [
                'title' => 'Inventory & Stock Control',
                'desc' => 'Know exactly what you have and where it goes.',
                'items' => [
                    'Physical stock takes with mobile-friendly count sheets',
                    'Summary entry method for quick closing stock',
                    'Wastage recording with reason tracking',
                    'Inter-outlet transfers with send/receive workflow',
                    'Staff meal deductions from inventory',
                    'Prep item tracking linked to recipes',
                    'Par level management per outlet per ingredient',
                ],
            ],
            [
                'title' => 'Reports & Analytics',
                'desc' => 'Turn data into actionable insights.',
                'items' => [
                    'Monthly cost summary with COGS breakdown',
                    'P&L by cost category (Opening + Purchases + Transfers - Closing)',
                    'Labour cost tracking with FOH/BOH breakdown',
                    'Weekly comparison and week-of-year navigation',
                    'Ingredient price history and trend analysis',
                    'CSV and PDF export for all reports',
                    'AI-powered analytics with operational recommendations',
                ],
            ],
            [
                'title' => 'Training & LMS',
                'desc' => 'Standardize operations across all outlets.',
                'items' => [
                    'Standard Operating Procedure (SOP) builder per recipe',
                    'Step-by-step cooking instructions with numbered steps',
                    'Training video embedding (YouTube/Vimeo)',
                    'Dine-in and takeaway plating image galleries',
                    'Separate staff portal with company branding',
                    'QR code access for kitchen printing',
                    'PDF export for offline reference',
                    'Staff registration with manager approval workflow',
                ],
            ],
            [
                'title' => 'Multi-Outlet & Team',
                'desc' => 'Scale from one outlet to many.',
                'items' => [
                    'Multi-outlet support with shared ingredient and recipe databases',
                    'Outlet-scoped data with easy switching',
                    'Role-based access: Admin, Manager, Staff, and more',
                    'Per-outlet recipe tagging for menu customization',
                    'Centralized settings with per-outlet overrides',
                    'All-outlets view for operations and business managers',
                ],
            ],
        ];
    @endphp

    <div class="space-y-12">
        @foreach ($sections as $section)
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8">
                <h2 class="text-lg font-bold text-gray-900">{{ $section['title'] }}</h2>
                <p class="text-sm text-gray-500 mt-1 mb-4">{{ $section['desc'] }}</p>
                <ul class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach ($section['items'] as $item)
                        <li class="flex items-start gap-2 text-sm text-gray-600">
                            <svg class="h-4 w-4 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>

    {{-- CTA --}}
    <div class="text-center mt-12">
        <a href="{{ route('saas.register') }}"
           class="inline-block px-8 py-3 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 transition shadow-lg">
            Start Your 14-Day Free Trial
        </a>
        <p class="text-xs text-gray-400 mt-2">No credit card required.</p>
    </div>
</div>
