<?php

namespace App\Livewire\Reports\Order;

use App\Models\ProcurementInvoice;
use App\Traits\ReportFilters;
use App\Traits\ScopesToActiveOutlet;
use Livewire\Component;
use Livewire\WithPagination;

class InvoiceSummary extends Component
{
    use WithPagination, ReportFilters, ScopesToActiveOutlet;

    public string $statusFilter = '';
    public string $typeFilter = '';

    public function mount(): void
    {
        $this->mountReportFilters();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function exportCsv()
    {
        $rows = $this->buildQuery()->get();

        return $this->exportCsvDownload('invoice-summary.csv', [
            'Invoice Number', 'Type', 'Outlet/Supplier', 'Issued Date', 'Due Date', 'Total Amount', 'Status',
        ], $rows->map(fn ($inv) => [
            $inv->invoice_number,
            $inv->type === 'cpu_to_outlet' ? 'CPU to Outlet' : 'Supplier',
            $inv->type === 'cpu_to_outlet' ? ($inv->outlet?->name ?? '-') : ($inv->supplier?->name ?? '-'),
            $inv->issued_date?->format('d M Y'),
            $inv->due_date?->format('d M Y'),
            number_format((float) $inv->total_amount, 2),
            ucfirst($inv->status),
        ])->toArray());
    }

    public function render()
    {
        $query = $this->buildQuery();

        $invoices = $query->paginate(25);

        // Summary stats from unfiltered-by-pagination query
        $statsQuery = $this->buildQuery();
        $totalOutstanding = (clone $statsQuery)->where('status', 'pending')->sum('total_amount');
        $totalPaid        = (clone $statsQuery)->where('status', 'paid')->sum('total_amount');
        $totalOverdue     = (clone $statsQuery)->where('status', '!=', 'paid')
            ->where('due_date', '<', now()->toDateString())->sum('total_amount');

        return view('livewire.reports.order.invoice-summary', [
            'invoices'         => $invoices,
            'totalOutstanding' => $totalOutstanding,
            'totalPaid'        => $totalPaid,
            'totalOverdue'     => $totalOverdue,
            'outlets'          => $this->getOutlets(),
            'suppliers'        => $this->getSuppliers(),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Invoice Summary']);
    }

    private function buildQuery()
    {
        $query = ProcurementInvoice::with(['outlet', 'supplier'])
            ->whereBetween('issued_date', [$this->dateFrom, $this->dateTo]);

        $this->scopeByOutlet($query);

        if ($this->outletFilter) {
            $query->where('outlet_id', $this->outletFilter);
        }

        if ($this->typeFilter !== '') {
            $query->where('type', $this->typeFilter);
        }

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        return $query->orderByDesc('issued_date');
    }
}
