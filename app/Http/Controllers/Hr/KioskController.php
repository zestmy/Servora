<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Middleware\KioskAuthenticate;
use App\Models\ClockDevice;
use App\Models\ClockEvent;
use App\Models\ClockSetting;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeFaceDescriptor;
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

    /**
     * Captures per person. Mirrors FaceEnrolmentController deliberately — the
     * two screens enrol into the same table and must not disagree on what
     * "enough" means, or somebody enrolled on the tablet would read as
     * incomplete on the HR screen.
     */
    private const MIN_CAPTURES = 3;
    private const MAX_CAPTURES = 8;

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

    /* ── Enrolment ───────────────────────────────────────────────────── */

    /**
     * Open the enrolment window with the code a manager read out.
     *
     * Throttled per device: the code is six characters, and this endpoint is
     * the one that decides whose face the kiosk will believe from now on.
     */
    public function enrolStart(Request $request): JsonResponse
    {
        $device = $this->device();
        $key    = 'kiosk-enrol:' . $device->id;

        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Too many attempts. Wait ' . RateLimiter::availableIn($key) . ' seconds.',
            ], 429);
        }

        if (! $this->devices->startEnrolment($device, (string) $request->input('code'))) {
            RateLimiter::hit($key, 300);

            return response()->json([
                'status'  => 'error',
                'message' => 'That code is not valid, or it has expired. Ask for a new one.',
            ], 422);
        }

        RateLimiter::clear($key);

        return response()->json([
            'status'       => 'ok',
            'minutes_left' => $device->fresh()->enrolmentMinutesLeft(),
            'employees'    => $this->enrolmentRoster($device),
        ]);
    }

    public function enrolStop(): JsonResponse
    {
        $this->devices->stopEnrolment($this->device());

        return response()->json(['status' => 'ok']);
    }

    /**
     * Record one capture against a member of staff.
     *
     * The window is re-checked HERE on every capture, not once when it opened.
     * A time box read only at the start is not a time box — this is the call
     * that writes to the root of trust, so it is the call that has to be sure.
     */
    public function enrolCapture(Request $request, FaceIdentifier $identifier): JsonResponse
    {
        $device = $this->device();

        if (! $device->enrolmentOpen()) {
            return response()->json([
                'status'  => 'closed',
                'message' => 'The enrolment window has closed. Ask a manager for a new code.',
            ], 403);
        }

        $employee = $this->employeeAtOutlet((int) $request->input('employee_id'), $device);

        if (! $employee) {
            return response()->json([
                'status'  => 'error',
                'message' => 'That person is not at this outlet.',
            ], 404);
        }

        $descriptor = $request->input('descriptor');

        if (! EmployeeFaceDescriptor::isValidDescriptor($descriptor)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'That capture could not be read. Fill more of the frame and try again.',
            ], 422);
        }

        $existing = EmployeeFaceDescriptor::withoutGlobalScope(CompanyScope::class)
            ->where('employee_id', $employee->id)
            ->where('model_version', EmployeeFaceDescriptor::MODEL_VERSION)
            ->count();

        if ($existing >= self::MAX_CAPTURES) {
            return response()->json([
                'status'  => 'full',
                'message' => 'Already ' . self::MAX_CAPTURES . ' captures on file — plenty.',
                'count'   => $existing,
            ], 422);
        }

        /*
         * Refuse a face that already belongs to SOMEBODY ELSE at this outlet.
         *
         * The one check that makes unattended-ish enrolment defensible. Without
         * it the whole system can be defeated once, quietly, in advance:
         * enrol your own face against a colleague's name and every punch
         * afterwards is genuinely, verifiably that face. A manager holding the
         * tablet is watching the person, not the screen, and would not catch
         * the wrong name being selected.
         *
         * Uses the company's own kiosk threshold, so it is exactly as strict
         * as the matching that will happen at the door.
         */
        $match = $identifier->identify(
            $device->company_id, $device->outlet_id, $descriptor,
            ClockSetting::forCompany($device->company_id)
        );

        if ($match['status'] === FaceIdentifier::MATCHED
            && (int) $match['employee']->id !== (int) $employee->id) {
            return response()->json([
                'status'  => 'conflict',
                'message' => sprintf(
                    'That face is already enrolled as %s. Check you have the right person selected.',
                    $match['employee']->name
                ),
            ], 422);
        }

        $capture = EmployeeFaceDescriptor::withoutGlobalScope(CompanyScope::class)->create([
            'company_id'    => $employee->company_id,
            'employee_id'   => $employee->id,
            'descriptor'    => array_map('floatval', $descriptor),
            'model_version' => EmployeeFaceDescriptor::MODEL_VERSION,
            'photo_path'    => $this->storeEnrolPhoto($employee, $request->input('photo')),
            // Nobody is signed in on a kiosk, so the capture is attributed to
            // the manager who authorised the window.
            'enrolled_by'   => $device->enrol_by,
        ]);

        return response()->json([
            'status'       => 'ok',
            'id'           => $capture->id,
            'count'        => $existing + 1,
            'enough'       => ($existing + 1) >= self::MIN_CAPTURES,
            'minutes_left' => $device->enrolmentMinutesLeft(),
        ]);
    }

    /** Everyone at this outlet, with how many captures they already have. */
    private function enrolmentRoster(ClockDevice $device): array
    {
        $counts = EmployeeFaceDescriptor::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $device->company_id)
            ->where('model_version', EmployeeFaceDescriptor::MODEL_VERSION)
            ->selectRaw('employee_id, count(*) as c')
            ->groupBy('employee_id')
            ->pluck('c', 'employee_id');

        return Employee::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $device->company_id)
            ->where('outlet_id', $device->outlet_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($e) => [
                'id'     => $e->id,
                'name'   => $e->name,
                'count'  => (int) ($counts[$e->id] ?? 0),
                'enough' => (int) ($counts[$e->id] ?? 0) >= self::MIN_CAPTURES,
            ])
            ->values()
            ->all();
    }

    /**
     * Keep the enrolment still. Private disk, never public — the same rule the
     * punch selfies follow, for the same reason.
     */
    private function storeEnrolPhoto(Employee $employee, mixed $dataUrl): ?string
    {
        if (! is_string($dataUrl) || ! preg_match('#^data:image/(jpeg|jpg|png);base64,#', $dataUrl, $m)) {
            return null;
        }

        if (strlen($dataUrl) > 2_500_000) {
            return null;
        }

        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);

        if ($binary === false || $binary === '' || ! @getimagesizefromstring($binary)) {
            return null;
        }

        $path = sprintf(
            'face-enrolments/%d/%s.%s',
            $employee->company_id, Str::uuid(), $m[1] === 'png' ? 'png' : 'jpg'
        );

        return \Illuminate\Support\Facades\Storage::disk('local')->put($path, $binary) ? $path : null;
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
        $allowPin = (bool) $settings->kiosk_allow_pin;

        $result = $identifier->identify(
            $device->company_id,
            $device->outlet_id,
            $request->input('descriptor'),
            $settings,
        );

        /*
         * `allow_pin` rides on EVERY identify response, and it is the setting
         * as it stands right now rather than as it stood when the page loaded.
         *
         * A kiosk is the one screen in the building nobody ever reloads — it
         * is opened once, put in a stand, and left for weeks. Without this, a
         * manager who switches the PIN fallback off watches the tablet go on
         * offering a PIN indefinitely and reasonably concludes the setting
         * does nothing. (The server refuses those punches either way, so the
         * hole is closed regardless; this is about the screen telling the
         * truth about itself.)
         */
        if ($result['status'] === FaceIdentifier::MATCHED) {
            return response()->json(
                ['allow_pin' => $allowPin]
                + $this->matchedPayload($result['employee'], $result['distance'], $device, $settings, $state)
            );
        }

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
                'allow_pin' => $allowPin,
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
            'allow_pin' => $allowPin,
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
                // The identification, and the distance it was made at, carried
                // forward from /identify. The descriptor above stays null — the
                // tablet sent it to that call, not this one — so without these
                // two the punch would look to ClockInService like a face that
                // was never captured, and every clean kiosk punch would be
                // flagged for a manager who has nothing to decide.
                'identification' => $identification,
                'face_distance'  => $distance,
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

        // A PIN, not a face status. This punch has no face behind it whatever
        // the camera was pointed at, so it carries no distance and is flagged
        // by ClockInService for a manager — deliberately. It is the weakest
        // door in the room and it is the one thing here worth a human look.
        return [$employee, FaceIdentifier::PIN_FALLBACK, null];
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
