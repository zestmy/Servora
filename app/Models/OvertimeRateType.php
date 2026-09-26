<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;

/**
 * A custom OT type with its own fixed hourly rate — "Part Time", RM15/hour —
 * as opposed to the three statutory types, which are the employee's hourly
 * rate × a multiplier (CompensationSetting::multiplierFor()).
 *
 * A claim of this type stores ot_type "custom_{id}" and a copy of the rate
 * (ot_hourly_rate); see OvertimeClaim::otTypeKey() and ::otAmount().
 */
class OvertimeRateType extends Model
{
    public const KEY_PREFIX = 'custom_';

    protected $fillable = ['company_id', 'name', 'hourly_rate', 'sort_order', 'is_active'];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
        'sort_order'  => 'integer',
        'is_active'   => 'boolean',
    ];

    protected $attributes = [
        'sort_order' => 0,
        'is_active'  => true,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        // Claim lists cache the type names for the request.
        static::saved(fn () => OvertimeClaim::flushTypeLabels());
        static::deleted(fn () => OvertimeClaim::flushTypeLabels());
    }

    /** The ot_type a claim of this type is stored under. */
    public function key(): string
    {
        return self::KEY_PREFIX . $this->id;
    }

    /** "Part Time (RM15.00/h)" — the rate is part of what the type means. */
    public function optionLabel(): string
    {
        return $this->name . ' (RM' . number_format((float) $this->hourly_rate, 2) . '/h)';
    }

    /** The id inside a "custom_{id}" ot_type, or null for a statutory one. */
    public static function idFromKey(?string $otType): ?int
    {
        return $otType !== null && str_starts_with($otType, self::KEY_PREFIX)
            ? (int) substr($otType, strlen(self::KEY_PREFIX))
            : null;
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function claimsCount(): int
    {
        return OvertimeClaim::withTrashed()->where('ot_type', $this->key())->count();
    }
}
