<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Subscription invoicing — the platform billing its tenants.
 *
 * Not to be confused with ProcurementInvoiceService, which is a tenant
 * recording what its SUPPLIER billed IT. Money flows the other way here and
 * nothing is shared between them.
 *
 * Every method that writes a number takes the transaction with it:
 * Invoice::generateNumber() locks the year's last row, and a lock outside a
 * transaction is released the moment the SELECT returns, which is no lock at
 * all.
 */
class InvoiceService
{
    /** Days from issue to due, when the caller does not say. */
    public const DEFAULT_TERMS_DAYS = 14;

    // ── Creation ───────────────────────────────────────────────────────────

    /**
     * The invoice for a payment that has already succeeded. Called by the
     * CHIP-IN webhook, so it must be idempotent: the gateway retries, and a
     * retry that raises a second invoice for one payment puts a duplicate on
     * the customer's statement and double-counts the month's revenue.
     */
    public function createFromPayment(Payment $payment): Invoice
    {
        if ($existing = Invoice::where('payment_id', $payment->id)->first()) {
            return $existing;
        }

        $subscription = $payment->subscription;
        $plan         = $subscription?->plan;
        $company      = $payment->company;

        $amount = (float) $payment->amount;

        $description = $plan
            ? "Servora {$plan->name} Plan — " . ucfirst((string) $subscription->billing_cycle)
            : 'Servora subscription';

        return DB::transaction(function () use ($payment, $subscription, $company, $amount, $description) {
            return Invoice::create([
                'company_id'      => $payment->company_id,
                'payment_id'      => $payment->id,
                'subscription_id' => $subscription?->id,
                'invoice_number'  => Invoice::generateNumber(),
                'amount'          => $amount,
                'tax_rate'        => 0,
                'tax_amount'      => 0,
                'total'           => $amount,
                'currency'        => $payment->currency ?: $this->defaultCurrency(),
                'status'          => Invoice::STATUS_PAID,
                'issued_at'       => $payment->paid_at ?? now(),
                'period_start'    => $subscription?->current_period_start?->toDateString(),
                'period_end'      => $subscription?->current_period_end?->toDateString(),
                'paid_at'         => $payment->paid_at ?? now(),
                'line_items'      => [[
                    'description' => $description,
                    'quantity'    => 1,
                    'unit_price'  => $amount,
                    'amount'      => $amount,
                ]],
                'bill_to'         => $this->billingSnapshot($company),
            ]);
        });
    }

    /**
     * An invoice raised by hand from the admin dashboard — the renewal that
     * was paid by bank transfer, the annual upgrade agreed over the phone, the
     * credit for a month of downtime.
     *
     * @param  array<int, array{description: string, quantity: float, unit_price: float}>  $lines
     */
    public function createManual(
        Company $company,
        array $lines,
        array $attributes = [],
        ?User $author = null,
    ): Invoice {
        $totals = $this->totals($lines, (float) ($attributes['tax_rate'] ?? 0));
        $status = $attributes['status'] ?? Invoice::STATUS_DRAFT;

        $issuedAt = $attributes['issued_at'] ?? now();
        $issuedAt = $issuedAt instanceof Carbon ? $issuedAt : Carbon::parse($issuedAt);

        return DB::transaction(function () use ($company, $lines, $attributes, $author, $totals, $status, $issuedAt) {
            return Invoice::create([
                'company_id'      => $company->id,
                'subscription_id' => $attributes['subscription_id'] ?? null,
                // Numbered off the ISSUE year, not the calendar year: a
                // December invoice raised on 2 January belongs to December's
                // sequence, and an accountant reading INV-2027-0001 dated
                // 2026-12-28 has found a bug, not an invoice.
                'invoice_number'  => Invoice::generateNumber((int) $issuedAt->year),
                'amount'          => $totals['amount'],
                'tax_rate'        => $totals['tax_rate'],
                'tax_label'       => $attributes['tax_label'] ?? ($totals['tax_rate'] > 0 ? $this->defaultTaxLabel() : null),
                'tax_amount'      => $totals['tax_amount'],
                'total'           => $totals['total'],
                'currency'        => $attributes['currency'] ?? $this->defaultCurrency(),
                'status'          => $status,
                'issued_at'       => $status === Invoice::STATUS_DRAFT ? null : $issuedAt,
                'period_start'    => $attributes['period_start'] ?? null,
                'period_end'      => $attributes['period_end'] ?? null,
                'due_at'          => $attributes['due_at'] ?? $issuedAt->copy()->addDays(self::DEFAULT_TERMS_DAYS),
                'paid_at'         => $status === Invoice::STATUS_PAID ? ($attributes['paid_at'] ?? now()) : null,
                'line_items'      => $this->normaliseLines($lines),
                'notes'           => $attributes['notes'] ?? null,
                'bill_to'         => $this->billingSnapshot($company),
                'created_by'      => $author?->id,
            ]);
        });
    }

