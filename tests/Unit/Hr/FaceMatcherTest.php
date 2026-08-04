<?php

namespace Tests\Unit\Hr;

use App\Models\EmployeeFaceDescriptor;
use App\Services\Hr\FaceMatcher;
use PHPUnit\Framework\TestCase;

class FaceMatcherTest extends TestCase
{
    private function descriptor(float $fill = 0.1): array
    {
        return array_fill(0, EmployeeFaceDescriptor::DESCRIPTOR_LENGTH, $fill);
    }

    public function test_identical_vectors_are_zero_apart(): void
    {
        $this->assertSame(0.0, FaceMatcher::euclidean($this->descriptor(), $this->descriptor()));
    }

    public function test_distance_grows_with_difference(): void
    {
        $near = FaceMatcher::euclidean($this->descriptor(0.1), $this->descriptor(0.11));
        $far  = FaceMatcher::euclidean($this->descriptor(0.1), $this->descriptor(0.9));

        $this->assertLessThan($far, $near);
    }

    public function test_a_short_vector_is_not_a_valid_descriptor(): void
    {
        // The vector arrives from a browser and is attacker-controlled: a
        // short array reaching the distance maths would produce a small
        // number that happens to pass the threshold.
        $this->assertFalse(EmployeeFaceDescriptor::isValidDescriptor(array_fill(0, 10, 0.1)));
    }

    public function test_a_correct_length_vector_is_valid(): void
    {
        $this->assertTrue(EmployeeFaceDescriptor::isValidDescriptor($this->descriptor()));
    }

    public function test_non_numeric_and_non_finite_components_are_rejected(): void
    {
        $withString = $this->descriptor();
        $withString[7] = 'nope';
        $this->assertFalse(EmployeeFaceDescriptor::isValidDescriptor($withString));

        $withNull = $this->descriptor();
        $withNull[7] = null;
        $this->assertFalse(EmployeeFaceDescriptor::isValidDescriptor($withNull));

        $withNan = $this->descriptor();
        $withNan[7] = NAN;
        $this->assertFalse(EmployeeFaceDescriptor::isValidDescriptor($withNan));

        $withInf = $this->descriptor();
        $withInf[7] = INF;
        $this->assertFalse(EmployeeFaceDescriptor::isValidDescriptor($withInf));
    }

    public function test_non_arrays_are_rejected(): void
    {
        $this->assertFalse(EmployeeFaceDescriptor::isValidDescriptor(null));
        $this->assertFalse(EmployeeFaceDescriptor::isValidDescriptor('0.1,0.2'));
        $this->assertFalse(EmployeeFaceDescriptor::isValidDescriptor(42));
    }
}
