<?php

namespace App\Livewire\Ingredients;

use App\Models\ScannedDocument;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Step 2 index — list of scanned documents awaiting review.
 */
class ReviewDocuments extends Component
{
    use WithPagination;

    public string $statusFilter = 'extracted';

    public function updatingStatusFilter(): void { $this->resetPage(); }

    public function discard(int $id): void
    {
        $doc = ScannedDocument::findOrFail($id);
        if ($doc->status === 'imported') return;
        $doc->update(['status' => 'discarded']);
        session()->flash('success', 'Document discarded.');
    }

    public function render()
    {
        $query = ScannedDocument::with(['uploader', 'supplier'])
            ->orderByDesc('created_at');

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $docs = $query->paginate(20);

        $counts = [
            'extracted' => ScannedDocument::where('status', 'extracted')->count(),
            'failed'    => ScannedDocument::where('status', 'failed')->count(),
            'imported'  => ScannedDocument::where('status', 'imported')->count(),
        ];

        return view('livewire.ingredients.review-documents', compact('docs', 'counts'))
            ->layout('layouts.app', ['title' => 'Review Documents']);
    }
}
