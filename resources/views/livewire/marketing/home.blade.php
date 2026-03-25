<div>
    {{-- Hero --}}
    <section class="bg-gradient-to-br from-indigo-600 via-indigo-700 to-purple-800 text-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
            <h1 class="text-4xl sm:text-5xl font-extrabold leading-tight">
                The Complete F&B<br>Management Platform
            </h1>
            <p class="text-lg text-indigo-200 mt-4 max-w-2xl mx-auto">
                Ingredients, recipes, purchasing, sales, inventory, and reports — all in one place.
                Built for Malaysian F&B businesses.
            </p>
            <div class="mt-8 flex items-center justify-center gap-4">
                <a href="{{ route('saas.register') }}"
                   class="px-6 py-3 bg-white text-indigo-700 font-bold rounded-lg hover:bg-indigo-50 transition shadow-lg">
                    Start Free Trial
                </a>
                <a href="{{ route('features') }}"
                   class="px-6 py-3 border-2 border-white/30 text-white font-medium rounded-lg hover:bg-white/10 transition">
                    See Features
                </a>
            </div>
            <p class="text-xs text-indigo-300 mt-3">14-day free trial. No credit card required.</p>
        </div>
    </section>

    {{-- Features Grid --}}
    <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="text-center mb-12">
            <h2 class="text-2xl font-bold text-gray-900">Everything You Need to Run Your F&B Business</h2>
            <p class="text-sm text-gray-500 mt-2">From ingredient costing to P&L reports — we've got you covered.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @php
                $features = [
                    ['icon' => '🥕', 'title' => 'Ingredient Management', 'desc' => 'Track ingredients with UOM conversions, cost history, supplier links, and automated cost calculations.'],
                    ['icon' => '📋', 'title' => 'Recipe Costing', 'desc' => 'Build recipes with real-time cost calculations, yield tracking, food cost %, and multi-outlet tagging.'],
                    ['icon' => '🛒', 'title' => 'Purchasing & GRN', 'desc' => 'Full PO to DO to GRN workflow with approval chains, PDF documents, and automatic cost updates.'],
                    ['icon' => '💰', 'title' => 'Sales Tracking', 'desc' => 'Daily sales entry with Z-report OCR, meal period tracking, CSV import, and revenue analytics.'],
                    ['icon' => '📦', 'title' => 'Inventory Control', 'desc' => 'Stock takes, wastage tracking, inter-outlet transfers, staff meals, and par level management.'],
                    ['icon' => '📊', 'title' => 'Reports & P&L', 'desc' => 'Monthly cost summaries, COGS calculation, labour cost tracking, and CSV/PDF exports.'],
                    ['icon' => '📖', 'title' => 'Staff Training (LMS)', 'desc' => 'SOPs with step-by-step instructions, training videos, plating images, and QR code access.'],
                    ['icon' => '🤖', 'title' => 'AI Analytics', 'desc' => 'AI-powered operational insights, trend analysis, and cost optimization recommendations.'],
                    ['icon' => '🏢', 'title' => 'Multi-Outlet', 'desc' => 'Manage multiple outlets with shared ingredients and recipes, outlet-scoped data, and easy switching.'],
                ];
            @endphp

            @foreach ($features as $feature)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition">
                    <span class="text-2xl">{{ $feature['icon'] }}</span>
                    <h3 class="text-sm font-bold text-gray-800 mt-3">{{ $feature['title'] }}</h3>
                    <p class="text-xs text-gray-500 mt-1.5 leading-relaxed">{{ $feature['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Social Proof --}}
    <section class="bg-gray-100">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
            <h2 class="text-2xl font-bold text-gray-900 mb-8">Trusted by F&B Operators Across Malaysia</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <p class="text-sm text-gray-600 italic">"Servora helped us cut food costs by 12% in 3 months. The recipe costing alone is worth it."</p>
                    <p class="text-xs font-semibold text-gray-800 mt-3">— Restaurant Owner, KL</p>
                </div>
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <p class="text-sm text-gray-600 italic">"Finally, a system that understands F&B operations. The PO to GRN flow saved us hours every week."</p>
                    <p class="text-xs font-semibold text-gray-800 mt-3">— Operations Manager, Penang</p>
                </div>
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <p class="text-sm text-gray-600 italic">"The LMS module transformed our staff training. New hires get up to speed in half the time."</p>
                    <p class="text-xs font-semibold text-gray-800 mt-3">— F&B Group Director, JB</p>
                </div>
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
        <h2 class="text-2xl font-bold text-gray-900">Ready to Take Control of Your F&B Operations?</h2>
        <p class="text-sm text-gray-500 mt-2">Start your 14-day free trial today. No credit card needed.</p>
        <a href="{{ route('saas.register') }}"
           class="inline-block mt-6 px-8 py-3 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 transition shadow-lg">
            Get Started Free
        </a>
    </section>
</div>
