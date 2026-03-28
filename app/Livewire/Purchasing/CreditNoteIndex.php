<?php

namespace App\Livewire\Purchasing;

use App\Models\CreditNote;
use App\Models\Supplier;
use App\Services\CreditNoteService;
use App\Traits\ScopesToActiveOutlet;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class CreditNoteIndex extends Component
{
    use WithPagination, ScopesToActiveOutlet;

    public string $search       = '';
    public string $typeFilter   = '';
    public string $statusFilter = '';
    public string $supplierFilter = '';
    public string $dateFrom     = '';
    public string $dateTo       = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedTypeFilter(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedSupplierFilter(): void { $this->resetPage(); }
    public function updatedDateFrom(): void { $this->resetPage(); }
    public function updatedDateTo(): void { $this->resetPage(); }

    public function issue(int $id): void
    {
        $cn = CreditNote::findOrFail($id);
        if ($cn->status !== 'draft') return;

        $cn->update(['status' => 'issued']);
        session()->flash('success', "Credit/Debit note {$cn->credit_note_number} issued.");
    }

    public function apply(int $id): void
    {
        $cn = CreditNote::findOrFail($id);
        if ($cn->status !== 'issued') return;

        CreditNoteService::applyToInvoice($cn);
        session()->flash('success', "Credit/Debit note {$cn->credit_note_number} applied.");
    }

    public function cancel(int $id): void
    {
        $cn = CreditNote::findOrFail($id);
        if (in_array($cn->status, ['applied', 'cancelled'])) return;

        $cn->update(['status' => 'cancelled']);
        session()->flash('success', "Credit/Debit note {$cn->credit_note_number} cancelled.");
    }

    public function render()
    {
        $query = CreditNote::with(['supplier', 'outlet'])
            ->withCount('lines');

        if ($this->search) {
            $query->where('credit_note_number', 'like', '%' . $this->search . '%');
        }
        if ($this->typeFilter) {
            $query->where('type', $this->typeFilter);
        }
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }
        if ($this->supplierFilter) {
            $query->where('supplier_id', $this->supplierFilter);
        }
        if ($this->dateFrom) {
            $query->where('issued_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->where('issued_date', '<=', $this->dateTo);
        }

        $creditNotes = $query->orderByDesc('issued_date')->orderByDesc('id')->paginate(15);

        $stats = [
            [
                'label' => 'Total Outstanding',
                'value' => number_format(
                    CreditNote::whereIn('status', ['draft', 'issued'])->sum('total_amount'), 2
                ),
                'color' => 'yellow',
            ],
            [
                'label' => 'Total Applied',
                'value' => number_format(
                    CreditNote::where('status', 'applied')->sum('total_amount'), 2
                ),
                'color' => 'green',
            ],
            [
                'label' => 'Pending (Drafts)',
                'value' => CreditNote::where('status', 'draft')->count(),
                'color' => 'gray',
            ],
        ];

        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();

        return view('livewire.purchasing.credit-note-index', compact('creditNotes', 'stats', 'suppliers'))
            ->layout('layouts.app', ['title' => 'Credit & Debit Notes']);
    }
}
