<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="alert-success mb-4">{{ session('success') }}</div>
    @endif

    <x-page-header title="Billing settings" eyebrow="Admin"
                   subtitle="The seller details printed on every subscription invoice, and the defaults a new invoice starts with.">
        <x-slot:actions>
            <a href="{{ route('admin.invoices.index') }}" wire:navigate class="btn-secondary">Invoices</a>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save" class="max-w-3xl space-y-6">
        <div class="card p-6">
            <h2 class="text-sm font-semibold text-gray-800 mb-1">Issuer</h2>
            <p class="help mb-4">Printed in the top-left of the invoice PDF.</p>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="seller_name" value="Legal name *" />
                    <x-text-input id="seller_name" wire:model="seller_name" type="text" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('seller_name')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="seller_reg_no" value="Registration number" />
                    <x-text-input id="seller_reg_no" wire:model="seller_reg_no" type="text" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('seller_reg_no')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="seller_tax_no" value="Tax / SST number" />
                    <x-text-input id="seller_tax_no" wire:model="seller_tax_no" type="text" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('seller_tax_no')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="seller_email" value="Billing email" />
                    <x-text-input id="seller_email" wire:model="seller_email" type="email" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('seller_email')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="seller_phone" value="Phone" />
                    <x-text-input id="seller_phone" wire:model="seller_phone" type="text" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('seller_phone')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="seller_address" value="Address" />
                    <textarea id="seller_address" wire:model="seller_address" rows="2" class="input mt-1"></textarea>
                    <x-input-error :messages="$errors->get('seller_address')" class="mt-1" />
                </div>
            </div>
        </div>

        <div class="card p-6">
            <h2 class="text-sm font-semibold text-gray-800 mb-1">Invoice defaults</h2>
            <p class="help mb-4">What a new invoice starts with. Each invoice can still override them.</p>

            <div class="grid sm:grid-cols-3 gap-4">
                <div>
                    <x-input-label for="currency" value="Currency *" />
                    <x-text-input id="currency" wire:model="currency" type="text" maxlength="3" class="mt-1 block w-full uppercase" />
                    <x-input-error :messages="$errors->get('currency')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="tax_rate" value="Tax rate (%) *" />
                    <x-text-input id="tax_rate" wire:model="tax_rate" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('tax_rate')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="tax_label" value="Tax label" />
                    <x-text-input id="tax_label" wire:model="tax_label" type="text" class="mt-1 block w-full" placeholder="SST" />
                    <x-input-error :messages="$errors->get('tax_label')" class="mt-1" />
                </div>
            </div>

            <div class="mt-4">
                <x-input-label for="bank_details" value="Payment instructions" />
                <textarea id="bank_details" wire:model="bank_details" rows="3" class="input mt-1"
                          placeholder="Bank, account name, account number, reference to quote."></textarea>
                <p class="help mt-1">Printed only on invoices that are still unpaid — a paid invoice does not need paying again.</p>
                <x-input-error :messages="$errors->get('bank_details')" class="mt-1" />
            </div>

            <div class="mt-4">
                <x-input-label for="invoice_footer" value="Footer note" />
                <textarea id="invoice_footer" wire:model="invoice_footer" rows="2" class="input mt-1"
                          placeholder="This is a computer-generated invoice."></textarea>
                <x-input-error :messages="$errors->get('invoice_footer')" class="mt-1" />
            </div>
        </div>

        {{-- Payment gateway --}}
        <div class="card p-6">
            <h2 class="text-sm font-semibold text-gray-800 mb-1">Payment gateway (CHIP-IN)</h2>
            <p class="help mb-4">
                How a card payment becomes an active subscription. All four of these have to be
                right or a customer can pay and nothing happens at this end.
            </p>

            <dl class="grid sm:grid-cols-2 gap-3 mb-5">
                @foreach ([
                    ['API key',        $gateway['api_key'],    'Set in the environment. Without it, checkout refuses before it starts.'],
                    ['Brand ID',       $gateway['brand_id'],   'Set in the environment.'],
                    ['Webhook key',    $gateway['can_verify'], 'Needed to trust a callback. Without it, every payment is refused.'],
                    ['Live mode',      ! $gateway['sandbox'],  'Sandbox takes test cards only.'],
                ] as [$label, $ok, $note])
                    <div class="flex items-start gap-2.5 rounded-surface border {{ $ok ? 'border-success-200 bg-success-50' : 'border-warning-200 bg-warning-50' }} p-3">
                        <x-icon :name="$ok ? 'check' : 'warning'" size="h-4 w-4"
                                class="mt-0.5 flex-none {{ $ok ? 'text-success-700' : 'text-warning-700' }}" />
                        <div class="min-w-0">
                            <dt class="text-xs font-semibold {{ $ok ? 'text-success-800' : 'text-warning-800' }}">
                                {{ $label }} — {{ $ok ? 'configured' : 'not set' }}
                            </dt>
                            <dd class="text-[11px] leading-snug {{ $ok ? 'text-success-800/80' : 'text-warning-800/80' }}">{{ $note }}</dd>
                        </div>
                    </div>
                @endforeach
            </dl>

            @if ($gateway['legacy_secret'])
                <div class="alert-warning mb-4 text-xs">
                    <div>
                        <p class="font-medium">CHIPIN_WEBHOOK_SECRET is still set in the environment and does nothing.</p>
                        <p class="mt-0.5">
                            CHIP signs callbacks with a public key, not a shared secret. That variable fed an
                            HMAC check that could never match a real signature — which is why payments were
                            being refused. Remove it and set the public key below instead.
                        </p>
                    </div>
                </div>
            @endif

            <div>
                <x-input-label for="chipin_webhook_public_key" value="Webhook public key" />
                <textarea id="chipin_webhook_public_key" wire:model="chipin_webhook_public_key" rows="6"
                          class="input mt-1 font-mono text-xs"
                          placeholder="-----BEGIN PUBLIC KEY-----&#10;MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8A...&#10;-----END PUBLIC KEY-----"></textarea>
                <p class="help mt-1">
                    From the CHIP-IN merchant portal, on the webhook you created for
                    <code>{{ $gateway['webhook_url'] }}</code>. Paste the whole block. It is checked when
                    you save, so a key OpenSSL cannot read is refused here rather than silently failing
                    every callback.
                </p>
                <x-input-error :messages="$errors->get('chipin_webhook_public_key')" class="mt-1" />
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn-primary">Save billing settings</button>
        </div>
    </form>
</div>
