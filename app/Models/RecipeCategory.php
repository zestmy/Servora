<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecipeCategory extends Model
{
    use SoftDeletes;

    protected $fillable = ['company_id', 'parent_id', 'name', 'color', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public static function colorOptions(): array
    {
        return [
            '#ef4444' => 'Red',
            '#f97316' => 'Orange',
            '#eab308' => 'Yellow',
            '#22c55e' => 'Green',
            '#14b8a6' => 'Teal',
            '#3b82f6' => 'Blue',
            '#6366f1' => 'Indigo',
            '#a855f7' => 'Purple',
            '#ec4899' => 'Pink',
            '#6b7280' => 'Gray',
        ];
    }
}
