<div>
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.plans.index') }}" class="text-gray-400 hover:text-gray-600 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div>
            <p class="text-xs text-gray-400"><a href="{{ route('admin.plans.index') }}" class="hover:underline">Plans</a> / {{ $planId ? 'Edit' : 'Create' }}</p>
        </div>
    </div>

    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <form wire:submit="save" class="max-w-2xl space-y-6">

        {{-- Basic Info --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-4">Plan Details</h3>

            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="plan_name" value="Plan Name *" />
                        <x-text-input id="plan_name" wire:model.live.debounce.300ms="name" type="text" class="mt-1 block w-full" placeholder="e.g. Professional" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="plan_slug" value="Slug *" />
                        <x-text-input id="plan_slug" wire:model="slug" type="text" class="mt-1 block w-full" placeholder="e.g. professional" />
                        <x-input-error :messages="$errors->get('slug')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="plan_desc" value="Description" />
                    <textarea id="plan_desc" wire:model="description" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="Brief description shown on the pricing page…"></textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-1" />
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="plan_sort" value="Sort Order" />
                        <x-text-input id="plan_sort" wire:model="sort_order" type="number" min="0" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <x-input-label for="plan_trial" value="Trial Days" />
                        <x-text-input id="plan_trial" wire:model="trial_days" type="number" min="0" max="365" class="mt-1 block w-full" />
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="is_active"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700 font-medium">Active</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        {{-- Pricing --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-4">Pricing</h3>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <x-input-label for="plan_currency" value="Currency" />
                    <select id="plan_currency" wire:model="currency"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="MYR">MYR</option>
                        <option value="USD">USD</option>
                        <option value="SGD">SGD</option>
                    </select>
                </div>
                <div>
                    <x-input-label for="plan_monthly" value="Monthly Price *" />
                    <x-text-input id="plan_monthly" wire:model="price_monthly" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('price_monthly')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="plan_yearly" value="Yearly Price *" />
                    <x-text-input id="plan_yearly" wire:model="price_yearly" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('price_yearly')" class="mt-1" />
                </div>
            </div>
        </div>

        {{-- Usage Limits --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-1">Usage Limits</h3>
            <p class="text-xs text-gray-400 mb-4">Leave blank for unlimited.</p>

            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div>
                    <x-input-label for="plan_outlets" value="Max Outlets" />
                    <x-text-input id="plan_outlets" wire:model="max_outlets" type="number" min="1" class="mt-1 block w-full" placeholder="Unlimited" />
                </div>
                <div>
                    <x-input-label for="plan_users" value="Max Users" />
                    <x-text-input id="plan_users" wire:model="max_users" type="number" min="1" class="mt-1 block w-full" placeholder="Unlimited" />
                </div>
                <div>
                    <x-input-label for="plan_recipes" value="Max Recipes" />
                    <x-text-input id="plan_recipes" wire:model="max_recipes" type="number" min="1" class="mt-1 block w-full" placeholder="Unlimited" />
                </div>
                <div>
                    <x-input-label for="plan_ingredients" value="Max Ingredients" />
                    <x-text-input id="plan_ingredients" wire:model="max_ingredients" type="number" min="1" class="mt-1 block w-full" placeholder="Unlimited" />
                </div>
                <div>
                    <x-input-label for="plan_lms" value="Max LMS Users" />
                    <x-text-input id="plan_lms" wire:model="max_lms_users" type="number" min="1" class="mt-1 block w-full" placeholder="Unlimited" />
                </div>
            </div>
        </div>

        {{-- Feature Flags --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-1">Feature Access</h3>
            <p class="text-xs text-gray-400 mb-4">Toggle which modules are available on this plan.</p>

            <div class="grid grid-cols-2 gap-3">
                <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-100 cursor-pointer hover:bg-gray-50 transition">
                    <input type="checkbox" wire:model="flag_lms"
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                    <div>
                        <p class="text-sm font-medium text-gray-700">LMS / Training</p>
                        <p class="text-xs text-gray-400">Staff training portal & SOPs</p>
                    </div>
                </label>
                <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-100 cursor-pointer hover:bg-gray-50 transition">
                    <input type="checkbox" wire:model="flag_reports"
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                    <div>
                        <p class="text-sm font-medium text-gray-700">Reports</p>
                        <p class="text-xs text-gray-400">Cost summary & P&L reports</p>
                    </div>
                </label>
                <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-100 cursor-pointer hover:bg-gray-50 transition">
                    <input type="checkbox" wire:model="flag_analytics"
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                    <div>
                        <p class="text-sm font-medium text-gray-700">Analytics</p>
                        <p class="text-xs text-gray-400">Advanced analytics dashboard</p>
                    </div>
                </label>
                <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-100 cursor-pointer hover:bg-gray-50 transition">
                    <input type="checkbox" wire:model="flag_ai_analysis"
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                    <div>
                        <p class="text-sm font-medium text-gray-700">AI Analysis</p>
                        <p class="text-xs text-gray-400">AI-powered insights & suggestions</p>
                    </div>
                </label>
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.plans.index') }}"
               class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">Cancel</a>
            <button type="submit"
                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                {{ $planId ? 'Update Plan' : 'Create Plan' }}
            </button>
        </div>
    </form>
</div>
