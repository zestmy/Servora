<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Middleware\KioskAuthenticate;
use App\Models\ClockDevice;
use App\Models\ClockEvent;
use App\Models\ClockSetting;
use App\Models\Company;
use App\Models\Employee;
use App\Scopes\CompanyScope;
use App\Services\Hr\ClockDeviceService;
use App\Services\Hr\ClockInException;
use App\Services\Hr\ClockInService;
use App\Services\Hr\FaceIdentifier;
use App\Services\Hr\PunchState;
use App\Services\Staff\StaffSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * The outlet kiosk: a tablet on a counter that anybody may walk up to.
 *
 * Plain controllers and JSON rather than Livewire, for the reason the face
 * enrolment endpoints already give — this gates the whole feature and must not
 * depend on Livewire's JavaScript having started. It applies doubly here. The
 * screen runs a detection loop for fourteen hours on a device nobody is
 * watching, and the failure mode of a component that quietly stops re-rendering
 * is a kiosk that looks alive and records nothing.
 *
 * THE HANDSHAKE, and why it is two calls.
 *
 *   identify()  takes 128 floats and answers with a NAME and an opaque token.
 *               It never returns an employee id, and the token is encrypted,
 *               so the tablet is told who it is looking at without ever being
 *               in a position to assert it.
 *
 *   punch()     takes that token back. The identity comes out of the token,
 *               decrypted here, never out of the request body.
 *
 * A single call would have to trust an employee id from the browser, which
 * would make the whole face check decorative — anybody able to open devtools
 * could punch as anybody. This is the same principle the rest of the clock
 * runs on, applied to identity rather than to verdicts.
 */
class KioskController extends Controller
{
    /**
     * How long an identification is good for.
     *
     * Long enough to read a name and tap a button, short enough that a token
     * skimmed off the wire is worthless by the time anybody has it. It also
     * quietly bounds the "walked away mid-tap" case: the next person to stand
     * there gets their own identification, not the leftovers of the last one.
     */
    private const TOKEN_TTL_SECONDS = 60;

    /** Identify calls allowed per device per minute. */
    private const IDENTIFY_PER_MINUTE = 120;

    public function __construct(
        private ClockDeviceService $devices,
        private StaffSession $staff,
    ) {
    }

    /* ── Pairing ─────────────────────────────────────────────────────── */

    public function pair(Request $request)
    {
        return view('clock.kiosk.pair', [
            'company' => Company::find($this->staff->companyId()),
            'notice'  => session('kiosk_notice'),
            'alreadyPaired' => $this->devices->resolveFromRequest($request, (int) $this->staff->companyId()) !== null,
        ]);
    }

