<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Department extends Model
{
    protected $fillable = [
        'company_id', 'name', 'sales_category_id', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function salesCategory(): BelongsTo
    {
        return $this->belongsTo(SalesCategory::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Departments a picker may offer: the active ones, plus whatever this record
     * already points at.
     *
     * Same rule and same reason as Outlet::selectable(), whose docblock has
     * the long version: a dropdown filtered on is_active stops containing the
     * value of the record it is editing the moment that department is retired,
     * and the browser then highlights a different one.
     *
     * @param  array<int, int|string|null>|int|string|null  $keep  ids already on the record
     */
    public function scopeSelectable($query, $keep = [])
    {
        $keep = collect(is_array($keep) ? $keep : [$keep])
            ->filter()->map(fn ($id) => (int) $id)->all();

        return $query->where(function ($q) use ($keep) {
            $q->where('is_active', true);

            if ($keep) {
                $q->orWhereIn('id', $keep);
            }
        });
    }
}
