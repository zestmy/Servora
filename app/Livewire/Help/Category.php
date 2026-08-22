<?php

namespace App\Livewire\Help;

use App\Models\DocCategory;
use Illuminate\Support\Facades\Auth;
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
        // 404, not 403. A refusal confirms the section exists and what it is
        // called, and a section restricted to system roles is exactly the
        // content not worth naming to someone who may not read it.
        $this->category = DocCategory::published()
            ->visibleTo(Auth::user())
            ->where('slug', $categorySlug)
            ->firstOrFail();
    }

    public function render()
    {
        return view('livewire.help.category', [
            'articles'   => $this->category->publishedArticles()->get(),
            'categories' => DocCategory::published()->visibleTo(Auth::user())->ordered()->get(),
        ])->layout('layouts.marketing', [
            'title'       => $this->category->title . ' — Help Centre',
            'description' => $this->category->summary
                ?? 'Servora guides for ' . strtolower($this->category->title) . '.',
        ]);
    }
}
