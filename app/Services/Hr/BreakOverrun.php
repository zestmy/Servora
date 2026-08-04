<?php

namespace App\Services\Hr;

/**
 * How much of a shift's break time is over the allowance, and how much of
 * that has not yet been charged for.
 *
 * The allowance is per SHIFT, not per break. Somebody with an hour's rest
 * who takes two forty-minute breaks is twenty minutes over — but neither
 * break on its own exceeds an hour, so judging them individually would find
 * nothing. Every calculation here works from the shift total.
 *
 * Charging happens as each break ends, so the figure has to be incremental:
 * what is owed now is the shift's overrun minus whatever earlier breaks in
 * the same shift were already charged for. Summing the charges across a
 * shift then gives exactly the shift's overrun, once.
 *
 * Pure and dependency-free on purpose — it decides money.
 */
final class BreakOverrun
{
    /** unsignedSmallInteger ceiling; beyond it the row is a data error. */
    private const MAX_MINUTES = 65_535;

    /** Used when neither the roster line nor the outlet says otherwise. */
    public const DEFAULT_ALLOWANCE_MINUTES = 60;

    /**
     * Rest minutes a shift is allowed.
     *
     * The ROSTER owns this: the shift's own rest_duration when set, else the
     * outlet's roster setting. There is deliberately no per-employee break
     * field — it would be a second source of truth for a number the roster
     * already holds, and the two would drift.
     */
    public static function allowanceFor(?\App\Models\RosterEntry $entry, ?int $outletId): int
    {
        if ($entry && $entry->rest_duration !== null) {
            return max(0, (int) $entry->rest_duration);
        }

        $setting = $outletId
            ? \App\Models\RosterSetting::where('outlet_id', $outletId)->first()
            : null;

        return max(0, (int) ($setting?->rest_duration ?? self::DEFAULT_ALLOWANCE_MINUTES));
    }

    /**
     * @param  int  $takenMinutes      total completed break minutes this shift
     * @param  int  $allowanceMinutes  the shift's rest allowance
     * @param  int  $alreadyCharged    overrun minutes charged earlier this shift
     * @return array{taken: int, overrun: int, chargeable: int}
     */
    public static function compute(int $takenMinutes, int $allowanceMinutes, int $alreadyCharged = 0): array
    {
        $taken     = (int) max(0, min(self::MAX_MINUTES, $takenMinutes));
        $allowance = max(0, $allowanceMinutes);
        $overrun   = max(0, $taken - $allowance);

        return [
            'taken'      => $taken,
            'overrun'    => $overrun,
            // Never negative: a manager who reduced an earlier charge must not
            // cause the next break to bill the difference back.
            'chargeable' => max(0, $overrun - max(0, $alreadyCharged)),
        ];
    }

    /**
     * RM owed for this break, respecting what the shift has already been
     * charged against its cap.
     *
     * The per-shift cap covers late arrival AND break overrun together —
     * they are one shift's worth of charges, and letting each kind have its
     * own cap would quietly double the ceiling a manager thought they set.
     */
    public static function amount(
        int $chargeableMinutes,
        float $ratePerMinute,
        ?float $capPerShift = null,
        float $alreadyChargedAmount = 0.0,
    ): float {
        if ($chargeableMinutes <= 0 || $ratePerMinute <= 0) {
            return 0.0;
        }

        $amount = $chargeableMinutes * $ratePerMinute;

        if ($capPerShift !== null) {
            $remaining = max(0.0, $capPerShift - max(0.0, $alreadyChargedAmount));
            $amount    = min($amount, $remaining);
        }

        return round($amount, 2);
    }
}
