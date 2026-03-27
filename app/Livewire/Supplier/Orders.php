<?php

namespace App\Livewire\Supplier;

use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Orders extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }

    public function render()
    {
        $supplierId = Auth::guard('supplier')->user()->supplier_id;

        $query = PurchaseOrder::withoutGlobalScopes()
            ->where('supplier_id', $supplierId)
            ->with(['outlet', 'lines'])
            ->withCount('lines');

        if ($this->search) {
            $query->where('po_number', 'like', '%' . $this->search . '%');
        }
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $orders = $query->orderByDesc('order_date')->paginate(15);

        return view('livewire.supplier.orders', compact('orders'))
            ->layout('layouts.supplier', ['title' => 'Orders']);
    }
}
