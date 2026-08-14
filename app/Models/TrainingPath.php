<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An ordered run of courses — the induction, the barista ladder.
 *
 * The order is the point. See the migration note for why sequential unlocking
 * is a flag rather than always-on.
 */
class TrainingPath extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'draft'     => 'Draft',
        'published' => 'Published',
        'archived'  => 'Archived',
    ];

    protected $fillable = [
        'company_id', 'name', 'description', 'status',
        'unlocks_sequentially', 'target_days', 'created_by',
    ];

    protected $casts = [
        'unlocks_sequentially' => 'boolean',
        'target_days'          => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TrainingPathItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(TrainingPathProgress::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TrainingAssignment::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }
}
