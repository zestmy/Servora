<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An asset inventory count — the stock take of the things an outlet keeps.
 *
 * A COMPLETED COUNT IS THE BASELINE. Assets have no consumption ledger to check
 * against, so the count is not a variance report against a truth held elsewhere
 * — it IS the truth, and everything booked in or out after its date is what has
 * changed since. AssetOnHandService is the only place that reads it that way.
 */
class AssetCount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'outlet_id', 'department_id', 'reference_number', 'status',
        'count_date', 'total_asset_value', 'total_variance_cost', 'notes', 'created_by',
    ];

    protected $casts = [
        'count_date'          => 'date',
        'total_asset_value'   => 'decimal:4',
        'total_variance_cost' => 'decimal:4',
    ];

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_COMPLETED = 'completed';

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AssetCountLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeCompleted(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_COMPLETED);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
