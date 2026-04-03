<?php

namespace App\Livewire\Purchasing;

use App\Models\ProcurementInvoice;
use App\Services\ProcurementInvoiceService;
use Livewire\Component;

class InvoiceShow extends Component
{
    public int $invoiceId;
    public ?ProcurementInvoice $invoice = null;

    public function mount(int $id): void
    {
        $this->invoiceId = $id;
    }

    public function markPaid(): void
    {
        $invoice = ProcurementInvoice::findOrFail($this->invoiceId);
        ProcurementInvoiceService::markPaid($invoice);
        session()->flash('success', "Invoice {$invoice->invoice_number} marked as paid.");
    }

    public function cancelInvoice(): void
    {
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
