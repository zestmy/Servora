<?php

namespace App\Services\Hr;

/**
 * Distance between two coordinates.
 *
 * Haversine on a spherical earth. The ellipsoidal formulae are more correct
 * but disagree with this one by well under a metre over the few hundred
 * metres a geofence spans — far inside the phone's own error — and they cost
 * a dependency to get.
 */
class Geo
{
    /** Mean earth radius in metres. */
    private const EARTH_RADIUS_M = 6_371_008.8;

    public static function distanceMetres(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dPhi = deg2rad($lat2 - $lat1);
        $dLam = deg2rad($lon2 - $lon1);

        $a = sin($dPhi / 2) ** 2
           + cos($phi1) * cos($phi2) * sin($dLam / 2) ** 2;

        // min() guards the sqrt: accumulated floating-point error can push $a
        // a hair above 1 for two identical points, and sqrt(1 - 1.0000000001)
        // is NaN, which would quietly become a distance of zero downstream.
        return 2 * self::EARTH_RADIUS_M * asin(min(1.0, sqrt($a)));
    }

    /** Whether a coordinate pair is a real point on earth. */
    public static function isValidCoordinate(mixed $lat, mixed $lon): bool
    {
        if (! is_numeric($lat) || ! is_numeric($lon)) {
            return false;
        }

        $lat = (float) $lat;
        $lon = (float) $lon;

        if (! is_finite($lat) || ! is_finite($lon)) {
            return false;
        }

        // (0, 0) is in the Gulf of Guinea and is what a broken geolocation
        // shim reports far more often than anyone actually stands there.
        if ($lat === 0.0 && $lon === 0.0) {
            return false;
        }

        return $lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180;
    }
}
