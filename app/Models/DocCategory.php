<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocCategory extends Model
{
    /** Anyone, signed in or not. */
    public const VISIBILITY_PUBLIC = 'public';

    /** Any signed-in user, of any tenant. */
    public const VISIBILITY_AUTHENTICATED = 'authenticated';

    /** System roles only — the audience of the screens such a section describes. */
    public const VISIBILITY_SYSTEM = 'system';

    /** @var array<string, string> */
    public const VISIBILITIES = [
        self::VISIBILITY_PUBLIC        => 'Everyone',
        self::VISIBILITY_AUTHENTICATED => 'Signed-in users',
        self::VISIBILITY_SYSTEM        => 'System admins only',
    ];

    protected $fillable = [
        'title', 'slug', 'summary', 'icon', 'sort_order', 'is_published', 'visibility',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order'   => 'integer',
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(DocArticle::class)->orderBy('sort_order')->orderBy('title');
    }

    public function publishedArticles(): HasMany
    {
        return $this->articles()->where('is_published', true);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    /**
     * Sections this viewer is allowed to see. Pass null for a guest.
     *
     * Kept as a query scope AND a per-model check (isVisibleTo) because both
     * questions get asked: the index needs to list what you may open, and the
     * article route needs to decide about the one you asked for.
     */
    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        if ($viewer === null) {
            return $query->where('visibility', self::VISIBILITY_PUBLIC);
        }

        if ($viewer->isSystemRole()) {
            return $query;
        }

        return $query->whereIn('visibility', [self::VISIBILITY_PUBLIC, self::VISIBILITY_AUTHENTICATED]);
    }

    public function isVisibleTo(?User $viewer): bool
    {
        return match ($this->visibility) {
            self::VISIBILITY_SYSTEM        => (bool) $viewer?->isSystemRole(),
            self::VISIBILITY_AUTHENTICATED => $viewer !== null,
            default                        => true,
        };
    }

    /** What the admin list shows next to a section that is not public. */
    public function visibilityLabel(): string
    {
        return self::VISIBILITIES[$this->visibility] ?? self::VISIBILITIES[self::VISIBILITY_PUBLIC];
    }

    public function isPublic(): bool
    {
        return $this->visibility === self::VISIBILITY_PUBLIC;
    }

    public function url(): string
    {
        return route('help.category', $this->slug);
    }
}
