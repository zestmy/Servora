<?php

namespace App\Services\Hr;

use App\Models\AttendanceCode;
use App\Models\AttendanceRecord;
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
 *   A failed CHECK flags the punch; a missing INPUT refuses it. Somebody
 *   whose face does not match still gets their attendance recorded, with a
 *   selfie for their manager to look at — refusing them would turn a beard
 *   into a missing day's pay, and the manager, not the model, is who should
 *   decide. But somebody who never turned the camera on has produced no
 *   evidence at all, and that is worth stopping at the door where it can
 *   still be fixed.
 *
 *   Nothing here trusts a verdict computed by the phone. The device sends
 *   raw observations — coordinates, a descriptor — and every comparison
 *   against the company's thresholds happens on this side.
 */
class ClockInService
{
    /** Biggest selfie accepted, before base64 decoding. */
    private const MAX_SELFIE_BYTES = 2_500_000;

    public function __construct(
        private ShiftResolver $shifts,
        private FaceMatcher $faces,
    ) {
    }

    /**
     * @param  array{
     *     latitude?: mixed, longitude?: mixed, accuracy?: mixed,
     *     descriptor?: mixed, selfie?: ?string, reason?: ?string,
     *     device_label?: ?string, user_agent?: ?string, ip?: ?string,
     * }  $input  Raw observations from the device. Every value is untrusted.
     *
     * @throws ClockInException when the punch cannot be recorded at all.
     */
    public function punch(Employee $employee, string $type, array $input): ClockEvent
    {
        $type = $type === ClockEvent::TYPE_OUT ? ClockEvent::TYPE_OUT : ClockEvent::TYPE_IN;

        $outlet = $this->outletFor($employee);

        // now(), not a time from the device: a phone's clock is settable, and
        // this one decides whether its owner was late.
        $at       = now();
        $settings = ClockSetting::forCompany($employee->company_id);

        $flags = [];

        $shift    = $this->shifts->resolve($employee, $at, $type);
        $workDate = $shift
            ? Carbon::parse($shift['entry']->day_date->format('Y-m-d'))
            : $at->copy()->startOfDay();

        if (! $shift) {
            $flags[] = 'no_shift';
        }

        $location = $this->assessLocation($input, $outlet, $settings, $flags);
        $face     = $this->assessFace($employee, $input, $settings, $flags);

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
        } elseif (! $this->countedPunches($employee, $workDate, ClockEvent::TYPE_IN)->first()) {
            $flags[] = 'no_open_punch';
        }

        $selfiePath = $this->storeSelfie($employee, $input['selfie'] ?? null);

        // "late" is a record of what happened, not a problem to be reviewed —
        // the deduction is the consequence, and a manager who wants to waive
        // it can still find the punch. Anything else in the list means a
        // check could not be satisfied and a human has to look.
        $reviewable = array_values(array_diff($flags, ['late']));

        $event = ClockEvent::create([
            'company_id'      => $employee->company_id,
            'outlet_id'       => $outlet->id,
            'employee_id'     => $employee->id,
            'roster_entry_id' => $shift['entry']->id ?? null,
            'type'            => $type,
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
    private function assessLocation(array $input, Outlet $outlet, ClockSetting $settings, array &$flags): array
    {
        $result = [
            'latitude' => null, 'longitude' => null, 'accuracy' => null,
            'distance' => null, 'within'   => false,
        ];

        $hasFix = Geo::isValidCoordinate($input['latitude'] ?? null, $input['longitude'] ?? null);

        if (! $hasFix) {
            if ($settings->require_gps) {
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
            if ($settings->require_gps && ! $settings->allow_offsite_with_reason) {
                throw new ClockInException(sprintf(
                    'You are about %s from %s. Clock in when you get to the outlet.',
                    $this->humanDistance($distance), $outlet->name
                ));
            }

            if ($settings->require_gps && trim((string) ($input['reason'] ?? '')) === '') {
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
     * @return array{distance: ?float, verified: bool}
     */
    private function assessFace(Employee $employee, array $input, ClockSetting $settings, array &$flags): array
    {
        $descriptor = $input['descriptor'] ?? null;

        if (! EmployeeFaceDescriptor::isValidDescriptor($descriptor)) {
            if ($settings->require_face) {
                throw new ClockInException(
                    'We could not read your face. Hold the phone at arm’s length in decent light and try again.'
                );
            }

            $flags[] = 'no_face';

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
