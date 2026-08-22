<?php

namespace App\Livewire\Admin\Docs;

use App\Models\DocArticle;
use App\Models\DocCategory;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * The help centre's table of contents, editable.
 *
 * Categories and their articles are managed on one screen because that is how
 * the manual is actually read: nobody edits a category in isolation, they move
 * an article into it or reorder the three that are already there.
 */
class Index extends Component
{
    public string $search = '';

    /** Which category's articles are expanded. */
    public ?int $openCategory = null;

    // Category editor
    public bool    $showCategory = false;
    public ?int    $categoryId = null;
    public string  $cat_title = '';
    public string  $cat_slug = '';
    public string  $cat_summary = '';
    public string  $cat_icon = '';
    public string  $cat_sort_order = '0';
    public bool    $cat_is_published = true;
    public string  $cat_visibility = DocCategory::VISIBILITY_PUBLIC;

    /** The <x-icon> names offered for a category tile. */
    public const ICON_CHOICES = [
        'book-open', 'home', 'cart', 'cube', 'tag', 'chart', 'users', 'academic',
        'trending-up', 'cog', 'shield', 'printer', 'currency', 'receipt', 'clock',
        'clipboard', 'truck', 'device', 'sparkles', 'lifebuoy',
    ];

    public function mount(): void
    {
        $this->openCategory = DocCategory::ordered()->value('id');
    }

    public function toggleCategory(int $id): void
    {
        $this->openCategory = $this->openCategory === $id ? null : $id;
    }

    // ── Categories ─────────────────────────────────────────────────────────

    public function createCategory(): void
    {
        $this->resetCategoryForm();
        $this->cat_sort_order = (string) (((int) DocCategory::max('sort_order')) + 10);
        $this->showCategory   = true;
    }

    public function editCategory(int $id): void
    {
        $category = DocCategory::findOrFail($id);

        $this->categoryId       = $category->id;
        $this->cat_title        = $category->title;
        $this->cat_slug         = $category->slug;
        $this->cat_summary      = $category->summary ?? '';
        $this->cat_icon         = $category->icon ?? '';
        $this->cat_sort_order   = (string) $category->sort_order;
        $this->cat_is_published = $category->is_published;
        $this->cat_visibility   = $category->visibility ?? DocCategory::VISIBILITY_PUBLIC;
        $this->showCategory     = true;
    }

    public function updatedCatTitle(): void
    {
        if (! $this->categoryId) {
            $this->cat_slug = Str::slug($this->cat_title);
        }
    }

    public function saveCategory(): void
    {
        $this->validate([
            'cat_title'      => ['required', 'string', 'max:120'],
            'cat_slug'       => ['required', 'string', 'max:120', 'alpha_dash',
                                 'unique:doc_categories,slug' . ($this->categoryId ? ',' . $this->categoryId : '')],
            'cat_summary'    => ['nullable', 'string', 'max:500'],
            'cat_icon'       => ['nullable', 'string', 'max:60'],
            'cat_sort_order' => ['required', 'integer', 'min:0'],
            'cat_visibility' => ['required', 'in:' . implode(',', array_keys(DocCategory::VISIBILITIES))],
        ]);

        $data = [
            'title'        => $this->cat_title,
            'slug'         => $this->cat_slug,
            'summary'      => $this->cat_summary ?: null,
            'icon'         => $this->cat_icon ?: null,
            'sort_order'   => (int) $this->cat_sort_order,
            'is_published' => $this->cat_is_published,
            'visibility'   => $this->cat_visibility,
        ];

        if ($this->categoryId) {
            DocCategory::findOrFail($this->categoryId)->update($data);
            session()->flash('success', 'Section updated.');
        } else {
            $category = DocCategory::create($data);
            $this->openCategory = $category->id;
            session()->flash('success', 'Section created.');
        }

        $this->closeCategory();
    }

    public function toggleCategoryPublished(int $id): void
    {
        $category = DocCategory::findOrFail($id);
        $category->update(['is_published' => ! $category->is_published]);
    }

    /**
     * Deleting a section takes its articles with it — the FK cascades — so the
     * confirmation in the view has to say the count, not just the title.
     */
    public function deleteCategory(int $id): void
    {
        $category = DocCategory::withCount('articles')->findOrFail($id);
        $title    = $category->title;
        $count    = $category->articles_count;

        $category->delete();

        session()->flash('success', $count > 0
            ? "Section “{$title}” deleted along with {$count} article(s)."
            : "Section “{$title}” deleted.");
    }

    public function closeCategory(): void
    {
        $this->showCategory = false;
        $this->resetCategoryForm();
        $this->resetValidation();
    }

    private function resetCategoryForm(): void
    {
        $this->categoryId       = null;
        $this->cat_title        = '';
        $this->cat_slug         = '';
        $this->cat_summary      = '';
        $this->cat_icon         = 'book-open';
        $this->cat_sort_order   = '0';
        $this->cat_is_published = true;
        $this->cat_visibility   = DocCategory::VISIBILITY_PUBLIC;
    }

    // ── Articles ───────────────────────────────────────────────────────────

    public function toggleArticlePublished(int $id): void
    {
        $article = DocArticle::findOrFail($id);
        $article->update(['is_published' => ! $article->is_published]);
    }

    public function deleteArticle(int $id): void
    {
        $article = DocArticle::findOrFail($id);
        $title   = $article->title;
        $article->delete();

        session()->flash('success', "“{$title}” deleted.");
    }

    /**
     * Nudge an article up or down within its section.
     *
     * Swaps sort_order with the neighbour rather than renumbering the list:
     * seeded articles are spaced by ten so a hand-written article can be
     * dropped between two of them, and a full renumber would throw that away.
     */
    public function moveArticle(int $id, string $direction): void
    {
        $article = DocArticle::findOrFail($id);

        $neighbour = DocArticle::where('doc_category_id', $article->doc_category_id)
            ->when($direction === 'up',
                fn ($q) => $q->where('sort_order', '<', $article->sort_order)->orderByDesc('sort_order'),
                fn ($q) => $q->where('sort_order', '>', $article->sort_order)->orderBy('sort_order'))
            ->first();

        if (! $neighbour) {
            return;
        }

        $mine = $article->sort_order;
        $article->update(['sort_order' => $neighbour->sort_order]);
        $neighbour->update(['sort_order' => $mine]);
    }

    public function render()
    {
        $categories = DocCategory::ordered()
            ->withCount('articles')
            ->get();

        $articles = DocArticle::query()
            ->with('category')
            ->when($this->search !== '', function ($q) {
                $term = "%{$this->search}%";
                $q->where(fn ($w) => $w->where('title', 'like', $term)
                    ->orWhere('excerpt', 'like', $term)
                    ->orWhere('keywords', 'like', $term));
            })
            // A search spans the whole manual; without one the list is scoped
            // to the open section, which is what the accordion is showing.
            ->when($this->search === '', fn ($q) => $q->where('doc_category_id', $this->openCategory))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return view('livewire.admin.docs.index', [
            'categories'  => $categories,
            'articles'    => $articles,
            'iconChoices' => self::ICON_CHOICES,
            'visibilities' => DocCategory::VISIBILITIES,
        ])->layout('layouts.app', ['title' => 'Documentation']);
    }
}
