<?php

namespace App\Livewire\Supplier;

use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class OrderShow extends Component
{
    public int $orderId;
    public ?PurchaseOrder $order = null;

    public function mount(int $id): void
    {
        $supplierId = Auth::guard('supplier')->user()->supplier_id;
        $this->orderId = $id;
        $this->order = PurchaseOrder::withoutGlobalScopes()
            ->where('supplier_id', $supplierId)
            ->with(['outlet', 'lines.ingredient', 'lines.uom', 'createdBy'])
            ->findOrFail($id);
    }

    public function render()
    {
        return view('livewire.supplier.order-show')
            ->layout('layouts.supplier', ['title' => 'PO ' . $this->order->po_number]);
    }
}
