<div>
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-gray-700">Settings</h2>
    </div>

    {{-- ── System Administration (Super Admin / System Admin only) ──────── --}}
    @if ($isSystemLevel)
        <div class="mb-6">
            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">System Administration</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

                {{-- Users & Roles --}}
                <a href="{{ route('settings.users') }}"
                   class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:border-indigo-300 hover:shadow-md transition flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center text-2xl group-hover:bg-gray-200 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Users & Roles</p>
                        <p class="text-sm text-gray-500 mt-0.5">Manage user accounts and permissions</p>
                        <p class="text-xs text-indigo-500 font-medium mt-2">{{ $userCount }} {{ Str::plural('user', $userCount) }}</p>
                    </div>
                </a>

                {{-- API Keys --}}
                <a href="{{ route('settings.api-keys') }}"
                   class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:border-indigo-300 hover:shadow-md transition flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center text-2xl group-hover:bg-gray-200 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">API Keys</p>
                        <p class="text-sm text-gray-500 mt-0.5">External integrations & API access</p>
                    </div>
                </a>

            </div>
        </div>
    @endif

    {{-- ── Business Settings (Super Admin / Business Manager) ──────────── --}}
    @if ($isBusinessLevel)
        <div class="mb-6">
            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Business Settings</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

                {{-- Users & Roles (for Business Manager who isn't System level) --}}
                @if (!$isSystemLevel)
                    <a href="{{ route('settings.users') }}"
                       class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:border-indigo-300 hover:shadow-md transition flex items-start gap-4">
                        <div class="flex-shrink-0 w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-2xl group-hover:bg-indigo-100 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800">Users & Roles</p>
                            <p class="text-sm text-gray-500 mt-0.5">Manage employee access</p>
                            <p class="text-xs text-indigo-500 font-medium mt-2">{{ $userCount }} {{ Str::plural('user', $userCount) }}</p>
                        </div>
                    </a>
                @endif

                {{-- Company Details --}}
                <a href="{{ route('settings.company-details') }}"
                   class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:border-indigo-300 hover:shadow-md transition flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-2xl group-hover:bg-indigo-100 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Company Details</p>
                        <p class="text-sm text-gray-500 mt-0.5">Logo, billing info & document details</p>
                    </div>
                </a>

                {{-- Branches --}}
                <a href="{{ route('settings.outlets') }}"
                   class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:border-indigo-300 hover:shadow-md transition flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-2xl group-hover:bg-indigo-100 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Branches</p>
                        <p class="text-sm text-gray-500 mt-0.5">Manage outlet locations</p>
                        <p class="text-xs text-indigo-500 font-medium mt-2">{{ $outletCount }} {{ Str::plural('branch', $outletCount) }}</p>
                    </div>
                </a>

                {{-- Suppliers moved to Purchasing nav --}}

                {{-- Cost Types --}}
                <a href="{{ route('settings.cost-types') }}"
                   class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:border-indigo-300 hover:shadow-md transition flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-2xl group-hover:bg-indigo-100 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Cost Types</p>
                        <p class="text-sm text-gray-500 mt-0.5">Define cost classification types (Food, Beverage, etc.)</p>
                        <p class="text-xs text-indigo-500 font-medium mt-2">{{ $costTypeCount }} {{ Str::plural('type', $costTypeCount) }}</p>
                    </div>
                </a>

                {{-- Ingredient Categories --}}
                <a href="{{ route('settings.categories') }}"
                   class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:border-indigo-300 hover:shadow-md transition flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-2xl group-hover:bg-indigo-100 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Ingredient Categories</p>
                        <p class="text-sm text-gray-500 mt-0.5">Organise ingredients by type</p>
                        <p class="text-xs text-indigo-500 font-medium mt-2">{{ $categoryCount }} {{ Str::plural('category', $categoryCount) }}</p>
                    </div>
                </a>

                {{-- Recipe Categories --}}
                <a href="{{ route('settings.recipe-categories') }}"
                   class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:border-indigo-300 hover:shadow-md transition flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-2xl group-hover:bg-indigo-100 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Recipe Categories</p>
                        <p class="text-sm text-gray-500 mt-0.5">Organise recipes by type</p>
                        <p class="text-xs text-indigo-500 font-medium mt-2">{{ $recipeCategoryCount }} {{ Str::plural('category', $recipeCategoryCount) }}</p>
                    </div>
                </a>

                {{-- Sales Categories --}}
                <a href="{{ route('settings.sales-categories') }}"
                   class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:border-indigo-300 hover:shadow-md transition flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-2xl group-hover:bg-indigo-100 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Sales Categories</p>
                        <p class="text-sm text-gray-500 mt-0.5">Food, Beverage, Merchandise & more</p>
                        <p class="text-xs text-indigo-500 font-medium mt-2">{{ $salesCategoryCount }} {{ Str::plural('category', $salesCategoryCount) }}</p>
                    </div>
                </a>

                {{-- PO Approvers --}}
                <a href="{{ route('settings.po-approvers') }}"
                   class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:border-indigo-300 hover:shadow-md transition flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-2xl group-hover:bg-indigo-100 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">PO Approvers</p>
                        <p class="text-sm text-gray-500 mt-0.5">Assign who approves purchase orders per outlet</p>
                        <p class="text-xs text-indigo-500 font-medium mt-2">{{ $poApproverCount }} {{ Str::plural('assignment', $poApproverCount) }}</p>
                    </div>
                </a>

                {{-- Price Alerts --}}
                <a href="{{ route('settings.price-alerts') }}"
                   class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:border-indigo-300 hover:shadow-md transition flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-2xl group-hover:bg-indigo-100 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Price Alerts</p>
                        <p class="text-sm text-gray-500 mt-0.5">Monitor ingredient price changes</p>
                        <p class="text-xs text-indigo-500 font-medium mt-2">{{ $priceAlertCount ?? 0 }} {{ Str::plural('alert', $priceAlertCount ?? 0) }}</p>
                    </div>
                </a>

                {{-- Supplier Product Mapping moved to Purchasing nav --}}

                {{-- Tax Rates --}}
                <a href="{{ route('settings.tax-rates') }}"
                   class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:border-indigo-300 hover:shadow-md transition flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-2xl group-hover:bg-indigo-100 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Tax Rates</p>
                        <p class="text-sm text-gray-500 mt-0.5">Configure tax rates per country (SST, GST, VAT)</p>
                        <p class="text-xs text-indigo-500 font-medium mt-2">{{ $taxRateCount ?? 0 }} {{ Str::plural('rate', $taxRateCount ?? 0) }}</p>
                    </div>
                </a>

                {{-- Kitchen Management --}}
                <a href="{{ route('settings.kitchen-management') }}"
                   class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:border-indigo-300 hover:shadow-md transition flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-2xl group-hover:bg-indigo-100 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Central Kitchen</p>
                        <p class="text-sm text-gray-500 mt-0.5">Manage kitchens and assign production staff</p>
                        <p class="text-xs text-indigo-500 font-medium mt-2">{{ $kitchenCount ?? 0 }} {{ Str::plural('kitchen', $kitchenCount ?? 0) }}</p>
                    </div>
                </a>

                {{-- CPU Management (only in CPU mode) --}}
                @if (Auth::user()->company?->ordering_mode === 'cpu')
                    <a href="{{ route('settings.cpu-management') }}"
                       class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:border-indigo-300 hover:shadow-md transition flex items-start gap-4">
                        <div class="flex-shrink-0 w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-2xl group-hover:bg-indigo-100 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800">Central Purchasing</p>
                            <p class="text-sm text-gray-500 mt-0.5">Manage CPU units and assigned staff</p>
                            <p class="text-xs text-indigo-500 font-medium mt-2">{{ $cpuCount ?? 0 }} {{ Str::plural('unit', $cpuCount ?? 0) }}</p>
                        </div>
                    </a>
                @endif

                {{-- Departments --}}
                <a href="{{ route('settings.departments') }}"
                   class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:border-indigo-300 hover:shadow-md transition flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-2xl group-hover:bg-indigo-100 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Departments</p>
                        <p class="text-sm text-gray-500 mt-0.5">Ordering departments for purchase orders</p>
                        <p class="text-xs text-indigo-500 font-medium mt-2">{{ $departmentCount }} {{ Str::plural('department', $departmentCount) }}</p>
                    </div>
                </a>

                {{-- Par Levels --}}
                <a href="{{ route('settings.par-levels') }}"
                   class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:border-indigo-300 hover:shadow-md transition flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-2xl group-hover:bg-indigo-100 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Par Levels</p>
                        <p class="text-sm text-gray-500 mt-0.5">Set stock par levels per ingredient per outlet for auto-ordering</p>
                    </div>
                </a>

                {{-- Calendar Events --}}
                <a href="{{ route('settings.calendar-events') }}"
                   class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:border-indigo-300 hover:shadow-md transition flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-2xl group-hover:bg-indigo-100 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Calendar Events</p>
                        <p class="text-sm text-gray-500 mt-0.5">Holidays, promotions & events for AI analytics</p>
                        <p class="text-xs text-indigo-500 font-medium mt-2">{{ $calendarEventCount }} {{ Str::plural('event', $calendarEventCount) }}</p>
                    </div>
                </a>

                {{-- Sales Targets moved to Sales nav --}}
                {{-- Labour Costs moved to Operations nav --}}

                {{-- LMS Users moved to Training nav --}}

                {{-- Form Templates moved to Purchasing nav --}}

            </div>
        </div>
    @endif
</div>
