<?php

namespace App\Livewire\Supplier;

use App\Models\ProcurementInvoice;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $supplier = Auth::guard('supplier')->user()->supplier;
        $supplierId = $supplier->id;

        $pendingPos = PurchaseOrder::withoutGlobalScopes()
            ->where('supplier_id', $supplierId)
            ->whereIn('status', ['approved', 'sent'])
            ->count();

        $totalPos = PurchaseOrder::withoutGlobalScopes()
            ->where('supplier_id', $supplierId)
            ->count();

        $activeProducts = $supplier->products()->where('is_active', true)->count();

        $outstandingInvoices = ProcurementInvoice::withoutGlobalScopes()
            ->where('supplier_id', $supplierId)
            ->where('type', 'supplier')
            ->whereIn('status', ['issued', 'overdue'])
            ->sum('total_amount');

        $recentOrders = PurchaseOrder::withoutGlobalScopes()
            ->where('supplier_id', $supplierId)
            ->with('outlet')
            ->orderByDesc('order_date')
            ->limit(5)
            ->get();

        return view('livewire.supplier.dashboard', compact(
            'supplier', 'pendingPos', 'totalPos', 'activeProducts', 'outstandingInvoices', 'recentOrders'
        ))->layout('layouts.supplier', ['title' => 'Dashboard']);
    }
}