    /**
     * Rewrite a draft. Only a draft: once an invoice has been issued the
     * customer is holding a document with these numbers on it, and the fix for
     * a wrong issued invoice is to void it and raise another — which is what
     * the admin screen offers instead.
     */
    public function updateDraft(Invoice $invoice, array $lines, array $attributes = []): Invoice
    {
        if (! $invoice->isDraft()) {
            throw new \RuntimeException('Only a draft invoice can be edited. Void it and raise a new one instead.');
        }

        $totals = $this->totals($lines, (float) ($attributes['tax_rate'] ?? 0));

        $invoice->update([
            'subscription_id' => $attributes['subscription_id'] ?? null,
            'amount'          => $totals['amount'],
            'tax_rate'        => $totals['tax_rate'],
            'tax_label'       => $attributes['tax_label'] ?? ($totals['tax_rate'] > 0 ? $this->defaultTaxLabel() : null),
            'tax_amount'      => $totals['tax_amount'],
            'total'           => $totals['total'],
            'currency'        => $attributes['currency'] ?? $invoice->currency,
            'period_start'    => $attributes['period_start'] ?? null,
            'period_end'      => $attributes['period_end'] ?? null,
            'due_at'          => $attributes['due_at'] ?? $invoice->due_at,
            'line_items'      => $this->normaliseLines($lines),
            'notes'           => $attributes['notes'] ?? null,
        ]);

        // A stale PDF is worse than no PDF: it is a document with the old
        // numbers on it, reachable from the row that now says something else.
        $this->discardPdf($invoice);

        return $invoice->refresh();
    }

    // ── Lifecycle ──────────────────────────────────────────────────────────

    public function issue(Invoice $invoice, ?Carbon $issuedAt = null): Invoice
    {
        if (! $invoice->isDraft()) {
            return $invoice;
        }

        $issuedAt = $issuedAt ?? now();

        $invoice->update([
            'status'    => Invoice::STATUS_ISSUED,
            'issued_at' => $issuedAt,
            'due_at'    => $invoice->due_at ?? $issuedAt->copy()->addDays(self::DEFAULT_TERMS_DAYS),
        ]);

        return $invoice->refresh();
    }

    /**
     * Record settlement. The method and reference describe money that arrived
     * outside the gateway — a bank transfer, a cheque — and are kept as a
     * Payment row so the invoice, the payment history and the revenue figures
     * all agree about what was received.
     */
    public function markPaid(
        Invoice $invoice,
        ?Carbon $paidAt = null,
        string $method = 'manual',
        ?string $reference = null,
    ): Invoice {
        if ($invoice->isVoid()) {
            throw new \RuntimeException('A voided invoice cannot be marked paid.');
        }

        if ($invoice->isPaid()) {
            return $invoice;
        }

        $paidAt = $paidAt ?? now();

        return DB::transaction(function () use ($invoice, $paidAt, $method, $reference) {
            $payment = $invoice->payment;

            if (! $payment) {
                $payment = Payment::create([
                    'company_id'      => $invoice->company_id,
                    'subscription_id' => $invoice->subscription_id,
                    'amount'          => $invoice->total,
                    'currency'        => $invoice->currency,
                    'status'          => Payment::STATUS_COMPLETED,
                    'payment_method'  => $method,
                    'paid_at'         => $paidAt,
                    'metadata'        => array_filter([
                        'source'    => 'admin_manual',
                        'reference' => $reference,
                        'invoice'   => $invoice->invoice_number,
                    ]),
                ]);
            } else {
                $payment->update([
                    'status'  => Payment::STATUS_COMPLETED,
                    'paid_at' => $paidAt,
                ]);
            }

            $invoice->update([
                'status'     => Invoice::STATUS_PAID,
                'paid_at'    => $paidAt,
                'issued_at'  => $invoice->issued_at ?? $paidAt,
                'payment_id' => $payment->id,
            ]);

            return $invoice->refresh();
        });
    }

    /**
     * Void, never delete. An issued invoice number is a gap in the sequence if
     * the row disappears, and a gap is the thing an audit asks about.
     */
    public function void(Invoice $invoice, ?string $reason = null): Invoice
    {
        if ($invoice->isPaid()) {
            throw new \RuntimeException('A paid invoice cannot be voided. Refund the payment first.');
        }

        $invoice->update([
            'status'      => Invoice::STATUS_VOID,
            'voided_at'   => now(),
            'void_reason' => $reason,
        ]);

        return $invoice->refresh();
    }

    public function markSent(Invoice $invoice): Invoice
    {
        $invoice->update(['sent_at' => now()]);

        return $invoice->refresh();
    }

    // ── Money ──────────────────────────────────────────────────────────────

