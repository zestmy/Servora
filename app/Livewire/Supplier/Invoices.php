<?php

namespace App\Livewire\Supplier;

use App\Models\ProcurementInvoice;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Invoices extends Component
{
    use WithPagination;

    public string $statusFilter = '';

    public function updatedStatusFilter(): void { $this->resetPage(); }

    public function render()
    {
        $supplierId = Auth::guard('supplier')->user()->supplier_id;

        $query = ProcurementInvoice::withoutGlobalScopes()
            ->where('supplier_id', $supplierId)
            ->where('type', 'supplier')
            ->withCount('lines');

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $invoices = $query->orderByDesc('issued_date')->paginate(15);

        $totalOutstanding = ProcurementInvoice::withoutGlobalScopes()
            ->where('supplier_id', $supplierId)
            ->whereIn('status', ['issued', 'overdue'])
            ->sum('total_amount');

        return view('livewire.supplier.invoices', compact('invoices', 'totalOutstanding'))
            ->layout('layouts.supplier', ['title' => 'Invoices']);
    }
}
