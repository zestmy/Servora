<div>
    @if (session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="mb-4 px-4 py-3 bg-danger-50 border border-danger-200 text-danger-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <h1 class="text-lg font-bold text-gray-800 mb-1">Billing & Plan</h1>
    <p class="text-xs text-gray-600 mb-6">Manage your subscription and view usage.</p>

    {{-- Arrived from a locked sidebar link: say what it was and how to get it. --}}
    @if ($unlockPitch && $unlockPitch['pitch'])
        <div class="alert-info mb-6 flex items-start gap-3" role="status">
            <x-icon name="lock" size="h-5 w-5" class="mt-0.5 flex-shrink-0" />
            <div>
                <p class="text-sm font-semibold">{{ $unlockPitch['name'] }} is not on your plan yet</p>
                <p class="mt-0.5 text-sm">{{ $unlockPitch['pitch'] }}</p>
            </div>
        </div>
    @endif

    {{-- Coupon Redemption --}}
    <div class="mb-6 bg-gradient-to-r from-brand-50 to-purple-50 rounded-xl border border-brand-100 p-5">
        <div class="flex items-start gap-4 flex-wrap">
            <div class="flex-shrink-0 w-10 h-10 bg-brand-600 rounded-lg flex items-center justify-center text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
            </div>
            <div class="flex-1 min-w-[200px]">
                <h3 class="text-sm font-semibold text-gray-800">Redeem a Coupon</h3>
                <p class="text-xs text-gray-500 mt-0.5">Have a promo code? Enter it below to extend your subscription.</p>
            </div>
            <div class="flex gap-2 w-full md:w-auto">
                <input type="text" wire:model="couponCode"
                       placeholder="ENTER CODE"
                       class="flex-1 md:w-56 rounded-lg border-brand-200 text-sm font-mono uppercase shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                <button wire:click="redeemCoupon" wire:loading.attr="disabled"
                        class="btn-primary">
                    <span wire:loading.remove wire:target="redeemCoupon">Redeem</span>
                    <span wire:loading wire:target="redeemCoupon">Redeeming…</span>
                </button>
            </div>
        </div>
        <x-input-error :messages="$errors->get('couponCode')" class="mt-2 ml-14" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Current Plan --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Plan Card --}}
            <div class="card p-6">
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
                            <span class="mt-1 block text-xl font-bold text-gray-900">Free</span>
                        @endif
                    </div>

                    @if ($subscription && $plan)
                        <div class="text-right">
                            <p class="text-2xl font-bold text-gray-900">
                                {{ $subscription->currency ?: $plan->currency }} {{ number_format($subscription->currentPrice(), 0) }}
                            </p>
                            <p class="text-xs text-gray-600">/{{ $subscription->billing_cycle === 'yearly' ? 'year' : 'month' }}</p>
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
                        @if ($subscription->amount !== null)
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-gray-500 font-medium">Outlets</p>
                                <p class="text-gray-900 font-bold mt-0.5">{{ $subscription->outlet_quantity }}</p>
                            </div>
                        @endif
                    </div>

                    @if ($addons->isNotEmpty())
                        <p class="mt-3 text-xs text-gray-600">
                            Add-ons:
                            {{ $addons->map(fn ($a) => config("modules.catalogue.{$a->module}.name").(in_array(config("modules.catalogue.{$a->module}.kind"), ['metered'], true) ? " ({$a->quantity})" : ''))->join(', ') }}
                        </p>
                    @endif

                    @if ($subscription->pending_change)
                        <p class="alert-info mt-3 text-sm">
                            Your plan changes on {{ $subscription->current_period_end?->format('d M Y') }}, when the period you have paid for ends.
                        </p>
                    @endif
                @elseif (! $isGrandfathered)
                    <p class="mt-3 text-sm text-gray-600">
                        You are on <span class="font-semibold text-gray-900">Free</span>: recipe costing for one outlet.
                        Choose a suite to unlock purchasing, inventory control, full reports and add-ons.
                    </p>
                @endif
            </div>

            {{-- Usage Meters --}}
            @if (!$isGrandfathered)
                <div class="card p-6">
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
                                            {{-- Full is a limit reached, not a fault: on Free, 1 of 1 outlet is the normal state. --}}
                                            {{ $metric['percent'] >= 70 ? 'bg-warning-500' : 'bg-brand-500' }}"
                                             style="width: {{ $metric['percent'] }}%"></div>
                                    </div>
                                @else
                                    <div class="w-full h-2 bg-gray-100 rounded-full">
                                        <div class="h-full bg-success-300 rounded-full" style="width: 5%"></div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Plans (docs/pricing-model.md). Suites are per outlet; add-ons are
             chosen on the checkout page, which prices the whole thing. --}}
        <div class="space-y-4">
            <h2 class="text-sm font-semibold text-gray-800">Plans</h2>
            @foreach ($plans as $availPlan)
                @php
                    $isCurrent = $plan && $plan->id === $availPlan->id && $subscription && ! $subscription->isTrial();
                    $isFree = ! in_array('basic', (array) $availPlan->modules, true);
                @endphp
                <div wire:key="plan-{{ $availPlan->id }}"
                     class="card p-4 {{ ($isCurrent || ($isFree && ! $subscription && ! $isGrandfathered)) ? 'ring-1 ring-brand-300 border-brand-300' : '' }}">
                    <div class="mb-1 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-gray-900">{{ $availPlan->name }}</h3>
                        @if ($isCurrent || ($isFree && ! $subscription && ! $isGrandfathered))
                            <span class="badge-brand">Current</span>
                        @endif
                    </div>
                    <p class="text-lg font-bold text-gray-900">
                        {{ $book->format((float) ($book->suite((string) $availPlan->slug, (float) $availPlan->price_monthly) ?? $availPlan->price_monthly), 0) }}
                        @unless ($isFree)
                            <span class="text-xs font-normal text-gray-600">/ outlet / month</span>
                        @endunless
                    </p>
                    <p class="mt-1 text-xs text-gray-600">{{ $availPlan->description }}</p>
                    @unless ($isFree || $isGrandfathered)
                        <a href="{{ route('billing.checkout', ['planSlug' => $availPlan->slug, 'unlock' => $unlock]) }}" wire:navigate
                           class="{{ $availPlan->slug === 'full' ? 'btn-primary' : 'btn-secondary' }} mt-3 w-full justify-center">
                            {{ $isCurrent ? 'Change outlets or add-ons' : 'Choose '.$availPlan->name }}
                        </a>
                    @endunless
                </div>
            @endforeach
            <p class="help">HR &amp; Payroll (RM3 per employee, min 10) and Central Kitchen (RM300 per kitchen) go on either suite.</p>
        </div>
    </div>

    {{-- Invoice history. Drafts are excluded upstream — see Billing\Index::render(). --}}
    <div class="mt-8">
        <div class="flex items-end justify-between gap-3 mb-3">
            <div>
                <h2 class="text-sm font-semibold text-gray-800">Invoices</h2>
                <p class="text-xs text-gray-600 mt-0.5">Your billing history. Each one downloads as a PDF.</p>
            </div>
            @if ($amountDue > 0)
                <span class="badge-warning">
                    {{ $invoices->first()?->currency ?? 'MYR' }} {{ number_format($amountDue, 2) }} due
                </span>
            @endif
        </div>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table-surface min-w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left">Invoice</th>
                            <th class="px-4 py-3 text-left">Issued</th>
                            <th class="px-4 py-3 text-left">Period</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">PDF</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            <tr wire:key="cust-inv-{{ $invoice->id }}" class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $invoice->invoice_number }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $invoice->issued_at?->format('d M Y') ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">
                                    @if ($invoice->period_start && $invoice->period_end)
                                        {{ $invoice->period_start->format('d M Y') }} – {{ $invoice->period_end->format('d M Y') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-900">
                                    {{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="badge-{{ $invoice->statusColor() === 'gray' ? 'neutral' : $invoice->statusColor() }}">
                                        {{ $invoice->statusLabel() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('invoices.pdf', $invoice->id) }}" class="icon-btn ml-auto"
                                       title="Download {{ $invoice->invoice_number }}"
                                       aria-label="Download {{ $invoice->invoice_number }}">
                                        <x-icon name="download" size="h-4 w-4" />
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10">
                                    <div class="empty-state">
                                        <x-icon name="receipt" size="h-8 w-8" class="text-gray-400" />
                                        <p class="font-medium text-gray-700">No invoices yet</p>
                                        <p class="text-xs text-gray-600">
                                            An invoice appears here as soon as your first payment is taken.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
