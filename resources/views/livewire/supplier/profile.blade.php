<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">{{ session('success') }}</div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-gray-700">Company Profile</h2>
        <button wire:click="save" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">Save Changes</button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Company Info --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
            <h3 class="text-sm font-semibold text-gray-700">Company Information</h3>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Company Name *</label>
                <input type="text" wire:model="name" class="w-full rounded-lg border-gray-300 text-sm" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Contact Person</label>
                    <input type="text" wire:model="contact_person" class="w-full rounded-lg border-gray-300 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Email *</label>
                    <input type="email" wire:model="email" class="w-full rounded-lg border-gray-300 text-sm" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Phone</label>
                    <input type="text" wire:model="phone" class="w-full rounded-lg border-gray-300 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">WhatsApp</label>
                    <input type="text" wire:model="whatsapp_number" class="w-full rounded-lg border-gray-300 text-sm" placeholder="+60123456789" />
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Notification Preference</label>
                <select wire:model="notification_preference" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="email">Email only</option>
                    <option value="whatsapp">WhatsApp only</option>
                    <option value="both">Email & WhatsApp</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Address</label>
                <textarea wire:model="address" rows="2" class="w-full rounded-lg border-gray-300 text-sm"></textarea>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">City</label>
                    <input type="text" wire:model="city" class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. Kuala Lumpur" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">State</label>
                    <input type="text" wire:model="state" class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. Selangor" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Country</label>
                    <input type="text" wire:model="country" maxlength="2" class="w-full rounded-lg border-gray-300 text-sm uppercase" placeholder="MY" />
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Payment Terms</label>
                <input type="text" wire:model="payment_terms" class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. NET 30" />
            </div>
        </div>

        {{-- Billing --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
            <h3 class="text-sm font-semibold text-gray-700">Billing & Banking</h3>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Tax Registration Number</label>
                <input type="text" wire:model="tax_registration_number" class="w-full rounded-lg border-gray-300 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Billing Address</label>
                <textarea wire:model="billing_address" rows="2" class="w-full rounded-lg border-gray-300 text-sm"></textarea>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Bank Name</label>
                <input type="text" wire:model="bank_name" class="w-full rounded-lg border-gray-300 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Bank Account Number</label>
                <input type="text" wire:model="bank_account_number" class="w-full rounded-lg border-gray-300 text-sm" />
            </div>
        </div>
    </div>
</div>
