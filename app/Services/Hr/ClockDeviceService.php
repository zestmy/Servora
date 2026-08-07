<?php

namespace App\Services\Hr;

use App\Models\ClockDevice;
use App\Models\User;
use App\Scopes\CompanyScope;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Pairing, credentials and the heartbeat for outlet kiosks.
 *
 * The only thing that turns a token into a ClockDevice. Everything that gates
 * a kiosk request goes through resolve(), so there is one place to be wrong
 * about what counts as a valid device.
 *
 * WHY PAIRING IS IN TWO HALVES. A manager creates the device row — naming it
 * and choosing its outlet — and gets a short code. Somebody then keys that
 * code into the tablet, which redeems it for a token.
 *
 * Two things fall out of that split, and both are the point:
 *
 *   Nobody types an admin password into a device that lives on a counter in a
 *   room full of people. The code is worth ten minutes and one outlet; a
 *   password is worth the company.
 *
 *   The outlet a kiosk vouches for is chosen by somebody who knows where it
 *   is going, before it goes there. A kiosk punch skips the geofence on the
 *   strength of that choice, so letting the tablet nominate its own outlet
 *   would hand the skipped check straight back to the device.
 */
class ClockDeviceService
{
    /**
     * Cookie holding the device token.
     *
     * A cookie rather than localStorage, and httpOnly: this is a long-lived
     * credential on a device left unattended in a public room, and there is no
     * reason for page scripts to be able to read it. What the page gets is a
     * copy handed to it deliberately — see tokenForPage() — which is a
     * narrower exposure than leaving the credential itself in reach.
     */
    public const COOKIE = 'servora_kiosk';

    /** Roughly a year. A kiosk is paired once and then left alone. */
    public const COOKIE_MINUTES = 525_600;

    /** Header the JSON endpoints authenticate on. See resolveFromRequest(). */
    public const HEADER = 'X-Kiosk-Token';

    /**
     * Don't write last_seen_at more often than this.
     *
     * The screen pings every minute and the middleware would otherwise stamp
     * a row on every request the tablet makes — an UPDATE per identify call,
     * several times a minute per outlet, for a column nothing reads more
     * precisely than "within the last ten minutes".
     */
    private const HEARTBEAT_THROTTLE_SECONDS = 55;

    /**
     * Start pairing: put a fresh code on the device and return it.
     *
     * Regenerating is always allowed. A manager who has lost the code, or
     * whose ten minutes ran out while they walked to the counter, needs
     * another one — and the old code dying with it is the correct behaviour
     * rather than a side effect.
     */
    public function issuePairingCode(ClockDevice $device): string
    {
        $code = $this->uniqueCode($device->company_id);

        $device->forceFill([
            'pairing_code'       => $code,
            'pairing_expires_at' => now()->addMinutes(ClockDevice::PAIRING_TTL_MINUTES),
        ])->save();

        return $code;
    }

