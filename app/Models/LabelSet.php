<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named collection a chef prints in one go — "Chiller 1", "Grill Station".
 *
 * Outlet-owned: there are no company-wide sets. Named LabelSet rather than
 * LabelGroup because OutletGroup already exists.
 */
class LabelSet extends Model
{
    protected $fillable = [
        'company_id', 'outlet_id', 'name', 'description',
        'sort_order', 'is_active', 'created_by',
        'show_storage', 'storage_states',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'sort_order'     => 'integer',
        'show_storage'   => 'boolean',
        'storage_states' => 'array',
    ];

    /**
     * Mirrors the column default. Eloquent does not read DB-side defaults
     * back after an insert, so without this a just-created set has a null
     * show_storage in memory and storageForLabel() returns nothing until
     * the model is reloaded.
     */
    protected $attributes = [
        'show_storage' => true,
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Lines in print order. The order is physical — labels peel off the roll
     * in sequence and get applied walking down the shelf — so it is always
     * applied, never left to insertion order.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(LabelSetLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForOutlet(Builder $query, int $outletId): Builder
    {
        return $query->where('outlet_id', $outletId);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Storage states to print on this set's QR label, with their ranges.
     *
     * An explicit choice wins. Otherwise they are derived from the set's
     * own lines, which is right often enough to be the default but wrong
     * whenever a unit holds an odd item that isn't representative of the
     * door it's behind.
     *
     * Always ordered by STORAGE_STATES so two sets never list the same
     * pair in a different order.
     *
     * @return array<int, array{state: string, label: string, temperature: string|null}>
     */
    public function storageForLabel(): array
    {
        if (! $this->show_storage) {
            return [];
        }

        $chosen = collect($this->storage_states ?? [])->filter();

        $states = $chosen->isNotEmpty()
            ? $chosen
            : $this->lines->pluck('storage_state')->filter()->unique();

        return collect(array_keys(ShelfLifeRule::STORAGE_STATES))
            ->filter(fn ($state) => $states->contains($state))
            ->map(fn ($state) => [
                'state'       => $state,
                'label'       => ShelfLifeRule::stateLabel($state),
                'temperature' => ShelfLifeRule::temperatureFor($state),
            ])
            ->values()
            ->all();
    }

    /** True when the set is showing whatever its items happen to say. */
    public function storageIsAutomatic(): bool
    {
        return empty(array_filter($this->storage_states ?? []));
    }
}
