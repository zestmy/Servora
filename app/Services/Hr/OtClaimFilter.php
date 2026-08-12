<?php

namespace App\Services\Hr;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The Overtime Claims screen's filter set, in one object.
 *
 * It exists so the SCREEN and the FILTERED PDF cannot disagree. A document
 * headed "matches your current filter" that quietly applies different rules is
 * worse than no document at all — somebody signs it believing it is the list
 * they were looking at. The component applies these rules and so does the
 * export, from the same method.
 *
 * Every field is nullable and null means "not narrowed". That matters for
 * `employmentStatus`, where 'none' is a real value meaning "no status
 * recorded" and must not be confused with the absence of a filter.
 */
final class OtClaimFilter
{
    public function __construct(
        public readonly ?string $status = null,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
        public readonly ?int $employeeId = null,
        public readonly ?int $sectionId = null,
        public readonly ?string $employmentStatus = null,
        public readonly ?int $outletId = null,
    ) {}

    /** Built from the screen's own properties. */
    public static function fromScreen(
        string $status,
        string $from,
        string $to,
        string $employeeId,
        string $sectionId,
        string $employmentStatus,
        string $outletId,
    ): self {
        return new self(
            $status ?: null,
            $from ?: null,
            $to ?: null,
            $employeeId !== '' ? (int) $employeeId : null,
            $sectionId !== '' ? (int) $sectionId : null,
            $employmentStatus !== '' ? $employmentStatus : null,
            $outletId !== '' ? (int) $outletId : null,
        );
    }

    /** Rebuilt on the other side of the link, from the query string. */
    public static function fromRequest(Request $request): self
    {
        return self::fromScreen(
            (string) $request->query('status', ''),
            (string) $request->query('from', ''),
            (string) $request->query('to', ''),
            (string) $request->query('employee', ''),
            (string) $request->query('section', ''),
            (string) $request->query('employment', ''),
            (string) $request->query('outlet', ''),
        );
    }

    /** The same values back as a query string, for the export link. */
    public function toQuery(): array
    {
        return array_filter([
            'status'     => $this->status,
            'from'       => $this->from,
            'to'         => $this->to,
            'employee'   => $this->employeeId,
            'section'    => $this->sectionId,
            'employment' => $this->employmentStatus,
            'outlet'     => $this->outletId,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * The outlets this filter actually covers.
     *
     * Narrowed to one when an outlet is chosen, but only if the viewer may see
     * it — a filter arriving in a URL is not permission to read a branch.
     *
     * @param  array<int, int>  $accessibleOutletIds
     * @return array<int, int>
     */
    public function outletScope(array $accessibleOutletIds): array
    {
        return $this->outletId !== null && in_array($this->outletId, $accessibleOutletIds, true)
            ? [$this->outletId]
            : $accessibleOutletIds;
    }

    /**
     * Narrow a claims query.
     *
     * Employees are matched on THEIR outlet rather than the claim's: a claim
     * carries the outlet it was raised at, which on older records is not
     * reliably where the person works, and the screen has always scoped by the
     * employee. Changing that here would make the PDF disagree with the list
     * it claims to reproduce.
     *
     * @param  array<int, int>  $accessibleOutletIds
     */
    public function apply(Builder $query, array $accessibleOutletIds): Builder
    {
        $outletIds = $this->outletScope($accessibleOutletIds);

        $query->whereIn('employee_id', function ($sub) use ($outletIds) {
            $sub->select('id')->from('employees')->whereIn('outlet_id', $outletIds ?: [0]);
        });

        if ($this->status !== null) {
            $query->where('status', $this->status);
        }
        if ($this->from !== null) {
            $query->where('claim_date', '>=', $this->from);
        }
        if ($this->to !== null) {
            $query->where('claim_date', '<=', $this->to);
        }
        if ($this->employeeId !== null) {
            $query->where('employee_id', $this->employeeId);
        }
        if ($this->sectionId !== null) {
            $query->whereIn('employee_id', function ($sub) {
                $sub->select('id')->from('employees')->where('section_id', $this->sectionId);
            });
        }
        if ($this->employmentStatus !== null) {
            $query->whereIn('employee_id', function ($sub) {
                $sub->select('id')->from('employees');
                $this->applyEmploymentStatus($sub, 'employment_status');
            });
        }

        return $query;
    }

    /**
     * The employment-status branch, matching the Employees list and the
     * Attendance grid: "exclude outsourcing" and "none" are synthetic options
     * rather than stored values.
     *
     * @param  \Illuminate\Contracts\Database\Query\Builder|Builder  $query
     */
    public function applyEmploymentStatus($query, string $column = 'employment_status'): void
    {
        match (true) {
            $this->employmentStatus === null => null,
            $this->employmentStatus === 'none' => $query->whereNull($column),
            $this->employmentStatus === 'exclude_outsourcing' => $query->where(
                fn ($q) => $q->whereNull($column)->orWhere($column, '!=', 'outsourcing')
            ),
            default => $query->where($column, $this->employmentStatus),
        };
    }

    /**
     * What was narrowed, in words, for the document to carry.
     *
     * Printed on the PDF rather than left on the screen: a filtered statement
     * that does not say what it was filtered by is unreadable the moment it
     * leaves the browser, and somebody will treat it as the complete record.
     *
     * @return array<int, string>
     */
    public function describe(): array
    {
        $parts = [];

        $parts[] = 'Status: ' . ($this->status
            ? ucfirst(str_replace('_', ' ', $this->status))
            : 'All statuses');

        $parts[] = 'Period: ' . match (true) {
            $this->from && $this->to => $this->fmt($this->from) . ' – ' . $this->fmt($this->to),
            (bool) $this->from       => 'from ' . $this->fmt($this->from),
            (bool) $this->to         => 'up to ' . $this->fmt($this->to),
            default                  => 'All dates',
        };

        if ($this->employmentStatus !== null) {
            $parts[] = 'Employment: ' . match ($this->employmentStatus) {
                'none' => 'No status recorded',
                'exclude_outsourcing' => 'Own staff only (excluding outsourced)',
                default => \App\Models\Employee::EMPLOYMENT_STATUSES[$this->employmentStatus]
                    ?? ucfirst($this->employmentStatus),
            };
        }

        return $parts;
    }

    private function fmt(string $date): string
    {
        try {
            return \Carbon\Carbon::parse($date)->format('d M Y');
        } catch (\Throwable $e) {
            return $date;
        }
    }
}
