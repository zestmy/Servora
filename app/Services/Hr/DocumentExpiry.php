<?php

namespace App\Services\Hr;

use App\Models\CertificationType;
use App\Models\ComplianceSetting;
use App\Models\Employee;
use App\Scopes\CompanyScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Compliance document and training expiry, computed once for every caller.
 *
 * The Employees screen card and the scheduled reminder email both read this,
 * so the list a manager sees and the list that lands in their inbox can never
 * disagree about who is due — which is the whole point of the reminder.
 *
 * Give it a query that is ALREADY scoped to the outlets in question; this
 * class narrows to active staff and decides state, and never widens the scope.
 * The company id is passed explicitly rather than read from the session
 * because the reminder runs on the scheduler with no authenticated user, where
 * CompanyScope silently matches everything.
 */
class DocumentExpiry
{
    /**
     * How far ahead counts as "expiring soon".
     *
     * 60 days: long enough to book a clinic appointment and chase a renewal
     * before the document lapses, short enough that the list stays actionable
     * rather than becoming a roster of everyone.
     */
    public const WARNING_DAYS = 60;

    /** Past its expiry date. */
    public const EXPIRED = 'expired';
    /** Expires within WARNING_DAYS. */
    public const EXPIRING = 'expiring';
    /** Held, and not due yet. */
    public const VALID = 'valid';
    /** Held, but no expiry date recorded — cannot be judged either way. */
    public const UNDATED = 'undated';
    /** Not held at all. Only reported for documents everyone is expected to hold. */
    public const MISSING = 'missing';

    /** States that put someone on the needs-attention list, most urgent first. */
    public const ACTIONABLE = [self::EXPIRED, self::EXPIRING, self::UNDATED, self::MISSING];

    public function __construct(
        public readonly int $warningDays = self::WARNING_DAYS,
    ) {}

    /**
     * Only the columns the report needs. Selecting explicitly keeps salary and
     * service points out of the result entirely, so this can be rendered for a
     * user without hr.compensation without relying on the view to hide them.
     */
    public static function columns(): array
    {
        $columns = ['id', 'company_id', 'outlet_id', 'name', 'staff_id', 'is_active', 'photo_path'];

        foreach (Employee::COMPLIANCE_DOCUMENTS as $doc) {
            $columns[] = $doc['held'];
            $columns[] = $doc['expires'];
        }

        return $columns;
    }

    /**
     * @param  Builder  $employees  already scoped to the outlets wanted
     * @return array{
     *     documents: array<int, array<string, mixed>>,
     *     rows: Collection,
     *     counts: array<string, int>,
     *     employees: int,
     *     warning_days: int,
     *     as_of: Carbon,
     * }
     */
    public function summarise(Builder $employees, int $companyId, ?Carbon $today = null): array
    {
        $today ??= Carbon::today();
        $limit = $today->copy()->addDays($this->warningDays);

        // Resigned or deactivated staff cannot be chased for a renewal, and
        // their lapsed cards would sit at the top of the list forever.
        $staff = (clone $employees)
            ->where('is_active', true)
            ->select(static::columns())
            ->with(['outlet:id,name', 'certifications:id,employee_id,certification_type_id,expires_on'])
            ->orderBy('name')
            ->get();

        $definitions = $this->definitions($companyId);

        $documents = [];
        $rows      = collect();
        $counts    = array_fill_keys(self::ACTIONABLE, 0);

        foreach ($definitions as $def) {
            $tally   = array_fill_keys([self::EXPIRED, self::EXPIRING, self::VALID, self::UNDATED, self::MISSING], 0);
            $holders = 0;

            foreach ($staff as $employee) {
                [$held, $expiresOn] = $this->readDocument($employee, $def);
                $state = $this->state($held, $expiresOn, $def['has_expiry'], $today, $limit);

                // An optional course nobody was asked to take is not a finding.
                // Without this, every specialist certificate would report the
                // entire payroll as missing it.
                if ($state === self::MISSING && ! $def['required']) {
                    continue;
                }

                $tally[$state]++;
                if ($held) $holders++;

                if ($state === self::VALID) {
                    continue;
                }

                $rows->push([
                    'employee_id'  => $employee->id,
                    'name'         => $employee->name,
                    'photo_path'   => $employee->photo_path,
                    'staff_id'     => $employee->staff_id,
                    'outlet'       => $employee->outlet?->name,
                    'document'     => $def['label'],
                    'document_key' => $def['key'],
                    'state'        => $state,
                    'expires_on'   => $expiresOn,
                    // Negative = already lapsed by that many days.
                    'days'         => $expiresOn ? $today->diffInDays($expiresOn, false) : null,
                ]);
            }

            $documents[] = [
                'key'      => $def['key'],
                'label'    => $def['label'],
                'required' => $def['required'],
                'holders'  => $holders,
            ] + $tally;

            foreach ($counts as $state => $_) {
                $counts[$state] += $tally[$state];
            }
        }

        return [
            'documents'    => $documents,
            'rows'         => $this->sortRows($rows),
            'counts'       => $counts,
            'employees'    => $staff->count(),
            'warning_days' => $this->warningDays,
            'as_of'        => $today,
        ];
    }

