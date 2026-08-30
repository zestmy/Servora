<?php

namespace App\Services\Hr;

use App\Models\ClockSetting;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;

/**
 * Records that an employee's phone was open, and where it was if it will say.
 *
 * The single writer of employees.last_seen_*. Same principle as
 * ClockInService: the handset sends RAW OBSERVATIONS and nothing else. It
 * does not get to say whether it was on site, how old its fix was, or when
 * "now" is — a phone's clock is settable and this one is about to be shown to
 * a manager as a fact.
 *
 * TWO SEPARATE DECISIONS, and keeping them separate is most of the design:
 *
 *   THE TIMESTAMP is always recorded. "Opened the Staff Portal 4 minutes ago"
 *   is the same class of fact as users.last_active_at, which the manager app
 *   has recorded for every login since July, and it needs no coordinates.
 *
 *   THE COORDINATES are recorded only where the company has switched
 *   location_heartbeat on. A company that has not asked for this collects the
 *   timestamp and nothing else, and turning the setting off later stops the
 *   collection AND clears what was already gathered — see forget().
 *
 * WHY THIS IS NOT TRACKING, stated here because it is the thing somebody will
 * misread the column as. A ping only ever happens while the app is in the
 * foreground; there is no browser API that fires when it is not. Somebody who
 * clocks in and pockets their phone produces exactly one ping, and their row
 * says "8 minutes ago" and then "3 hours ago" and then nothing more. That is
 * the ceiling of what this can be, on any phone, and the screens are written
 * to say so rather than to imply a fresher answer than they have.
 */
class PresenceHeartbeat
{
    /**
     * Don't rewrite the row more often than this.
     *
     * The client throttles too, but the client is the untrusted half. Without
     * a floor here, a page that re-registered its listener on every Livewire
     * navigation would turn a walk through five tabs into five writes to a
     * row that half the HR screens read.
     */
    private const MIN_INTERVAL_SECONDS = 60;

    /**
     * Fixes vaguer than this are dropped rather than stored.
     *
     * A wifi-derived fix indoors is routinely a few hundred metres out, and a
     * cell-tower one can be several kilometres. Stored, it would render as
     * "1.8 km from Bangsar" — which reads as somebody being somewhere they are
     * not, and reads that way most confidently about the staff whose phones
     * have the worst GPS. The timestamp still lands; only the coordinates are
     * discarded.
     *
     * Deliberately NOT clock_settings.max_accuracy_m, which is the threshold
     * for flagging a PUNCH and is tuned against a geofence radius. This is a
     * display, and a display can afford to be pickier than a payroll record.
     */
    private const MAX_ACCURACY_M = 500;

    /**
     * @param  array{latitude?: mixed, longitude?: mixed, accuracy?: mixed}  $input
     *         Untrusted, all of it.
     */
    public function record(Employee $employee, array $input = []): void
    {
        $now = now();

        if ($employee->last_seen_at && $employee->last_seen_at->diffInSeconds($now) < self::MIN_INTERVAL_SECONDS) {
            return;
        }

        $update = ['last_seen_at' => $now];

        $location = $this->location($employee, $input);

        /*
         * A ping WITHOUT a usable fix clears the old one rather than leaving
         * it standing.
         *
         * This is the whole difference between "last seen" and a stale claim.
         * Somebody who granted location at 8am and revoked it at noon would
         * otherwise keep showing this morning's coordinates under a timestamp
         * that says "1 minute ago" — the freshest possible presentation of the
         * least current thing on the row. Better to show the time alone and
         * admit the location is not known.
         */
        $update['last_seen_latitude']   = $location['latitude'];
        $update['last_seen_longitude']  = $location['longitude'];
        $update['last_seen_accuracy_m'] = $location['accuracy'];

        // A bare UPDATE, not a save(): this fires on ordinary page views and
        // has no business waking model events, touching updated_at, or
        // colliding with a manager editing the same employee.
        DB::table('employees')->where('id', $employee->id)->update($update);

        $employee->forceFill($update);
    }

    /**
     * The coordinates worth keeping, or nulls.
     *
     * @return array{latitude: ?float, longitude: ?float, accuracy: ?int}
     */
    private function location(Employee $employee, array $input): array
    {
        $none = ['latitude' => null, 'longitude' => null, 'accuracy' => null];

        if (! ClockSetting::forCompany($employee->company_id)->location_heartbeat) {
            return $none;
        }

        $latitude  = $input['latitude']  ?? null;
        $longitude = $input['longitude'] ?? null;

        if (! Geo::isValidCoordinate($latitude, $longitude)) {
            return $none;
        }

        $accuracy = $input['accuracy'] ?? null;

        /*
         * NO ACCURACY MEANS NO FIX WORTH SHOWING.
         *
         * Every browser that answers getCurrentPosition() supplies
         * coords.accuracy — the spec makes it non-null. So a pair of
         * coordinates arriving without one did not come from the Geolocation
         * API, and the one thing that reliably sends that shape is somebody
         * calling the endpoint by hand. Refusing it costs nothing real and
         * closes the easiest way to put a chosen location on your own row.
         */
        if (! is_numeric($accuracy) || ! is_finite((float) $accuracy) || (float) $accuracy < 0) {
            return $none;
        }

        $accuracy = (int) round((float) $accuracy);

        if ($accuracy > self::MAX_ACCURACY_M) {
            return $none;
        }

        return [
            'latitude'  => (float) $latitude,
            'longitude' => (float) $longitude,
            'accuracy'  => $accuracy,
        ];
    }

    /**
     * Drop every stored location for a company, keeping the timestamps.
     *
     * Called when location_heartbeat is switched OFF. Switching a collection
     * off while keeping what it already collected is the version of this that
     * a company would have to explain later; this is the version they do not.
     */
    public static function forget(int $companyId): void
    {
        DB::table('employees')
            ->where('company_id', $companyId)
            ->whereNotNull('last_seen_latitude')
            ->update([
                'last_seen_latitude'   => null,
                'last_seen_longitude'  => null,
                'last_seen_accuracy_m' => null,
            ]);
    }
}
