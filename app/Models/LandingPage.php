<?php

namespace App\Models;

use App\Support\Marketing\HomeCopy;
use Illuminate\Database\Eloquent\Model;

/**
 * The marketing home page in a local language, for the countries it lists.
 * Served at /{slug}; `strings` overrides HomeCopy::defaults() key by key, and
 * a key left blank falls back to the English.
 */
class LandingPage extends Model
{
    protected $fillable = [
        'slug', 'locale', 'language_name', 'countries', 'strings', 'is_published', 'auto_redirect',
    ];

    protected $casts = [
        'countries'     => 'array',
        'strings'       => 'array',
        'is_published'  => 'boolean',
        'auto_redirect' => 'boolean',
    ];

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public static function forCountry(?string $country): ?self
    {
        if (! $country) {
            return null;
        }

        return static::published()->get()
            ->first(fn (self $p) => in_array(strtoupper($country), (array) $p->countries, true));
    }

    /** @return array<string, string> every key, translated where it has been */
    public function copy(): array
    {
        $strings = array_filter((array) $this->strings, fn ($v) => is_string($v) && trim($v) !== '');

        return array_merge(HomeCopy::defaults(), array_intersect_key($strings, HomeCopy::defaults()));
    }

    public function translatedCount(): int
    {
        return count(array_intersect_key(
            array_filter((array) $this->strings, fn ($v) => is_string($v) && trim($v) !== ''),
            HomeCopy::defaults(),
        ));
    }
}
