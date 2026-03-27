<div>
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-indigo-600 via-indigo-700 to-purple-800 text-white">
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-20 left-10 w-72 h-72 bg-white rounded-full blur-3xl"></div>
            <div class="absolute bottom-10 right-20 w-96 h-96 bg-purple-300 rounded-full blur-3xl"></div>
        </div>
        <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-24 text-center">
            <span class="inline-block px-4 py-1.5 bg-white/10 backdrop-blur rounded-full text-sm font-medium mb-6">For F&B Suppliers</span>
            <h1 class="text-4xl sm:text-5xl font-bold leading-tight mb-6">
                Grow Your Business with<br>
                <span class="text-amber-300">Servora's F&B Network</span>
            </h1>
            <p class="text-lg text-indigo-100 max-w-2xl mx-auto mb-10">
                List your products once and get discovered by restaurants, cafes, hotels, and food businesses across Malaysia. Receive digital purchase orders, respond to quotation requests, and track payments — all in one platform.
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="{{ route('supplier.register') }}"
                   class="px-8 py-3.5 bg-white text-indigo-700 font-semibold rounded-xl hover:bg-gray-100 transition shadow-lg text-lg">
                    Register as Supplier — Free
                </a>
                <a href="{{ route('supplier.login') }}"
                   class="px-6 py-3 text-white/80 hover:text-white border border-white/30 rounded-xl transition font-medium">
                    Already registered? Log In
                </a>
            </div>
        </div>
    </section>

    {{-- Why Join --}}
    <section class="py-20 bg-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14">
                <h2 class="text-3xl font-bold text-gray-900">Why Suppliers Choose Servora</h2>
                <p class="text-gray-500 mt-3 max-w-2xl mx-auto">Join the platform that connects you directly with F&B decision-makers who are actively ordering ingredients and supplies.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div class="text-center">
                    <div class="w-14 h-14 bg-indigo-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">Get Discovered</h3>
                    <p class="text-sm text-gray-500">Your products are visible to every F&B business on Servora. No cold calling needed — let them find you.</p>
                </div>
                <div class="text-center">
                    <div class="w-14 h-14 bg-green-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">Digital Orders</h3>
                    <p class="text-sm text-gray-500">Receive purchase orders digitally via email or your portal. No more phone calls, faxes, or handwritten notes.</p>
                </div>
                <div class="text-center">
                    <div class="w-14 h-14 bg-amber-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">Track Payments</h3>
                    <p class="text-sm text-gray-500">View invoices, track what's outstanding, and know exactly when you'll get paid — all from your dashboard.</p>
                </div>
                <div class="text-center">
                    <div class="w-14 h-14 bg-purple-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">Respond to Quotations</h3>
                    <p class="text-sm text-gray-500">Businesses send you RFQs directly. Respond with your best price — listed, discounted, or tender — and win the order.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- How It Works --}}
    <section class="py-20 bg-gray-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14">
                <h2 class="text-3xl font-bold text-gray-900">How It Works</h2>
                <p class="text-gray-500 mt-3">Get started in three simple steps — it's completely free.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="relative bg-white rounded-2xl p-8 shadow-sm border border-gray-100 text-center">
                    <div class="w-10 h-10 bg-indigo-600 text-white rounded-full flex items-center justify-center text-lg font-bold mx-auto mb-4">1</div>
                    <h3 class="font-semibold text-gray-900 mb-2">Register Your Company</h3>
                    <p class="text-sm text-gray-500">Create your free supplier account with your company details, contact info, and payment preferences.</p>
                </div>
                <div class="relative bg-white rounded-2xl p-8 shadow-sm border border-gray-100 text-center">
                    <div class="w-10 h-10 bg-indigo-600 text-white rounded-full flex items-center justify-center text-lg font-bold mx-auto mb-4">2</div>
                    <h3 class="font-semibold text-gray-900 mb-2">List Your Products</h3>
                    <p class="text-sm text-gray-500">Upload your product catalog — individually or via CSV. Set your prices, pack sizes, and minimum order quantities.</p>
                </div>
                <div class="relative bg-white rounded-2xl p-8 shadow-sm border border-gray-100 text-center">
                    <div class="w-10 h-10 bg-indigo-600 text-white rounded-full flex items-center justify-center text-lg font-bold mx-auto mb-4">3</div>
                    <h3 class="font-semibold text-gray-900 mb-2">Receive Orders</h3>
                    <p class="text-sm text-gray-500">F&B businesses discover your products, send you POs or quotation requests, and you fulfil orders digitally.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Stats / Social Proof --}}
    <section class="py-16 bg-indigo-700 text-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-8 text-center">
                <div>
                    <p class="text-4xl font-bold">{{ $stats['companies'] }}+</p>
                    <p class="text-indigo-200 mt-1">F&B Businesses</p>
                </div>
                <div>
                    <p class="text-4xl font-bold">{{ number_format($stats['ingredients']) }}+</p>
                    <p class="text-indigo-200 mt-1">Ingredients Listed</p>
                </div>
                <div>
                    <p class="text-4xl font-bold">{{ number_format($stats['orders']) }}+</p>
                    <p class="text-indigo-200 mt-1">Purchase Orders Processed</p>
                </div>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="py-20 bg-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-gray-900 text-center mb-12">Frequently Asked Questions</h2>
            <div x-data="{ open: null }" class="space-y-3">
                @foreach ([
                    ['Is it free to register?', 'Yes, completely free. There are no listing fees or subscription charges for suppliers. You only pay standard payment processing fees when receiving payments through the platform.'],
                    ['Who can see my products?', 'Any F&B business using Servora can discover your products when they search for ingredients or suppliers. Your company profile and product catalog are visible to all active Servora users.'],
                    ['How do I receive orders?', 'When a business creates a purchase order for your products, you receive an email notification and can view the full PO details in your supplier portal dashboard.'],
                    ['Can I set different prices for different customers?', 'Yes. When responding to a Request for Quotation (RFQ), you can offer your listed price, a discounted price, or a special tender price per request. This gives you full pricing flexibility.'],
                    ['What products can I list?', 'Any food & beverage ingredients, packaging materials, cleaning supplies, kitchen equipment, or other products that F&B businesses need. The more products you list, the more discoverable you become.'],
                ] as $i => $faq)
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <button @click="open = open === {{ $i }} ? null : {{ $i }}"
                                class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 transition">
                            <span class="text-sm font-medium text-gray-800">{{ $faq[0] }}</span>
                            <svg :class="open === {{ $i }} ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open === {{ $i }}" x-collapse class="px-6 pb-4 text-sm text-gray-500">
                            {{ $faq[1] }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA Footer --}}
    <section class="py-20 bg-gray-900 text-white text-center">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold mb-4">Ready to Reach More Customers?</h2>
            <p class="text-gray-400 mb-8 text-lg">Join Servora's supplier network today. List your products, receive digital orders, and grow your F&B business.</p>
            <a href="{{ route('supplier.register') }}"
               class="inline-block px-8 py-3.5 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 transition shadow-lg text-lg">
                Register Now — It's Free
            </a>
        </div>
    </section>
</div>
