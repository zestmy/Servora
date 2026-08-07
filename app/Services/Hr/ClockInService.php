<?php

namespace App\Services\Hr;

use App\Jobs\ResolveClockEventAddress;
use App\Models\AttendanceCode;
use App\Models\AttendanceRecord;
use App\Models\ClockDevice;
use App\Models\ClockEvent;
use App\Models\ClockSetting;
use App\Models\Employee;
use App\Models\EmployeeFaceDescriptor;
use App\Models\Outlet;
use App\Scopes\CompanyScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Records a clock-in or clock-out and everything that was checked about it.
 *
 * The single writer of clock_events. Livewire components collect the input
 * and render the result; all of the judgement lives here so the staff app,
 * a future kiosk, and any back-fill tool cannot drift apart on what "late"
 * or "on site" means.
 *
 * Two principles run through it:
 *
 *   A failed CHECK flags the punch; a missing INPUT refuses it. By default,
 *   somebody whose face does not match still gets their attendance recorded,
 *   with a selfie for their manager to look at — refusing them would turn a
 *   beard into a missing day's pay, and the manager, not the model, is who
 *   should decide. But somebody who never turned the camera on has produced
 *   no evidence at all, and that is worth stopping at the door where it can
 *   still be fixed.
 *
 *   `require_face_match` lets a company overrule the first half of that and
 *   refuse a mismatched CLOCK-IN outright. It is off by default and it is
 *   narrow on purpose: it never applies to somebody with no enrolled face
 *   (there is nothing to match), never to a break, and never to a clock-out —
 *   see assessFace() for why each of those would do more harm than the check
 *   prevents.
 *
 *   Nothing here trusts a verdict computed by the phone. The device sends
 *   raw observations — coordinates, a descriptor — and every comparison
 *   against the company's thresholds happens on this side.
 *
 * TWO WAYS IN, judged by the same rules but not by the same evidence.
 *
 *   BYOD   somebody's own phone. It has to prove where it is, because it
 *          could be anywhere, and that proof is a GPS fix against the
 *          outlet's geofence.
 *
 *   KIOSK  a tablet registered to one outlet by a manager who knew where it
 *          was going. Its location is a fact somebody configured rather than
 *          a claim a handset makes, so a kiosk punch skips the geofence
 *          entirely — no indoor fix, no accuracy tolerance, and nothing a
 *          mock-location app can contribute. Registering the device replaces
 *          the measurement rather than adding to it.
 *
 * What a kiosk punch does NOT get is a free pass on identity. It gets the
 * opposite: the face had to name somebody out of everybody at the outlet
 * before the punch could be offered at all. What it does get is leniency
 * about a face that could not be read, because the kiosk's own fallback is
 * the PIN, and refusing a punch that already went through the fallback would
 * leave the person no way in at all.
 */
class ClockInService
{
    /** Biggest selfie accepted, before base64 decoding. */
    private const MAX_SELFIE_BYTES = 2_500_000;

    public function __construct(
        private ShiftResolver $shifts,
        private FaceMatcher $faces,
        private OwnDevicePolicy $ownDevice,
        private PunchState $state,
    ) {
    }

