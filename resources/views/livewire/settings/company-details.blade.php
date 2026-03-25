<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-700">Company Details</h2>
            <p class="text-xs text-gray-400 mt-0.5">These details appear on PO, DO and GRN documents</p>
        </div>
    </div>

    <form wire:submit="save">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Left: Company Info --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">Company Information</h3>

                <div>
                    <x-input-label for="name" value="Company Name *" />
                    <x-text-input id="name" wire:model="name" type="text" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="brand_name" value="Brand Name" />
                    <x-text-input id="brand_name" wire:model="brand_name" type="text" class="mt-1 block w-full" placeholder="e.g. Nando's (display name for LMS)" />
                    <p class="text-xs text-gray-400 mt-1">Displayed on the Training Portal. Defaults to company name if empty.</p>
                    <x-input-error :messages="$errors->get('brand_name')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="slug" value="URL Slug *" />
                    <x-text-input id="slug" wire:model="slug" type="text" class="mt-1 block w-full" placeholder="my-restaurant" />
                    <p class="text-xs text-gray-400 mt-1">Used in your Training Portal URL. Lowercase letters, numbers and dashes only.</p>
                    <x-input-error :messages="$errors->get('slug')" class="mt-1" />
                </div>

                {{-- LMS URLs --}}
                @if ($slug)
                    @php $appDomain = config('app.domain', 'servora.com.my'); @endphp
                    <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                        <h4 class="text-xs font-semibold text-indigo-800 uppercase tracking-wider mb-2">Training Portal URLs</h4>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs text-indigo-600 font-medium">Staff Login (Subdomain)</p>
                                    <p class="text-sm text-indigo-900 font-mono">https://{{ $slug }}.{{ $appDomain }}/lms/login</p>
                                </div>
                                <a href="https://{{ $slug }}.{{ $appDomain }}/lms/login" target="_blank"
                                   class="text-indigo-500 hover:text-indigo-700 transition" title="Open">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                </a>
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs text-indigo-600 font-medium">Staff Registration</p>
                                    <p class="text-sm text-indigo-900 font-mono">https://{{ $slug }}.{{ $appDomain }}/lms/register</p>
                                </div>
                                <a href="https://{{ $slug }}.{{ $appDomain }}/lms/register" target="_blank"
                                   class="text-indigo-500 hover:text-indigo-700 transition" title="Open">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                </a>
                            </div>
                        </div>
                        <p class="text-[10px] text-indigo-500 mt-3">Share these links with your staff to access SOPs and training materials.</p>
                    </div>
                @endif

                <div>
                    <x-input-label for="registration_number" value="Registration Number" />
                    <x-text-input id="registration_number" wire:model="registration_number" type="text" class="mt-1 block w-full" placeholder="e.g. 202301012345 (1234567-A)" />
                    <x-input-error :messages="$errors->get('registration_number')" class="mt-1" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="email" value="Email" />
                        <x-text-input id="email" wire:model="email" type="email" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="phone" value="Phone" />
                        <x-text-input id="phone" wire:model="phone" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="address" value="Registered Address" />
                    <textarea id="address" wire:model="address" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    <x-input-error :messages="$errors->get('address')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="billing_address" value="Billing Address" />
                    <textarea id="billing_address" wire:model="billing_address" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="Leave blank to use registered address"></textarea>
                    <p class="text-xs text-gray-400 mt-1">Used on purchase documents. Defaults to registered address if empty.</p>
                    <x-input-error :messages="$errors->get('billing_address')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="currency" value="Currency *" />
                    <x-text-input id="currency" wire:model="currency" type="text" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('currency')" class="mt-1" />
                </div>

                {{-- Tax Settings --}}
                <div class="border-t border-gray-100 pt-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Tax Settings</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="tax_type" value="Tax Type" />
                            <select id="tax_type" wire:model="tax_type"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">No Tax</option>
                                <option value="SST">SST</option>
                                <option value="GST">GST</option>
                                <option value="VAT">VAT</option>
                            </select>
                            <x-input-error :messages="$errors->get('tax_type')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="tax_percent" value="Tax %" />
                            <div class="mt-1 relative">
                                <x-text-input id="tax_percent" wire:model="tax_percent" type="number" step="0.01" min="0" max="100" class="block w-full pr-8" />
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">%</span>
                            </div>
                            <x-input-error :messages="$errors->get('tax_percent')" class="mt-1" />
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">Applied automatically to purchase orders.</p>
                </div>

                {{-- Document Display Settings --}}
                <div class="border-t border-gray-100 pt-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Document Display</h3>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="show_price_on_do_grn"
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                        <span class="text-sm text-gray-700">Show pricing on DO &amp; GRN</span>
                    </label>
                    <p class="text-xs text-gray-400 mt-1">When disabled, unit cost and totals are hidden on Delivery Order and GRN forms and PDFs. Pricing data is still recorded internally.</p>
                </div>

                {{-- Purchasing Workflow --}}
                <div class="border-t border-gray-100 pt-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Purchasing Workflow</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="auto_generate_do"
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700">Auto-generate DO upon PO approval</span>
                            </label>
                            <p class="text-xs text-gray-400 mt-1">When enabled, a Delivery Order and GRN will be automatically created when a Purchase Order is approved.</p>
                        </div>
                        <div>
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="direct_supplier_order"
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700">Direct Supplier Order (email PO on approval)</span>
                            </label>
                            <p class="text-xs text-gray-400 mt-1">When enabled, approved POs are emailed directly to the supplier (with PDF attached). No DO generation is needed — the supplier delivers based on the PO.</p>
                        </div>
                    </div>
                </div>

                {{-- PO Email CC List --}}
                <div class="border-t border-gray-100 pt-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">PO Email Notifications</h3>
                    <div>
                        <x-input-label for="po_cc_emails" value="CC Email List" />
                        <textarea id="po_cc_emails" wire:model="po_cc_emails" rows="2"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="manager@company.com, finance@company.com"></textarea>
                        <p class="text-xs text-gray-400 mt-1">Comma-separated emails to CC on all PO approval emails. The supplier, approver, and creator are included automatically.</p>
                    </div>
                </div>
            </div>

            {{-- Right: Logo --}}
            <div class="space-y-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Company Logo</h3>
                    <p class="text-xs text-gray-400 mb-4">This logo will appear on PO, DO and GRN PDF documents.</p>

                    @if ($currentLogo)
                        <div class="mb-4">
                            <img src="{{ asset('storage/' . $currentLogo) }}" alt="Company Logo" class="h-20 object-contain rounded border border-gray-200 p-2 bg-gray-50">
                        </div>
                    @endif

                    <div>
                        <input type="file" wire:model="logo" accept="image/*"
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                        <x-input-error :messages="$errors->get('logo')" class="mt-1" />
                        @if ($logo)
                            <p class="text-xs text-green-600 mt-2">New logo selected. Save to apply.</p>
                        @endif
                    </div>
                </div>

                <div class="bg-blue-50 rounded-xl border border-blue-200 p-5">
                    <h4 class="text-sm font-medium text-blue-800 mb-2">Document Info</h4>
                    <p class="text-xs text-blue-700 leading-relaxed">
                        Purchase documents (PO, DO, GRN) will display your company details as billing information.
                        Each outlet's name, address and phone will be used as the delivery address.
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                Save Company Details
            </button>
        </div>
    </form>
</div>
