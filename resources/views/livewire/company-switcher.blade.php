<div>
    <div x-show="sidebarExpanded"
         x-transition:enter="transition-opacity duration-150 delay-100"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity duration-75"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="px-3 pt-3 pb-2 space-y-1">

        @if ($companies->count() > 1)
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" @click.away="open = false"
                        class="flex items-center gap-2 w-full px-1 py-1 rounded-lg hover:bg-gray-800 transition text-left">
                    <span class="text-sm leading-none">🏢</span>
                    <span class="flex-1 text-xs font-medium text-gray-300 truncate">
                        {{ $companies->firstWhere('id', $activeCompanyId)?->name ?? '—' }}
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-gray-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
                    </svg>
                </button>

                <div x-show="open" x-cloak
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     class="absolute bottom-full left-0 right-0 mb-1 py-1 bg-gray-800 border border-gray-700 rounded-lg shadow-lg z-50 max-h-64 overflow-y-auto">
                    @foreach ($companies as $company)
                        <button wire:click="switchCompany({{ $company->id }})"
                                class="flex items-center gap-2 w-full px-3 py-2 text-left text-xs transition
                                       {{ $company->id === $activeCompanyId ? 'text-white bg-gray-700/60' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                            <span class="flex-1 truncate">{{ $company->name }}</span>
                            @if ($company->id === $activeCompanyId)
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-indigo-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            @endif
                        </button>
                    @endforeach

                    @if ($canCreate)
                        <button wire:click="openCreate" @click="open = false"
                                class="flex items-center gap-2 w-full px-3 py-2 text-left text-xs text-indigo-300 hover:bg-gray-700 hover:text-indigo-200 transition border-t border-gray-700 mt-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Create New Company</span>
                        </button>
                    @endif
                </div>
            </div>
        @else
            <div class="flex items-center gap-2 px-1">
                <span class="text-sm leading-none">🏢</span>
                <span class="flex-1 text-xs font-medium text-gray-300 truncate">
                    {{ $companies->first()?->name ?? (Auth::user()->company->name ?? '—') }}
                </span>
                @if ($canCreate)
                    <button wire:click="openCreate" title="Create a new company"
                            class="p-0.5 rounded text-gray-500 hover:text-indigo-300 hover:bg-gray-800 transition flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                    </button>
                @endif
            </div>
        @endif
    </div>

    {{-- Create New Company modal --}}
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/60" wire:click="closeCreate"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md p-6 text-left">
                <h2 class="text-base font-bold text-gray-800">Create New Company</h2>
                <p class="text-xs text-gray-400 mt-0.5 mb-4">A separate company under your current login — its own outlets, data, and subscription. You can switch between companies from the sidebar.</p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Company Name *</label>
                        <input type="text" wire:model="new_company_name" placeholder="e.g. My Second Restaurant Sdn Bhd"
                               class="w-full rounded-lg border-gray-300 text-sm" />
                        @error('new_company_name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Plan *</label>
                        <div class="space-y-2">
                            @foreach ($plans as $plan)
                                <label class="flex items-center gap-3 p-2.5 border rounded-lg cursor-pointer transition
                                              {{ (int) $new_plan_id === (int) $plan->id ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300' }}">
                                    <input type="radio" wire:model.live="new_plan_id" value="{{ $plan->id }}"
                                           class="text-indigo-600 focus:ring-indigo-500" />
                                    <span class="flex-1">
                                        <span class="block text-sm font-medium text-gray-800">{{ $plan->name }}</span>
                                        <span class="block text-xs text-gray-400">
                                            RM{{ number_format($new_billing_cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly, 2) }}
                                            /{{ $new_billing_cycle === 'yearly' ? 'year' : 'month' }}
                                            @if ($plan->trial_days) · {{ $plan->trial_days }}-day free trial @endif
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('new_plan_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Billing Cycle *</label>
                        <select wire:model.live="new_billing_cycle" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button wire:click="closeCreate"
                            class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition">Cancel</button>
                    <button wire:click="createCompany" wire:loading.attr="disabled"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition disabled:opacity-60">
                        <span wire:loading.remove wire:target="createCompany">Create Company</span>
                        <span wire:loading wire:target="createCompany">Creating…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
