<div>
    @if (session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <h1 class="text-lg font-bold text-gray-800 mb-1">Billing & Plan</h1>
    <p class="text-xs text-gray-400 mb-6">Manage your subscription and view usage.</p>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Current Plan --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Plan Card --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-800">Current Plan</h2>
                        @if ($isGrandfathered)
                            <span class="inline-flex items-center px-2 py-0.5 mt-1 rounded-full text-xs font-medium bg-purple-100 text-purple-700">
                                Legacy (Unlimited)
                            </span>
                        @elseif ($subscription)
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-xl font-bold text-gray-900">{{ $plan->name }}</span>
                                @php $color = $subscription->statusColor(); @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $color }}-100 text-{{ $color }}-700">
                                    {{ $subscription->statusLabel() }}
                                </span>
                            </div>
                        @else
                            <p class="text-sm text-red-600 mt-1 font-medium">No active subscription</p>
                        @endif
                    </div>

                    @if ($subscription && $plan)
                        <div class="text-right">
                            <p class="text-2xl font-bold text-gray-900">
                                {{ $plan->currency }} {{ number_format($subscription->currentPrice(), 0) }}
                            </p>
                            <p class="text-xs text-gray-400">/{{ $subscription->billing_cycle === 'yearly' ? 'year' : 'month' }}</p>
                        </div>
                    @endif
                </div>

                @if ($subscription)
                    <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                        @if ($subscription->isTrial())
                            <div class="bg-blue-50 rounded-lg p-3">
                                <p class="text-blue-500 font-medium">Trial Ends</p>
                                <p class="text-blue-900 font-bold mt-0.5">{{ $subscription->trial_ends_at?->format('d M Y') }}</p>
                                <p class="text-blue-500 mt-0.5">{{ $subscription->daysRemaining() }} days left</p>
                            </div>
                        @endif
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-gray-500 font-medium">Period End</p>
                            <p class="text-gray-900 font-bold mt-0.5">{{ $subscription->current_period_end?->format('d M Y') ?? '—' }}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-gray-500 font-medium">Billing Cycle</p>
                            <p class="text-gray-900 font-bold mt-0.5 capitalize">{{ $subscription->billing_cycle }}</p>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Usage Meters --}}
            @if (!$isGrandfathered)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-base font-semibold text-gray-800 mb-4">Usage</h2>
                    <div class="space-y-3">
                        @foreach ($usageMetrics as $metric)
                            <div>
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span class="text-gray-600 font-medium">{{ $metric['label'] }}</span>
                                    <span class="text-gray-500">
                                        {{ $metric['current'] }} / {{ $metric['limit'] ?? '∞' }}
                                    </span>
                                </div>
                                @if ($metric['limit'])
                                    <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-300
                                            {{ $metric['percent'] >= 90 ? 'bg-red-500' : ($metric['percent'] >= 70 ? 'bg-amber-500' : 'bg-indigo-500') }}"
                                             style="width: {{ $metric['percent'] }}%"></div>
                                    </div>
                                @else
                                    <div class="w-full h-2 bg-gray-100 rounded-full">
                                        <div class="h-full bg-green-300 rounded-full" style="width: 5%"></div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Available Plans --}}
        <div class="space-y-4">
            <h2 class="text-sm font-semibold text-gray-800">Available Plans</h2>
            @foreach ($plans as $availPlan)
                @php $isCurrent = $plan && $plan->id === $availPlan->id; @endphp
                <div class="bg-white rounded-xl shadow-sm border {{ $isCurrent ? 'border-indigo-300 ring-1 ring-indigo-300' : 'border-gray-100' }} p-4">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-bold text-gray-800">{{ $availPlan->name }}</h3>
                        @if ($isCurrent)
                            <span class="text-[10px] font-bold text-indigo-600 uppercase">Current</span>
                        @endif
                    </div>
                    <p class="text-lg font-bold text-gray-900">
                        {{ $availPlan->currency }} {{ number_format($availPlan->price_monthly, 0) }}
                        <span class="text-xs text-gray-400 font-normal">/mo</span>
                    </p>
                    <p class="text-xs text-gray-400 mt-1 mb-3">
                        {{ $availPlan->max_outlets ?? '∞' }} outlets,
                        {{ $availPlan->max_users ?? '∞' }} users
                    </p>
                    @if ($isCurrent && $subscription && $subscription->isTrial())
                        <a href="{{ route('billing.checkout', $availPlan->slug) }}"
                           class="block w-full text-center py-2 text-xs font-semibold rounded-lg transition bg-green-600 text-white hover:bg-green-700">
                            Pay & Activate
                        </a>
                    @elseif (!$isCurrent)
                        <a href="{{ route('billing.checkout', $availPlan->slug) }}"
                           class="block w-full text-center py-2 text-xs font-semibold rounded-lg transition
                                  {{ ($plan && $availPlan->price_monthly > $plan->price_monthly) ? 'bg-indigo-600 text-white hover:bg-indigo-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            {{ ($plan && $availPlan->price_monthly > $plan->price_monthly) ? 'Upgrade' : 'Switch' }}
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

</div>
