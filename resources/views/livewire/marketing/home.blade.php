<div>
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-indigo-600 via-indigo-700 to-purple-800 text-white">
        {{-- Decorative blobs --}}
        <div class="absolute top-0 left-0 w-72 h-72 bg-white/5 rounded-full -translate-x-1/2 -translate-y-1/2 blur-3xl"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-purple-400/10 rounded-full translate-x-1/3 translate-y-1/3 blur-3xl"></div>
        <div class="absolute top-1/2 left-1/2 w-64 h-64 bg-indigo-400/10 rounded-full -translate-x-1/2 -translate-y-1/2 blur-2xl"></div>

        <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 bg-white/10 rounded-full text-xs font-medium text-indigo-200 mb-6 backdrop-blur-sm">
                        <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                        Trusted by F&B businesses across Malaysia
                    </div>
                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold leading-[1.1] tracking-tight">
                        Run Your F&B<br>Business <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-300 to-orange-300">Smarter</span>
                    </h1>
                    <p class="text-lg text-indigo-200 mt-6 max-w-lg leading-relaxed">
                        From ingredient costing to P&L reports, purchasing to staff training — everything you need in one powerful platform.
                    </p>
                    <div class="mt-8 flex flex-wrap items-center gap-4">
                        <a href="{{ route('saas.register') }}"
                           class="px-7 py-3.5 bg-white text-indigo-700 font-bold rounded-xl hover:bg-indigo-50 transition shadow-lg shadow-indigo-900/20 text-sm">
                            Start Free {{ $trialDays }}-Day Trial
                        </a>
                        <a href="{{ route('features') }}"
                           class="px-7 py-3.5 border-2 border-white/20 text-white font-medium rounded-xl hover:bg-white/10 transition backdrop-blur-sm text-sm">
                            Explore Features
                        </a>
                    </div>
                    <p class="text-xs text-indigo-300/80 mt-4">No credit card required. Setup in under 2 minutes.</p>
                </div>

                {{-- Dashboard preview mockup --}}
                <div class="hidden lg:block relative">
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl border border-white/20 p-4 shadow-2xl shadow-indigo-900/30">
                        <div class="bg-gray-900 rounded-xl overflow-hidden">
                            {{-- Mock browser bar --}}
                            <div class="flex items-center gap-2 px-4 py-2.5 bg-gray-800">
                                <div class="flex gap-1.5">
                                    <div class="w-2.5 h-2.5 rounded-full bg-red-400"></div>
                                    <div class="w-2.5 h-2.5 rounded-full bg-amber-400"></div>
                                    <div class="w-2.5 h-2.5 rounded-full bg-green-400"></div>
                                </div>
                                <div class="flex-1 mx-4 bg-gray-700 rounded-md px-3 py-1 text-[10px] text-gray-400 font-mono">servora.com.my/dashboard</div>
                            </div>
                            {{-- Mock dashboard content --}}
                            <div class="p-4 space-y-3">
                                {{-- Stats row --}}
                                <div class="grid grid-cols-3 gap-2">
                                    <div class="bg-gray-800 rounded-lg p-3">
                                        <div class="text-[10px] text-gray-500">Revenue</div>
                                        <div class="text-sm font-bold text-white mt-0.5">RM 48,250</div>
                                        <div class="text-[10px] text-green-400 mt-0.5">+12.3%</div>
                                    </div>
                                    <div class="bg-gray-800 rounded-lg p-3">
                                        <div class="text-[10px] text-gray-500">Food Cost</div>
                                        <div class="text-sm font-bold text-white mt-0.5">28.4%</div>
                                        <div class="text-[10px] text-green-400 mt-0.5">On target</div>
                                    </div>
                                    <div class="bg-gray-800 rounded-lg p-3">
                                        <div class="text-[10px] text-gray-500">Recipes</div>
                                        <div class="text-sm font-bold text-white mt-0.5">156</div>
                                        <div class="text-[10px] text-gray-500 mt-0.5">Active</div>
                                    </div>
                                </div>
                                {{-- Chart mockup --}}
                                <div class="bg-gray-800 rounded-lg p-3">
                                    <div class="text-[10px] text-gray-500 mb-2">Revenue vs Purchases (6 months)</div>
                                    <div class="flex items-end gap-1 h-16">
                                        @foreach ([40, 55, 45, 65, 50, 72] as $h)
                                            <div class="flex-1 flex gap-0.5">
                                                <div class="flex-1 bg-indigo-500 rounded-t" style="height: {{ $h }}%"></div>
                                                <div class="flex-1 bg-amber-500/60 rounded-t" style="height: {{ $h * 0.35 }}%"></div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                {{-- Table preview --}}
                                <div class="bg-gray-800 rounded-lg p-2">
                                    @foreach (['Nasi Lemak Set', 'Roti Canai', 'Teh Tarik'] as $i => $item)
                                        <div class="flex items-center justify-between py-1.5 px-2 {{ $i > 0 ? 'border-t border-gray-700' : '' }}">
                                            <span class="text-[10px] text-gray-300">{{ $item }}</span>
                                            <span class="text-[10px] text-green-400 font-medium">{{ [28, 22, 15][$i] }}%</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Floating badges --}}
                    <div class="absolute -left-4 top-1/4 bg-white rounded-xl shadow-lg p-3 animate-bounce" style="animation-duration: 3s">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div>
                                <div class="text-[10px] font-bold text-gray-800">Cost Saved</div>
                                <div class="text-xs font-bold text-green-600">-12% COGS</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Logos / Trust bar --}}
    <section class="border-b border-gray-100 bg-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <p class="text-center text-xs text-gray-400 font-medium uppercase tracking-widest mb-6">Built for every type of F&B business</p>
            <div class="flex flex-wrap items-center justify-center gap-8 text-gray-400">
                @foreach (['Restaurants', 'Cafes', 'Cloud Kitchens', 'Catering', 'Bakeries', 'Bars & Pubs', 'Food Courts', 'Hotels'] as $type)
                    <span class="text-sm font-medium">{{ $type }}</span>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Features Grid --}}
    <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <div class="text-center mb-14">
            <p class="text-xs font-bold text-indigo-600 uppercase tracking-widest mb-2">All-in-One Platform</p>
            <h2 class="text-3xl font-bold text-gray-900">Everything You Need to Run Your F&B Business</h2>
            <p class="text-sm text-gray-500 mt-3 max-w-xl mx-auto">From ingredient costing to P&L reports — we've got you covered.</p>
        </div>

        @php
            $features = [
                ['icon' => '<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>', 'title' => 'Ingredient Management', 'desc' => 'Track ingredients with UOM conversions, cost history, supplier links, and automated cost calculations.', 'color' => 'orange'],
                ['icon' => '<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 012.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/></svg>', 'title' => 'Recipe Costing', 'desc' => 'Build recipes with real-time cost calculations, yield tracking, food cost %, and multi-outlet tagging.', 'color' => 'blue'],
                ['icon' => '<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/></svg>', 'title' => 'Purchasing & GRN', 'desc' => 'Full PO to DO to GRN workflow with approval chains, PDF documents, and automatic cost updates.', 'color' => 'green'],
                ['icon' => '<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>', 'title' => 'Sales Tracking', 'desc' => 'Daily sales entry with Z-report OCR, meal period tracking, CSV import, and revenue analytics.', 'color' => 'emerald'],
                ['icon' => '<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/></svg>', 'title' => 'Inventory Control', 'desc' => 'Stock takes, wastage tracking, inter-outlet transfers, staff meals, and par level management.', 'color' => 'violet'],
                ['icon' => '<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>', 'title' => 'Reports & P&L', 'desc' => 'Monthly cost summaries, COGS calculation, labour cost tracking, and CSV/PDF exports.', 'color' => 'rose'],
                ['icon' => '<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/></svg>', 'title' => 'Staff Training (LMS)', 'desc' => 'SOPs with step-by-step instructions, training videos, plating images, and QR code access.', 'color' => 'cyan'],
                ['icon' => '<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"/></svg>', 'title' => 'AI Analytics', 'desc' => 'AI-powered operational insights, trend analysis, and cost optimization recommendations.', 'color' => 'purple'],
                ['icon' => '<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/></svg>', 'title' => 'Multi-Outlet', 'desc' => 'Manage multiple outlets with shared ingredients and recipes, outlet-scoped data, and easy switching.', 'color' => 'amber'],
                ['icon' => '<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>', 'title' => 'Central Kitchen', 'desc' => 'Produce in bulk, track yield, route outlet prep requests through one production hub.', 'color' => 'lime'],
                ['icon' => '<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25"/></svg>', 'title' => 'RFQ & Quotations', 'desc' => 'Request quotes from multiple suppliers, compare side-by-side, accept → auto-generate PO.', 'color' => 'teal'],
                ['icon' => '<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614"/></svg>', 'title' => 'Supplier Portal', 'desc' => 'Your suppliers log in, manage catalogues, receive POs, and submit quotes — all in one place.', 'color' => 'sky'],
                ['icon' => '<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>', 'title' => 'HR & Overtime', 'desc' => 'Employee master, OT claims with approver routing, labour cost rolled into the P&L.', 'color' => 'pink'],
                ['icon' => '<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08M15.75 18.75H6.75a2.25 2.25 0 01-2.25-2.25V6.108c0-1.135.845-2.098 1.976-2.192a48.424 48.424 0 011.123-.08"/></svg>', 'title' => 'Invoice AI & Match', 'desc' => 'Upload a supplier invoice — AI extracts lines, three-way match PO ↔ GRN ↔ invoice.', 'color' => 'indigo'],
            ];

            $colorMap = [
                'orange' => ['bg' => 'bg-orange-50', 'text' => 'text-orange-600', 'border' => 'border-orange-100'],
                'blue' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'border' => 'border-blue-100'],
                'green' => ['bg' => 'bg-green-50', 'text' => 'text-green-600', 'border' => 'border-green-100'],
                'emerald' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'border' => 'border-emerald-100'],
                'violet' => ['bg' => 'bg-violet-50', 'text' => 'text-violet-600', 'border' => 'border-violet-100'],
                'rose' => ['bg' => 'bg-rose-50', 'text' => 'text-rose-600', 'border' => 'border-rose-100'],
                'cyan' => ['bg' => 'bg-cyan-50', 'text' => 'text-cyan-600', 'border' => 'border-cyan-100'],
                'purple' => ['bg' => 'bg-purple-50', 'text' => 'text-purple-600', 'border' => 'border-purple-100'],
                'amber' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-600', 'border' => 'border-amber-100'],
                'lime' => ['bg' => 'bg-lime-50', 'text' => 'text-lime-600', 'border' => 'border-lime-100'],
                'teal' => ['bg' => 'bg-teal-50', 'text' => 'text-teal-600', 'border' => 'border-teal-100'],
                'sky' => ['bg' => 'bg-sky-50', 'text' => 'text-sky-600', 'border' => 'border-sky-100'],
                'pink' => ['bg' => 'bg-pink-50', 'text' => 'text-pink-600', 'border' => 'border-pink-100'],
                'indigo' => ['bg' => 'bg-indigo-50', 'text' => 'text-indigo-600', 'border' => 'border-indigo-100'],
            ];
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ($features as $feature)
                @php $c = $colorMap[$feature['color']]; @endphp
                <div class="group bg-white rounded-2xl border border-gray-100 p-6 hover:shadow-lg hover:border-{{ $feature['color'] }}-200 transition-all duration-300 hover:-translate-y-1">
                    <div class="w-12 h-12 {{ $c['bg'] }} rounded-xl flex items-center justify-center {{ $c['text'] }} mb-4 group-hover:scale-110 transition-transform">
                        {!! $feature['icon'] !!}
                    </div>
                    <h3 class="text-base font-bold text-gray-800">{{ $feature['title'] }}</h3>
                    <p class="text-sm text-gray-500 mt-2 leading-relaxed">{{ $feature['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- How It Works --}}
    <section class="bg-gradient-to-b from-gray-50 to-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <div class="text-center mb-14">
                <p class="text-xs font-bold text-indigo-600 uppercase tracking-widest mb-2">Simple Setup</p>
                <h2 class="text-3xl font-bold text-gray-900">Up and Running in Minutes</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                @php
                    $steps = [
                        ['num' => '01', 'title' => 'Sign Up', 'desc' => 'Create your account with your company name and email. No credit card needed.', 'gradient' => 'from-indigo-500 to-blue-500'],
                        ['num' => '02', 'title' => 'Add Your Data', 'desc' => 'Import ingredients, build recipes, and set up your outlets. We guide you step by step.', 'gradient' => 'from-blue-500 to-cyan-500'],
                        ['num' => '03', 'title' => 'Take Control', 'desc' => 'Track costs, manage purchases, and generate reports. See results from day one.', 'gradient' => 'from-cyan-500 to-emerald-500'],
                    ];
                @endphp
                @foreach ($steps as $i => $step)
                    <div class="relative">
                        @if ($i < 2)
                            <div class="hidden md:block absolute top-8 left-full w-full h-0.5 bg-gradient-to-r {{ $step['gradient'] }} opacity-20 -translate-x-4"></div>
                        @endif
                        <div class="w-16 h-16 bg-gradient-to-br {{ $step['gradient'] }} rounded-2xl flex items-center justify-center text-white font-bold text-lg shadow-lg mb-5">
                            {{ $step['num'] }}
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">{{ $step['title'] }}</h3>
                        <p class="text-sm text-gray-500 mt-2 leading-relaxed">{{ $step['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Stats --}}
    <section class="bg-indigo-600">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center text-white">
                @php
                    $stats = [
                        ['value' => '500+', 'label' => 'Recipes Managed'],
                        ['value' => '12%', 'label' => 'Avg Cost Reduction'],
                        ['value' => '10x', 'label' => 'Faster Reporting'],
                        ['value' => '24/7', 'label' => 'Cloud Access'],
                    ];
                @endphp
                @foreach ($stats as $stat)
                    <div>
                        <p class="text-3xl sm:text-4xl font-extrabold">{{ $stat['value'] }}</p>
                        <p class="text-sm text-indigo-200 mt-1">{{ $stat['label'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Testimonials --}}
    <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <div class="text-center mb-14">
            <p class="text-xs font-bold text-indigo-600 uppercase tracking-widest mb-2">Testimonials</p>
            <h2 class="text-3xl font-bold text-gray-900">Trusted by F&B Operators Across Malaysia</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @php
                $testimonials = [
                    ['quote' => 'Servora helped us cut food costs by 12% in 3 months. The recipe costing alone is worth it.', 'name' => 'Ahmad R.', 'role' => 'Restaurant Owner, KL', 'rating' => 5],
                    ['quote' => 'Finally, a system that understands F&B operations. The PO to GRN flow saved us hours every week.', 'name' => 'Sarah L.', 'role' => 'Operations Manager, Penang', 'rating' => 5],
                    ['quote' => 'The LMS module transformed our staff training. New hires get up to speed in half the time.', 'name' => 'David T.', 'role' => 'F&B Group Director, JB', 'rating' => 5],
                ];
            @endphp
            @foreach ($testimonials as $t)
                <div class="bg-white rounded-2xl border border-gray-100 p-6 hover:shadow-lg transition-shadow">
                    <div class="flex gap-0.5 mb-4">
                        @for ($s = 0; $s < $t['rating']; $s++)
                            <svg class="w-4 h-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        @endfor
                    </div>
                    <p class="text-sm text-gray-600 leading-relaxed italic">"{{ $t['quote'] }}"</p>
                    <div class="mt-5 flex items-center gap-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-indigo-400 to-purple-500 rounded-full flex items-center justify-center text-white font-bold text-sm">
                            {{ substr($t['name'], 0, 1) }}
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-800">{{ $t['name'] }}</p>
                            <p class="text-xs text-gray-400">{{ $t['role'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- CTA --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-gray-900 via-indigo-950 to-gray-900">
        <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9ImdyaWQiIHdpZHRoPSI2MCIgaGVpZ2h0PSI2MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTSA2MCAwIEwgMCAwIDAgNjAiIGZpbGw9Im5vbmUiIHN0cm9rZT0icmdiYSgyNTUsMjU1LDI1NSwwLjAzKSIgc3Ryb2tlLXdpZHRoPSIxIi8+PC9wYXR0ZXJuPjwvZGVmcz48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSJ1cmwoI2dyaWQpIi8+PC9zdmc+')] opacity-50"></div>
        <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
            <h2 class="text-3xl sm:text-4xl font-extrabold text-white">Ready to Take Control of Your F&B Operations?</h2>
            <p class="text-lg text-gray-400 mt-4 max-w-xl mx-auto">Join F&B businesses across Malaysia who use Servora to cut costs, save time, and grow smarter.</p>
            <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                <a href="{{ route('saas.register') }}"
                   class="px-8 py-4 bg-white text-indigo-700 font-bold rounded-xl hover:bg-indigo-50 transition shadow-lg text-sm">
                    Start Your Free {{ $trialDays }}-Day Trial
                </a>
                <a href="{{ route('pricing') }}"
                   class="px-8 py-4 border-2 border-white/20 text-white font-medium rounded-xl hover:bg-white/10 transition text-sm">
                    View Pricing
                </a>
            </div>
            <p class="text-xs text-gray-500 mt-4">No credit card required. Cancel anytime.</p>
        </div>
    </section>
</div>
