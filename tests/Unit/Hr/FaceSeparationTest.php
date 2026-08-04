<?php

namespace Tests\Unit\Hr;

use App\Services\Hr\FaceSeparation;
use PHPUnit\Framework\TestCase;

/**
 * The measurement that decides whether face identification is viable.
 *
 * Real descriptors cannot be written down by hand, but they do not need to
 * be: every question here is about DISTANCES, and a 128-dimensional point at
 * a chosen distance from another is easy to construct — move along one axis.
 * That makes the separation between "people" exact and known, so the tests
 * assert against arithmetic rather than against a photograph.
 *
 * The case that matters most is the overlap one. A recommendation that
 * quietly splits the difference between two overlapping distributions would
 * produce a plausible-looking threshold for a system that cannot actually
 * tell two colleagues apart — the one outcome this whole exercise exists to
 * catch before it ships.
 */
class FaceSeparationTest extends TestCase
{
    /** A descriptor at a known offset along the first axis. */
    private function vec(float $offset, float $jitter = 0.0): array
    {
        $v = array_fill(0, 128, 0.0);
        $v[0] = $offset;
        $v[1] = $jitter;

        return $v;
    }

    /** Somebody whose captures sit tightly together around $centre. */
    private function person(int $id, string $label, float $centre, array $jitters): array
    {
        return [
            'id'       => $id,
            'label'    => $label,
            'captures' => array_map(fn ($j) => $this->vec($centre, $j), $jitters),
        ];
    }

    public function test_well_separated_people_are_all_identified_correctly(): void
    {
        // Own captures 0.02 apart; the neighbours a full 1.0 away.
        $people = [
            $this->person(1, 'A', 0.0, [0.0, 0.02, 0.04]),
            $this->person(2, 'B', 1.0, [0.0, 0.02, 0.04]),
            $this->person(3, 'C', 2.0, [0.0, 0.02, 0.04]),
        ];

        $result = FaceSeparation::leaveOneOut($people);

        $this->assertSame(9, $result['probes']);
        $this->assertSame(9, $result['correct']);
        $this->assertSame(0, $result['undecided']);
        $this->assertSame([], $result['collisions']);

        // Genuine distances are the jitter gaps; rivals are ~1.0 away.
        $this->assertLessThan(0.1, $result['genuine']['max']);
        $this->assertGreaterThan(0.9, $result['impostor']['min']);
    }

    public function test_two_people_closer_to_each_other_than_to_themselves_are_reported(): void
    {
        // B sits 0.01 from A, while A's own captures are 0.30 apart — the
        // shape of a badly enrolled pair, and exactly the case that must
        // never be waved through.
        $people = [
            $this->person(1, 'AISHA', 0.0, [0.0, 0.30]),
            $this->person(2, 'BALQIS', 0.01, [0.0, 0.30]),
        ];

        $result = FaceSeparation::leaveOneOut($people);

        $this->assertGreaterThan(0, count($result['collisions']));
        $this->assertSame(['AISHA', 'BALQIS'], [
            min($result['collisions'][0]['a'], $result['collisions'][0]['b']),
            max($result['collisions'][0]['a'], $result['collisions'][0]['b']),
        ]);
    }

    public function test_a_colliding_pair_is_reported_once_not_once_per_capture(): void
    {
        $people = [
            $this->person(1, 'A', 0.0, [0.0, 0.4, 0.5, 0.6]),
            $this->person(2, 'B', 0.01, [0.0, 0.4, 0.5, 0.6]),
        ];

        $this->assertCount(1, FaceSeparation::leaveOneOut($people)['collisions']);
    }

    public function test_people_with_a_single_capture_are_skipped_not_counted(): void
    {
        $people = [
            $this->person(1, 'A', 0.0, [0.0, 0.02]),
            $this->person(2, 'B', 1.0, [0.0]),          // one capture only
        ];

        $result = FaceSeparation::leaveOneOut($people);

        // A contributes two probes; B contributes none.
        $this->assertSame(2, $result['probes']);
        // But B is still a candidate to be confused WITH.
        $this->assertSame(2, $result['impostor']['n']);
    }

    public function test_a_lone_enrolled_person_has_no_rival_and_no_margin(): void
    {
        $result = FaceSeparation::leaveOneOut([
            $this->person(1, 'A', 0.0, [0.0, 0.02, 0.04]),
        ]);

        $this->assertSame(3, $result['probes']);
        $this->assertSame(3, $result['correct']);
        $this->assertSame(0, $result['impostor']['n']);
        $this->assertSame(0, $result['margin']['n']);
    }

    public function test_a_clear_gap_produces_a_threshold_inside_it(): void
    {
        $result = FaceSeparation::leaveOneOut([
            $this->person(1, 'A', 0.0, [0.0, 0.05, 0.1]),
            $this->person(2, 'B', 1.0, [0.0, 0.05, 0.1]),
            $this->person(3, 'C', 2.0, [0.0, 0.05, 0.1]),
        ]);

        $advice = FaceSeparation::recommend($result);

        $this->assertTrue($advice['safe']);
        $this->assertGreaterThan($result['genuine']['p95'], $advice['threshold']);
        $this->assertLessThan($result['impostor']['p05'], $advice['threshold']);
        $this->assertGreaterThan(0, $advice['margin']);
    }

    public function test_overlapping_distributions_are_refused_rather_than_split(): void
    {
        // Everybody within 0.05 of everybody: no line exists that admits all
        // genuine matches and refuses all impostors.
        $result = FaceSeparation::leaveOneOut([
            $this->person(1, 'A', 0.00, [0.0, 0.3]),
            $this->person(2, 'B', 0.02, [0.0, 0.3]),
            $this->person(3, 'C', 0.04, [0.0, 0.3]),
        ]);

        $advice = FaceSeparation::recommend($result);

        $this->assertFalse($advice['safe']);
        $this->assertNull($advice['threshold']);
        $this->assertStringContainsString('OVERLAP', $advice['reason']);
    }

    public function test_too_little_data_is_refused_rather_than_guessed(): void
    {
        $advice = FaceSeparation::recommend(FaceSeparation::leaveOneOut([]));

        $this->assertFalse($advice['safe']);
        $this->assertNull($advice['threshold']);
        $this->assertStringContainsString('Not enough', $advice['reason']);
    }

    public function test_summary_reports_the_shape_of_a_distribution(): void
    {
        $s = FaceSeparation::summary([0.5, 0.1, 0.3, 0.2, 0.4]);

        $this->assertSame(5, $s['n']);
        $this->assertSame(0.1, $s['min']);
        $this->assertSame(0.5, $s['max']);
        $this->assertSame(0.3, $s['median']);
    }

    public function test_an_empty_distribution_reports_nothing_rather_than_zero(): void
    {
        // Zero would read as "every distance was 0.000", which is the most
        // alarming possible reading of "there was no data".
        $this->assertSame(['n' => 0], FaceSeparation::summary([]));
    }
}
