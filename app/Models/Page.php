<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = ['title', 'slug', 'content', 'is_published', 'menu_placement', 'sort_order'];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order'   => 'integer',
    ];

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeInHeader($query)
    {
        return $query->published()->whereIn('menu_placement', ['header', 'both'])->orderBy('sort_order');
    }

    public function scopeInFooter($query)
    {
        return $query->published()->whereIn('menu_placement', ['footer', 'both'])->orderBy('sort_order');
    }

    public function url(): string
    {
        return url('/page/' . $this->slug);
    }
}
