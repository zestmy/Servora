<?php

namespace App\Livewire\Purchasing;

use App\Models\ProcurementInvoice;
use App\Services\ProcurementInvoiceService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class InvoiceShow extends Component
{
    public int $invoiceId;
    public ?ProcurementInvoice $invoice = null;

    public function mount(int $id): void
    {
        $invoice = ProcurementInvoice::findOrFail($id);
        if ($invoice->outlet_id && ! Auth::user()->canAccessOutlet($invoice->outlet_id)) {
            abort(403, 'You do not have access to this outlet.');
        }
        $this->invoiceId = $id;
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
        ])->findOrFail($this->invoiceId);

        return view('livewire.purchasing.invoice-show')
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Invoice: ' . $this->invoice->invoice_number]);
    }
}
