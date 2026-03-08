<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CostType extends Model
{
    use SoftDeletes;

    protected $fillable = ['company_id', 'name', 'slug', 'color', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Return [slug => name] map for dropdowns.
     */
    public static function options(): array
    {
        return static::active()->ordered()->pluck('name', 'slug')->toArray();
    }
}
