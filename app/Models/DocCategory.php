<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocCategory extends Model
{
    protected $fillable = [
        'title', 'slug', 'summary', 'icon', 'sort_order', 'is_published',
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

    public function url(): string
    {
        return route('help.category', $this->slug);
    }
}
