<?php

namespace Tests\Unit\Hr;

use App\Models\Outlet;
use PHPUnit\Framework\TestCase;

/**
 * Guards the geofence columns against being dropped from $fillable.
 *
 * They were missing when the clock-in feature shipped, and the failure was
 * silent in the worst way: ClockSettings::save() calls $outlet->update(),
 * Eloquent quietly discarded the three attributes, and the screen still
 * flashed "Clock-in settings saved." A manager set their outlet's location,
 * was told it saved, and every punch afterwards was flagged for having no
 * geofence to check against.
 *
 * Nothing else in the codebase would have caught that — hence a test whose
 * only job is to notice the columns going missing again.
 */
class OutletGeofenceFillableTest extends TestCase
{
    private const GEOFENCE_COLUMNS = ['latitude', 'longitude', 'clock_radius_m'];

    public function test_geofence_columns_are_mass_assignable(): void
    {
        $fillable = (new Outlet())->getFillable();

        foreach (self::GEOFENCE_COLUMNS as $column) {
            $this->assertContains(
                $column,
                $fillable,
                "Outlet::\$fillable is missing '{$column}' — saving the geofence will silently do nothing."
            );
        }
    }

    public function test_geofence_columns_are_cast(): void
    {
        $casts = (new Outlet())->getCasts();

        // Decimals, not floats: these feed a distance comparison that decides
        // whether somebody is charged for being late.
        $this->assertSame('decimal:7', $casts['latitude'] ?? null);
        $this->assertSame('decimal:7', $casts['longitude'] ?? null);
        $this->assertSame('integer', $casts['clock_radius_m'] ?? null);
    }
}
