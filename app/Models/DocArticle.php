<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DocArticle extends Model
{
    protected $fillable = [
        'doc_category_id', 'title', 'slug', 'excerpt', 'body', 'keywords',
        'hero_image', 'sort_order', 'is_published', 'updated_by',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order'   => 'integer',
        'view_count'   => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocCategory::class, 'doc_category_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(DocImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function url(): string
    {
        return route('help.article', [$this->category?->slug ?? 'general', $this->slug]);
    }

    /**
     * Roughly how long this takes to read, for the byline. Counted off the
     * rendered text rather than the HTML, or every attribute and class name
     * in the markup would be charged to the reader.
     */
    public function readingMinutes(): int
    {
        $words = str_word_count(strip_tags((string) $this->body));

        return max(1, (int) ceil($words / 220));
    }

    /** The excerpt if the author wrote one, otherwise the opening prose. */
    public function teaser(int $length = 160): string
    {
        if ($this->excerpt) {
            return Str::limit($this->excerpt, $length);
        }

        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $this->body))), $length);
    }

    /**
     * view_count is a popularity signal on a public page, not an audit trail:
     * an increment must never fail a page render, and it must not touch
     * updated_at — that column is shown to readers as "last reviewed".
     */
    public function recordView(): void
    {
        try {
            static::withoutTimestamps(fn () => $this->increment('view_count'));
        } catch (\Throwable) {
            // A read-only replica or a locked row is not worth a 500 on a
            // help page.
        }
    }
}
