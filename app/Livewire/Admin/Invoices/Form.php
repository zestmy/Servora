<?php

namespace App\Livewire\Admin\Invoices;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Raise or amend a subscription invoice.
 *
 * Amend means DRAFT only. Anything issued is a document the customer already
 * has, so the screen switches to read-only and points at void-and-reissue —
 * the model enforces the same rule in InvoiceService::updateDraft().
 */
class Form extends Component
{
    public ?int $invoiceId = null;

    public ?int    $company_id = null;
    public ?int    $subscription_id = null;
    public string  $issued_at = '';
    public string  $due_at = '';
    public string  $period_start = '';
    public string  $period_end = '';
    public string  $currency = 'MYR';
    public string  $tax_rate = '0';
    public string  $tax_label = '';
    public string  $notes = '';
    public bool    $issueNow = false;

    /** @var array<int, array{description: string, quantity: string, unit_price: string}> */
    public array $lines = [];

    public function mount(?int $id = null): void
    {
        $service = app(InvoiceService::class);

        if ($id) {
            $invoice = Invoice::findOrFail($id);

            if (! $invoice->isDraft()) {
                session()->flash('error', "{$invoice->invoice_number} has been issued and can no longer be edited. Void it and raise a new one.");
                $this->redirectRoute('admin.invoices.index', navigate: true);

                return;
            }

            $this->invoiceId       = $invoice->id;
            $this->company_id      = $invoice->company_id;
            $this->subscription_id = $invoice->subscription_id;
            $this->issued_at       = ($invoice->issued_at ?? now())->format('Y-m-d');
            $this->due_at          = $invoice->due_at?->format('Y-m-d') ?? '';
            $this->period_start    = $invoice->period_start?->format('Y-m-d') ?? '';
            $this->period_end      = $invoice->period_end?->format('Y-m-d') ?? '';
            $this->currency        = $invoice->currency ?: $service->defaultCurrency();
            $this->tax_rate        = (string) (float) $invoice->tax_rate;
            $this->tax_label       = $invoice->tax_label ?? '';
            $this->notes           = $invoice->notes ?? '';

            $this->lines = array_map(fn ($line) => [
                'description' => $line['description'] ?? '',
                'quantity'    => (string) ($line['quantity'] ?? 1),
                'unit_price'  => (string) ($line['unit_price'] ?? 0),
            ], $invoice->line_items ?? []);
        } else {
            $this->issued_at = now()->format('Y-m-d');
            $this->due_at    = now()->addDays(InvoiceService::DEFAULT_TERMS_DAYS)->format('Y-m-d');
            $this->currency  = $service->defaultCurrency();
            $this->tax_rate  = (string) $service->defaultTaxRate();
            $this->tax_label = $service->defaultTaxLabel();
        }

        if ($this->lines === []) {
            $this->addLine();
        }
    }

    /**
     * Picking a company offers its live subscription and prefills the renewal
     * line off the plan price. The admin can still overwrite everything — this
     * is a starting point, not a calculation.
     */
    public function updatedCompanyId(): void
    {
        $this->subscription_id = null;

        $subscription = $this->companySubscriptions()->first();

        if ($subscription) {
            $this->subscription_id = $subscription->id;
            $this->applySubscription($subscription);
        }
    }

    public function updatedSubscriptionId($value): void
    {
        if (! $value) {
            return;
        }

        $subscription = Subscription::with('plan')->find($value);

        if ($subscription) {
            $this->applySubscription($subscription);
        }
    }

    private function applySubscription(Subscription $subscription): void
    {
        $line = app(InvoiceService::class)->lineForSubscription($subscription);

        // Only prefill an empty sheet. Overwriting lines the admin has already
        // typed because they changed the plan dropdown is data loss.
        if ($this->hasOnlyBlankLines()) {
            $this->lines = [[
                'description' => $line['description'],
                'quantity'    => (string) $line['quantity'],
                'unit_price'  => (string) $line['unit_price'],
            ]];
        }

        if ($this->period_start === '' && $subscription->current_period_start) {
            $this->period_start = $subscription->current_period_start->format('Y-m-d');
        }

        if ($this->period_end === '' && $subscription->current_period_end) {
            $this->period_end = $subscription->current_period_end->format('Y-m-d');
        }
    }

