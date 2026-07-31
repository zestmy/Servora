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

    /**
     * Target temperature for each storage state, printed on the QR labels
     * that go on chiller doors so staff can check the unit against it.
     *
     * Standard HACCP figures. If a company works to different limits these
     * are the single place to change them — they are not configurable per
     * company yet.
     *
     * 'opened' is deliberately absent: an opened item might belong in a
     * chiller or on a dry-store shelf, so stating a temperature would be
     * inventing one. The state prints without a range instead.
     */
    public const STORAGE_TEMPERATURES = [
        'ambient' => '10-25°C',
        'chill'   => '0-4°C',
        'frozen'  => '-18°C or below',
        'thawed'  => '0-4°C',
        'cooked'  => '63°C or above',
    ];

    public static function temperatureFor(?string $state): ?string
    {
        return self::STORAGE_TEMPERATURES[$state] ?? null;
    }

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
