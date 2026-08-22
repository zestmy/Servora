<?php

namespace App\Livewire\Help;

use App\Models\DocArticle;
use App\Models\DocCategory;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Article extends Component
{
    public DocArticle $article;
    public DocCategory $category;

    /*
     * The parameters are named …Slug rather than after the models: Livewire
     * assigns a route parameter onto the public property of the same name
     * before mount() runs, so `{article}` would try to store a string in a
     * property typed DocArticle and 404 the whole page.
     */
    public function mount(string $categorySlug, string $articleSlug): void
    {
        $viewer = Auth::user();

        $this->category = DocCategory::published()->visibleTo($viewer)
            ->where('slug', $categorySlug)
            ->firstOrFail();

        $found = DocArticle::published()
            ->where('slug', $articleSlug)
            ->firstOrFail();

        // The slug is unique across the whole manual, so an article in a
        // restricted section is reachable by name from ANY section's URL. The
        // article's own section decides, not the one in the address bar.
        abort_unless($found->category?->isVisibleTo($viewer) ?? false, 404);

        // Assigned before the redirect below, not after: the property is
        // typed and non-nullable, and Livewire still calls render() on a
        // component that queued a redirect during mount.
        $this->article = $found;

        // The slug is unique across the whole manual, so an article that has
        // been moved to another section still resolves — it just resolves at
        // the wrong URL. Redirect to the canonical one rather than 404ing a
        // link somebody has bookmarked.
        if ($found->doc_category_id !== $this->category->id) {
            $this->category = $found->category;
            $this->redirect($found->url());

            return;
        }

        $this->article->recordView();
    }

    public function render()
    {
        $siblings = $this->category->publishedArticles()->get();
        $position = $siblings->search(fn ($a) => $a->id === $this->article->id);

        return view('livewire.help.article', [
            'siblings'   => $siblings,
            'previous'   => $position > 0 ? $siblings[$position - 1] : null,
            'next'       => $position !== false && $position < $siblings->count() - 1 ? $siblings[$position + 1] : null,
            'categories' => DocCategory::published()->visibleTo(Auth::user())->ordered()->get(),
        ])->layout('layouts.marketing', [
            'title'       => $this->article->title . ' — Servora Help',
            'description' => $this->article->teaser(155),
        ]);
    }
}
