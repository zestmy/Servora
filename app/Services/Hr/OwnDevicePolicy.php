<?php

namespace App\Services\Hr;

use App\Models\ClockDevice;
use App\Models\ClockSetting;
use App\Models\Employee;
use App\Models\Outlet;
use App\Scopes\CompanyScope;

/**
 * Whether this person may clock in on their own phone, right now.
 *
 * ONE answer, read by two callers, and that is the entire reason this is a
 * class rather than a method on either of them:
 *
 *   ClockInService  decides what to do with a punch that arrives.
 *   The staff app   decides whether to offer the camera at all.
 *
 * Those must agree. A screen that shows a big green button for a punch the
 * service is going to refuse is the worst version of this feature — somebody
 * holds their phone up at a doorway, waits for a GPS fix, holds still for the
 * face, and is then told to go and use the tablet. Asking the same object
 * makes that impossible rather than merely unlikely.
 *
 * The rules, in order:
 *
 *   1. A NAMED EXCEPTION wins outright. Area managers, drivers, offsite crew.
 *      It outranks the outlet's mode and the company switch alike, because a
 *      company that turns phone clock-in off still has to be able to keep its
 *      area manager working.
 *
 *   2. NO LIVE KIOSK, no case to answer. A dead tablet must never cost an
 *      outlet its attendance, so nothing below this line can refuse when there
 *      was no kiosk to have used.
 *
 *   3. Otherwise the phone is not the way in here, and the answer names the
 *      tablet that is.
 */
class OwnDevicePolicy
{
    /** The phone is fine. Nothing to record, nothing to say. */
    public const ALLOWED = 'allowed';

    /**
     * The phone is fine BECAUSE the kiosk is not answering.
     *
     * Distinct from ALLOWED so the punch can record why it was accepted at an
     * outlet that normally would not accept it — and so the staff app can say
     * so, which saves somebody walking to a tablet that is not working.
     */
    public const KIOSK_DOWN = 'kiosk_down';

    /** Use the kiosk. It is up, and it is what this outlet clocks in on. */
    public const REFUSED = 'refused';

    /**
     * @return array{status: string, kiosk: ?ClockDevice}
     */
    public function decide(Employee $employee, Outlet $outlet, ?ClockSetting $settings = null): array
    {
        $settings ??= ClockSetting::forCompany($employee->company_id);

        if ($employee->allow_byod === true) {
            return ['status' => self::ALLOWED, 'kiosk' => null];
        }

        // canUseOwnDevice() carries the tri-state: an explicit "kiosk only" on
        // the employee still pins them to the tablet even at an outlet that
        // allows phones, which a bare expectsKiosk() check here would drop.
        if ($employee->canUseOwnDevice($outlet) && $settings->byod_enabled) {
            return ['status' => self::ALLOWED, 'kiosk' => null];
        }

        $kiosk = $this->liveKiosk($employee->company_id, $outlet->id);

        return $kiosk
            ? ['status' => self::REFUSED, 'kiosk' => $kiosk]
            : ['status' => self::KIOSK_DOWN, 'kiosk' => null];
    }

    /**
     * A kiosk at this outlet that has pinged recently enough to be relied on.
     *
     * Returns the DEVICE rather than a boolean so the refusal can name it.
     * "Clock in on the Front counter iPad" sends somebody to a specific thing
     * on a specific counter; "clock in at the kiosk" sends them looking.
     */
    public function liveKiosk(int $companyId, int $outletId): ?ClockDevice
    {
        return ClockDevice::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('outlet_id', $outletId)
            ->active()
            ->paired()
            ->where('last_seen_at', '>', now()->subMinutes(ClockDevice::HEARTBEAT_STALE_MINUTES))
            ->orderByDesc('last_seen_at')
            ->first();
    }

    /**
     * What to tell somebody holding a phone they cannot punch with.
     *
     * Names the device when there is one to name, and always ends with what to
     * DO — the reader is standing in a doorway at the start of a shift.
     */
    public function message(Outlet $outlet, ?ClockDevice $kiosk = null): string
    {
        return $kiosk
            ? sprintf('Clock in on the %s at %s.', $kiosk->name, $outlet->name)
            : sprintf('Clock in on the kiosk at %s.', $outlet->name);
    }
}
