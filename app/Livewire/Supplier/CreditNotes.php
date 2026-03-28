<?php

namespace App\Livewire\Supplier;

use App\Models\CreditNote;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class CreditNotes extends Component
{
    use WithPagination;

    public string $statusFilter = '';

    public function updatedStatusFilter(): void { $this->resetPage(); }

    public function acknowledge(int $id): void
    {
        $supplierId = Auth::guard('supplier')->user()->supplier_id;

        $cn = CreditNote::withoutGlobalScopes()
            ->where('supplier_id', $supplierId)
            ->where('id', $id)
            ->firstOrFail();

        if ($cn->status !== 'issued' || $cn->direction !== 'issued') return;

        $cn->update(['status' => 'acknowledged']);
        session()->flash('success', "Note {$cn->credit_note_number} acknowledged.");
    }

    public function render()
    {
        $supplierId = Auth::guard('supplier')->user()->supplier_id;

        $query = CreditNote::withoutGlobalScopes()
            ->where('supplier_id', $supplierId);

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $creditNotes = $query->orderByDesc('issued_date')->orderByDesc('id')->paginate(15);

        $totalOutstanding = CreditNote::withoutGlobalScopes()
            ->where('supplier_id', $supplierId)
            ->whereIn('status', ['draft', 'issued'])
            ->sum('total_amount');

        return view('livewire.supplier.credit-notes', compact('creditNotes', 'totalOutstanding'))
            ->layout('layouts.supplier', ['title' => 'Credit & Debit Notes']);
    }
}
