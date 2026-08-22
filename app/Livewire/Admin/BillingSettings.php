<?php

namespace App\Livewire\Admin;

use App\Models\AppSetting;
use App\Services\ChipInService;
use App\Services\InvoiceService;
use Livewire\Component;

/**
 * The seller half of every invoice.
 *
 * These belong in settings rather than a Blade template because they are the
 * details that change without a deploy: a registration number issued after
 * launch, a new bank account, an office move. Compiling them into
 * pdf/subscription-invoice.blade.php would mean a code change to correct a
 * company address.
 */
class BillingSettings extends Component
{
    public string $seller_name = '';
    public string $seller_reg_no = '';
    public string $seller_tax_no = '';
    public string $seller_address = '';
    public string $seller_email = '';
    public string $seller_phone = '';
    public string $bank_details = '';
    public string $invoice_footer = '';
    public string $currency = 'MYR';
    public string $tax_rate = '0';
    public string $tax_label = 'SST';

    /** CHIP-IN webhook public key (PEM), from the merchant portal. */
    public string $chipin_webhook_public_key = '';

    public function mount(): void
    {
        $this->seller_name    = (string) AppSetting::get('billing_seller_name', 'Servora');
        $this->seller_reg_no  = (string) AppSetting::get('billing_seller_reg_no', '');
        $this->seller_tax_no  = (string) AppSetting::get('billing_seller_tax_no', '');
        $this->seller_address = (string) AppSetting::get('billing_seller_address', '');
        $this->seller_email   = (string) AppSetting::get('billing_seller_email', '');
        $this->seller_phone   = (string) AppSetting::get('billing_seller_phone', '');
        $this->bank_details   = (string) AppSetting::get('billing_bank_details', '');
        $this->invoice_footer = (string) AppSetting::get('billing_invoice_footer', '');
        $this->currency       = (string) AppSetting::get('billing_currency', 'MYR');
        $this->tax_rate       = (string) AppSetting::get('billing_tax_rate', '0');
        $this->tax_label      = (string) AppSetting::get('billing_tax_label', 'SST');

        $this->chipin_webhook_public_key = (string) AppSetting::get(
            'chipin_webhook_public_key',
            (string) config('chipin.webhook_public_key', '')
        );
    }

    public function save(): void
    {
        $this->validate([
            'seller_name'    => ['required', 'string', 'max:150'],
            'seller_reg_no'  => ['nullable', 'string', 'max:60'],
            'seller_tax_no'  => ['nullable', 'string', 'max:60'],
            'seller_address' => ['nullable', 'string', 'max:500'],
            'seller_email'   => ['nullable', 'email', 'max:150'],
            'seller_phone'   => ['nullable', 'string', 'max:40'],
            'bank_details'   => ['nullable', 'string', 'max:1000'],
            'invoice_footer' => ['nullable', 'string', 'max:500'],
            'currency'       => ['required', 'string', 'size:3'],
            'tax_rate'       => ['required', 'numeric', 'min:0', 'max:100'],
            'tax_label'      => ['nullable', 'string', 'max:30'],
            'chipin_webhook_public_key' => ['nullable', 'string', 'max:4000'],
        ]);

        /*
         * Reject a key OpenSSL cannot read, here rather than at 3am in a
         * webhook log — inside verifyWebhook() an unparseable key and a forged
         * signature are both just "false", and the endpoint would sit refusing
         * every genuine payment with no clue why.
         *
         * Checked BEFORE anything is written. Storing it first and validating
         * after would leave a broken key in the database behind an error
         * message, which is exactly how an integration ends up unverifiable.
         */
        if (filled($this->chipin_webhook_public_key)
            && ! ChipInService::isUsablePublicKey($this->chipin_webhook_public_key)) {
            $this->addError('chipin_webhook_public_key',
                'OpenSSL cannot read that as a public key. Copy the whole block from the CHIP-IN merchant portal, including the BEGIN and END lines.');

            return;
        }

        AppSetting::set(
            'chipin_webhook_public_key',
            filled($this->chipin_webhook_public_key) ? trim($this->chipin_webhook_public_key) : null
        );

        // Stored as null rather than '' so InvoiceService::sellerDetails() can
        // fall back on its defaults, and the PDF's @if guards actually skip
        // the empty lines instead of printing blank rows.
        $map = [
            'billing_seller_name'    => $this->seller_name,
            'billing_seller_reg_no'  => $this->seller_reg_no,
            'billing_seller_tax_no'  => $this->seller_tax_no,
            'billing_seller_address' => $this->seller_address,
            'billing_seller_email'   => $this->seller_email,
            'billing_seller_phone'   => $this->seller_phone,
            'billing_bank_details'   => $this->bank_details,
            'billing_invoice_footer' => $this->invoice_footer,
            'billing_currency'       => strtoupper($this->currency),
            'billing_tax_rate'       => $this->tax_rate,
            'billing_tax_label'      => $this->tax_label,
        ];

        foreach ($map as $key => $value) {
            AppSetting::set($key, filled($value) ? $value : null);
        }

        session()->flash('success', 'Billing details saved. New invoices will use them; invoices already raised keep the details they were issued with.');
    }

    public function render()
    {
        $chipIn = app(ChipInService::class);

        return view('livewire.admin.billing-settings', [
            'preview' => app(InvoiceService::class)->sellerDetails(),
            'gateway' => [
                'api_key'     => filled(config('chipin.api_key')),
                'brand_id'    => filled(config('chipin.brand_id')),
                'can_verify'  => $chipIn->canVerifyWebhooks(),
                'sandbox'     => (bool) config('chipin.sandbox'),
                'webhook_url' => route('webhooks.chipin'),
                // Flagged so a deployment still carrying the retired variable
                // is told rather than left assuming it does something.
                'legacy_secret' => filled(config('chipin.legacy_webhook_secret')),
            ],
        ])->layout('layouts.app', ['title' => 'Billing Settings']);
    }
}
