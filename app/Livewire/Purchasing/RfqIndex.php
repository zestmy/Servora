<?php

namespace App\Livewire\Purchasing;

use App\Models\QuotationRequest;
use App\Traits\ScopesToActiveOutlet;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class RfqIndex extends Component
{
    use WithPagination, ScopesToActiveOutlet;

    public string $search       = '';
    public string $statusFilter = '';
    public string $dateFrom     = '';
    public string $dateTo       = '';

    public function updatedSearch(): void       { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedDateFrom(): void     { $this->resetPage(); }
    public function updatedDateTo(): void       { $this->resetPage(); }

    public function render()
    {
        $query = QuotationRequest::with(['createdBy'])
            ->withCount(['lines', 'suppliers', 'quotations']);

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('rfq_number', 'like', '%' . $this->search . '%')
                  ->orWhere('title', 'like', '%' . $this->search . '%');
            });
        }

        // Status filter
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        // Date range
        if ($this->dateFrom) {
            $query->where('needed_by_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->where('needed_by_date', '<=', $this->dateTo);
        }

        $rfqs = $query->orderByDesc('created_at')->paginate(15);

        return view('livewire.purchasing.rfq-index', [
            'rfqs' => $rfqs,
        ])->layout('layouts.app', ['title' => 'RFQ Management']);
    }
}
