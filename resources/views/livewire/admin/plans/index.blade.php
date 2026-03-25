<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    <div class="flex items-center gap-3 mb-4">
        <div class="flex-1">
            <h1 class="text-lg font-bold text-gray-800">Subscription Plans</h1>
            <p class="text-xs text-gray-400 mt-0.5">Manage plans that customers can subscribe to.</p>
        </div>
        <a href="{{ route('admin.plans.create') }}"
           class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            + New Plan
        </a>
    </div>

    {{-- Plans Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($plans as $plan)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 {{ !$plan->is_active ? 'opacity-60' : '' }}">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <h3 class="text-base font-bold text-gray-800">{{ $plan->name }}</h3>
                        <p class="text-xs text-gray-400">{{ $plan->slug }}</p>
                    </div>
                    <div class="flex items-center gap-1.5">
                        @if ($plan->is_active)
                            <span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs font-medium rounded-full">Active</span>
                        @else
                            <span class="px-2 py-0.5 bg-gray-100 text-gray-500 text-xs font-medium rounded-full">Inactive</span>
                        @endif
                    </div>
                </div>

                @if ($plan->description)
                    <p class="text-xs text-gray-500 mb-3">{{ $plan->description }}</p>
                @endif

                {{-- Pricing --}}
                <div class="flex items-baseline gap-2 mb-4">
                    <span class="text-2xl font-bold text-gray-900">{{ $plan->currency }} {{ number_format($plan->price_monthly, 0) }}</span>
                    <span class="text-sm text-gray-400">/month</span>
                </div>
                <p class="text-xs text-gray-400 -mt-3 mb-4">
                    {{ $plan->currency }} {{ number_format($plan->price_yearly, 0) }}/year
                    @if ($plan->yearlyDiscount() > 0)
                        <span class="text-green-600 font-medium">(save {{ $plan->yearlyDiscount() }}%)</span>
                    @endif
                </p>

                {{-- Limits --}}
                <div class="space-y-1.5 mb-4 text-xs">
                    <div class="flex justify-between text-gray-600">
                        <span>Outlets</span>
                        <span class="font-medium">{{ $plan->max_outlets ?? 'Unlimited' }}</span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>Users</span>
                        <span class="font-medium">{{ $plan->max_users ?? 'Unlimited' }}</span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>Recipes</span>
                        <span class="font-medium">{{ $plan->max_recipes ?? 'Unlimited' }}</span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>Ingredients</span>
                        <span class="font-medium">{{ $plan->max_ingredients ?? 'Unlimited' }}</span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>LMS Users</span>
                        <span class="font-medium">{{ $plan->max_lms_users ?? 'Unlimited' }}</span>
                    </div>
                </div>

                {{-- Feature Flags --}}
                <div class="flex flex-wrap gap-1.5 mb-4">
                    @foreach ($plan->feature_flags ?? [] as $flag => $enabled)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium
                            {{ $enabled ? 'bg-indigo-50 text-indigo-700' : 'bg-gray-50 text-gray-400 line-through' }}">
                            {{ str_replace('_', ' ', ucfirst($flag)) }}
                        </span>
                    @endforeach
                </div>

                {{-- Stats & Actions --}}
                <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                    <span class="text-xs text-gray-400">{{ $plan->subscriptions_count }} {{ Str::plural('subscriber', $plan->subscriptions_count) }}</span>
                    <div class="flex items-center gap-2">
                        <button wire:click="toggleActive({{ $plan->id }})"
                                title="{{ $plan->is_active ? 'Deactivate' : 'Activate' }}"
                                class="{{ $plan->is_active ? 'text-green-500 hover:text-green-700' : 'text-gray-400 hover:text-gray-600' }} transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </button>
                        <a href="{{ route('admin.plans.edit', $plan->id) }}" title="Edit"
                           class="text-indigo-500 hover:text-indigo-700 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </a>
                        <button wire:click="delete({{ $plan->id }})"
                                wire:confirm="Delete '{{ $plan->name }}'? Only possible if no subscribers."
                                title="Delete"
                                class="text-red-400 hover:text-red-600 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center text-gray-400">
                <p class="font-medium">No plans yet</p>
                <p class="text-xs mt-1">Create your first subscription plan to get started.</p>
            </div>
        @endforelse
    </div>
</div>