    /**
     * Redeem a pairing code.
     *
     * Throttled per IP because this is the one unauthenticated endpoint in the
     * kiosk, and a six-character code from a 31-letter alphabet is a billion
     * combinations — comfortable against a person, thin against a script left
     * running for a weekend.
     */
    public function storePair(Request $request)
    {
        $companyId = (int) $this->staff->companyId();

        abort_unless($companyId, 404);

        $key = 'kiosk-pair:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->withErrors([
                'code' => 'Too many attempts. Wait ' . RateLimiter::availableIn($key) . ' seconds.',
            ]);
        }

        $result = $this->devices->redeem($companyId, (string) $request->input('code'), $request);

        if (! $result) {
            RateLimiter::hit($key, 300);

            return back()->withErrors([
                'code' => 'That code is not valid, or it has expired. Ask for a new one.',
            ]);
        }

        RateLimiter::clear($key);

        /*
         * httpOnly, so page scripts cannot read the credential; secure, so it
         * never crosses a plain connection. The path is the staff app's own,
         * which keeps it off every other request this domain serves.
         */
        return redirect()->route('clock.kiosk.screen')->withCookie(
            Cookie::make(
                ClockDeviceService::COOKIE,
                $result['token'],
                ClockDeviceService::COOKIE_MINUTES,
                '/staff',
                null,
                $request->isSecure(),
                true,
                false,
                'Lax',
            )
        );
    }

    /* ── The screen ──────────────────────────────────────────────────── */

    public function screen(Request $request)
    {
        $device   = $this->device();
        $settings = ClockSetting::forCompany($device->company_id);
        $allowPin = (bool) $settings->kiosk_allow_pin;

        return view('clock.kiosk.screen', [
            'company'  => Company::find($device->company_id),
            'device'   => $device,
            'outlet'   => $device->outlet,
            'settings' => $settings,
            'allowPin' => $allowPin,
            /*
             * For the PIN fallback. Everyone at this outlet who has a PIN —
             * the same list, scoped the same way, as the staff sign-in screen.
             *
             * Not fetched at all when the fallback is off. This is a roster of
             * everybody who works here, rendered into a page on a counter in a
             * public room; when nothing can be done with it, it should not be
             * in the markup to be read.
             */
            'employees' => $allowPin ? $this->pinHolders($device) : collect(),
            /*
             * The device token, handed to the page deliberately so its scripts
             * can send it as a header. The cookie itself stays httpOnly and
             * unreadable; this is a copy with a job, not the credential left
             * lying around, and the JSON endpoints accept ONLY the header
             * precisely so that a cookie alone can never drive them.
             */
            'kioskToken' => $request->cookie(ClockDeviceService::COOKIE)
                ?? $request->header(ClockDeviceService::HEADER),
        ]);
    }

    /** Keeps last_seen_at fresh on a screen nobody is touching. */
    public function ping(): JsonResponse
    {
        // KioskAuthenticate already stamped the heartbeat on the way in; this
        // route exists so that an idle kiosk still counts as online, and it
        // deliberately does nothing else.
        return response()->json(['ok' => true]);
    }

    /* ── Identify ────────────────────────────────────────────────────── */

    public function identify(Request $request, FaceIdentifier $identifier, PunchState $state): JsonResponse
    {
        $device = $this->device();

        $key = 'kiosk-identify:' . $device->id;

        if (RateLimiter::tooManyAttempts($key, self::IDENTIFY_PER_MINUTE)) {
            return response()->json(['status' => 'busy', 'message' => 'Too many attempts. A moment.'], 429);
        }

        RateLimiter::hit($key, 60);

        $settings = ClockSetting::forCompany($device->company_id);

        $result = $identifier->identify(
            $device->company_id,
            $device->outlet_id,
            $request->input('descriptor'),
            $settings,
        );

        if ($result['status'] === FaceIdentifier::MATCHED) {
            return response()->json(
                $this->matchedPayload($result['employee'], $result['distance'], $device, $settings, $state)
            );
        }

        $allowPin = (bool) $settings->kiosk_allow_pin;

        if ($result['status'] === FaceIdentifier::AMBIGUOUS) {
            /*
             * Two people the model cannot separate. The shortlist is offered
             * as a SHORTCUT INTO THE PIN STEP and never as an answer — tapping
             * a name here still asks for that person's PIN.
             *
             * Letting a tap alone settle it would hand the decision straight
             * back to whoever is standing there, which is precisely the person
             * the margin gate exists to keep out of it. With the fallback off
             * there is nothing to shortcut into, so no names are sent at all —
             * a list of colleagues the camera half-matched is not something to
             * put on a counter screen for no reason.
             */
            return response()->json([
                'status'    => 'ambiguous',
                'message'   => $allowPin
                    ? 'More than one person matched. Tap your name and key your PIN.'
                    : 'Could not tell you apart from a colleague. Try again face-on, or ask your manager to record this shift.',
                'shortlist' => $allowPin
                    ? collect($result['shortlist'])
                        ->map(fn ($row) => [
                            'id'   => $row['employee']->id,
                            'name' => $row['employee']->name,
                        ])->values()
                    : [],
            ]);
        }

        // Every message ends with what to DO, and what there is to do changes
        // entirely with the fallback. Telling somebody to use a PIN that the
        // company has switched off is worse than saying nothing.
        return response()->json([
            'status'  => $result['status'],
            'message' => match (true) {
                $result['status'] === FaceIdentifier::NO_FACES && $allowPin
                    => 'Nobody at this outlet has been enrolled yet. Use your PIN.',
                $result['status'] === FaceIdentifier::NO_FACES
                    => 'Nobody at this outlet has been enrolled yet. Ask your manager.',
                $result['status'] === FaceIdentifier::BAD_INPUT && $allowPin
                    => 'That did not read as a face. Try again, or use your PIN.',
                $result['status'] === FaceIdentifier::BAD_INPUT
                    => 'That did not read as a face. Move into better light and try again.',
                $allowPin
                    => 'Not recognised. Try again, or use your PIN.',
                default
                    => 'Not recognised. Try again face-on, or ask your manager to record this shift.',
            },
        ]);
    }

    /**
     * What the confirm card needs, plus the token that lets it act.
     *
     * Cooldown is answered HERE rather than after the tap, so somebody walking
     * past their own kiosk twice is told "you already clocked in at 8:52"
     * instead of being handed a button that would clock them out.
     */
    private function matchedPayload(
        Employee $employee,
        ?float $distance,
        ClockDevice $device,
        ClockSetting $settings,
        PunchState $state,
    ): array {
        $recent = $state->recentShiftPunch($employee, (int) $settings->kiosk_cooldown_minutes);

        if ($recent) {
            return [
                'status'   => 'cooldown',
                'employee' => ['name' => $employee->name],
                'message'  => sprintf(
                    '%s, you already %s at %s.',
                    Str::before($employee->name, ' '),
                    $recent->type === ClockEvent::TYPE_IN ? 'clocked in' : 'clocked out',
                    $recent->happened_at->format('g:i a'),
                ),
            ];
        }

        $nextType  = $state->nextType($employee);
        $breakType = $state->nextBreakType($employee);

        return [
            'status'   => 'matched',
            // A name and nothing else. No id: the tablet is told who it is
            // looking at, and is never put in a position to assert it.
            'employee' => ['name' => $employee->name],
            'next'     => [
                'label'       => $nextType === ClockEvent::TYPE_IN ? 'Clock IN' : 'Clock OUT',
                'type'        => $nextType,
                'break'       => $breakType,
                'break_label' => match ($breakType) {
                    ClockEvent::TYPE_BREAK_START => 'Start break',
                    ClockEvent::TYPE_BREAK_END   => 'End break',
                    default                      => null,
                },
            ],
            'token' => $this->mintToken($employee, $device, $distance, FaceIdentifier::MATCHED),
        ];
    }

    /* ── Punch ───────────────────────────────────────────────────────── */

    public function punch(Request $request, ClockInService $clock, PunchState $state): JsonResponse
    {
        $device   = $this->device();
        $settings = ClockSetting::forCompany($device->company_id);

        [$employee, $identification, $distance] = $request->filled('token')
            ? $this->fromToken($request->input('token'), $device)
            : $this->fromPin($request, $device);

        if (! $employee) {
            /*
             * "Wrong PIN" is the right answer to a wrong PIN and the wrong
             * answer to a PIN that was never going to be accepted. Somebody
             * keying a PIN they know is correct, being told it is wrong, will
             * try it three more times and then report a broken kiosk — and the
             * manager will go looking at PINs. Name the actual reason.
             */
            return response()->json([
                'status'  => 'error',
                'message' => match (true) {
                    $request->filled('token') => 'That took too long. Look at the camera again.',
                    ! $settings->kiosk_allow_pin => 'PIN clock-in is switched off here. Ask your manager to record this shift.',
                    default => 'Wrong PIN.',
                },
            ], 422);
        }

        $intent = $request->input('intent') === 'break' ? 'break' : 'shift';

        // Cooldown again, not only at identify. The token is short-lived but a
        // double tap fits comfortably inside it, and the consequence of one is
        // a clock-OUT thirty seconds into a shift.
        if ($intent === 'shift') {
            $recent = $state->recentShiftPunch($employee, (int) $settings->kiosk_cooldown_minutes);

            if ($recent) {
                return response()->json([
                    'status'  => 'error',
                    'message' => sprintf(
                        'Already recorded at %s.',
                        $recent->happened_at->format('g:i a')
                    ),
                ], 422);
            }
        }

        $type = $state->typeFor($employee, $intent);

        if (! $type) {
            return response()->json([
                'status'  => 'error',
                'message' => 'That is not something you can do right now.',
            ], 422);
        }

        try {
            $event = $clock->punch($employee, $type, [
                // No coordinates, deliberately. A registered kiosk's location
                // is a fact a manager configured, and ClockInService skips the
                // geofence for exactly that reason — sending a GPS fix as well
                // would invite somebody to start believing the weaker of the
                // two.
                'descriptor'     => null,
                'selfie'         => is_string($request->input('selfie')) ? $request->input('selfie') : null,
                'device'         => $device,
                'identification' => $identification,
                'device_label'   => $device->name,
                'user_agent'     => $request->userAgent(),
                'ip'             => $request->ip(),
            ]);
        } catch (ClockInException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status'   => 'ok',
            'name'     => $employee->name,
            'headline' => $event->typeLabel(),
            'at'       => $event->happened_at->format('g:i a'),
            'flagged'  => $event->needsReview(),
            'note'     => $this->punchNote($event),
        ]);
    }

    /** One line under the confirmation, when there is something worth saying. */
    private function punchNote(ClockEvent $event): ?string
    {
        if ($event->minutes_late > 0) {
            return $event->minutes_late . ' min late';
        }

        return $event->needsReview() ? 'Sent to your manager for review' : null;
    }

    /* ── Identity resolution ─────────────────────────────────────────── */

    /**
     * Unwrap an identify token.
     *
     * Encrypted rather than signed, so the tablet never learns the employee id
     * it is holding, and bound to the DEVICE so a token minted at one outlet
     * cannot be replayed at another.
     *
     * @return array{0: ?Employee, 1: ?string, 2: ?float}
     */
    private function fromToken(mixed $token, ClockDevice $device): array
    {
        if (! is_string($token)) {
            return [null, null, null];
        }

        try {
            $payload = Crypt::decrypt($token);
        } catch (\Throwable $e) {
            return [null, null, null];
        }

        if (! is_array($payload)
            || ($payload['d'] ?? null) !== $device->id
            || ($payload['x'] ?? 0) < now()->timestamp) {
            return [null, null, null];
        }

        $employee = $this->employeeAtOutlet((int) ($payload['e'] ?? 0), $device);

        return [$employee, $payload['i'] ?? null, $payload['f'] ?? null];
    }

    /**
     * The fallback: pick a name, key the PIN.
     *
     * This is the path a cook at 6am with a hairnet and steamed-up glasses
     * takes, and the one a new hire takes on their first morning. It is also,
     * unavoidably, the weakest door in the room — which is why the punch it
     * produces carries no face at all and is flagged by ClockInService for a
     * manager to see.
     *
     * Rate limited per EMPLOYEE, not per IP: one kiosk is one address for the
     * whole outlet, so an IP limit would let one person fumbling their PIN
     * lock out everybody behind them in the queue.
     *
     * @return array{0: ?Employee, 1: ?string, 2: ?float}
     */
    private function fromPin(Request $request, ClockDevice $device): array
    {
        /*
         * Refused HERE, not merely hidden on the screen.
         *
         * The kiosk's endpoints answer to a device token, and that token is
         * readable in the page it is handed to. A company that has switched
         * the PIN fallback off has said something about how people clock in,
         * and leaving the door open behind a hidden button would make that
         * statement true only for people who do not open devtools.
         */
        if (! ClockSetting::forCompany($device->company_id)->kiosk_allow_pin) {
            return [null, null, null];
        }

        $employee = $this->employeeAtOutlet((int) $request->input('employee_id'), $device);

        if (! $employee) {
            return [null, null, null];
        }

        // The same key the staff sign-in uses. One PIN, one budget of guesses:
        // letting somebody get five tries per door would double it for free.
        $key = 'label-pin:' . $employee->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return [null, null, null];
        }

        if (! $employee->verifyLabelPin((string) $request->input('pin'))) {
            RateLimiter::hit($key, 60);

            return [null, null, null];
        }

        RateLimiter::clear($key);

        // 'pin' rather than a face status. ClockInService turns an ambiguous
        // identification into a flag; a plain PIN punch has no face to speak
        // of and is already flagged by the no_face path.
        return [$employee, 'pin', null];
    }

    private function mintToken(Employee $employee, ClockDevice $device, ?float $distance, string $identification): string
    {
        return Crypt::encrypt([
            'e' => $employee->id,
            'd' => $device->id,
            'i' => $identification,
            'f' => $distance,
            'x' => now()->addSeconds(self::TOKEN_TTL_SECONDS)->timestamp,
        ]);
    }

    /**
     * An active employee at this kiosk's outlet.
     *
     * Re-read from the database and re-checked against the device's outlet on
     * every call, never trusted from the payload. A token minted before
     * somebody was moved or deactivated must stop working the moment they
     * were, and it is a one-indexed-row query to be sure of it.
     */
    private function employeeAtOutlet(int $employeeId, ClockDevice $device): ?Employee
    {
        if ($employeeId < 1) {
            return null;
        }

        return Employee::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $device->company_id)
            ->where('outlet_id', $device->outlet_id)
            ->where('is_active', true)
            ->find($employeeId);
    }

    /** Staff at this outlet who have a PIN, for the fallback list. */
    private function pinHolders(ClockDevice $device)
    {
        return Employee::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $device->company_id)
            ->where('outlet_id', $device->outlet_id)
            ->where('is_active', true)
            ->whereNotNull('label_pin')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** The device the middleware resolved. */
    private function device(): ClockDevice
    {
        abort_unless(app()->bound(KioskAuthenticate::DEVICE_KEY), 403);

        return app(KioskAuthenticate::DEVICE_KEY);
    }
}
