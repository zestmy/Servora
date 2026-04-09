<?php

namespace App\Livewire\Purchasing;

use App\Models\ProcurementInvoice;
use App\Models\Supplier;
use App\Services\ProcurementInvoiceService;
use App\Traits\ScopesToActiveOutlet;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class InvoiceIndex extends Component
{
    use WithPagination, ScopesToActiveOutlet;

    public string $search       = '';
    public string $typeFilter   = '';
    public string $statusFilter = '';
    public string $dateFrom     = '';
    public string $dateTo       = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedTypeFilter(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedDateFrom(): void { $this->resetPage(); }
    public function updatedDateTo(): void { $this->resetPage(); }

    public function markPaid(int $id): void
    {
        if (! Auth::user()->hasCapability('can_manage_invoices')) {
            session()->flash('error', 'You do not have permission to manage invoice payments.');
            return;
        }
        $invoice = ProcurementInvoice::findOrFail($id);
        ProcurementInvoiceService::markPaid($invoice);
        session()->flash('success', "Invoice {$invoice->invoice_number} marked as paid.");
    }

    public function cancelInvoice(int $id): void
    {
        if (! Auth::user()->hasCapability('can_manage_invoices')) {
            session()->flash('error', 'You do not have permission to cancel invoices.');
            return;
        }
        $invoice = ProcurementInvoice::findOrFail($id);
        ProcurementInvoiceService::cancel($invoice);
        session()->flash('success', "Invoice {$invoice->invoice_number} cancelled.");
    }

    public function render()
    {
        $query = ProcurementInvoice::with(['outlet', 'supplier', 'stockTransferOrder'])
            ->withCount('lines');

        if ($this->search) {
            $query->where('invoice_number', 'like', '%' . $this->search . '%');
        }
        if ($this->typeFilter) {
            $query->where('type', $this->typeFilter);
        }
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }
        if ($this->dateFrom) {
            $query->where('issued_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->where('issued_date', '<=', $this->dateTo);
        }

        $invoices = $query->orderByDesc('issued_date')->orderByDesc('id')->paginate(15);

        $stats = [
            ['label' => 'Total Outstanding', 'value' => number_format(ProcurementInvoice::whereIn('status', ['issued', 'overdue'])->sum('total_amount'), 2), 'color' => 'yellow'],
            ['label' => 'Issued', 'value' => ProcurementInvoice::where('status', 'issued')->count(), 'color' => 'blue'],
            ['label' => 'Paid', 'value' => ProcurementInvoice::where('status', 'paid')->count(), 'color' => 'green'],
        ];

        return view('livewire.purchasing.invoice-index', compact('invoices', 'stats'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Procurement Invoices']);
    }
}
