<div>
    <x-page-header :title="$invoiceId ? 'Edit draft invoice' : 'New invoice'" eyebrow="Admin · Invoices"
                   subtitle="Bill a customer company for a subscription period, an upgrade, or anything agreed off-gateway.">
        <x-slot:actions>
            <a href="{{ route('admin.invoices.index') }}" wire:navigate class="btn-secondary">Back to invoices</a>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save" class="max-w-4xl space-y-6">
        {{-- Who and when --}}
        <div class="card p-6">
            <h2 class="text-sm font-semibold text-gray-800 mb-4">Customer</h2>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="company_id" value="Company *" />
                    <select id="company_id" wire:model.live="company_id" class="input mt-1">
                        <option value="">Select company…</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('company_id')" class="mt-1" />
                    <p class="help mt-1">The billing name and address are copied onto the invoice when it is saved.</p>
                </div>

                <div>
                    <x-input-label for="subscription_id" value="Subscription" />
                    {{-- wire:key keyed on the company, so choosing one REPLACES this
                         select rather than morphing it. Morphing loses the selection:
                         updatedCompanyId() picks the live subscription and adds its
                         option in the same round trip, and a browser cannot apply a
                         value to an <option> that did not exist when the value was
                         set — the field came back blank and the invoice saved with no
                         subscription attached. --}}
                    <select id="subscription_id" wire:key="sub-select-{{ $company_id ?? 'none' }}"
                            wire:model.live="subscription_id" class="input mt-1"
                            @disabled(! $company_id)>
                        <option value="">Not tied to a subscription</option>
                        @foreach ($subscriptions as $subscription)
                            <option value="{{ $subscription->id }}">{{ $subscription->plan?->name ?? 'No plan' }} · {{ ucfirst($subscription->billing_cycle ?? 'monthly') }} · {{ $subscription->statusLabel() }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('subscription_id')" class="mt-1" />
                    <p class="help mt-1">Picking one prefills the line and the service period from the plan.</p>
                </div>
            </div>

            <div class="grid sm:grid-cols-4 gap-4 mt-4">
                <div>
                    <x-input-label for="issued_at" value="Invoice date *" />
                    <x-text-input id="issued_at" wire:model="issued_at" type="date" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('issued_at')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="due_at" value="Due date" />
                    <x-text-input id="due_at" wire:model="due_at" type="date" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('due_at')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="period_start" value="Period from" />
                    <x-text-input id="period_start" wire:model="period_start" type="date" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('period_start')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="period_end" value="Period to" />
                    <x-text-input id="period_end" wire:model="period_end" type="date" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('period_end')" class="mt-1" />
                </div>
            </div>
        </div>

        {{-- Lines --}}
        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold text-gray-800">Lines</h2>
                <button type="button" wire:click="addLine" class="btn-secondary py-1.5 px-3 text-xs">+ Add line</button>
            </div>

            <div class="space-y-3">
                @foreach ($lines as $index => $line)
                    <div wire:key="line-{{ $index }}" class="grid grid-cols-12 gap-3 items-start">
                        <div class="col-span-12 sm:col-span-6">
                            <input type="text" wire:model.blur="lines.{{ $index }}.description" class="input"
                                   placeholder="Description" aria-label="Line {{ $index + 1 }} description" />
                            <x-input-error :messages="$errors->get('lines.' . $index . '.description')" class="mt-1" />
                        </div>
                        <div class="col-span-4 sm:col-span-2">
                            <input type="number" step="0.01" min="0.01" wire:model.live.debounce.400ms="lines.{{ $index }}.quantity"
                                   class="input text-right tabular-nums" aria-label="Line {{ $index + 1 }} quantity" />
                            <x-input-error :messages="$errors->get('lines.' . $index . '.quantity')" class="mt-1" />
                        </div>
                        <div class="col-span-5 sm:col-span-3">
                            <input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="lines.{{ $index }}.unit_price"
                                   class="input text-right tabular-nums" aria-label="Line {{ $index + 1 }} unit price" />
                            <x-input-error :messages="$errors->get('lines.' . $index . '.unit_price')" class="mt-1" />
                        </div>
                        <div class="col-span-3 sm:col-span-1 flex justify-end pt-0.5">
                            <button type="button" wire:click="removeLine({{ $index }})"
                                    class="icon-btn icon-btn-danger" aria-label="Remove line {{ $index + 1 }}">
                                <x-icon name="trash" size="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="grid sm:grid-cols-3 gap-4 mt-6 pt-6 border-t border-gray-200">
                <div>
                    <x-input-label for="currency" value="Currency *" />
                    <x-text-input id="currency" wire:model="currency" type="text" maxlength="3"
                                  class="mt-1 block w-full uppercase" />
                    <x-input-error :messages="$errors->get('currency')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="tax_rate" value="Tax rate (%)" />
                    <x-text-input id="tax_rate" wire:model.live.debounce.400ms="tax_rate" type="number" step="0.01"
                                  min="0" max="100" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('tax_rate')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="tax_label" value="Tax label" />
                    <x-text-input id="tax_label" wire:model="tax_label" type="text" class="mt-1 block w-full"
                                  placeholder="SST" />
                    <x-input-error :messages="$errors->get('tax_label')" class="mt-1" />
                </div>
            </div>

            {{-- Totals mirror InvoiceService::totals() exactly — same rounding,
                 same order — so what the admin sees here is what gets stored. --}}
            <div class="mt-6 ml-auto max-w-xs space-y-1 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-600">Subtotal</span>
                    <span class="tabular-nums">{{ strtoupper($currency) }} {{ number_format($totals['amount'], 2) }}</span>
                </div>
                @if ($totals['tax_amount'] > 0)
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ $tax_label ?: 'Tax' }} ({{ rtrim(rtrim(number_format($totals['tax_rate'], 2), '0'), '.') }}%)</span>
                        <span class="tabular-nums">{{ strtoupper($currency) }} {{ number_format($totals['tax_amount'], 2) }}</span>
                    </div>
                @endif
                <div class="flex justify-between font-semibold text-gray-900 border-t border-gray-200 pt-1">
                    <span>Total</span>
                    <span class="tabular-nums">{{ strtoupper($currency) }} {{ number_format($totals['total'], 2) }}</span>
                </div>
            </div>
        </div>

        {{-- Notes & issue --}}
        <div class="card p-6">
            <x-input-label for="notes" value="Notes" />
            <textarea id="notes" wire:model="notes" rows="3" class="input mt-1"
                      placeholder="Anything the customer should read on the invoice."></textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-1" />

            <label class="mt-4 inline-flex items-start gap-2 cursor-pointer">
                <input type="checkbox" wire:model="issueNow"
                       class="mt-0.5 rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500" />
                <span class="text-sm text-gray-700">
                    <span class="font-medium">Issue immediately</span>
                    <span class="block text-xs text-gray-600">
                        An issued invoice is visible to the customer on their billing page and can no
                        longer be edited — only voided.
                    </span>
                </span>
            </label>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('admin.invoices.index') }}" wire:navigate class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">
                {{ $invoiceId ? 'Save draft' : 'Create invoice' }}
            </button>
        </div>
    </form>
</div>
