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

    {{-- ── Company Administration (Company Admin / Business Admin only) ──── --}}
    @if ($isBusinessLevel && !$isSystemLevel)
        <div class="mb-6">
            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Company Administration</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

                {{-- Users & Roles --}}
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

            </div>
        </div>
    @endif

    {{-- ── General Settings (anyone with settings.view permission) ──────── --}}
    @if ($hasSettingsAccess)
        <div class="mb-6">
            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">General Settings</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

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
                {{-- Ingredient Categories moved to Inventory & Recipes nav --}}
                {{-- Recipe Categories moved to Inventory & Recipes nav --}}
                {{-- Price Classes moved to Inventory & Recipes nav --}}
                {{-- Sales Categories moved to Sales nav --}}

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

                {{-- OT Approvers --}}
                <a href="{{ route('settings.ot-approvers') }}"
                   class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:border-indigo-300 hover:shadow-md transition flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-2xl group-hover:bg-indigo-100 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">OT Approvers</p>
                        <p class="text-sm text-gray-500 mt-0.5">Assign who approves overtime claims per outlet</p>
                    </div>
                </a>

                {{-- Price Alerts moved to Procurement nav --}}
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

                {{-- Sections (employee grouping) --}}
                <a href="{{ route('settings.sections') }}"
                   class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:border-indigo-300 hover:shadow-md transition flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-2xl group-hover:bg-indigo-100 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Sections</p>
                        <p class="text-sm text-gray-500 mt-0.5">Employee groups (FOH, BOH, …) for OT claims and duty roster</p>
                    </div>
                </a>

                {{-- Outlet Groups --}}
                <a href="{{ route('settings.outlet-groups') }}"
                   class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:border-indigo-300 hover:shadow-md transition flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-2xl group-hover:bg-indigo-100 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 100-8 4 4 0 000 8zm6 4a3 3 0 100-6 3 3 0 000 6zM7 14a3 3 0 100-6 3 3 0 000 6z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Outlet Groups</p>
                        <p class="text-sm text-gray-500 mt-0.5">Group outlets to tag recipes in bulk</p>
                        <p class="text-xs text-indigo-500 font-medium mt-2">{{ $outletGroupCount ?? 0 }} {{ Str::plural('group', $outletGroupCount ?? 0) }}</p>
                    </div>
                </a>

                {{-- Par Levels moved to Inventory & Recipes nav --}}
                {{-- Calendar Events moved to Business Intelligence nav --}}

                {{-- Scheduled Reports --}}
                @can('reports.view')
                    <a href="{{ route('settings.reports') }}"
                       class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:border-indigo-300 hover:shadow-md transition flex items-start gap-4">
                        <div class="flex-shrink-0 w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-2xl group-hover:bg-indigo-100 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800">Scheduled Reports</p>
                            <p class="text-sm text-gray-500 mt-0.5">AI-powered analytics reports via email</p>
                            <p class="text-xs text-indigo-500 font-medium mt-2">{{ \App\Models\ReportSubscription::count() }} {{ Str::plural('subscription', \App\Models\ReportSubscription::count()) }}</p>
                        </div>
                    </a>
                @endcan

                {{-- Document Folders --}}
                @can('hr.documents.manage')
                    <a href="{{ route('settings.document-folders') }}"
                       class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:border-indigo-300 hover:shadow-md transition flex items-start gap-4">
                        <div class="flex-shrink-0 w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-2xl group-hover:bg-indigo-100 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800">Document Folders</p>
                            <p class="text-sm text-gray-500 mt-0.5">Configure Google Drive folders for company documents</p>
                            <p class="text-xs text-indigo-500 font-medium mt-2">{{ \App\Models\DocumentFolder::count() }} {{ Str::plural('folder', \App\Models\DocumentFolder::count()) }}</p>
                        </div>
                    </a>
                @endcan

                {{-- Sales Targets moved to Sales nav --}}
                {{-- Labour Costs moved to Operations nav --}}

                {{-- LMS Users moved to Training nav --}}

                {{-- Form Templates moved to Purchasing nav --}}

            </div>
        </div>
    @endif
</div>
