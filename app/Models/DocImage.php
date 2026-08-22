<?php

namespace App\Models;

use App\Models\Concerns\PurgesStoredFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocImage extends Model
{
    use PurgesStoredFiles;

    protected static function booted(): void
    {
        static::deleted(fn (self $image) => $image->purgeOwnedFile('path'));
    }

    protected $fillable = [
        'doc_article_id', 'path', 'alt', 'caption', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(DocArticle::class, 'doc_article_id');
    }

    /**
     * Seeded figures ship in the repo under public/images/docs and are stored
     * as a plain relative path; uploads live on the public disk. One accessor
     * so the view never has to know which kind it is holding.
     */
    public function url(): string
    {
        if (Str::startsWith($this->path, ['http://', 'https://', '/'])) {
            return $this->path;
        }

        if (Str::startsWith($this->path, 'images/')) {
            return asset($this->path);
        }

        return Storage::disk('public')->url($this->path);
    }

    /** The HTML an author drops into an article body to place this figure. */
    public function figureHtml(): string
    {
        $src     = e($this->url());
        $alt     = e($this->alt ?? '');
        $caption = $this->caption ? '<figcaption>' . e($this->caption) . '</figcaption>' : '';

        return "<figure><img src=\"{$src}\" alt=\"{$alt}\">{$caption}</figure>";
    }
}
