<?php

namespace App\Livewire\Help;

use App\Models\DocCategory;
use Livewire\Component;

class Category extends Component
{
    public DocCategory $category;

    /*
     * categorySlug, not category. Livewire assigns a route parameter onto the
     * public property of the same name before mount() ever runs, so a
     * `{category}` segment tried to put the string 'purchasing' into a
     * property typed DocCategory — which surfaced as a bare 404 on every
     * article in the manual.
     */
    public function mount(string $categorySlug): void
    {
        $this->category = DocCategory::published()
            ->where('slug', $categorySlug)
            ->firstOrFail();
    }

    public function render()
    {
        return view('livewire.help.category', [
            'articles'   => $this->category->publishedArticles()->get(),
            'categories' => DocCategory::published()->ordered()->get(),
        ])->layout('layouts.marketing', [
            'title'       => $this->category->title . ' — Help Centre',
            'description' => $this->category->summary
                ?? 'Servora guides for ' . strtolower($this->category->title) . '.',
        ]);
    }
}