    private function hasOnlyBlankLines(): bool
    {
        foreach ($this->lines as $line) {
            if (trim((string) ($line['description'] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    public function addLine(): void
    {
        $this->lines[] = ['description' => '', 'quantity' => '1', 'unit_price' => '0.00'];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);

        if ($this->lines === []) {
            $this->addLine();
        }
    }

    protected function rules(): array
    {
        return [
            'company_id'          => ['required', Rule::exists('companies', 'id')],
            'subscription_id'     => ['nullable', Rule::exists('subscriptions', 'id')],
            'issued_at'           => ['required', 'date'],
            'due_at'              => ['nullable', 'date', 'after_or_equal:issued_at'],
            'period_start'        => ['nullable', 'date'],
            'period_end'          => ['nullable', 'date', 'after_or_equal:period_start'],
            'currency'            => ['required', 'string', 'size:3'],
            'tax_rate'            => ['required', 'numeric', 'min:0', 'max:100'],
            'tax_label'           => ['nullable', 'string', 'max:30'],
            'notes'               => ['nullable', 'string', 'max:2000'],
            'lines'               => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:300'],
            'lines.*.quantity'    => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price'  => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'lines.*.description.required' => 'Every line needs a description.',
            'lines.*.quantity.required'    => 'Every line needs a quantity.',
            'lines.*.unit_price.required'  => 'Every line needs a unit price.',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $service = app(InvoiceService::class);
        $company = Company::findOrFail($this->company_id);

        $attributes = [
            'subscription_id' => $this->subscription_id,
            'issued_at'       => Carbon::parse($this->issued_at),
            'due_at'          => $this->due_at ? Carbon::parse($this->due_at)->endOfDay() : null,
            'period_start'    => $this->period_start ?: null,
            'period_end'      => $this->period_end ?: null,
            'currency'        => strtoupper($this->currency),
            'tax_rate'        => (float) $this->tax_rate,
            'tax_label'       => $this->tax_label ?: null,
            'notes'           => $this->notes ?: null,
            'status'          => $this->issueNow ? Invoice::STATUS_ISSUED : Invoice::STATUS_DRAFT,
        ];

        if ($this->invoiceId) {
            $invoice = Invoice::findOrFail($this->invoiceId);
            $service->updateDraft($invoice, $this->lines, $attributes);

            if ($this->issueNow) {
                $service->issue($invoice, Carbon::parse($this->issued_at));
            }

            session()->flash('success', "{$invoice->invoice_number} saved.");
        } else {
            $invoice = $service->createManual($company, $this->lines, $attributes, Auth::user());
            session()->flash('success', "{$invoice->invoice_number} created for {$company->name}.");
        }

        $this->redirectRoute('admin.invoices.index', navigate: true);
    }

    /** Live subscriptions plus history, so an invoice can be tied to a lapsed one. */
    private function companySubscriptions()
    {
        if (! $this->company_id) {
            return collect();
        }

        return Subscription::with('plan')
            ->where('company_id', $this->company_id)
            ->orderByRaw("CASE WHEN status IN ('active', 'trialing', 'past_due') THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->get();
    }

    public function render()
    {
        $totals = app(InvoiceService::class)->totals($this->lines, (float) $this->tax_rate);

        return view('livewire.admin.invoices.form', [
            'companies'     => Company::orderBy('name')->get(['id', 'name']),
            'subscriptions' => $this->companySubscriptions(),
            'totals'        => $totals,
        ])->layout('layouts.app', ['title' => $this->invoiceId ? 'Edit Invoice' : 'New Invoice']);
    }
}
