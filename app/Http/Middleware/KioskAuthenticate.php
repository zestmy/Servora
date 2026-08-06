<?php

namespace App\Http\Middleware;

use App\Models\ClockSetting;
use App\Services\Hr\ClockDeviceService;
use App\Services\Staff\StaffSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the outlet kiosk behind its device token.
 *
 * Nobody is signed in here — that is the entire point of a kiosk, which any
 * member of staff may walk up to. What is authenticated is the DEVICE, and
 * what that buys is the right to ask "who is this face?" about one outlet's
 * staff and to record a punch that skips the geofence.
 *
 * Runs after company.subdomain, so the company is resolved and the token is
 * checked against it: a tablet paired at one company must not be able to
 * identify anybody at another.
 *
 * Takes a parameter, and the JSON routes pass it:
 *
 *   clock.kiosk          cookie or header. For the page itself, which is a
 *                        plain navigation and has nothing but the cookie.
 *   clock.kiosk:header   header only. Refusing the cookie makes those routes
 *                        immune to CSRF by construction — a cookie is spent
 *                        by any page the browser can be made to load, a
 *                        header has to be set by script on this origin — and
 *                        that is what lets them be exempt from Laravel's CSRF
 *                        token. They have to be: a kiosk screen stays up for
 *                        a fourteen-hour shift and the session behind that
 *                        token expires in two.
 */
class KioskAuthenticate
{
    /** Container key holding the device for the rest of the request. */
    public const DEVICE_KEY = 'currentKioskDevice';

    public function __construct(
        private ClockDeviceService $devices,
        private StaffSession $staff,
    ) {
    }

    public function handle(Request $request, Closure $next, string $via = 'any'): Response
    {
        $companyId = $this->staff->companyId();

        if (! $companyId) {
            abort(404);
        }

        $device = $this->devices->resolveFromRequest($request, $companyId, $via === 'header');

        if (! $device) {
            return $this->bounce(
                $request,
                'unpaired',
                'This tablet is not paired. Ask a manager for a pairing code.',
            );
        }

        /*
         * The company switch, checked on every request rather than at pairing.
         * A company that turns kiosks off has decided something about how its
         * staff clock in, and a tablet already open in a kitchen must stop
         * being an answer to that question within the minute — not whenever it
         * next happens to be re-paired.
         */
        if (! ClockSetting::forCompany($companyId)->kiosk_enabled) {
            return $this->bounce(
                $request,
                'disabled',
                'Kiosk clock-in is switched off for this company.',
            );
        }

        app()->instance(self::DEVICE_KEY, $device);

        // Before the route, not after: an identify call that fails still
        // proves the tablet is awake and in front of somebody, and that is
        // exactly the state a manager is asking about.
        $this->devices->heartbeat($device, $request);

        return $next($request);
    }

    /**
     * One refusal, in whichever shape the caller can read.
     *
     * The JSON branch matters more than it looks: the punch screen polls in
     * the background all day, and an HTML redirect arriving where JSON was
     * expected is how a kiosk ends up silently doing nothing while appearing
     * to work.
     */
    private function bounce(Request $request, string $status, string $message): Response
    {
        if ($request->expectsJson() || $request->isMethod('POST')) {
            return response()->json(['status' => $status, 'message' => $message], 401);
        }

        return redirect()->route('clock.kiosk.pair')->with('kiosk_notice', $message);
    }
}
