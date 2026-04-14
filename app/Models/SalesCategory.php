<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesCategory extends Model
{
    use SoftDeletes;

    protected $fillable = ['company_id', 'name', 'color', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesRecordLine::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('name');
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