    /**
     * Totals from lines. Every amount is rounded to cents at the point it is
     * computed, because the stored columns are decimal(10,2) and a total that
     * disagrees with the sum of its rounded lines is the classic one-cent
     * complaint.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{amount: float, tax_rate: float, tax_amount: float, total: float}
     */
    public function totals(array $lines, float $taxRate = 0): array
    {
        $amount = 0.0;

        foreach ($this->normaliseLines($lines) as $line) {
            $amount += $line['amount'];
        }

        $amount    = round($amount, 2);
        $taxRate   = round(max(0, $taxRate), 2);
        $taxAmount = round($amount * $taxRate / 100, 2);

        return [
            'amount'     => $amount,
            'tax_rate'   => $taxRate,
            'tax_amount' => $taxAmount,
            'total'      => round($amount + $taxAmount, 2),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array{description: string, quantity: float, unit_price: float, amount: float}>
     */
    public function normaliseLines(array $lines): array
    {
        $out = [];

        foreach ($lines as $line) {
            $description = trim((string) ($line['description'] ?? ''));

            if ($description === '') {
                continue;
            }

            $quantity = (float) ($line['quantity'] ?? 1);

            // Round the unit price FIRST, then multiply. Multiplying the raw
            // figure and rounding after gives a line whose own numbers do not
            // add up on the printed page: 3 × 33.333 prints as "3 × 33.33 =
            // 100.00", and the customer who checks it makes 99.99. The price
            // shown is the price charged.
            $unitPrice = round((float) ($line['unit_price'] ?? 0), 2);

            $out[] = [
                'description' => $description,
                'quantity'    => $quantity,
                'unit_price'  => $unitPrice,
                'amount'      => round($quantity * $unitPrice, 2),
            ];
        }

        return $out;
    }

    /**
     * The line a subscription renewal bills, prefilled from the plan so the
     * admin does not retype a price that already exists in the product.
     *
     * @return array{description: string, quantity: float, unit_price: float}
     */
    public function lineForSubscription(Subscription $subscription): array
    {
        $plan  = $subscription->plan;
        $cycle = $subscription->billing_cycle === 'yearly' ? 'Yearly' : 'Monthly';

        return [
            'description' => $plan
                ? "Servora {$plan->name} Plan — {$cycle} subscription"
                : 'Servora subscription',
            'quantity'    => 1,
            'unit_price'  => round($subscription->currentPrice(), 2),
        ];
    }

    // ── Documents ──────────────────────────────────────────────────────────

    /**
     * Render the PDF. Streamed rather than stored: an invoice is a handful of
     * rows on one page, dompdf renders it in well under a second, and a stored
     * copy is one more thing that can disagree with the row after an edit.
     * The pdf_path column stays for invoices whose PDF was archived elsewhere.
     */
    public function pdf(Invoice $invoice)
    {
        $invoice->loadMissing(['company', 'subscription.plan', 'payment']);

        return Pdf::loadView('pdf.subscription-invoice', [
            'invoice' => $invoice,
            'seller'  => $this->sellerDetails(),
        ])->setPaper('a4', 'portrait');
    }

    public function filename(Invoice $invoice): string
    {
        return "{$invoice->invoice_number}.pdf";
    }

    private function discardPdf(Invoice $invoice): void
    {
        if (! $invoice->pdf_path) {
            return;
        }

        Storage::disk('public')->delete($invoice->pdf_path);
        $invoice->update(['pdf_path' => null]);
    }

    // ── Parties ────────────────────────────────────────────────────────────

    /**
     * Who the invoice is TO, frozen at the moment it is raised. Reading these
     * live off the company would rewrite the address on a document that has
     * already been sent.
     *
     * @return array<string, string|null>
     */
    public function billingSnapshot(?Company $company): array
    {
        if (! $company) {
            return [];
        }

        return array_filter([
            'name'                => $company->name,
            'registration_number' => $company->registration_number,
            'email'               => $company->email,
            'phone'               => $company->phone,
            'address'             => $company->billing_address ?: $company->address,
        ], fn ($value) => filled($value));
    }

    /**
     * Who the invoice is FROM. Editable from the admin billing settings so the
     * platform's own registration number, address and tax id are not compiled
     * into a Blade template.
     *
     * @return array<string, string|null>
     */
    public function sellerDetails(): array
    {
        return [
            'name'                => AppSetting::get('billing_seller_name', 'Servora'),
            'registration_number' => AppSetting::get('billing_seller_reg_no'),
            'tax_number'          => AppSetting::get('billing_seller_tax_no'),
            'address'             => AppSetting::get('billing_seller_address'),
            'email'               => AppSetting::get('billing_seller_email'),
            'phone'               => AppSetting::get('billing_seller_phone'),
            'bank_details'        => AppSetting::get('billing_bank_details'),
            'footer_note'         => AppSetting::get('billing_invoice_footer'),
        ];
    }

    public function defaultCurrency(): string
    {
        return (string) AppSetting::get('billing_currency', 'MYR');
    }

    public function defaultTaxRate(): float
    {
        return (float) AppSetting::get('billing_tax_rate', 0);
    }

    public function defaultTaxLabel(): string
    {
        return (string) AppSetting::get('billing_tax_label', 'SST');
    }
}