    /**
     * @param  array{
     *     latitude?: mixed, longitude?: mixed, accuracy?: mixed,
     *     descriptor?: mixed, selfie?: ?string, reason?: ?string,
     *     device_label?: ?string, user_agent?: ?string, ip?: ?string,
     *     source?: ?string, device?: ?ClockDevice, identification?: ?string,
     *     face_distance?: mixed,
     * }  $input  Raw observations from the device. Every value is untrusted
     *            EXCEPT `device`, `identification` and `face_distance`, which
     *            the kiosk middleware and controller resolved from a token this
     *            server minted — findings of ours, handed back, not claims.
     *
     * @throws ClockInException when the punch cannot be recorded at all.
     */
    public function punch(Employee $employee, string $type, array $input): ClockEvent
    {
        $type = in_array($type, [
            ClockEvent::TYPE_OUT, ClockEvent::TYPE_BREAK_START, ClockEvent::TYPE_BREAK_END,
        ], true) ? $type : ClockEvent::TYPE_IN;

        $device = $input['device'] ?? null;
        $device = $device instanceof ClockDevice ? $device : null;

        /*
         * The source is derived from the DEVICE, not from what was sent.
         * A caller holding a verified kiosk token is a kiosk punch and a
         * caller without one is not, and letting a field decide that would
         * make "source: kiosk" a request anybody could make — which is to say
         * a way to skip the geofence by typing a word.
         */
        $source = $device ? ClockEvent::SOURCE_KIOSK : ClockEvent::SOURCE_BYOD;

        $outlet = $this->outletFor($employee);

        // now(), not a time from the device: a phone's clock is settable, and
        // this one decides whether its owner was late.
        $at       = now();
        $settings = ClockSetting::forCompany($employee->company_id);

        $flags = [];

        $shift = $this->shifts->resolve($employee, $at, $type);

        /*
         * THE DAY THIS PUNCH BELONGS TO, which is not always the day it
         * happened on.
         *
         * A rostered punch takes its shift's date, as before. An unrostered one
         * used to take today's, and that was wrong in the one case it mattered:
         * a shift that crosses midnight. Somebody who clocked in at 9pm with no
         * roster entry and clocked out at 2am produced an IN dated yesterday
         * and an OUT dated today — two half-shifts, neither of which pairs up.
         *
         * Worse, it contradicted the screen they were standing at. PunchState
         * reads a rolling day, so the kiosk correctly offered "Clock OUT"; this
         * then looked for a clock-in on TODAY's date, found none, and flagged
         * the punch "Clocked out without clocking in" — a punch the system
         * itself had just proposed. So: when a shift is open, the punch closing
         * it joins it.
         */
        $open = $type === ClockEvent::TYPE_IN ? null : $this->state->openPunch($employee);

        $workDate = match (true) {
            $shift !== null => Carbon::parse($shift['entry']->day_date->format('Y-m-d')),
            $open !== null  => Carbon::parse($open->work_date->format('Y-m-d')),
            default         => $at->copy()->startOfDay(),
        };

        if (! $shift) {
            $flags[] = 'no_shift';
        }

        // A break punch is judged leniently. It cannot open or close
        // attendance — its only consequence is a charge against the person
        // making it — so refusing one because the camera failed would leave
        // somebody unable to end a break that is costing them money by the
        // minute. Everything is still recorded; nothing is enforced.
        $lenient = $this->isBreak($type);
        $isKiosk = $source === ClockEvent::SOURCE_KIOSK;

        /*
         * FIRST, ahead of the location and face checks, because it is the only
         * one of the three that can be answered without asking anything of the
         * person. Making somebody wait for a GPS fix and hold up their face,
         * and only then telling them their company does not accept phone
         * punches, is thirty seconds spent to arrive at a refusal that was
         * knowable before they pressed the button.
         */
        if (! $isKiosk) {
            $this->assessOwnDevice($employee, $outlet, $settings, $flags, $lenient);
        }

        $location = $isKiosk
            ? $this->kioskLocation()
            : $this->assessLocation($employee, $input, $outlet, $settings, $flags, $lenient);

        /*
         * A kiosk punch is lenient about the face for the same reason a break
         * is, and it is worth being explicit about why, because on the face of
         * it a kiosk is where you would want the check STRICTEST.
         *
         * The check has already happened. Getting this far on a kiosk means
         * either the face named this person out of the whole outlet, or the
         * face could not and they keyed their PIN instead. Throwing here would
         * refuse the second group — who are exactly the people the fallback
         * exists for: the cook at 6am with a hairnet and steamed-up glasses,
         * and the new hire nobody has enrolled yet. They are recorded, they
         * are flagged, and a manager decides.
         */
        $face = $this->assessFace($employee, $input, $settings, $flags, $lenient || $isKiosk, $type);

        // How the kiosk arrived at this person. Recorded as a flag rather than
        // decided here: the identification itself is FaceIdentifier's job, but
        // what a close call MEANS for the punch is this file's, like every
        // other verdict.
        if (($input['identification'] ?? null) === FaceIdentifier::AMBIGUOUS) {
            $flags[] = 'face_ambiguous';
        }

        [$minutesLate, $chargeable, $penalty] = $this->assessLateness(
            $type, $shift, $at, $settings, $flags
        );

        if ($type === ClockEvent::TYPE_IN) {
            $priorIn = $this->countedPunches($employee, $workDate, ClockEvent::TYPE_IN)->first();

            if ($priorIn) {
                $flags[] = 'duplicate';

                // Never charge the same shift twice. The first punch already
                // carries the penalty; a repeat tap must not compound it.
                $chargeable = 0;
                $penalty    = 0.0;
            }
        } elseif (! $open && ! $this->countedPunches($employee, $workDate, ClockEvent::TYPE_IN)->first()) {
            // $open is asked FIRST and answers the actual question — "is there
            // a clock-in this closes" — over a rolling day, the same window the
            // screen used to decide what to offer. The work_date lookup stays
            // behind it for the case a shift was resolved: a rostered punch
            // belongs to its roster day whether or not the last 24 hours agree.
            $flags[] = 'no_open_punch';
        }

        if ($type === ClockEvent::TYPE_BREAK_END) {
            [$minutesLate, $chargeable, $penalty] = $this->assessBreak(
                $employee, $workDate, $shift, $at, $settings, $flags
            );
        }

        $selfiePath = $this->storeSelfie($employee, $input['selfie'] ?? null);

        // Which flags actually send a punch to a human. The list is on the
        // model, because the staff app explains this decision using the same
        // one — see ClockEvent::NON_REVIEWABLE_FLAGS for what is on it and why.
        $reviewable = array_values(array_diff($flags, ClockEvent::NON_REVIEWABLE_FLAGS));

        $event = ClockEvent::create([
            'company_id'      => $employee->company_id,
            'outlet_id'       => $outlet->id,
            'employee_id'     => $employee->id,
            'roster_entry_id' => $shift['entry']->id ?? null,
            'type'            => $type,
            'source'          => $source,
            'clock_device_id' => $device?->id,
            'work_date'       => $workDate->toDateString(),
            'happened_at'     => $at,

            'latitude'        => $location['latitude'],
            'longitude'       => $location['longitude'],
            'accuracy_m'      => $location['accuracy'],
            'distance_m'      => $location['distance'],
            'within_geofence' => $location['within'],

            'face_distance'   => $face['distance'],
            'face_verified'   => $face['verified'],
            'selfie_path'     => $selfiePath,

            'minutes_late'            => $minutesLate,
            'chargeable_late_minutes' => $chargeable,
            'penalty_amount'          => $penalty,

            'status' => $reviewable === []
                ? ClockEvent::STATUS_VERIFIED
                : ClockEvent::STATUS_FLAGGED,
            'flags'  => $flags === [] ? null : array_values(array_unique($flags)),

            'reason'       => $this->trimmed($input['reason'] ?? null, 1000),
            'device_label' => $this->trimmed($input['device_label'] ?? null, 255),
            'user_agent'   => $this->trimmed($input['user_agent'] ?? null, 512),
            'ip_address'   => $input['ip'] ?? null,
        ]);

        if ($type === ClockEvent::TYPE_IN && $settings->mark_attendance) {
            $this->markPresent($employee, $outlet, $workDate);
        }

        // Queued, never inline: the punch is already recorded and a member of
        // staff at a door must not wait on a geocoder, nor fail because one is
        // down. Only when the company has opted in and coordinates exist.
        if ($settings->resolve_addresses && $event->latitude !== null) {
            ResolveClockEventAddress::dispatch($event->id);
        }

        return $event;
    }

