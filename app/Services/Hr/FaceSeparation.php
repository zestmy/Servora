<?php

namespace App\Services\Hr;

/**
 * Can these faces actually be told apart?
 *
 * Verification and identification ask different questions of the same
 * descriptors. Verification already knows who you claim to be and tests one
 * hypothesis. Identification searches a whole outlet and keeps the best
 * match — which means taking the MINIMUM over N people, so the more staff
 * there are, the more chances for one of them to land close to you by luck.
 * A threshold that is comfortable at 1:1 is not automatically safe at 1:N,
 * and no amount of reasoning settles by how much. Only the data does.
 *
 * So this measures it, by leave-one-out cross-validation on the faces already
 * enrolled:
 *
 *   For every capture on file, hide it, then ask the remaining data who it
 *   is. The distance to its owner's other captures is what a GENUINE match
 *   scores. The distance to the nearest other person is what the closest
 *   IMPOSTOR scores. The gap between them is the margin available.
 *
 * Doing it this way means the answer comes from real staff photographed on
 * real phones in the real lighting of the outlet, rather than from a paper's
 * benchmark on a public dataset. If the two distributions overlap, no
 * threshold exists that admits everybody and refuses everybody else — and
 * that is worth discovering before shipping the feature rather than after.
 *
 * Pure. No database, no framework, no I/O — the command feeds it rows.
 */
class FaceSeparation
{
    /**
     * @param  array<int, array{id: int, label: string, captures: array<int, array<int, float>>}>  $people
     * @return array{
     *     people: int, probes: int, correct: int, undecided: int,
     *     genuine: array, impostor: array, margin: array,
     *     collisions: array<int, array{a: string, b: string, distance: float}>
     * }
     */
    public static function leaveOneOut(array $people): array
    {
        $genuine = $impostor = $margins = $collisions = [];
        $correct = 0;
        $probes  = 0;

        foreach ($people as $subject) {
            // One capture cannot be checked against itself. Somebody with a
            // single face on file contributes nothing here — which is itself
            // worth knowing, and the command reports it.
            if (count($subject['captures']) < 2) {
                continue;
            }

            foreach ($subject['captures'] as $index => $probe) {
                $probes++;

                $ownBest = null;

                foreach ($subject['captures'] as $other => $candidate) {
                    if ($other === $index) {
                        continue;
                    }

                    $d = FaceMatcher::euclidean($probe, $candidate);
                    $ownBest = $ownBest === null ? $d : min($ownBest, $d);
                }

                [$rivalBest, $rivalLabel] = self::nearestRival($probe, $people, $subject['id']);

                $genuine[] = $ownBest;

                if ($rivalBest === null) {
                    // A single enrolled person at this outlet. Nothing to be
                    // confused with, so there is no margin to measure.
                    $correct++;

                    continue;
                }

                $impostor[] = $rivalBest;
                $margins[]  = $rivalBest - $ownBest;

                if ($ownBest < $rivalBest) {
                    $correct++;
                } else {
                    // The system would name the wrong person. The pair is
                    // reported by name because the fix is human: re-enrol
                    // them, or keep the PIN for these two.
                    $collisions[] = [
                        'a'        => $subject['label'],
                        'b'        => $rivalLabel,
                        'distance' => round($rivalBest, 4),
                    ];
                }
            }
        }

        return [
            'people'     => count($people),
            'probes'     => $probes,
            'correct'    => $correct,
            'undecided'  => $probes - $correct,
            'genuine'    => self::summary($genuine),
            'impostor'   => self::summary($impostor),
            'margin'     => self::summary($margins),
            'collisions' => self::dedupe($collisions),
        ];
    }

    /** Closest capture belonging to anybody else, and whose it is. */
    private static function nearestRival(array $probe, array $people, int $excludeId): array
    {
        $best  = null;
        $label = '';

        foreach ($people as $rival) {
            if ($rival['id'] === $excludeId) {
                continue;
            }

            foreach ($rival['captures'] as $candidate) {
                $d = FaceMatcher::euclidean($probe, $candidate);

                if ($best === null || $d < $best) {
                    $best  = $d;
                    $label = $rival['label'];
                }
            }
        }

        return [$best, $label];
    }

    /**
     * Where to draw the two lines, given what was measured.
     *
     * The threshold has to sit above almost every genuine match, or real
     * staff get refused at the door, and below almost every impostor, or the
     * wrong person gets credited. When those two demands cannot both be met
     * the honest answer is that identification is not safe here, and this
     * says so rather than picking a number that splits the difference.
     *
     * @return array{safe: bool, threshold: ?float, margin: ?float, reason: string}
     */
    public static function recommend(array $result): array
    {
        $genuineHigh  = $result['genuine']['p95']  ?? null;
        $impostorLow  = $result['impostor']['p05'] ?? null;

        if ($genuineHigh === null || $impostorLow === null) {
            return [
                'safe' => false, 'threshold' => null, 'margin' => null,
                'reason' => 'Not enough enrolled faces to measure anything. Two people with '
                    . 'two captures each is the bare minimum.',
            ];
        }

        if ($genuineHigh >= $impostorLow) {
            return [
                'safe' => false, 'threshold' => null, 'margin' => null,
                'reason' => sprintf(
                    'The distributions OVERLAP: genuine matches reach %.3f while the nearest '
                    . 'other person gets as close as %.3f. No threshold separates them — '
                    . 'identification would have to guess. Improve enrolment quality first.',
                    $genuineHigh, $impostorLow
                ),
            ];
        }

        // Inside the gap, biased toward the genuine end. Refusing somebody who
        // is who they say they are is a visible, recoverable annoyance; naming
        // the wrong person is a silent, wrong attendance record.
        $threshold = $genuineHigh + (($impostorLow - $genuineHigh) * 0.4);

        return [
            'safe'      => true,
            'threshold' => round($threshold, 3),
            // Half the observed gap: wide enough to mean something, not so
            // wide that ordinary pairs of colleagues trip it constantly.
            'margin'    => round(max(0.05, ($impostorLow - $genuineHigh) / 2), 3),
            'reason'    => sprintf(
                'Genuine matches stay under %.3f and the nearest other person stays above '
                . '%.3f, so there is a clear gap to put a line in.',
                $genuineHigh, $impostorLow
            ),
        ];
    }

    /** @return array{n: int, min: float, p05: float, median: float, p95: float, max: float}|array{n: int} */
    public static function summary(array $values): array
    {
        if ($values === []) {
            return ['n' => 0];
        }

        sort($values);

        return [
            'n'      => count($values),
            'min'    => round($values[0], 4),
            'p05'    => round(self::percentile($values, 0.05), 4),
            'median' => round(self::percentile($values, 0.50), 4),
            'p95'    => round(self::percentile($values, 0.95), 4),
            'max'    => round($values[count($values) - 1], 4),
        ];
    }

    /** Nearest-rank on an already-sorted list. No interpolation: with a few
     *  dozen samples it would invent precision that is not there. */
    private static function percentile(array $sorted, float $p): float
    {
        $index = (int) floor($p * (count($sorted) - 1));

        return $sorted[max(0, min(count($sorted) - 1, $index))];
    }

    /** The same two people collide once per capture; report the pair once. */
    private static function dedupe(array $collisions): array
    {
        $seen = [];

        foreach ($collisions as $row) {
            $key = implode('|', [min($row['a'], $row['b']), max($row['a'], $row['b'])]);

            if (! isset($seen[$key]) || $row['distance'] < $seen[$key]['distance']) {
                $seen[$key] = $row;
            }
        }

        return array_values($seen);
    }
}
