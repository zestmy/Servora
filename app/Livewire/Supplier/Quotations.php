<?php

namespace App\Livewire\Supplier;

use App\Models\QuotationRequestSupplier;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Quotations extends Component
{
    use WithPagination;

    public string $statusFilter = '';

    public function updatedStatusFilter(): void { $this->resetPage(); }

    public function render()
    {
        $supplierId = Auth::guard('supplier')->user()->supplier_id;

        $query = QuotationRequestSupplier::where('supplier_id', $supplierId)
            ->with(['quotationRequest' => fn ($q) => $q->withoutGlobalScopes()->withCount('lines')])
            ->with('quotation');

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $rfqs = $query->orderByDesc('created_at')->paginate(15);

        return view('livewire.supplier.quotations', compact('rfqs'))
            ->layout('layouts.supplier', ['title' => 'Quotations']);
    }
}
