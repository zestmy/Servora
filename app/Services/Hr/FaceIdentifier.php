<?php

namespace App\Services\Hr;

use App\Models\ClockSetting;
use App\Models\Employee;
use App\Models\EmployeeFaceDescriptor;
use App\Scopes\CompanyScope;

/**
 * Works out WHO is standing at the kiosk, from a face alone.
 *
 * A different problem from FaceMatcher, and the difference is the whole
 * reason this class exists rather than a second method over there.
 *
 * FaceMatcher answers "is this Aina?" — the person has already picked their
 * name and keyed a PIN, and the face either agrees or it does not. Getting it
 * wrong flags a punch.
 *
 * This answers "who is this?" against everybody enrolled at the outlet.
 * Getting THAT wrong writes a punch onto an innocent third party's attendance
 * record, leaves the real person unable to clock in, and hands the result to
 * whoever was trying it on — all silently. The failure is not a worse version
 * of the 1:1 failure; it is a different failure, and it is why nothing here
 * resolves a close call.
 *
 * Two gates, and BOTH must pass:
 *
 *   THRESHOLD  the winner has to actually look like the person. Its own
 *              setting, tighter than the 1:1 line, because the pool being
 *              searched is the whole outlet rather than one person and more
 *              draws mean more chances to land under any given bar.
 *
 *   MARGIN     the winner has to be clearly ahead of the RUNNER-UP. This is
 *              the gate that matters. Two colleagues at 0.44 and 0.46 are not
 *              a match followed by a near miss — they are a coin toss wearing
 *              a decimal point, and the kiosk asks for a PIN instead of
 *              calling it. Siblings and cousins work the same shift in this
 *              industry more often than a model trained on strangers expects.
 *
 * Everything here runs on the SERVER. The tablet computes a descriptor from a
 * camera frame — a job that has to be local, so the photograph itself never
 * has to be uploaded to be checked — and sends 128 floats. It never receives
 * anybody's enrolment and it never learns whose face it just saw until the
 * server has decided. Doing this the other way round would mean shipping the
 * company's entire face database to a tablet sitting on a public counter.
 */
class FaceIdentifier
{
    public const MATCHED    = 'matched';
    public const AMBIGUOUS  = 'ambiguous';
    public const UNKNOWN    = 'unknown';
    public const NO_FACES   = 'no_enrolments';
    public const BAD_INPUT  = 'bad_input';

    /**
     * Not an outcome of identify() — the answer to the same question when the
     * camera never got one. It lives here so the door and the punch use one
     * vocabulary for "how did we decide who this is", rather than a bare
     * string passed between two files that both have to remember it.
     */
    public const PIN_FALLBACK = 'pin';

    /** How many near misses to hand back, for the "is it one of these?" screen. */
    private const SHORTLIST = 3;

