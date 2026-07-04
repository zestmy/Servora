<?php

namespace App\Livewire\Purchasing;

use App\Models\ProcurementInvoice;
use App\Models\ProcurementInvoicePayment;
use App\Services\ProcurementInvoiceService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class InvoiceShow extends Component
{
    public int $invoiceId;
    public ?ProcurementInvoice $invoice = null;

    // Record Payment modal
    public bool   $showPaymentModal = false;
    public string $pay_date      = '';
    public string $pay_amount    = '';
    public string $pay_method    = 'bank_transfer';
    public string $pay_reference = '';
    public string $pay_notes     = '';

    public function mount(int $id): void
    {
        $invoice = ProcurementInvoice::findOrFail($id);
        if ($invoice->outlet_id && ! Auth::user()->canAccessOutlet($invoice->outlet_id)) {
            abort(403, 'You do not have access to this outlet.');
        }
        $this->invoiceId = $id;
    }

    public function openPaymentModal(): void
    {
        if (! Auth::user()->hasCapability('can_manage_invoices')) {
            session()->flash('error', 'You do not have permission to manage invoice payments.');
            return;
        }

        $invoice = ProcurementInvoice::findOrFail($this->invoiceId);

        $this->pay_date      = now()->toDateString();
        $this->pay_amount    = (string) round($invoice->outstanding(), 2);
        $this->pay_method    = 'bank_transfer';
        $this->pay_reference = '';
        $this->pay_notes     = '';
        $this->resetErrorBag();
        $this->showPaymentModal = true;
    }

    public function recordPayment(): void
    {
        if (! Auth::user()->hasCapability('can_manage_invoices')) {
            session()->flash('error', 'You do not have permission to manage invoice payments.');
            return;
        }

        $this->validate([
            'pay_date'      => 'required|date|before_or_equal:today',
            'pay_amount'    => 'required|numeric|min:0.01',
            'pay_method'    => 'required|in:' . implode(',', array_keys(ProcurementInvoicePayment::METHODS)),
            'pay_reference' => 'nullable|string|max:255',
            'pay_notes'     => 'nullable|string|max:500',
        ], [], [
            'pay_date'   => 'payment date',
            'pay_amount' => 'amount',
            'pay_method' => 'payment method',
        ]);

        $invoice = ProcurementInvoice::findOrFail($this->invoiceId);

        try {
            ProcurementInvoiceService::recordPayment($invoice, [
                'payment_date' => $this->pay_date,
                'amount'       => floatval($this->pay_amount),
                'method'       => $this->pay_method,
                'reference'    => $this->pay_reference ?: null,
                'notes'        => $this->pay_notes ?: null,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->addError('pay_amount', $e->getMessage());
            return;
        }

        $this->showPaymentModal = false;
        session()->flash('success', 'Payment of RM ' . number_format(floatval($this->pay_amount), 2) . ' recorded.');
    }

    public function deletePayment(int $paymentId): void
    {
        if (! Auth::user()->hasCapability('can_manage_invoices')) {
            session()->flash('error', 'You do not have permission to manage invoice payments.');
            return;
        }

        $payment = ProcurementInvoicePayment::where('procurement_invoice_id', $this->invoiceId)->findOrFail($paymentId);
        ProcurementInvoiceService::removePayment($payment);
        session()->flash('success', 'Payment removed and invoice balance restored.');
    }

    public function markPaid(): void
    {
        if (! Auth::user()->hasCapability('can_manage_invoices')) {
            session()->flash('error', 'You do not have permission to manage invoice payments.');
            return;
        }
        $invoice = ProcurementInvoice::findOrFail($this->invoiceId);
        ProcurementInvoiceService::markPaid($invoice);
        session()->flash('success', "Invoice {$invoice->invoice_number} marked as paid.");
    }

    public function cancelInvoice(): void
    {
        if (! Auth::user()->hasCapability('can_manage_invoices')) {
            session()->flash('error', 'You do not have permission to cancel invoices.');
            return;
        }
        $invoice = ProcurementInvoice::findOrFail($this->invoiceId);
        ProcurementInvoiceService::cancel($invoice);
        session()->flash('success', "Invoice {$invoice->invoice_number} cancelled.");
    }

    public function render()
    {
        $this->invoice = ProcurementInvoice::with([
            'lines.ingredient',
            'lines.uom',
            'supplier',
            'outlet',
            'purchaseOrder',
            'goodsReceivedNote',
            'stockTransferOrder',
            'taxRate',
            'createdBy',
            'payments.recordedBy',
        ])->findOrFail($this->invoiceId);

        return view('livewire.purchasing.invoice-show')
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Invoice: ' . $this->invoice->invoice_number]);
    }
}
