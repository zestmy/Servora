<?php

namespace App\Livewire\Help;

use App\Models\DocArticle;
use App\Models\DocCategory;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The manual's front door.
 *
 * Public on purpose. Two thirds of the questions this answers — what does the
 * Central Kitchen workspace do, can it read my supplier's invoices, how does
 * label printing reach the printer — are asked BEFORE anyone has an account,
 * and a help centre behind a login cannot answer them. Nothing here is
 * tenant data.
 */
class Index extends Component
{
    use WithPagination;

    public string $q = '';

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $term    = trim($this->q);
        $results = null;

        if ($term !== '') {
            $like = '%' . $term . '%';

            $results = DocArticle::published()
                ->with('category')
                ->whereHas('category', fn ($c) => $c->published())
                ->where(fn ($q) => $q->where('title', 'like', $like)
                    ->orWhere('excerpt', 'like', $like)
                    ->orWhere('keywords', 'like', $like)
                    ->orWhere('body', 'like', $like))
                // Title matches first: someone typing "wastage" wants the
                // article called Wastage, not the eleven that mention it.
                ->orderByRaw('CASE WHEN title LIKE ? THEN 0 ELSE 1 END', [$like])
                ->orderBy('title')
                ->paginate(15);
        }

        return view('livewire.help.index', [
            'categories' => DocCategory::published()->ordered()
                ->withCount(['articles as published_articles_count' => fn ($q) => $q->where('is_published', true)])
                ->get(),
            'results'    => $results,
            'popular'    => $term === '' ? $this->popular() : collect(),
        ])->layout('layouts.marketing', [
            'title'       => 'Help Centre',
            'description' => 'How to use Servora — guides for purchasing, recipe costing, inventory, labels, HR, training and reporting.',
        ]);
    }

    /**
     * The most-read articles, with a floor: a manual on its first day has all
     * zeroes, and a "Popular" strip that renders an arbitrary six of them is
     * worse than one that falls back to the opening articles of each section.
     */
    private function popular()
    {
        $popular = DocArticle::published()
            ->with('category')
            ->where('view_count', '>', 0)
            ->orderByDesc('view_count')
            ->limit(6)
            ->get();

        if ($popular->count() >= 3) {
            return $popular;
        }

        return DocArticle::published()
            ->with('category')
            ->orderBy('doc_category_id')
            ->orderBy('sort_order')
            ->limit(6)
            ->get();
    }
}