    /**
     * @param  array  $descriptor  128 floats from the tablet. Untrusted.
     * @return array{
     *     status: string,
     *     employee: ?Employee,
     *     distance: ?float,
     *     runner_up: ?float,
     *     shortlist: array<int, array{employee: Employee, distance: float}>,
     * }
     */
    public function identify(int $companyId, int $outletId, mixed $descriptor, ClockSetting $settings): array
    {
        $blank = [
            'status' => self::BAD_INPUT, 'employee' => null,
            'distance' => null, 'runner_up' => null, 'shortlist' => [],
        ];

        if (! EmployeeFaceDescriptor::isValidDescriptor($descriptor)) {
            return $blank;
        }

        $ranked = $this->rank($companyId, $outletId, $descriptor);

        if ($ranked === []) {
            return ['status' => self::NO_FACES] + $blank;
        }

        $best      = $ranked[0];
        $runnerUp  = $ranked[1]['distance'] ?? null;
        $threshold = (float) $settings->kiosk_face_threshold;
        $margin    = (float) $settings->kiosk_face_margin;

        $shortlist = array_slice($ranked, 0, self::SHORTLIST);

        if ($best['distance'] > $threshold) {
            // Nobody at this outlet looks enough like the person in front of
            // the camera. Usually a genuine stranger, a visiting supplier, or
            // somebody whose enrolment predates a beard.
            return [
                'status'    => self::UNKNOWN,
                'employee'  => null,
                'distance'  => $best['distance'],
                'runner_up' => $runnerUp,
                'shortlist' => $shortlist,
            ];
        }

        /*
         * A single enrolled person has no runner-up, so the margin is vacuous
         * and correctly so: with a pool of one this IS a 1:1 verification, and
         * the threshold alone is the right test. It matters in practice —
         * a company rolls a kiosk out by enrolling one person to try it.
         */
        if ($runnerUp !== null && ($runnerUp - $best['distance']) < $margin) {
            return [
                'status'    => self::AMBIGUOUS,
                'employee'  => null,
                'distance'  => $best['distance'],
                'runner_up' => $runnerUp,
                'shortlist' => $shortlist,
            ];
        }

        return [
            'status'    => self::MATCHED,
            'employee'  => $best['employee'],
            'distance'  => $best['distance'],
            'runner_up' => $runnerUp,
            'shortlist' => $shortlist,
        ];
    }

    /**
     * Everybody enrolled at the outlet, nearest face first.
     *
     * One row per EMPLOYEE, not per capture. Somebody enrolled from five
     * angles would otherwise occupy the first five places and become their own
     * runner-up, which would make the margin gate pass for exactly the person
     * it is supposed to protect against.
     *
     * @return array<int, array{employee: Employee, distance: float}>
     */
    private function rank(int $companyId, int $outletId, array $descriptor): array
    {
        /*
         * Scoped to the OUTLET, and that is a quality decision as much as a
         * security one. A small pool is most of the accuracy here: searching
         * two hundred people company-wide to find one of the forty who work in
         * this building trades a real increase in false matches for nothing
         * anybody wanted.
         *
         * Somebody covering a shift from another branch is therefore not
         * recognised, and takes the PIN fallback. That is the correct outcome
         * — ClockInService records the punch against their OWN outlet either
         * way, so nothing about their pay moves because they stood in front of
         * a different tablet.
         *
         * CompanyScope is dropped and company_id matched by hand: a kiosk
         * request has no authenticated user, so the scope resolves from the
         * subdomain at best and matches nothing at worst.
         */
        $employees = Employee::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('outlet_id', $outletId)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        if ($employees->isEmpty()) {
            return [];
        }

        $rows = EmployeeFaceDescriptor::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->whereIn('employee_id', $employees->keys())
            // Descriptors from a superseded model are skipped rather than
            // compared. Mixing model outputs produces a number that looks
            // exactly like a distance and means nothing at all.
            ->where('model_version', EmployeeFaceDescriptor::MODEL_VERSION)
            ->get(['employee_id', 'descriptor']);

        $best = [];

        foreach ($rows as $row) {
            if (! EmployeeFaceDescriptor::isValidDescriptor($row->descriptor)) {
                continue;
            }

            $distance = FaceMatcher::euclidean($descriptor, $row->descriptor);

            if (! isset($best[$row->employee_id]) || $distance < $best[$row->employee_id]) {
                $best[$row->employee_id] = $distance;
            }
        }

        asort($best);

        $ranked = [];

        foreach ($best as $employeeId => $distance) {
            $employee = $employees->get($employeeId);

            if ($employee) {
                $ranked[] = ['employee' => $employee, 'distance' => $distance];
            }
        }

        return $ranked;
    }

    /** Whether anybody at this outlet is enrolled at all. */
    public function outletHasEnrolments(int $companyId, int $outletId): bool
    {
        return EmployeeFaceDescriptor::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('model_version', EmployeeFaceDescriptor::MODEL_VERSION)
            ->whereIn('employee_id', Employee::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $companyId)
                ->where('outlet_id', $outletId)
                ->where('is_active', true)
                ->select('id'))
            ->exists();
    }
}
