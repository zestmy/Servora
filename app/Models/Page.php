<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = [
        'title', 'slug', 'external_url', 'open_in_new_tab',
        'content', 'is_published', 'menu_placement', 'sort_order',
    ];

    protected $casts = [
        'is_published'    => 'boolean',
        'open_in_new_tab' => 'boolean',
        'sort_order'      => 'integer',
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

    public function isExternal(): bool
    {
        return !empty($this->external_url);
    }

    public function url(): string
    {
        if ($this->isExternal()) {
            return $this->external_url;
        }

        return url('/page/' . $this->slug);
    }

    public function linkTarget(): string
    {
        return ($this->isExternal() || $this->open_in_new_tab) ? '_blank' : '_self';
    }
}
