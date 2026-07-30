<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One print run. Groups the labels so the audit trail reads
 * "Chiller 1 relabel — 14 items, 32 labels, 07:15, Aiman".
 *
 * printed_at is resolved ONCE for the batch. Resolving now() per line makes
 * the times drift across a single chiller relabel.
 */
class LabelPrintBatch extends Model
{
    protected $fillable = [
        'company_id', 'outlet_id', 'label_set_id', 'employee_id', 'user_id',
        'printed_at', 'item_count', 'label_count',
    ];

    protected $casts = [
        'printed_at'  => 'datetime',
        'item_count'  => 'integer',
        'label_count' => 'integer',
    ];

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

    public function labelSet(): BelongsTo
    {
        return $this->belongsTo(LabelSet::class, 'label_set_id');
    }

    /** Who was picked from the staff list. */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Who was logged in — on a shared kitchen login, usually not the same person. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prints(): HasMany
    {
        return $this->hasMany(LabelPrint::class, 'batch_id');
    }

    public function scopeForOutlet(Builder $query, int $outletId): Builder
    {
        return $query->where('outlet_id', $outletId);
    }

    /** Label the audit list shows for this run. */
    public function describe(): string
    {
        $source = $this->labelSet?->name ?? 'Ad-hoc';

        return "{$source} — {$this->item_count} items, {$this->label_count} labels";
    }
}