    /**
     * Redeem a pairing code for a device token.
     *
     * Returns the RAW token, which is the only time it exists in readable
     * form — only its hash is stored. The caller's job is to get it onto the
     * tablet in a cookie and then forget it.
     *
     * @return array{device: ClockDevice, token: string}|null  null when the
     *         code is wrong, expired, or belongs to a revoked device.
     */
    public function redeem(int $companyId, string $code, ?Request $request = null): ?array
    {
        $code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($code)) ?? '');

        if ($code === '') {
            return null;
        }

        $device = ClockDevice::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('pairing_code', $code)
            ->active()
            ->first();

        if (! $device || ! $device->hasLivePairingCode()) {
            return null;
        }

        $token = Str::random(64);

        $device->forceFill([
            'token_hash'         => $this->hash($token),
            // Consumed. A code that stays live after it has been used is a
            // second credential for the same tablet, sitting in the history of
            // whatever chat app it was pasted into.
            'pairing_code'       => null,
            'pairing_expires_at' => null,
            'paired_at'          => now(),
            'last_seen_at'       => now(),
            'last_seen_ip'       => $request?->ip(),
            'user_agent'         => Str::limit((string) $request?->userAgent(), 512, ''),
        ])->save();

        return ['device' => $device, 'token' => $token];
    }

    /**
     * Authorise enrolment on this kiosk, returning the code to read out.
     *
     * Separate from the pairing code and deliberately so: pairing decides
     * WHICH OUTLET a device speaks for, enrolment decides WHOSE FACE it
     * records. Sharing one code would mean a manager handing over the ability
     * to do both when they meant one.
     */
    public function issueEnrolCode(ClockDevice $device, ?User $by = null): string
    {
        $code = $this->uniqueCode($device->company_id);

        $device->forceFill([
            'enrol_code'            => $code,
            'enrol_code_expires_at' => now()->addMinutes(ClockDevice::PAIRING_TTL_MINUTES),
            'enrol_by'              => $by?->id,
        ])->save();

        return $code;
    }

    /**
     * Open the enrolment window on a kiosk that has been given the code.
     *
     * The code is consumed, exactly as at pairing: it authorises one window,
     * not a standing ability to re-open one.
     *
     * @return bool whether the window is now open.
     */
    public function startEnrolment(ClockDevice $device, string $code): bool
    {
        $code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($code)) ?? '');

        if ($code === '' || ! $device->hasLiveEnrolCode() || ! hash_equals((string) $device->enrol_code, $code)) {
            return false;
        }

        $device->forceFill([
            'enrol_code'            => null,
            'enrol_code_expires_at' => null,
            'enrol_until'           => now()->addMinutes(ClockDevice::ENROL_WINDOW_MINUTES),
        ])->save();

        return true;
    }

    /**
     * Close the window immediately.
     *
     * The window closes by itself, so this is the "done, and I would rather
     * not leave it open while I walk away" path — which is the one somebody
     * standing at a counter with a queue behind them actually wants.
     */
    public function stopEnrolment(ClockDevice $device): void
    {
        $device->forceFill([
            'enrol_code'            => null,
            'enrol_code_expires_at' => null,
            'enrol_until'           => null,
        ])->save();
    }

    /**
     * The device holding this token, or null.
     *
     * Null for every way a token can stop being good — unknown, revoked,
     * belonging to another company — because all of them mean the same thing
     * to the caller: this tablet needs pairing again.
     */
    public function resolve(int $companyId, ?string $token): ?ClockDevice
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        $device = ClockDevice::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('token_hash', $this->hash($token))
            ->active()
            ->first();

        // The outlet has to still exist and still be open. A kiosk whose
        // outlet was closed last month is vouching for somewhere that is not
        // there, and every punch it takes would be recorded against it.
        if (! $device || ! $device->outlet || ! $device->outlet->is_active) {
            return null;
        }

        return $device;
    }

    /**
     * The device behind a request.
     *
     * @param  bool  $headerOnly  Refuse the cookie and require the header.
     *
     * The JSON endpoints pass true, and that is a CSRF decision rather than a
     * preference. A cookie is ambient authority — any page the tablet's
     * browser can be made to load could spend it — whereas a header has to be
     * set deliberately by script on this origin. Requiring the header is what
     * makes those endpoints safe to exempt from Laravel's CSRF token, which
     * they have to be: a kiosk screen stays open for a fourteen-hour shift and
     * the session token behind it does not.
     */
    public function resolveFromRequest(Request $request, int $companyId, bool $headerOnly = false): ?ClockDevice
    {
        $token = $request->header(self::HEADER);

        if (! $token && ! $headerOnly) {
            $token = $request->cookie(self::COOKIE);
        }

        return $this->resolve($companyId, is_string($token) ? $token : null);
    }

    /**
     * Record that this kiosk is alive.
     *
     * Throttled, and deliberately not inside a transaction: it is a hint for
     * a manager and an input to "was there a kiosk to use", and neither is
     * worth contending a row several times a minute for.
     */
    public function heartbeat(ClockDevice $device, ?Request $request = null): void
    {
        if ($device->last_seen_at
            && $device->last_seen_at->gt(now()->subSeconds(self::HEARTBEAT_THROTTLE_SECONDS))) {
            return;
        }

        $device->forceFill([
            'last_seen_at' => now(),
            'last_seen_ip' => $request?->ip() ?? $device->last_seen_ip,
            'user_agent'   => $request
                ? Str::limit((string) $request->userAgent(), 512, '')
                : $device->user_agent,
        ])->saveQuietly();
    }

    /**
     * Take a device out of service.
     *
     * The token dies with it — a revoked row with a live hash would still
     * resolve if the active() scope were ever dropped by accident, and there
     * is no reason to keep a credential nobody may use.
     *
     * The ROW stays. Punches point at it, and a tablet that walked out of the
     * building is exactly when somebody wants to ask which punches came from
     * it; deleting the device would take that answer away at the one moment it
     * is worth having.
     */
    public function revoke(ClockDevice $device, ?User $by = null): void
    {
        $device->forceFill([
            'revoked_at'         => now(),
            'revoked_by'         => $by?->id,
            'token_hash'         => null,
            'pairing_code'       => null,
            'pairing_expires_at' => null,
            // An open enrolment window is the last thing that should survive a
            // revocation — a tablet taken out of service must not still be
            // able to record faces on the way out.
            'enrol_code'            => null,
            'enrol_code_expires_at' => null,
            'enrol_until'           => null,
        ])->save();
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * A code nobody else in this company is holding.
     *
     * Scoped per company because it is only ever redeemed against one, and a
     * globally unique code would need to be longer to stay readable-aloud as
     * the platform grows.
     */
    private function uniqueCode(int $companyId): string
    {
        $alphabet = ClockDevice::CODE_ALPHABET;
        $length   = strlen($alphabet);

        for ($attempt = 0; $attempt < 12; $attempt++) {
            $code = '';

            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, $length - 1)];
            }

            $taken = ClockDevice::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $companyId)
                ->where('pairing_code', $code)
                ->exists();

            if (! $taken) {
                return $code;
            }
        }

        // Twelve collisions on a 31^6 space means something is very wrong with
        // the RNG. Fall back to something certainly unique rather than
        // returning a code that is already in play.
        return strtoupper(Str::random(8));
    }
}
