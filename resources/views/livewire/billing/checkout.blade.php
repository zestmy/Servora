<div class="mx-auto max-w-5xl">
    <x-page-header :title="$selectedPlan->name.' suite'" eyebrow="Checkout"
                   subtitle="Priced per outlet. Pick your add-ons, see the total, pay with FPX, card or e-wallet.">
        <x-slot:actions>
            <a href="{{ route('billing.index') }}" wire:navigate class="btn-ghost">Back to billing</a>
        </x-slot:actions>
    </x-page-header>

    @if ($blocked)
        <div class="alert-warning mb-6" role="status">{{ $blocked }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        {{-- ── Choices ───────────────────────────────────────────────────── --}}
        <div class="space-y-6">
            <section class="card p-5">
                <h2 class="text-sm font-semibold text-gray-900">Billing</h2>
                <div class="seg mt-3 w-full sm:w-auto" role="radiogroup" aria-label="Billing cycle">
                    <button type="button" role="radio" wire:click="$set('billing_cycle', 'monthly')"
                            aria-checked="{{ $billing_cycle === 'monthly' ? 'true' : 'false' }}"
                            class="seg-item {{ $billing_cycle === 'monthly' ? 'seg-item-on' : '' }}">Monthly</button>
                    <button type="button" role="radio" wire:click="$set('billing_cycle', 'yearly')"
                            aria-checked="{{ $billing_cycle === 'yearly' ? 'true' : 'false' }}"
                            class="seg-item {{ $billing_cycle === 'yearly' ? 'seg-item-on' : '' }}">Yearly — 2 months free</button>
                </div>

                <label for="outlets" class="label mt-5 block">Outlets</label>
                <input id="outlets" type="number" min="{{ $minOutlets }}" max="19" wire:model.live.debounce.300ms="outlets"
                       class="input mt-1 w-32">
                <p class="help">You have {{ $minOutlets }} active {{ Str::plural('outlet', $minOutlets) }}. 10% off outlets 6–10, 15% off 11–19. 20 or more is quoted.</p>
            </section>

            <section class="card p-5">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 class="text-sm font-semibold text-gray-900">Add-ons</h2>
                    @unless ($isFull)
                        <span class="text-xs text-gray-600">Up to {{ $cap }} on Basic</span>
                    @endunless
                </div>

                @if ($isFull)
                    <p class="help mt-1">Full includes all six: Labels, Learn SOP, Audits, Assets, POS Sync and AI Insights.</p>
                @else
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach ($catalogue as $key => $m)
                            @continue($m['kind'] !== 'addon')
                            <label wire:key="addon-{{ $key }}"
                                   class="flex min-h-[44px] cursor-pointer items-center gap-3 rounded-control border border-gray-200 px-3">
                                <input type="checkbox" wire:model.live="addons.{{ $key }}" class="rounded border-gray-300 text-brand-600">
                                <span class="flex-1 text-sm text-gray-900">{{ $m['name'] }}</span>
                                <span class="text-sm tabular-nums text-gray-600">RM{{ $m['price'] }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif

                <div class="mt-5 space-y-3 border-t border-gray-100 pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">On either suite</p>

                    <div class="flex flex-wrap items-center gap-3">
                        <label class="flex min-h-[44px] flex-1 cursor-pointer items-center gap-3">
                            <input type="checkbox" wire:model.live="hr" class="rounded border-gray-300 text-brand-600">
                            <span class="text-sm text-gray-900">HR &amp; Payroll <span class="text-gray-600">— RM3 per employee, min 10</span></span>
                        </label>
                        @if ($hr)
                            <label class="flex items-center gap-2 text-sm text-gray-600">
                                <input type="number" min="1" wire:model.live.debounce.300ms="hrEmployees" class="input w-24" aria-label="Employees">
                                employees
                            </label>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <label class="flex min-h-[44px] flex-1 cursor-pointer items-center gap-3">
                            <input type="checkbox" wire:model.live="kitchen" class="rounded border-gray-300 text-brand-600">
                            <span class="text-sm text-gray-900">Central Kitchen <span class="text-gray-600">— RM300 per kitchen</span></span>
                        </label>
                        @if ($kitchen)
                            <label class="flex items-center gap-2 text-sm text-gray-600">
                                <input type="number" min="1" wire:model.live.debounce.300ms="kitchens" class="input w-24" aria-label="Kitchens">
                                kitchens
                            </label>
                        @endif
                    </div>
                </div>
            </section>
        </div>

        {{-- ── Summary ───────────────────────────────────────────────────── --}}
        <aside class="panel h-fit p-5 lg:sticky lg:top-20" aria-live="polite">
            <h2 class="text-sm font-semibold text-gray-900">Summary</h2>

            @if (! $quote->ok())
                <p class="alert-warning mt-4 text-sm">{{ $quote->error }}</p>
            @else
                <dl class="mt-4 space-y-2 text-sm">
                    @foreach ($quote->lines as $line)
                        <div class="flex justify-between gap-3">
                            <dt class="min-w-0">
                                <span class="text-gray-900">{{ $line['label'] }}</span>
                                <span class="block text-xs text-gray-600">{{ $line['detail'] }}</span>
                            </dt>
                            <dd class="tabular-nums {{ $line['amount'] < 0 ? 'text-success-700' : 'text-gray-900' }}">
                                {{ $line['amount'] < 0 ? '−' : '' }}RM{{ number_format(abs($line['amount']), 2) }}
                            </dd>
                        </div>
                    @endforeach
                </dl>

                <div class="mt-4 space-y-2 border-t border-gray-100 pt-4 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Per month</span>
                        <span class="tabular-nums text-gray-900">RM{{ number_format($quote->monthly, 2) }}</span>
                    </div>
                    @if ($quote->cycle === 'yearly')
                        <div class="flex justify-between">
                            <span class="text-gray-600">Per year (10 months)</span>
                            <span class="tabular-nums text-gray-900">RM{{ number_format($quote->cycleTotal, 2) }}</span>
                        </div>
                    @endif
                    @if ($quote->credit > 0)
                        <div class="flex justify-between">
                            <span class="text-gray-600">Credit for unused time</span>
                            <span class="tabular-nums text-success-700">−RM{{ number_format($quote->credit, 2) }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between pt-2 text-base font-semibold">
                        <span class="text-gray-900">Due today</span>
                        <span class="tabular-nums text-gray-900">RM{{ number_format($quote->due(), 2) }}</span>
                    </div>
                </div>

                @error('checkout') <p class="error-text mt-3">{{ $message }}</p> @enderror

                <button type="button" wire:click="pay" wire:loading.attr="disabled"
                        class="btn-primary mt-5 w-full justify-center" @disabled($blocked)>
                    <span wire:loading.remove wire:target="pay">
                        {{ $quote->due() > 0 ? 'Pay RM'.number_format($quote->due(), 2) : 'Switch at next renewal' }}
                    </span>
                    <span wire:loading wire:target="pay">Starting payment…</span>
                </button>
                <p class="help mt-2">
                    @if ($quote->due() > 0)
                        Your new plan starts as soon as the payment clears. Renews {{ $quote->cycle === 'yearly' ? 'yearly' : 'monthly' }} at RM{{ number_format($quote->cycleTotal, 2) }}.
                    @else
                        What you have already paid covers this, so the change starts when your current period ends.
                    @endif
                </p>
            @endif
        </aside>
    </div>
</div>