    /**
     * Which outlet the punch is recorded against.
     *
     * The employee's own posting, not wherever the phone happens to be. A
     * cook covering a shift at another branch is still that branch's problem
     * to roster, and silently re-homing the punch to the nearest outlet would
     * move someone's attendance between payroll scopes without anyone asking.
     */
    private function outletFor(Employee $employee): Outlet
    {
        $outlet = $employee->outlet_id
            ? Outlet::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $employee->company_id)
                ->find($employee->outlet_id)
            : null;

        if (! $outlet) {
            throw new ClockInException(
                'Your staff record is not assigned to an outlet yet, so there is nowhere to record this. Ask your manager to set it.'
            );
        }

        return $outlet;
    }

    /**
     * @return array{latitude: ?float, longitude: ?float, accuracy: ?int, distance: ?int, within: bool}
     */
    private function assessLocation(Employee $employee, array $input, Outlet $outlet, ClockSetting $settings, array &$flags, bool $lenient = false): array
    {
        $result = [
            'latitude' => null, 'longitude' => null, 'accuracy' => null,
            'distance' => null, 'within'   => false,
        ];

        $hasFix = Geo::isValidCoordinate($input['latitude'] ?? null, $input['longitude'] ?? null);

        if (! $hasFix) {
            if ($settings->require_gps && ! $lenient) {
                throw new ClockInException(
                    'Location is switched off. Allow location for this site in your browser, then try again.'
                );
            }

            $flags[] = 'no_location';

            return $result;
        }

        $result['latitude']  = (float) $input['latitude'];
        $result['longitude'] = (float) $input['longitude'];

        $accuracy = $input['accuracy'] ?? null;
        if (is_numeric($accuracy) && is_finite((float) $accuracy) && (float) $accuracy >= 0) {
            $result['accuracy'] = (int) min(65535, round((float) $accuracy));
        }

        if (! Geo::isValidCoordinate($outlet->latitude, $outlet->longitude) || ! $outlet->clock_radius_m) {
            // Nothing to measure against. Flagged rather than passed: an
            // unconfigured outlet must not read as "everyone was on site".
            $flags[] = 'no_outlet_fence';

            return $result;
        }

        $distance = Geo::distanceMetres(
            $result['latitude'], $result['longitude'],
            (float) $outlet->latitude, (float) $outlet->longitude
        );

        $result['distance'] = (int) min(4_294_967_295, round($distance));

        /*
         * The fix's own error is added to the radius before comparing. A
         * phone reporting 40m accuracy standing 160m from a 150m fence could
         * genuinely be inside it, and charging someone for their handset's
         * indoor GPS is not defensible. A fix too vague to mean anything is
         * flagged separately below.
         */
        $tolerance        = $result['accuracy'] ?? 0;
        $result['within'] = $distance <= ((int) $outlet->clock_radius_m + $tolerance);

        if ($result['accuracy'] !== null && $result['accuracy'] > $settings->max_accuracy_m) {
            $flags[] = 'weak_location';
        }

        if (! $result['within']) {
            /*
             * Somebody whose job is not at the outlet.
             *
             * Returned before any of the three consequences below, so being
             * away is neither refused, nor made to carry a typed reason, nor
             * flagged. Every one of those is the correct response to an
             * UNEXPECTED distance and the wrong response to an expected one:
             * an area manager touring four branches would otherwise be flagged
             * twice a day, every day, for the single most ordinary fact about
             * their job — and a review queue full of those is a review queue
             * nobody opens.
             *
             * Note what is already in $result by this point and is returned
             * intact: the coordinates, the accuracy, and the distance in
             * metres. The measurement is kept, only the verdict is dropped, so
             * a manager reading a driver's day still sees exactly where each
             * punch was made. weak_location is likewise left alone above — a
             * fix too vague to mean anything is a fact about the fix, not
             * about where this person is allowed to be.
             */
            if ($employee->canClockAnywhere()) {
                return $result;
            }

            if ($settings->require_gps && ! $settings->allow_offsite_with_reason && ! $lenient) {
                throw new ClockInException(sprintf(
                    'You are about %s from %s. Clock in when you get to the outlet.',
                    $this->humanDistance($distance), $outlet->name
                ));
            }

            if ($settings->require_gps && ! $lenient && trim((string) ($input['reason'] ?? '')) === '') {
                throw new ClockInException(sprintf(
                    'You are about %s from %s. Add a short reason and try again.',
                    $this->humanDistance($distance), $outlet->name
                ));
            }

            $flags[] = 'outside_geofence';
        }

        return $result;
    }

    /**
     * Where a kiosk punch happened: at the outlet, because the tablet is.
     *
     * No coordinates are recorded, and none are wanted. The device is bolted
     * to a counter at an outlet a manager chose when they paired it, which is
     * a far better answer than a GPS fix — a mall basement routinely reports
     * three hundred metres of error, and a rooted handset can report anything
     * at all. A registered device replaces the measurement rather than
     * supplementing it.
     *
     * within_geofence is set TRUE with a null distance, and that pairing is
     * deliberate. The column means "was this punch at the outlet", and for a
     * kiosk the answer is yes on stronger evidence than the column was built
     * for; leaving it false so it matched the absent coordinates would tell
     * every report that counts it that nobody at a kiosk outlet was ever on
     * site. The null distance is what says the basis was not a measurement,
     * and locationLabel() reads the source to explain which it was.
     *
     * @return array{latitude: ?float, longitude: ?float, accuracy: ?int, distance: ?int, within: bool}
     */
    private function kioskLocation(): array
    {
        return [
            'latitude' => null, 'longitude' => null, 'accuracy' => null,
            'distance' => null, 'within'   => true,
        ];
    }

    /**
     * A punch from somebody's own phone where a kiosk was the expected route.
     *
     * The problem being solved is not fraud, it is drift. Where both a tablet
     * and a phone will work, the phone wins every time — it is already in your
     * hand, and there are three people at the tablet. Left alone the kiosk
     * becomes decorative within a fortnight and the outlet is back to a PIN
     * anybody can share.
     *
     * Three gates, in this order, and the order is the design:
     *
     *   1. A NAMED EXCEPTION beats everything. Area managers, drivers, crew
     *      working an offsite event. This is the entire control: the choice
     *      moves out of the doorway, where convenience decides it, and onto a
     *      screen where somebody had to mean it. It outranks the company
     *      switch as well as the outlet's mode, because that is what an
     *      exception is for — a company that turns phones off still has to be
     *      able to keep its area manager working.
     *
     *   2. NO LIVE KIOSK, no case to answer. A dead tablet must never cost an
     *      outlet its attendance, so nothing below this line can refuse or
     *      flag when there was no kiosk to have used. kiosk_down records WHY
     *      the punch was fine, which is why it is kept out of the review
     *      queue; the place to notice a dead kiosk is the devices screen,
     *      where somebody can go and plug it in.
     *
     *   3. Only then does policy apply. A company that has switched phone
     *      clock-in off gets a refusal — it is a deliberate instruction with
     *      an obvious alternative three metres away, not a check the employee
     *      failed. An outlet that merely PREFERS its kiosk gets a flag, and
     *      the punch stands: preferring is not forbidding.
     */
    private function assessOwnDevice(
        Employee $employee,
        Outlet $outlet,
        ClockSetting $settings,
        array &$flags,
        bool $lenient,
    ): void {
        $decision = $this->ownDevice->decide($employee, $outlet, $settings);

        if ($decision['status'] === OwnDevicePolicy::ALLOWED) {
            return;
        }

        if ($decision['status'] === OwnDevicePolicy::KIOSK_DOWN) {
            $flags[] = 'kiosk_down';

            return;
        }

        /*
         * Refused — except for a break, which is never refused.
         *
         * Somebody mid-shift has already been let in by some door, and
         * declining to let them END a break would leave the overrun charge
         * running with nothing they can do about it. That punch is recorded
         * and flagged instead, which is also the only way byod_when_kiosk_up
         * is still reachable now that the staff app hides the button entirely.
         */
        if ($lenient) {
            $flags[] = 'byod_when_kiosk_up';

            return;
        }

        throw new ClockInException(
            $this->ownDevice->message($outlet, $decision['kiosk'])
            . ' This outlet clocks in on its kiosk.'
        );
    }

    /**
     * @return array{distance: ?float, verified: bool}
     */
    private function assessFace(Employee $employee, array $input, ClockSetting $settings, array &$flags, bool $lenient = false, string $type = ClockEvent::TYPE_IN): array
    {
        $identification = $input['identification'] ?? null;

        /*
         * A KIOSK MATCH IS ALREADY A VERIFIED FACE, and re-deriving it here is
         * not possible: the tablet sends its descriptor to /identify, not to
         * this punch, because identity has to be settled before there is a
         * button to press at all.
         *
         * So the verdict is carried forward instead of recomputed, and that is
         * safe for a reason worth stating, because the rule two hundred lines
         * up says nothing here trusts a verdict computed by the device.
         * Nothing here does. This distance was measured by FaceIdentifier on
         * this server, against enrolments this server holds, and travels back
         * in a token the server encrypted, bound to the device and good for
         * sixty seconds. The tablet cannot read it, let alone author one.
         *
         * It also passed a STRICTER bar than anything below: kiosk_face_
         * threshold, plus a margin against the runner-up out of the whole
         * outlet. Treating that as an unverified face — which is what happened
         * before, since a null descriptor lands in the no_face branch — flagged
         * every clean kiosk punch in the company for review and sent managers
         * to look at photographs of faces that had already matched.
         */
        if ($identification === FaceIdentifier::MATCHED && is_numeric($input['face_distance'] ?? null)) {
            return ['distance' => (float) $input['face_distance'], 'verified' => true];
        }

        $descriptor = $input['descriptor'] ?? null;

        if (! EmployeeFaceDescriptor::isValidDescriptor($descriptor)) {
            if ($settings->require_face && ! $lenient) {
                throw new ClockInException(
                    'We could not read your face. Hold the phone at arm’s length in decent light and try again.'
                );
            }

            // Named for what actually happened. Both are a punch with no face
            // behind it and both go to a manager, but "no face captured" on a
            // kiosk punch that plainly has a photograph attached reads as a
            // bug in the camera, and sends whoever is reviewing it looking for
            // one. Somebody keyed a PIN; say that.
            $flags[] = $identification === FaceIdentifier::PIN_FALLBACK ? 'pin_fallback' : 'no_face';

            return ['distance' => null, 'verified' => false];
        }

        if (! $this->faces->hasEnrolment($employee)) {
            // Not the employee's fault and not something they can fix at the
            // door, so it is recorded and passed to a manager rather than
            // turned into a locked-out shift.
            $flags[] = 'not_enrolled';

            return ['distance' => null, 'verified' => false];
        }

        $distance = $this->faces->bestDistance($employee, $descriptor);

        if ($distance === null) {
            $flags[] = 'not_enrolled';

            return ['distance' => null, 'verified' => false];
        }

        $verified = $distance <= (float) $settings->face_threshold;

        if (! $verified) {
            $flags[] = 'face_mismatch';

            // Turned away at the door, when the company has asked for that.
            //
            // Reached ONLY when there are enrolled faces to compare against —
            // the not_enrolled paths above have already returned. That order
            // is the whole safety of this: somebody with no face on file has
            // nothing to fail, and refusing them would lock out every
            // employee a manager has not got round to enrolling yet.
            //
            // $lenient still wins, so a break punch is never refused for this.
            // Breaks are lenient throughout — a camera that fails must not
            // strand somebody mid-shift, and ending a break is a punch you
            // cannot simply decline to make.
            //
            // Clock-OUT is exempt for a sharper reason: refusing one is a
            // trap with no way out. The shift stays open, so nextType() keeps
            // answering "out", so every later attempt is another refused
            // clock-out — and the person can never clock in again either.
            // That is a worse outcome than the flagged record it replaces,
            // and unlike a refused clock-in there is nothing the employee can
            // do about it. A mismatched clock-out is still recorded, still
            // flagged, and still lands in front of a manager.
            if ($settings->require_face_match && ! $lenient && $type === ClockEvent::TYPE_IN) {
                throw new ClockInException(
                    'That did not look like your face. Try again in better light, '
                    . 'or ask your manager to record this one.'
                );
            }
        }

        // Clamped to the column's decimal(5,4): two unrelated faces can score
        // above 1.0 and the exact figure stops mattering well before then.
        return ['distance' => round(min(9.9999, $distance), 4), 'verified' => $verified];
    }

    /**
     * @param  array{entry: \App\Models\RosterEntry, start: Carbon, end: Carbon}|null  $shift
     * @return array{0: int, 1: int, 2: float}  raw minutes, chargeable minutes, RM
     */
    private function assessLateness(string $type, ?array $shift, Carbon $at, ClockSetting $settings, array &$flags): array
    {
        if ($type !== ClockEvent::TYPE_IN || ! $shift) {
            return [0, 0, 0.0];
        }

        $start = $shift['start'];

        if ($at->lt($start->copy()->subMinutes($settings->early_window_minutes))) {
            $flags[] = 'too_early';
        }

        $charge = LatenessCharge::compute(
            $start,
            $at,
            (int) $settings->grace_minutes,
            (float) $settings->late_rate_per_minute,
            $settings->late_cap_per_shift !== null ? (float) $settings->late_cap_per_shift : null,
        );

        if ($charge['minutes'] > 0) {
            $flags[] = 'late';
        }

        return [$charge['minutes'], $charge['chargeable'], $charge['amount']];
    }

    private function isBreak(string $type): bool
    {
        return in_array($type, [ClockEvent::TYPE_BREAK_START, ClockEvent::TYPE_BREAK_END], true);
    }

    /**
     * Charge for a break that ran past the shift's rest allowance.
     *
     * The allowance is the ROSTER's, because that is where rest is planned:
     * the shift's own rest_duration when set, otherwise the outlet's roster
     * setting. There is deliberately no separate per-employee break field —
     * one would be a second source of truth for a number the roster already
     * owns, and the two would drift.
     *
     * Overrun is charged at the same RM/minute as lateness, and shares the
     * shift's cap with it: they are one shift's worth of charges, and giving
     * each its own ceiling would quietly double the one a manager set.
     *
     * @param  array{entry: \App\Models\RosterEntry, start: Carbon, end: Carbon}|null  $shift
     * @return array{0: int, 1: int, 2: float}  overrun minutes, chargeable, RM
     */
    private function assessBreak(
        Employee $employee,
        Carbon $workDate,
        ?array $shift,
        Carbon $at,
        ClockSetting $settings,
        array &$flags,
    ): array {
        $starts = $this->countedPunches($employee, $workDate, ClockEvent::TYPE_BREAK_START);
        $ends   = $this->countedPunches($employee, $workDate, ClockEvent::TYPE_BREAK_END);

        // Pair them in order. An unmatched start is the break ending now.
        $openStart = $starts->get($ends->count());

        if (! $openStart) {
            $flags[] = 'no_open_break';

            return [0, 0, 0.0];
        }

        $taken = 0;

        foreach ($ends as $index => $end) {
            $start = $starts->get($index);

            if ($start) {
                $taken += max(0, (int) floor($start->happened_at->diffInSeconds($end->happened_at, false) / 60));
            }
        }

        $taken += max(0, (int) floor($openStart->happened_at->diffInSeconds($at, false) / 60));

        $allowance      = BreakOverrun::allowanceFor($employee, $shift['entry'] ?? null, $employee->outlet_id);
        $alreadyMinutes = (int) $ends->sum('chargeable_late_minutes');
        $alreadyAmount  = (float) $this->countedPunches($employee, $workDate, ClockEvent::TYPE_IN)
            ->sum('penalty_amount') + (float) $ends->sum('penalty_amount');

        $result = BreakOverrun::compute($taken, $allowance, $alreadyMinutes);

        if ($result['overrun'] > 0) {
            $flags[] = 'break_overrun';
        }

        $amount = BreakOverrun::amount(
            $result['chargeable'],
            (float) $settings->late_rate_per_minute,
            $settings->late_cap_per_shift !== null ? (float) $settings->late_cap_per_shift : null,
            $alreadyAmount,
        );

        // minutes_late carries the shift's total overrun so the review screen
        // shows the real figure; chargeable carries only what this break adds.
        return [$result['overrun'], $result['chargeable'], $amount];
    }

    /** Punches for one shift that still count, oldest first. */
    private function countedPunches(Employee $employee, Carbon $workDate, string $type)
    {
        return ClockEvent::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->where('work_date', $workDate->toDateString())
            ->where('type', $type)
            ->counted()
            ->orderBy('happened_at')
            ->get();
    }

    /**
     * Decode and keep the selfie.
     *
     * Private disk, never public: these are photographs of staff taken at
     * work, and a guessable public URL would put them one lucky path away
     * from anyone on the internet.
     */
    private function storeSelfie(Employee $employee, ?string $dataUrl): ?string
    {
        if (! $dataUrl || ! preg_match('#^data:image/(jpeg|jpg|png);base64,#', $dataUrl, $m)) {
            return null;
        }

        if (strlen($dataUrl) > self::MAX_SELFIE_BYTES) {
            return null;
        }

        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);

        if ($binary === false || $binary === '') {
            return null;
        }

        // The declared MIME is the browser's word for it; check the bytes.
        if (! @getimagesizefromstring($binary)) {
            return null;
        }

        $extension = $m[1] === 'png' ? 'png' : 'jpg';
        $path      = sprintf(
            'clock-selfies/%d/%s/%s.%s',
            $employee->company_id, now()->format('Y-m'), Str::uuid(), $extension
        );

        return Storage::disk('local')->put($path, $binary) ? $path : null;
    }

    /**
     * Mirror a successful clock-in into the attendance grid.
     *
     * Only ever FILLS a blank. A manager who has already marked the day —
     * as leave, as a half day, as anything — has made a decision the clock
     * knows nothing about, and overwriting it would silently undo their work.
     */
    private function markPresent(Employee $employee, Outlet $outlet, Carbon $workDate): void
    {
        $presentId = AttendanceCode::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $employee->company_id)
            ->where('system_key', 'present')
            ->value('id');

        if (! $presentId) {
            return;
        }

        DB::transaction(function () use ($employee, $outlet, $workDate, $presentId) {
            $exists = AttendanceRecord::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $employee->company_id)
                ->where('employee_id', $employee->id)
                ->where('work_date', $workDate->toDateString())
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                return;
            }

            AttendanceRecord::create([
                'company_id'         => $employee->company_id,
                'outlet_id'          => $outlet->id,
                'employee_id'        => $employee->id,
                'work_date'          => $workDate->toDateString(),
                'attendance_code_id' => $presentId,
            ]);
        });
    }

    private function humanDistance(float $metres): string
    {
        return $metres >= 1000
            ? number_format($metres / 1000, 1) . 'km'
            : number_format($metres) . 'm';
    }

    private function trimmed(?string $value, int $limit): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : Str::limit($value, $limit, '');
    }
}
