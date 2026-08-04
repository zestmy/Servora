<?php

namespace App\Services\Hr;

use Carbon\Carbon;

/**
 * The lateness formula, in one place and with no dependencies.
 *
 * Pulled out of ClockInService deliberately. This is the arithmetic that
 * decides how much of somebody's service charge disappears, and it is the
 * one part of the feature that must be verifiable without a database, a
 * roster, or a camera in the room.
 */
final class LatenessCharge
{
    /** unsignedSmallInteger ceiling; anything past it is a data error. */
    private const MAX_MINUTES = 65_535;

    /**
     * @return array{minutes: int, chargeable: int, amount: float}
     *         minutes    — whole minutes past the rostered start, before grace
     *         chargeable — what is left after grace
     *         amount     — RM, after the per-shift cap
     */
    public static function compute(
        Carbon $scheduledStart,
        Carbon $punchedAt,
        int $graceMinutes,
        float $ratePerMinute,
        ?float $cap = null,
    ): array {
        // Negative when early, which max() turns into a punctual zero.
        // Floored, not rounded: 59 seconds late is not a minute late, and
        // rounding up would charge for a second.
        $minutes = (int) max(0, min(
            self::MAX_MINUTES,
            floor($scheduledStart->diffInSeconds($punchedAt, false) / 60)
        ));

        $chargeable = max(0, $minutes - max(0, $graceMinutes));

        if ($chargeable === 0 || $ratePerMinute <= 0) {
            return ['minutes' => $minutes, 'chargeable' => $chargeable, 'amount' => 0.0];
        }

        $amount = $chargeable * $ratePerMinute;

        if ($cap !== null) {
            $amount = min($amount, max(0.0, $cap));
        }

        return [
            'minutes'    => $minutes,
            'chargeable' => $chargeable,
            'amount'     => round($amount, 2),
        ];
    }
}
