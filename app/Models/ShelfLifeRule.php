<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One shelf life, for one thing, in one storage state.
 *
 * Hangs off a category by default so a single row covers every item in it;
 * per-item rows override. Resolution order lives in ShelfLifeService.
 */
class ShelfLifeRule extends Model
{
    /**
     * Storage states a label can be printed against.
     *
     * 'ambient', 'chill' and 'frozen' deliberately match the keys in
     * Recipe::STORAGE_OPTIONS so an item's existing storage instruction maps
     * straight onto a state with no translation table.
     */
    public const STORAGE_STATES = [
        'ambient' => 'Ambient',
        'chill'   => 'Chilled',
        'frozen'  => 'Frozen',
        'thawed'  => 'Thawed',
        'opened'  => 'Opened',
        'cooked'  => 'Cooked',
    ];

    /**
     * Units, matching Recipe::SHELF_LIFE_UNITS.
     *
     * Sub-day units matter: end-of-day rounding must never be applied to them,
     * because rounding a 4-hour life up to 23:59 extends it.
     */
    public const SUB_DAY_UNITS = ['minutes', 'hours'];

    protected $fillable = [
        'company_id', 'ruleable_type', 'ruleable_id',
        'storage_state', 'value', 'unit',
    ];

    protected $casts = [
        'value' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function ruleable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function stateLabel(?string $state): string
    {
        return self::STORAGE_STATES[$state] ?? ucfirst((string) $state);
    }

    /** True when this rule's unit is finer than a day. */
    public function isSubDay(): bool
    {
        return in_array($this->unit, self::SUB_DAY_UNITS, true);
    }
}
