<?php

namespace App\Services\Hr;

use App\Models\ClockDevice;
use App\Models\ClockSetting;
use App\Models\Employee;
use App\Models\Outlet;
use App\Scopes\CompanyScope;

/**
 * The rotating code an outlet kiosk shows, which a phone scans to clock in.
 *
 * WHAT IT PROVES: that the phone was pointed at that kiosk within the last few
 * seconds. The kiosk is bolted to a counter at an outlet a manager chose when
 * pairing it, so seeing its screen is a stronger statement about where
 * somebody is than a GPS fix taken indoors — and nothing a mock-location app
 * can contribute to.
 *
 * WHY IT ROTATES: buddy punching. A code that never changed could be
 * photographed once and sent round the group chat for the rest of the year. A
 * code that changes every half minute has to be relayed live, by somebody
 * standing at the kiosk, to a colleague who then still has to pass the face
 * check on their own phone.
 *
 * STATELESS BY DESIGN. The token is the kiosk's id, the time window it belongs
 * to, and an HMAC over both under the app key — so issuing one writes nothing,
 * and a kiosk can show a fresh code every few seconds all day without a row
 * per code. Checking one is arithmetic.
 *
 * A code is accepted during its own window and the one after, so somebody who
 * scans in the last second of a window is not refused while they hold still
 * for the face. The effective life is therefore between one and two rotations.
 */
class KioskQrToken
{
    /** Bumped if the token's layout ever changes, so old codes simply fail. */
    private const VERSION = 'k1';

    /**
     * A fresh code for this kiosk, and how long until the next one.
     *
     * @return array{token: string, expires_in: int, rotate_seconds: int}
     */
    public function issue(ClockDevice $device, ?int $now = null): array
    {
        $now     = $now ?? time();
        $seconds = ClockSetting::forCompany($device->company_id)->qrRotateSeconds();
        $window  = intdiv($now, $seconds);

        return [
            'token'          => $this->sign($device->id, $seconds, $window),
            // To the window boundary, so every kiosk in the company turns over
            // together and the screen can count down to something real.
            'expires_in'     => ($window + 1) * $seconds - $now,
            'rotate_seconds' => $seconds,
        ];
    }

    /**
     * The kiosk this token came from, provided it is genuine, current, and
     * belongs to the outlet this person clocks in at.
     *
     * @throws ClockInException naming what to do about it
     */
    public function verify(string $token, Employee $employee, Outlet $outlet, ?int $now = null): ClockDevice
    {
        $parts = explode('.', trim($token));

        if (count($parts) !== 5 || $parts[0] !== self::VERSION) {
            throw new ClockInException('That is not a kiosk clock-in code. Scan the QR on the kiosk screen.');
        }

        [, $deviceId, $seconds, $window, $signature] = $parts;

        if (! ctype_digit($deviceId) || ! ctype_digit($seconds) || ! ctype_digit($window)
            || ! hash_equals($this->sign((int) $deviceId, (int) $seconds, (int) $window), trim($token))) {
            throw new ClockInException('That is not a kiosk clock-in code. Scan the QR on the kiosk screen.');
        }

        $current = intdiv($now ?? time(), max(1, (int) $seconds));

        if (! in_array((int) $window, [$current, $current - 1], true)) {
            throw new ClockInException(sprintf(
                'That kiosk code has expired — it changes every %d seconds. Scan the one on the kiosk screen now.',
                (int) $seconds,
            ));
        }

        $device = ClockDevice::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $employee->company_id)
            ->active()
            ->paired()
            ->find((int) $deviceId);

        if (! $device) {
            throw new ClockInException('That kiosk is no longer registered. Ask your manager, or scan another kiosk at your outlet.');
        }

        // The employee's own posting, the same outlet the punch is recorded
        // against. Scanning the kiosk at the branch next door proves somebody
        // is at the branch next door — which is not where their shift is.
        if ((int) $device->outlet_id !== (int) $outlet->id) {
            throw new ClockInException(sprintf(
                'That code is from a kiosk at another outlet. Scan the kiosk at %s.',
                $outlet->name,
            ));
        }

        return $device;
    }

    private function sign(int $deviceId, int $seconds, int $window): string
    {
        $payload = implode('.', [self::VERSION, $deviceId, $seconds, $window]);

        // Short enough to keep the QR sparse (a denser code scans worse off a
        // screen across a counter), long enough that guessing one inside a
        // thirty-second window is not a thing.
        $mac = substr(hash_hmac('sha256', $payload, $this->key()), 0, 24);

        return $payload . '.' . $mac;
    }

    /** A key of its own, derived from the app key, so it signs nothing else. */
    private function key(): string
    {
        return hash_hmac('sha256', 'servora-kiosk-qr', (string) config('app.key'));
    }
}
