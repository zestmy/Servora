<?php

namespace App\Livewire\Admin\Docs;

use App\Models\DocArticle;
use App\Models\DocCategory;
use App\Models\DocImage;
use App\Services\ImageStorageService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Write or revise one help article.
 *
 * The body is HTML, like Admin\Pages — same editor idiom, same reason: the
 * people writing this are the same people writing the marketing pages, and a
 * second markup language to learn is a second markup language to get wrong.
 * The public template restricts what actually renders (see
 * resources/views/livewire/help/article.blade.php).
 *
 * Figures are uploaded here and INSERTED into the body as markup, rather than
 * rendered as a fixed gallery underneath it. A screenshot that illustrates
 * step four belongs at step four.
 */
class ArticleForm extends Component
{
    use WithFileUploads;

    public ?int $articleId = null;

    public ?int   $doc_category_id = null;
    public string $title = '';
    public string $slug = '';
    public string $excerpt = '';
    public string $body = '';
    public string $keywords = '';
    public string $sort_order = '0';
    public bool   $is_published = true;

    /** Pending upload. */
    public $upload;
    public string $upload_alt = '';
    public string $upload_caption = '';

    /** Max upload size, in kilobytes. */
    public const MAX_IMAGE_KB = 4096;

    public function mount(?int $id = null): void
    {
        if ($id) {
            $article = DocArticle::findOrFail($id);

            $this->articleId       = $article->id;
            $this->doc_category_id = $article->doc_category_id;
            $this->title           = $article->title;
            $this->slug            = $article->slug;
            $this->excerpt         = $article->excerpt ?? '';
            $this->body            = $article->body ?? '';
            $this->keywords        = $article->keywords ?? '';
            $this->sort_order      = (string) $article->sort_order;
            $this->is_published    = $article->is_published;
        } else {
            $this->doc_category_id = (int) request()->query('category') ?: DocCategory::ordered()->value('id');
            $this->sort_order      = (string) ($this->nextSortOrder() ?? 0);
        }
    }

    private function nextSortOrder(): int
    {
        if (! $this->doc_category_id) {
            return 0;
        }

        // Spaced by ten so an article can be dropped between two later on
        // without renumbering the section — see Index::moveArticle().
        return ((int) DocArticle::where('doc_category_id', $this->doc_category_id)->max('sort_order')) + 10;
    }

    public function updatedTitle(): void
    {
        if (! $this->articleId) {
            $this->slug = Str::slug($this->title);
        }
    }

    protected function rules(): array
    {
        return [
            'doc_category_id' => ['required', Rule::exists('doc_categories', 'id')],
            'title'           => ['required', 'string', 'max:200'],
            'slug'            => ['required', 'string', 'max:200', 'alpha_dash',
                                  'unique:doc_articles,slug' . ($this->articleId ? ',' . $this->articleId : '')],
            'excerpt'         => ['nullable', 'string', 'max:500'],
            'body'            => ['nullable', 'string'],
            'keywords'        => ['nullable', 'string', 'max:500'],
            'sort_order'      => ['required', 'integer', 'min:0'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'doc_category_id' => $this->doc_category_id,
            'title'           => $this->title,
            'slug'            => $this->slug,
            'excerpt'         => $this->excerpt ?: null,
            'body'            => $this->body ?: null,
            'keywords'        => $this->keywords ?: null,
            'sort_order'      => (int) $this->sort_order,
            'is_published'    => $this->is_published,
            'updated_by'      => Auth::id(),
        ];

        if ($this->articleId) {
            DocArticle::findOrFail($this->articleId)->update($data);
            session()->flash('success', 'Article saved.');
        } else {
            $article = DocArticle::create($data);
            $this->articleId = $article->id;
            session()->flash('success', 'Article created. Add figures below, or publish it.');
        }

        $this->redirectRoute('admin.docs.edit', ['id' => $this->articleId], navigate: true);
    }

    // ── Figures ────────────────────────────────────────────────────────────

    /**
     * Save the upload against this article. The article must already exist:
     * a figure needs a row to hang off, and inventing one silently on upload
     * would leave an untitled article behind if the author walked away.
     */
    public function addImage(): void
    {
        if (! $this->articleId) {
            $this->addError('upload', 'Save the article first, then add figures to it.');

            return;
        }

        $this->validate([
            'upload'         => ['required', ImageStorageService::uploadRule(self::MAX_IMAGE_KB)],
            'upload_alt'     => ['required', 'string', 'max:300'],
            'upload_caption' => ['nullable', 'string', 'max:300'],
        ], [
            'upload.mimes'      => ImageStorageService::uploadMessage(),
            'upload_alt.required' => 'Describe the image for readers who cannot see it.',
        ]);

        // storeCompressed, not store(): a screenshot arrives as whatever the
        // author's screen or phone produced, and a 6 MB 4000px PNG of a
        // 900px-wide article is bytes nobody reading on a phone asked for.
        // It caps the long side, re-encodes, and falls back to the raw file
        // whenever Imagick is missing or cannot read the upload.
        $path = ImageStorageService::storeCompressed($this->upload, 'docs', 'public');

        $image = DocImage::create([
            'doc_article_id' => $this->articleId,
            'path'           => $path,
            'alt'            => $this->upload_alt,
            'caption'        => $this->upload_caption ?: null,
            'sort_order'     => ((int) DocImage::where('doc_article_id', $this->articleId)->max('sort_order')) + 10,
        ]);

        // Appended, not silently dropped in the middle: the author moves it to
        // the right step, and a figure that never reaches the body at all is
        // the failure mode of an upload button that does nothing visible.
        $this->body = rtrim($this->body) . "\n\n" . $image->figureHtml() . "\n";

        $this->reset(['upload', 'upload_alt', 'upload_caption']);
        session()->flash('success', 'Figure added to the end of the article — move it to the right step and save.');
    }

    public function insertImage(int $id): void
    {
        $image = DocImage::where('doc_article_id', $this->articleId)->findOrFail($id);

        $this->body = rtrim($this->body) . "\n\n" . $image->figureHtml() . "\n";
    }

    public function deleteImage(int $id): void
    {
        $image = DocImage::where('doc_article_id', $this->articleId)->findOrFail($id);
        $image->delete();

        session()->flash('success', 'Figure deleted. Remove it from the body too if you had placed it.');
    }

    public function render()
    {
        return view('livewire.admin.docs.article-form', [
            'categories' => DocCategory::ordered()->get(),
            'images'     => $this->articleId
                ? DocImage::where('doc_article_id', $this->articleId)->orderBy('sort_order')->get()
                : collect(),
        ])->layout('layouts.app', ['title' => $this->articleId ? 'Edit article' : 'New article']);
    }
}
