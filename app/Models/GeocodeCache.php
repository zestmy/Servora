<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One resolved address per coordinate, shared across every company.
 *
 * Deliberately NOT company-scoped: a street address is a fact about a place,
 * not about a tenant, and scoping it would mean paying for the same lookup
 * once per company. Nothing identifying is stored — a point and a street.
 */
class GeocodeCache extends Model
{
    protected $table = 'geocode_cache';

    protected $fillable = ['latitude', 'longitude', 'address', 'provider'];

    /**
     * Coordinates are rounded to this many decimals before they are looked up
     * or stored. Five is about a metre — precise enough that two punches from
     * different buildings never share a row, coarse enough that the same
     * doorway resolves once.
     */
    public const PRECISION = 5;

    public static function round(float $value): float
    {
        return round($value, self::PRECISION);
    }

    /** The stored address for a point, or null if it has never been looked up. */
    public static function lookup(float $latitude, float $longitude, string $provider): ?self
    {
        return static::query()
            ->where('latitude', self::round($latitude))
            ->where('longitude', self::round($longitude))
            ->where('provider', $provider)
            ->first();
    }

    /**
     * Remember a result, including a null one — "we asked and there was
     * nothing there" is worth caching, or every view retries a dead point.
     */
    public static function remember(float $latitude, float $longitude, string $provider, ?string $address): self
    {
        return static::updateOrCreate(
            [
                'latitude'  => self::round($latitude),
                'longitude' => self::round($longitude),
                'provider'  => $provider,
            ],
            ['address' => $address]
        );
    }
}