    /**
     * The built-in columns first, then the company's catalogue courses — one
     * shape for both so the loop above never branches on where a document
     * came from.
     *
     * @return array<int, array<string, mixed>>
     */
    private function definitions(int $companyId): array
    {
        $definitions = [];
        $compliance  = ComplianceSetting::forCompany($companyId);

        foreach (Employee::COMPLIANCE_DOCUMENTS as $key => $doc) {
            // A one-off document has nothing to expire, so it has no place in
            // an expiry report at all — its held/not-held state is on the
            // Employees list, which is where that question belongs.
            if (! $compliance->expires($key)) {
                continue;
            }

            $definitions[] = [
                'key'        => $key,
                'label'      => $doc['label'],
                'source'     => 'column',
                'held'       => $doc['held'],
                'expires'    => $doc['expires'],
                'has_expiry' => true,
                // Of the documents that DO expire, not holding one at all is
                // itself the finding, so it is reported as missing.
                'required'   => true,
            ];
        }

        $types = CertificationType::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->active()
            ->ordered()
            ->get();

        foreach ($types as $type) {
            // Same rule as the built-ins: this report is about expiry, so a
            // course that never lapses is not part of it.
            if (! $type->has_expiry) {
                continue;
            }

            $definitions[] = [
                'key'        => 'cert:' . $type->id,
                'label'      => $type->name,
                'source'     => 'catalogue',
                'type_id'    => $type->id,
                'has_expiry' => true,
                'required'   => $type->is_required,
            ];
        }

        return $definitions;
    }

    /** @return array{0: bool, 1: ?Carbon} [held, expires on] */
    private function readDocument(Employee $employee, array $def): array
    {
        if ($def['source'] === 'column') {
            return [(bool) $employee->{$def['held']}, $employee->{$def['expires']}];
        }

        $record = $employee->certifications->firstWhere('certification_type_id', $def['type_id']);

        return [(bool) $record, $record?->expires_on];
    }

    private function state(bool $held, ?Carbon $expiresOn, bool $hasExpiry, Carbon $today, Carbon $limit): string
    {
        if (! $held) {
            return self::MISSING;
        }
        // A one-off course has nothing to expire, so holding it is the end of
        // the story — reporting it as "expiry date missing" would be noise.
        if (! $hasExpiry) {
            return self::VALID;
        }
        if (! $expiresOn) {
            return self::UNDATED;
        }
        if ($expiresOn->lt($today)) {
            return self::EXPIRED;
        }

        return $expiresOn->lte($limit) ? self::EXPIRING : self::VALID;
    }

    /**
     * Most urgent first: longest-expired, then soonest to expire, then the
     * undated and missing (which have no date to rank by) alphabetically.
     */
    private function sortRows(Collection $rows): Collection
    {
        $rank = array_flip(self::ACTIONABLE);

        return $rows
            ->sortBy([
                fn ($a, $b) => $rank[$a['state']] <=> $rank[$b['state']],
                fn ($a, $b) => ($a['expires_on']?->timestamp ?? 0) <=> ($b['expires_on']?->timestamp ?? 0),
                fn ($a, $b) => strcasecmp($a['name'], $b['name']),
            ])
            ->values();
    }
}
