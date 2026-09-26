<?php

namespace App\Services\Hr;

use App\Models\ClockDevice;
use App\Models\ClockSetting;
use App\Models\Employee;
use App\Models\Outlet;

/**
 * Whether this person's phone punch needs the kiosk's QR, right now.
 *
 * One answer for two callers, exactly as OwnDevicePolicy is and for the same
 * reason: the staff app decides whether to open the scanner, ClockInService
 * decides whether to accept the punch, and a screen that skips the scan for a
 * punch the service will then refuse is the failure this class exists to rule
 * out.
 *
 * The rules, in order:
 *
 *   1. The company has it OFF — nothing to scan, nothing changes.
 *   2. The person may CLOCK IN ANYWHERE — area managers, drivers. Their
 *      location was never the question, so there is nothing for a code to
 *      prove.
 *   3. NO LIVE KIOSK at their outlet — nothing is showing a code. A dead
 *      tablet must never cost an outlet its attendance, so the phone clocks
 *      in the way it always did.
 *   4. The outlet otherwise REFUSES phones (OwnDevicePolicy) — the code is the
 *      phone's only way in, so it is required whatever the company mode.
 *   5. Otherwise the company mode decides: required, or optional.
 */
class KioskQrPolicy
{
    public const NONE     = 'none';
    public const OPTIONAL = 'optional';
    public const REQUIRED = 'required';

    public function __construct(private OwnDevicePolicy $ownDevice)
    {
    }

    /**
     * @return array{need: string, kiosk: ?ClockDevice}
     */
    public function decide(Employee $employee, Outlet $outlet, ?ClockSetting $settings = null): array
    {
        $settings ??= ClockSetting::forCompany($employee->company_id);

        if ($settings->qrMode() === ClockSetting::QR_OFF || $employee->canClockAnywhere()) {
            return ['need' => self::NONE, 'kiosk' => null];
        }

        $kiosk = $this->ownDevice->liveKiosk($employee->company_id, $outlet->id);

        if (! $kiosk) {
            return ['need' => self::NONE, 'kiosk' => null];
        }

        $phonesRefused = $this->ownDevice->decide($employee, $outlet, $settings)['status'] === OwnDevicePolicy::REFUSED;

        $need = $phonesRefused || $settings->qrMode() === ClockSetting::QR_REQUIRED
            ? self::REQUIRED
            : self::OPTIONAL;

        return ['need' => $need, 'kiosk' => $kiosk];
    }
}
