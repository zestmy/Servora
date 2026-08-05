<?php

use App\Models\ClockEvent;
use Illuminate\Database\Migrations\Migration;

/**
 * Clear punches that are sitting in the review queue only because there was
 * no rostered shift.
 *
 * "No rostered shift" stopped being a reason to review — plenty of real
 * punches have no roster entry. Without this the rule only applies to future
 * punches and every existing one stays in the queue forever, which is the
 * half-fix that makes people stop trusting the queue.
 *
 * Deliberately narrow: only punches whose ONLY reviewable flag was no_shift,
 * and only those nobody has already decided on. A punch that was also outside
 * the geofence still needs a human.
 */
return new class extends Migration
{
    /** Flags that never required review. See ClockInService. */
    private const NOT_REVIEWABLE = ['late', 'no_shift'];

    public function up(): void
    {
        ClockEvent::withoutGlobalScopes()
            ->where('status', ClockEvent::STATUS_FLAGGED)
            ->whereNull('reviewed_at')
            ->whereNotNull('flags')
            ->chunkById(500, function ($events) {
                foreach ($events as $event) {
                    $flags = $event->flags ?? [];

                    // Must actually be the no_shift case, and nothing else.
                    if (! in_array('no_shift', $flags, true)) {
                        continue;
                    }
                    if (array_diff($flags, self::NOT_REVIEWABLE) !== []) {
                        continue;
                    }

                    // forceFill: status is not mass-assignable, and this must
                    // not touch updated_at semantics elsewhere.
                    $event->forceFill(['status' => ClockEvent::STATUS_VERIFIED])->save();
                }
            });
    }

    public function down(): void
    {
        // Not reversed: which punches were flagged for this reason alone is
        // not recorded once the status changes, and guessing would put
        // arbitrary punches back into someone's review queue.
    }
};
