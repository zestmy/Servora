<?php

namespace Tests\Unit\Hr;

use App\Services\Hr\Geo;
use PHPUnit\Framework\TestCase;

class GeoTest extends TestCase
{
    public function test_the_same_point_is_zero_metres_apart(): void
    {
        // Floating-point error here used to produce NaN via sqrt of a
        // slightly-negative number, which would read downstream as "0m away"
        // by accident rather than by calculation.
        $this->assertSame(0.0, Geo::distanceMetres(3.1390, 101.6869, 3.1390, 101.6869));
    }

    public function test_a_known_separation_is_measured_correctly(): void
    {
        // KLCC to KL Sentral, about 4.3km apart.
        $metres = Geo::distanceMetres(3.1578, 101.7117, 3.1339, 101.6869);

        $this->assertGreaterThan(3_200, $metres);
        $this->assertLessThan(4_200, $metres);
    }

    public function test_a_short_hop_is_measured_in_tens_of_metres(): void
    {
        // 0.0009 degrees of latitude is very close to 100m anywhere on earth.
        $metres = Geo::distanceMetres(3.1390, 101.6869, 3.1399, 101.6869);

        $this->assertGreaterThan(90, $metres);
        $this->assertLessThan(110, $metres);
    }

    public function test_null_island_is_rejected_as_a_coordinate(): void
    {
        // A broken geolocation shim reports (0,0) far more often than anyone
        // stands in the Gulf of Guinea.
        $this->assertFalse(Geo::isValidCoordinate(0, 0));
    }

    public function test_out_of_range_and_non_numeric_values_are_rejected(): void
    {
        $this->assertFalse(Geo::isValidCoordinate(91, 10));
        $this->assertFalse(Geo::isValidCoordinate(10, 181));
        $this->assertFalse(Geo::isValidCoordinate('here', 'there'));
        $this->assertFalse(Geo::isValidCoordinate(null, null));
        $this->assertFalse(Geo::isValidCoordinate(INF, 10));
    }

    public function test_a_real_coordinate_is_accepted(): void
    {
        $this->assertTrue(Geo::isValidCoordinate(3.1390, 101.6869));
        $this->assertTrue(Geo::isValidCoordinate('3.1390', '101.6869'));
    }
}
